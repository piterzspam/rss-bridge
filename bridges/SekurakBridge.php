<?php
class SekurakBridge extends FeedExpander {

	const MAINTAINER = 'No maintainer';
	const NAME = 'Sekurak';
	const URI = 'https://sekurak.pl/';
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
        $this->collectExpandableDatas('http://feeds.feedburner.com/Sekurak');
    }


	private function setGlobalArticlesParams()
	{
		$GLOBALS['my_debug'] = FALSE;
		//$GLOBALS['my_debug'] = TRUE;
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
		$new_url = get_text_from_attribute($article_html, 'LINK[rel="canonical"][href^="https://sekurak.pl/"]', 'href', NULL);
		if (isset($new_url))
		{
			$item['uri'] = $new_url;
		}

		$article = $article_html->find('ARTICLE#articleContent', 0);
		$article = str_get_html('<div class="article">'.$article->save().'</div>');

		$article = replace_attribute($article, "ARTICLE", "class", NULL);
		$article = replace_attribute($article, "ARTICLE", "id", NULL);
		$tags = return_tags_array($article, 'DIV.meta A[href][rel="category tag"]');

		$article = replace_tag_and_class($article, 'DIV.entry', 'single', 'DIV', 'article body');
		$article = insert_html($article, 'ARTICLE', '', '', '<h1 class="title">'.$item['title'].'</h1>');
		$date = date_format(date_timestamp_set(new DateTime(), $item['timestamp'])->setTimezone(new DateTimeZone('Europe/Warsaw')), 'c');
		$article = insert_html($article, 'H1.title', '', get_date_outertext($date));
		$article = move_element($article, 'DIV.article.body', 'DIV.dates', 'outertext', 'after');
		$article = insert_html($article, 'DIV.article.body', '', '<div class="authors">'.$item['author'].'</div>');
		$article = insert_html($article, 'DIV.authors', '', '', '<HR>');
		if (!is_null($authors_element = $article->find('DIV.authors', 0)))
		{
			$next_element = $authors_element->next_sibling();
			while (!is_null($next_element))
			{
				$next_element->outertext = "";
				$next_element = $next_element->next_sibling();
			}
		}
		$article = str_get_html($article->save());
		$article = foreach_replace_outertext_with_subelement_outertext($article, 'FIGURE.wp-block-embed-twitter', 'BLOCKQUOTE');
		$article = foreach_replace_outertext_with_subelement_outertext($article, 'DIV.authors FIGURE', 'IMG');

		$selectors_array = array();
		$selectors_array[] = 'SCRIPT';
		$selectors_array[] = 'comment';
		$article = foreach_delete_element_array($article, $selectors_array);

		$article = format_article_photos($article, 'FIGURE.wp-block-image', FALSE, 'src', 'FIGCAPTION');
		//$article = format_article_photos($article, 'FIGURE', FALSE);

		$article = add_style($article, 'FIGURE.photoWrapper', getStylePhotoParent());
		$article = add_style($article, 'FIGURE.photoWrapper IMG', getStylePhotoImg());
		$article = add_style($article, 'FIGCAPTION', getStylePhotoCaption());
		$article = add_style($article, 'BLOCKQUOTE', getStyleQuote());

		$item['content'] = $article;
		$item['categories'] = $tags;
		
		if (FALSE === in_array("szkolenie", $item['categories']))
		{
			return $item;
		}
	}

}
// Imaginary empty line!