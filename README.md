ArticleContentArea extension for MediaWiki
=============================================

The ArticleContentArea extension allows users to set the main content area of an article through
a parser hook named "articlecontentarea". The extension inserts said content area in a few places:

- the page_props table, which allows querying through the API
- Javascript config variables, as wgArticleContentArea

## Usage
- Use `{{#articlecontentarea:the_actual_article_type}}` in an article.
- You can read the property from PHP this way:
  ```php
    ArticleContentArea::getArticleContentArea( $this->getTitle() );
  ```
- You can read the article's content area from JS using `mw.config.get('wgArticleContentArea')`.
- You can read the article's content area using the API this way:
  ```
  api.php?action=query&titles=__article_title__&prop=pageprops&ppprop=ArticleContentArea
  ```
- You can use ArticleContentArea::getJoin( $contentArea ) in other queries to get the content area.
  This assumes the existence of the `page` table in the original query. A field aliased 'content_area'
  is added.
  $contentArea is null by default, and if passed the join is an inner join, for filtering.

## Configuration
- `$wgArticleContentAreaCategoryName` - this is the name of a category whose members are valid content
  areas. A value that is not one of them is stored as `unknown`. By default, it is null, and so is
  every content area stored as `unknown`; the same goes for a category with no members.
- `$wgArticleContentAreaSourceWiki` - the wiki ID (database name) of the wiki that has that category.
  In a wiki farm that stores one wiki's content-area names on every wiki, set it on the other wikis to
  that wiki, so that they accept the same names. By default, it is null, which means this wiki. If the
  source wiki's database cannot be queried, no content area is valid, and a warning is logged to the
  `ArticleContentArea` channel.

## Changelog
### 0.3.0
- No content area is valid while there is no list of valid content areas. Before, an empty list
  (no category configured, or an empty category) made every value valid.
- `$wgArticleContentAreaSourceWiki`: validate against another wiki's category.
- Requires MediaWiki 1.43.

### 0.1.2
bugfix: the wrong page property was used (copied over from extension:ArticleType)
### 0.1.1
Internal refactoring and some syntactic sugar in public functions

### 0.1.0
Initial release
