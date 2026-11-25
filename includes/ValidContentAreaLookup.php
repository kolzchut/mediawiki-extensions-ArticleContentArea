<?php

namespace MediaWiki\Extension\ArticleContentArea;

use InvalidArgumentException;
use MediaWiki\Title\TitleParser;
use Psr\Log\LoggerInterface;
use Wikimedia\Rdbms\DBError;
use Wikimedia\Rdbms\ILBFactory;
use Wikimedia\Rdbms\ILoadBalancer;
use Wikimedia\Rdbms\IReadableDatabase;

/**
 * The names of the valid content areas: the members of the category named by
 * $wgArticleContentAreaCategoryName, on the wiki named by
 * $wgArticleContentAreaSourceWiki (this wiki where that is null).
 *
 * A wiki farm that stores one wiki's content-area names on every wiki points
 * the others at that wiki, so they validate against the same list rather than
 * against a local category that may not exist there.
 *
 * Where there is no list (no category configured, an empty category, or a
 * source wiki that cannot be queried) the list is empty, and no content area
 * is valid.
 */
class ValidContentAreaLookup {

	/** @var string[]|null Names, once looked up */
	private ?array $names = null;

	/**
	 * @param string|null $categoryName The category whose members are the valid content areas
	 * @param string|null $sourceWiki Wiki ID of the wiki that has that category, or null for this wiki
	 * @param ILBFactory $lbFactory
	 * @param TitleParser $titleParser
	 * @param LoggerInterface $logger
	 */
	public function __construct(
		private readonly ?string $categoryName,
		private readonly ?string $sourceWiki,
		private readonly ILBFactory $lbFactory,
		private readonly TitleParser $titleParser,
		private readonly LoggerInterface $logger
	) {
	}

	/**
	 * @return string[] Page titles, with spaces, in category sort order
	 */
	public function getNames(): array {
		$this->names ??= $this->fetchNames();
		return $this->names;
	}

	/**
	 * @return string[]
	 */
	private function fetchNames(): array {
		if ( $this->categoryName === null || $this->categoryName === '' ) {
			return [];
		}
		$category = $this->titleParser->makeTitleValueSafe( NS_CATEGORY, $this->categoryName );
		if ( !$category ) {
			$this->logger->warning(
				'$wgArticleContentAreaCategoryName {category} is not a valid category name; ' .
					'no content area is valid',
				[ 'category' => $this->categoryName ]
			);
			return [];
		}

		try {
			$db = $this->sourceWiki === null
				? $this->lbFactory->getReplicaDatabase()
				: $this->getSourceWikiDatabase( $this->sourceWiki );
			$titles = $db->newSelectQueryBuilder()
				->select( 'page_title' )
				->from( 'categorylinks' )
				->join( 'page', null, 'cl_from = page_id' )
				->where( [ 'cl_to' => $category->getDBkey() ] )
				->orderBy( 'cl_sortkey' )
				->caller( __METHOD__ )
				->fetchFieldValues();
		} catch ( DBError | InvalidArgumentException $e ) {
			$this->logger->warning(
				'Could not read the content areas from wiki {wiki}; no content area is valid',
				[ 'wiki' => $this->sourceWiki, 'exception' => $e ]
			);
			return [];
		}

		return array_values( array_unique( array_map(
			static fn ( $title ) => str_replace( '_', ' ', (string)$title ),
			$titles
		) ) );
	}

	/**
	 * A replica connection to the source wiki's database.
	 *
	 * It is a connection of its own, in autocommit mode, rather than the
	 * request's shared one switched to that database: on a web request the
	 * shared connection is inside a transaction, and a failed switch to a
	 * database that does not exist would leave that transaction in an error
	 * state and fail every later query of the request.
	 *
	 * @param string $sourceWiki
	 * @return IReadableDatabase
	 */
	protected function getSourceWikiDatabase( string $sourceWiki ): IReadableDatabase {
		return $this->lbFactory->getMainLB( $sourceWiki )
			->getConnection( DB_REPLICA, [], $sourceWiki, ILoadBalancer::CONN_TRX_AUTOCOMMIT );
	}
}
