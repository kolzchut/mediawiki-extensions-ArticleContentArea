<?php
namespace MediaWiki\Extension\ArticleContentArea;

use Parser;

class Hooks implements
	\MediaWiki\Hook\MakeGlobalVariablesScriptHook,
	\MediaWiki\Hook\ParserFirstCallInitHook,
	\MediaWiki\Hook\OutputPageParserOutputHook
{

	/**
	 * Set our function hook
	 *
	 * @inheritDoc
	 */
	public function onParserFirstCallInit( $parser ): void {
		$parser->setFunctionHook( 'articlecontentarea', [ __CLASS__, 'setArticleContentArea' ] );
	}

	/**
	 * Save data from ParserOutput to OutputPage
	 *
	 * @inheritDoc
	 */
	public function onOutputPageParserOutput( $outputPage, $parserOutput ): void {
		$outputPage->setProperty(
			ArticleContentArea::$DATA_VAR, $parserOutput->getPageProperty( ArticleContentArea::$DATA_VAR )
		);
	}

	/**
	 * Parser hook handler for `{{#articlecontentarea}}`
	 *
	 * @param Parser &$parser : Parser instance available to render
	 *  wikitext into HTML, or parser methods.
	 * @param string $articleContentArea : the article type to set
	 *
	 * @return string HTML to insert in the page.
	 */
	public static function setArticleContentArea( Parser &$parser, string $articleContentArea ): string {
		$articleContentArea = trim( $articleContentArea );
		$articleContentArea = ArticleContentArea::isValidContentArea( $articleContentArea ) ?
			$articleContentArea : 'unknown';

		$parser->getOutput()->setPageProperty( ArticleContentArea::$DATA_VAR, $articleContentArea );

		return '';
	}

	/**
	 * Save the content area as a JS variable
	 *
	 * @inheritDoc
	 */
	public function onMakeGlobalVariablesScript( &$vars, $out ): void {
		$vars['wgArticleContentArea'] = $out->getProperty( ArticleContentArea::$DATA_VAR );
	}
}
