<?php

use MediaWiki\Extension\ArticleContentArea\ValidContentAreaLookup;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;
use MediaWiki\WikiMap\WikiMap;

return [
	'ArticleContentArea.ValidContentAreaLookup' => static function (
		MediaWikiServices $services
	): ValidContentAreaLookup {
		$config = $services->getMainConfig();
		$categoryName = $config->get( 'ArticleContentAreaCategoryName' );
		$sourceWiki = $config->get( 'ArticleContentAreaSourceWiki' );
		// This wiki, by name or by default, is read through the request's own connection
		if ( !is_string( $sourceWiki ) || $sourceWiki === '' || WikiMap::isCurrentWikiId( $sourceWiki ) ) {
			$sourceWiki = null;
		}

		return new ValidContentAreaLookup(
			is_string( $categoryName ) ? $categoryName : null,
			$sourceWiki,
			$services->getDBLoadBalancerFactory(),
			$services->getTitleParser(),
			LoggerFactory::getInstance( 'ArticleContentArea' )
		);
	},
];
