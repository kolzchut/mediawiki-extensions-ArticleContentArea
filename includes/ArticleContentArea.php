<?php

namespace MediaWiki\Extension\ArticleContentArea;

use Category;
use MediaWiki\MediaWikiServices;
use Title;

class ArticleContentArea {

	/** @var string */
	public static string $DATA_VAR = 'ArticleContentArea';

	/**
	 * Get SELECT fields and joins for retrieving the content area
	 *
	 * @param null|string|array $contentArea
	 * @param string $pageIdFieldName if we want to compare to a differently named page_id field, such as log_page
	 *
	 * @return array[] With three keys:
	 *   - tables: (string[]) to include in the `$table` to `IDatabase->select()`
	 *   - fields: (string[]) to include in the `$vars` to `IDatabase->select()`
	 *   - join_conds: (array) to include in the `$join_conds` to `IDatabase->select()`
	 *  All tables, fields, and joins are aliased, so `+` is safe to use.
	 */
	public static function getJoin( $contentArea = null, $pageIdFieldName = 'page_id' ): array {
		$dbr = MediaWikiServices::getInstance()->getConnectionProvider()->getReplicaDatabase();
		$joinType  = $contentArea ? 'INNER JOIN' : 'LEFT OUTER JOIN';
		$joinConds = [ $pageIdFieldName .
			' = content_area_page_props.pp_page', "content_area_page_props.pp_propname = 'ArticleContentArea'" ];
		if ( $contentArea ) {
			$joinConds[] = 'content_area_page_props.pp_value IN (' . $dbr->makeList( (array)$contentArea ) . ')';
		}

		$tables['content_area_page_props'] = 'page_props';
		$joins['content_area_page_props'] = [ $joinType, $joinConds ];

		// Changing the field's alias MUST be marked as a breaking change
		$fields['content_area'] = 'content_area_page_props.pp_value';

		return [
			'tables' => $tables,
			'fields' => $fields,
			'join_conds' => $joins
		];
	}

	/**
	 * Get article type from the page_props table
	 *
	 * @param Title $title
	 *
	 * @return string|null
	 */
	public static function getArticleContentArea( Title $title ): ?string {
		$pageProps = MediaWikiServices::getInstance()->getPageProps();
		$propArray = $pageProps->getProperties( $title, self::$DATA_VAR );

		return empty( $propArray ) ? null : array_values( $propArray )[0];
	}

	/**
	 * The valid content areas: the members of $wgArticleContentAreaCategoryName
	 * on $wgArticleContentAreaSourceWiki (this wiki where that is null).
	 *
	 * @return string[]
	 */
	public static function getValidContentAreas(): array {
		/** @var ValidContentAreaLookup $lookup */
		$lookup = MediaWikiServices::getInstance()->getService( 'ArticleContentArea.ValidContentAreaLookup' );
		return $lookup->getNames();
	}

	/**
	 * Return array of names and titles for content areas currently assigned to pages.
	 * @return array
	 */
	public static function getAssignedContentAreas(): array {
		static $assignedContentAreas;
		// Do this expensive thing only once.
		if ( !isset( $assignedContentAreas ) ) {
			$assignedContentAreas = [];
			$dbr = MediaWikiServices::getInstance()->getConnectionProvider()->getReplicaDatabase();
			$res = $dbr->select(
				[ 'page_props' => 'page_props' ],
				[ 'page_props.pp_value' ],
				[
					'page_props.pp_propname' => 'ArticleContentArea',
					"pp_value <> ''"
				],
				__METHOD__,
				[ 'GROUP BY' => 'pp_value', 'ORDER BY' => 'pp_value ASC' ],
			);
			for ( $row = $res->fetchRow(); is_array( $row ); $row = $res->fetchRow() ) {
				$category = Category::newFromName( $row['pp_value'] );
				$assignedContentAreas[ $category->getName() ] = $category->getTitle()->getBaseText();
			}
		}
		return $assignedContentAreas;
	}

	/**
	 * Whether every value given is one of the valid content areas.
	 *
	 * Where there are no valid content areas (no category configured, an empty
	 * category, or a source wiki that cannot be read), nothing is valid: the
	 * value is stored as a page property that other extensions read and show.
	 *
	 * @param string|array $contentArea
	 *
	 * @return bool
	 */
	public static function isValidContentArea( $contentArea ) {
		if ( empty( $contentArea ) ) {
			return false;
		}

		$validValues = self::getValidContentAreas();
		if ( !$validValues ) {
			return false;
		}

		$diff = array_diff( (array)$contentArea, $validValues );
		return count( $diff ) === 0;
	}
}
