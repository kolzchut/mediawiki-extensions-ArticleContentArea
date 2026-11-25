<?php

namespace MediaWiki\Extension\ArticleContentArea\Tests\Integration;

use MediaWiki\Extension\ArticleContentArea\ArticleContentArea;
use MediaWiki\Extension\ArticleContentArea\ValidContentAreaLookup;
use MediaWiki\Title\Title;
use MediaWiki\WikiMap\WikiMap;
use MediaWikiIntegrationTestCase;
use ParserOptions;
use Psr\Log\NullLogger;
use Wikimedia\Rdbms\IReadableDatabase;
use Wikimedia\Rdbms\SelectQueryBuilder;

/**
 * What `{{#articlecontentarea:…}}` stores
 *
 * @covers \MediaWiki\Extension\ArticleContentArea\Hooks
 * @covers \MediaWiki\Extension\ArticleContentArea\ArticleContentArea
 * @covers \MediaWiki\Extension\ArticleContentArea\ValidContentAreaLookup
 * @group ArticleContentArea
 * @group Database
 */
class ParserFunctionTest extends MediaWikiIntegrationTestCase {

	private const CATEGORY = 'ArticleContentAreaTest areas';

	private const MARKUP = '<i data-x=1>';

	/**
	 * @param string $value
	 * @return mixed The page property the parser function set
	 */
	private function storedContentArea( string $value ) {
		$output = $this->getServiceContainer()->getParserFactory()->getInstance()->parse(
			"{{#articlecontentarea:$value}}",
			Title::makeTitle( NS_MAIN, 'ArticleContentAreaTest page' ),
			ParserOptions::newFromAnon()
		);
		return $output->getPageProperty( ArticleContentArea::$DATA_VAR );
	}

	private function addToCategory( string $page, string $category ): void {
		$pageId = $this->getExistingTestPage( $page )->getId();
		$this->getDb()->newInsertQueryBuilder()
			->insertInto( 'categorylinks' )
			->row( [
				'cl_from' => $pageId,
				'cl_to' => str_replace( ' ', '_', $category ),
				'cl_sortkey' => strtoupper( $page ),
				'cl_sortkey_prefix' => '',
				'cl_timestamp' => $this->getDb()->timestamp(),
				'cl_collation' => 'uppercase',
				'cl_type' => 'page',
			] )
			->caller( __METHOD__ )
			->execute();
	}

	private function setUpWiki( ?string $categoryName, ?string $sourceWiki = null ): void {
		$this->overrideConfigValues( [
			'ArticleContentAreaCategoryName' => $categoryName,
			'ArticleContentAreaSourceWiki' => $sourceWiki,
		] );
	}

	public function testNoCategoryConfiguredStoresUnknown(): void {
		$this->setUpWiki( null );

		$this->assertFalse( ArticleContentArea::isValidContentArea( self::MARKUP ) );
		$this->assertSame( 'unknown', $this->storedContentArea( self::MARKUP ) );
		$this->assertSame( 'unknown', $this->storedContentArea( 'Housing' ) );
	}

	/**
	 * Before, an empty list made every value valid: the case of the ar and ru
	 * wikis, which have no local copy of the hub wiki's category
	 */
	public function testAnEmptyLocalCategoryStoresUnknown(): void {
		$this->setUpWiki( self::CATEGORY );

		$this->assertSame( [], ArticleContentArea::getValidContentAreas() );
		$this->assertFalse( ArticleContentArea::isValidContentArea( self::MARKUP ) );
		$this->assertSame( 'unknown', $this->storedContentArea( self::MARKUP ) );
	}

	public function testAMemberOfTheLocalCategoryIsStored(): void {
		$this->addToCategory( 'ArticleContentAreaTest housing', self::CATEGORY );
		$this->setUpWiki( self::CATEGORY );

		$this->assertSame(
			'ArticleContentAreaTest housing',
			$this->storedContentArea( ' ArticleContentAreaTest housing ' )
		);
		$this->assertSame( 'unknown', $this->storedContentArea( self::MARKUP ) );
		$this->assertSame( 'unknown', $this->storedContentArea( 'Housing' ) );
	}

	public function testThisWikiNamedAsTheSourceWikiReadsItsOwnCategory(): void {
		$this->addToCategory( 'ArticleContentAreaTest housing', self::CATEGORY );
		$this->setUpWiki( self::CATEGORY, WikiMap::getCurrentWikiId() );

		$this->assertSame( [ 'ArticleContentAreaTest housing' ], ArticleContentArea::getValidContentAreas() );
	}

	/**
	 * A wiki whose local category is empty accepts the source wiki's names, and
	 * only them
	 */
	public function testAWikiWithAnEmptyLocalCategoryStoresTheSourceWikiNames(): void {
		$this->setUpWiki( self::CATEGORY, 'hubwiki' );
		$hubDb = $this->createMock( IReadableDatabase::class );
		$hubDb->method( 'newSelectQueryBuilder' )
			->willReturnCallback( static fn () => new SelectQueryBuilder( $hubDb ) );
		$hubDb->method( 'selectFieldValues' )->willReturn( [ 'זכויות_עובדים' ] );
		$services = $this->getServiceContainer();
		$this->setService( 'ArticleContentArea.ValidContentAreaLookup', new class(
			self::CATEGORY,
			'hubwiki',
			$services->getDBLoadBalancerFactory(),
			$services->getTitleParser(),
			new NullLogger(),
			$hubDb
		) extends ValidContentAreaLookup {
			/** @var IReadableDatabase */
			private $hubDb;

			public function __construct( $categoryName, $sourceWiki, $lbFactory, $titleParser, $logger, $hubDb ) {
				parent::__construct( $categoryName, $sourceWiki, $lbFactory, $titleParser, $logger );
				$this->hubDb = $hubDb;
			}

			protected function getSourceWikiDatabase( string $sourceWiki ): IReadableDatabase {
				return $this->hubDb;
			}
		} );

		$this->assertSame( 'זכויות עובדים', $this->storedContentArea( 'זכויות עובדים' ) );
		$this->assertSame( 'unknown', $this->storedContentArea( self::MARKUP ) );
	}
}
