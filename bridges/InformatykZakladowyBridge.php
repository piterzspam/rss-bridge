<?php
class InformatykZakladowyBridge extends FeedExpander {

	const MAINTAINER = 'No maintainer';
	const NAME = 'Informatyk Zakładowy';
	const URI = 'https://informatykzakladowy.pl/';
	const DESCRIPTION = 'No description provided';
	const CACHE_TIMEOUT = 86400;

	const PARAMETERS = array
	(
		'Parametry' => array
		(
			'limit' => array
			(
				'name' => 'Liczba artykułów',
				'type' => 'number',
				'required' => true,
				'defaultValue' => 3,
			),
			'include_not_downloaded' => array
			(
				'name' => 'Uwzględnij niepobrane',
				'type' => 'checkbox',
				'required' => false,
				'title' => 'Uwzględnij niepobrane'
			),
		)
	);

    public function collectData(){
		include 'myFunctions.php';
		$this->setGlobalArticlesParams();
        $this->collectExpandableDatas('https://informatykzakladowy.pl/feed/');
    }

	private function setGlobalArticlesParams()
	{
		$GLOBALS['my_debug'] = FALSE;
		if (TRUE === $this->getInput('include_not_downloaded'))
			$GLOBALS['include_not_downloaded'] = TRUE;
		else
			$GLOBALS['include_not_downloaded'] = FALSE;
	}

	protected function parseItem($newsItem)
	{
		$item = parent::parseItem($newsItem);
		if (count($this->items) >= intval($this->getInput('limit')))
		{
			if (TRUE === $GLOBALS['include_not_downloaded'])
			{
				return $item;
			}
			else
			{
				return;
			}
		}
		$url_parts = explode("?utm_source=", $item['uri']);
		$item['uri'] = $url_parts[0];
		
		$returned_array = my_get_html($item['uri']);
		if (200 === $returned_array['code'])
		{
			$article_html = $returned_array['html'];
		}
		else
		{
			return $item;
		}
		$article_html = str_get_html(prepare_article($article_html));


		$article = $article_html->find('MAIN#site-content', 0);
//		print_element($article, "article1");
//		$article = foreach_delete_element_containing_subelement($article, 'DIV.section-inner', 'LI.post-tags.meta-wrapper');

		
//		$selectors_array[] = 'DIV.comments-wrapper';
//		$selectors_array[] = 'HEADER DIV.entry-categories';
		$selectors_array[] = 'comment';
		$selectors_array[] = 'script';
//		$selectors_array[] = 'NAV.pagination-single';
//		$selectors_array[] = 'DIV.post-meta-single-top';
		$article = foreach_delete_element_array($article, $selectors_array);
//		print_element($article, "article2");
		
		$date_published = get_text_from_attribute($article_html, 'META[property="article:published_time"][content]', 'content', "");
		$date_modified = get_text_from_attribute($article_html, 'META[property="article:modified_time"][content]', 'content', "");

		$tags = return_tags_array($article, 'LI.post-tags A[href*="informatykzakladowy.pl/tag/"][rel="tag"]');
//		print_element($article, "article3");
//		print_var_dump($tags, "tags");
		$article = replace_tag_and_class($article, 'H1.entry-title', 'single', 'H1', 'title');
		$article = replace_tag_and_class($article, 'DIV.entry-content', 'single', 'DIV', 'article body');
		$article = replace_tag_and_class($article, 'DIV.wp-block-group DIV.wp-block-group__inner-container DIV.wp-block-media-text', 'single', 'DIV', 'authors');
		$article = replace_tag_and_class($article, 'DIV.authors FIGURE IMG', 'single', 'IMG', 'author photo');
		$article = replace_tag_and_class($article, 'DIV.authors DIV P', 'single', 'P', 'author description');

		$article = move_element($article, 'H1.title', 'ARTICLE', 'innertext', 'before');
		$article = insert_html($article, 'H1.title', '', get_date_outertext($date_published, $date_modified));
		$article = move_element($article, 'DIV.article.body', 'DIV.dates', 'outertext', 'after');
		$article = move_element($article, 'DIV.authors', 'DIV.article.body', 'outertext', 'after');
		$article = insert_html($article, 'DIV.authors', '', '', '<HR>');
		
		$selectors_array[] = 'DIV.article.bod DIV.wp-block-group';
		$selectors_array[] = 'DIV.article.bod SPAN[id^="more-"]';
		$selectors_array[] = 'HEADER';
		$selectors_array[] = 'DIV.post-inner';
		$selectors_array[] = 'DIV.section-inner';
		$selectors_array[] = 'NAV.pagination-single';
		$selectors_array[] = 'DIV.comments-wrapper';
		$selectors_array[] = 'DIV.wp-block-group';
		$article = foreach_delete_element_array($article, $selectors_array);
		$article = replace_attribute($article, '[style]', 'style', NULL);
		$article = replace_attribute($article, '[id]', 'id', NULL);
		$article = replace_attribute($article, 'ARTICLE[class]', 'class', NULL);
		$article = foreach_replace_outertext_with_subelement_outertext($article, 'FIGURE.wp-block-embed-twitter', 'BLOCKQUOTE');
		$article = foreach_replace_outertext_with_subelement_outertext($article, 'DIV.authors FIGURE', 'IMG');
		$article = foreach_replace_outertext_with_subelement_outertext($article, 'DIV.authors DIV.wp-block-media-text__content', 'P');

		

		$article = $article->find('ARTICLE', 0);

		$article = format_article_photos($article, 'DIV.wp-block-image', FALSE, 'src', 'FIGCAPTION');

		$article = foreach_replace_outertext_with_innertext($article, 'FIGURE.photoWrapper A[href*="informatykzakladowy.pl/wp-content/uploads/"]');

		$article = add_style($article, 'FIGURE.photoWrapper', getStylePhotoParent());
		$article = add_style($article, 'FIGURE.photoWrapper IMG', getStylePhotoImg());
		$article = add_style($article, 'FIGCAPTION', getStylePhotoCaption());
		$article = add_style($article, 'BLOCKQUOTE', getStyleQuote());

		$item['content'] = $article;
		$item['categories'] = $tags;
		
				
		return $item;
	}

}
// Imaginary empty line!