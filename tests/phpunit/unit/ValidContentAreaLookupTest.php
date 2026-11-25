<?php

namespace MediaWiki\Extension\ArticleContentArea\Tests\Unit;

use MediaWiki\Extension\ArticleContentArea\ValidContentAreaLookup;
use MediaWiki\Title\TitleParser;
use MediaWiki\Title\TitleValue;
use MediaWikiUnitTestCase;
use Psr\Log\LoggerInterface;
use Throwable;
use Wikimedia\Rdbms\DBConnectionError;
use Wikimedia\Rdbms\ILBFactory;
use Wikimedia\Rdbms\ILoadBalancer;
use Wikimedia\Rdbms\IReadableDatabase;
use Wikimedia\Rdbms\SelectQueryBuilder;

/**
 * @covers \MediaWiki\Extension\ArticleContentArea\ValidContentAreaLookup
 * @group ArticleContentArea
 */
class ValidContentAreaLookupTest extends MediaWikiUnitTestCase {

	/** @var array[] The categorylinks queries each database was asked, keyed by its name */
	private array $queries = [];

	/**
	 * A database whose category-member query answers $result, or throws it
	 *
	 * @param string $name
	 * @param string[]|Throwable $result
	 * @return IReadableDatabase
	 */
	private function newDatabase( string $name, $result ): IReadableDatabase {
		$this->queries[$name] = [];
		$db = $this->createMock( IReadableDatabase::class );
		$db->method( 'newSelectQueryBuilder' )
			->willReturnCallback( static fn () => new SelectQueryBuilder( $db ) );
		$db->method( 'selectFieldValues' )->willReturnCallback(
			function ( $tables, $field, $conds ) use ( $name, $result ) {
				$this->queries[$name][] = $conds;
				if ( $result instanceof Throwable ) {
					throw $result;
				}
				return $result;
			}
		);
		return $db;
	}

	/**
	 * @param string|null $categoryName
	 * @param string|null $sourceWiki
	 * @param IReadableDatabase $localDb This wiki's database
	 * @param IReadableDatabase|null $sourceDb The source wiki's database
	 * @param LoggerInterface|null $logger
	 * @return ValidContentAreaLookup
	 */
	private function newLookup(
		?string $categoryName,
		?string $sourceWiki,
		IReadableDatabase $localDb,
		?IReadableDatabase $sourceDb = null,
		?LoggerInterface $logger = null
	): ValidContentAreaLookup {
		$lbFactory = $this->createMock( ILBFactory::class );
		$lbFactory->method( 'getReplicaDatabase' )->willReturn( $localDb );
		$lbFactory->method( 'getMainLB' )->willReturnCallback(
			function ( $wiki ) use ( $sourceWiki, $sourceDb ) {
				$this->assertSame( $sourceWiki, $wiki );
				$lb = $this->createMock( ILoadBalancer::class );
				$lb->method( 'getConnection' )->willReturnCallback(
					function ( $index, $groups, $domain ) use ( $sourceWiki, $sourceDb ) {
						$this->assertSame( DB_REPLICA, $index );
						$this->assertSame( $sourceWiki, $domain );
						return $sourceDb;
					}
				);
				return $lb;
			}
		);

		$titleParser = $this->createMock( TitleParser::class );
		$titleParser->method( 'makeTitleValueSafe' )->willReturnCallback(
			static fn ( $ns, $text ) => str_contains( $text, '<' )
				? null
				: new TitleValue( $ns, str_replace( ' ', '_', $text ) )
		);

		return new ValidContentAreaLookup(
			$categoryName,
			$sourceWiki,
			$lbFactory,
			$titleParser,
			$logger ?? $this->createMock( LoggerInterface::class )
		);
	}

	public function testMembersOfTheLocalCategory(): void {
		$local = $this->newDatabase( 'local', [ 'Workers_rights', 'Housing' ] );

		$lookup = $this->newLookup( 'Main areas', null, $local );

		$this->assertSame( [ 'Workers rights', 'Housing' ], $lookup->getNames() );
		$this->assertSame( [ [ 'cl_to' => 'Main_areas' ] ], $this->queries['local'] );
	}

	public function testAnEmptyLocalCategoryGivesNoNames(): void {
		$lookup = $this->newLookup( 'Main areas', null, $this->newDatabase( 'local', [] ) );

		$this->assertSame( [], $lookup->getNames() );
	}

	public function testNoCategoryGivesNoNamesWithoutAQuery(): void {
		$local = $this->newDatabase( 'local', [ 'Housing' ] );

		$this->assertSame( [], $this->newLookup( null, null, $local )->getNames() );
		$this->assertSame( [], $this->newLookup( '', null, $local )->getNames() );
		$this->assertSame( [], $this->queries['local'] );
	}

	public function testAnInvalidCategoryNameGivesNoNames(): void {
		$local = $this->newDatabase( 'local', [ 'Housing' ] );
		$logger = $this->createMock( LoggerInterface::class );
		$logger->expects( $this->once() )->method( 'warning' );

		$lookup = $this->newLookup( 'Main <areas>', null, $local, null, $logger );

		$this->assertSame( [], $lookup->getNames() );
		$this->assertSame( [], $this->queries['local'] );
	}

	/**
	 * A wiki whose own copy of the category is empty reads the source wiki's
	 */
	public function testTheSourceWikiCategoryIsReadInsteadOfTheLocalOne(): void {
		$local = $this->newDatabase( 'local', [] );
		$source = $this->newDatabase( 'source', [ 'זכויות_עובדים', 'דיור' ] );

		$lookup = $this->newLookup( 'קטגוריות ראשיות', 'hubwiki', $local, $source );

		$this->assertSame( [ 'זכויות עובדים', 'דיור' ], $lookup->getNames() );
		$this->assertSame( [ [ 'cl_to' => 'קטגוריות_ראשיות' ] ], $this->queries['source'] );
		$this->assertSame( [], $this->queries['local'] );
	}

	public function testASourceWikiThatCannotBeQueriedGivesNoNames(): void {
		$local = $this->newDatabase( 'local', [ 'Housing' ] );
		$source = $this->newDatabase( 'source', new DBConnectionError( null, 'down' ) );
		$logger = $this->createMock( LoggerInterface::class );
		$logger->expects( $this->once() )->method( 'warning' );

		$lookup = $this->newLookup( 'Main areas', 'hubwiki', $local, $source, $logger );

		$this->assertSame( [], $lookup->getNames() );
		$this->assertSame( [], $this->queries['local'] );
	}

	public function testNamesAreLookedUpOnce(): void {
		$local = $this->newDatabase( 'local', [ 'Housing', 'Housing' ] );

		$lookup = $this->newLookup( 'Main areas', null, $local );
		$lookup->getNames();

		$this->assertSame( [ 'Housing' ], $lookup->getNames() );
		$this->assertCount( 1, $this->queries['local'] );
	}
}
