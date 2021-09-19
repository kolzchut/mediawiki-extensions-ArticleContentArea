<?php
namespace MediaWiki\Extension\ArticleContentArea;

use OutputPage;
use Parser;
use ParserOutput;

class Hooks implements
	\MediaWiki\Hook\MakeGlobalVariablesScriptHook,
	\MediaWiki\Hook\ParserFirstCallInitHook,
	\MediaWiki\Hook\OutputPageParserOutputHook{

	/**
	 * This hook is called when the parser initialises for the first time.
	 *
	 * @param Parser $parser Parser object being initialised
	 * @return bool|void True or no return value to continue or false to abort
	 */
	public function onParserFirstCallInit( $parser ) {
		$parser->setFunctionHook( 'articlecontentarea', [ __CLASS__, 'setArticleContentArea' ] );
	}

	/*
	 * Save data from ParserOutput to OutputPage
	 *
	 * @inheritDoc
	 */
	public function onOutputPageParserOutput( $out, $parserOutput ) : void {
		$out->setProperty( ArticleContentArea::$DATA_VAR, $parserOutput->getProperty( ArticleContentArea::$DATA_VAR ) );
	}

	/**
	 * Parser hook handler for {{#articlecontentarea}}
	 *
	 * @param Parser $parser : Parser instance available to render
	 *  wikitext into html, or parser methods.
	 * @param string $articleContentArea : the article type to set
	 *
	 * @return string: HTML to insert in the page.
	 */
	public static function setArticleContentArea( Parser &$parser, string $articleContentArea ) {
		$articleContentArea = trim( $articleContentArea );
		$articleContentArea = ArticleContentArea::isValidContentArea( $articleContentArea ) ? $articleContentArea : 'unknown';

		$parser->getOutput()->setProperty( ArticleContentArea::$DATA_VAR, $articleContentArea );

		return '';
	}

	/**
	 * Save the content area as a JS variable
	 *
	 * @inheritDoc
	 */
	public function onMakeGlobalVariablesScript( &$vars, $out ) {
		$vars['wgArticleContentArea'] = $out->getProperty( ArticleContentArea::$DATA_VAR );
	}
}
