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
  areas. By default, it is null, and any content area is valid.

## Changelog
### 0.1.2
bugfix: the wrong page property was used (copied over from extension:ArticleType)
### 0.1.1
Internal refactoring and some syntactic sugar in public functions

### 0.1.0
Initial release
