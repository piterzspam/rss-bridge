<?php
class PolitykaBlogiBridge extends FeedExpander {
	const NAME = 'Polityka - Blogi';
	const URI = 'https://www.polityka.pl/blogi';
	const DESCRIPTION = 'No description provided';
	const MAINTAINER = 'No maintainer';
	const CACHE_TIMEOUT = 86400; // Can be omitted!

	const PARAMETERS = array
	(
		'Najnowsze' => array
		(
			'limit' => array
			(
				'name' => 'Liczba artykułów',
				'type' => 'number',
				'required' => true,
				'defaultValue' => 3,
			),
		),
		'Pojedynczy' => array
		(
			'url' => array
			(
				'name' => 'URL',
				'type' => 'text',
				'required' => true
			),
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
		),
	);

	public function collectData()
	{
		include 'myFunctions.php';
		$GLOBALS['my_debug'] = FALSE;
//		$GLOBALS['my_debug'] = TRUE;
		
		if (TRUE === $GLOBALS['my_debug'])
		{
			$GLOBALS['all_articles_time'] = 0;
			$GLOBALS['all_articles_counter'] = 0;
		}
		switch($this->queriedContext)
		{
			case 'Najnowsze':
				$this->collectNewest();
				break;
			case 'Pojedynczy':
				$this->collectSingleBlog();
				break;
		}
	}

	private function collectNewest()
	{
		$limit = intval($this->getInput('limit'));
		$articles_urls = array();
		$returned_array = my_get_html('https://www.polityka.pl/blogi');
		$html_articles_list = $returned_array['html'];
		if (200 === $returned_array['code'] && 0 !== count($preview_elements = $html_articles_list->find('UL.cg_blogs_articles.cg_tab_visible LI')))
		{
			foreach($preview_elements as $preview)
			{
				if (!is_null($href_element = $preview->find('A.cg_blogs_link[href]', 0)))
				{
					$articles_urls[] = $href_element->href;
				}
			}
		}
		$articles_urls = array_slice($articles_urls, 0, $limit);
		foreach ($articles_urls as $article_url)
		{
			$this->addArticle($article_url);
		}
	}

	private function collectSingleBlog()
	{
		if (TRUE === $this->getInput('include_not_downloaded'))
			$GLOBALS['include_not_downloaded'] = TRUE;
		else
			$GLOBALS['include_not_downloaded'] = FALSE;
		$url_array = parse_url($this->getInput('url'));
		$domain = $url_array["scheme"].'://'.$url_array["host"];
        $this->collectExpandableDatas($this->getInput('url')."/feed/");
	}
	
	protected function parseItem($newsItem)
	{
		$item = parent::parseItem($newsItem);
		if (count($this->items) >= intval($this->getInput('limit')))
		{
			if (TRUE === $GLOBALS['include_not_downloaded'])
				return $item;
			else
				return;
		}
		$this->addArticle($item['uri'], $item['timestamp']);
	}

	private function addArticle($url_article, $date = NULL)
	{
		$returned_array = my_get_html($url_article);
		if (200 !== $returned_array['code'])
		{
			return;
		}
		else
		{
			$article_html = $returned_array['html'];
		}
		
		$url_array = parse_url($url_article);
		$domain = $url_array["scheme"].'://'.$url_array["host"];
		$article_html = str_get_html(prepare_article($article_html, $domain));
		$article_html_str = $article_html->save();
		foreach($article_html->find('IMG[src^=" /"]') as $image)
		{
			$old_src = $image->src;
			$new_src = $domain.trim($old_src);
			$article_html_str = str_replace($old_src, $new_src, $article_html_str);
		}
		$article_html = str_get_html($article_html_str);

		$article_html = str_get_html(prepare_article($article_html));
		$article = $article_html->find('ARTICLE[id^="post-"]', 0);
		
		$tags = return_tags_array($article, 'DIV.tags A[rel="tag"]');
		if ($article->hasAttribute("class"))
		{
			$class_text = $article->class;
			$class_array = explode(" ", $class_text);
			foreach ($class_array as $class_part)
			{
				if (FALSE !== strpos($class_part, "category-"))
				{
					$tags[] = remove_substring_if_exists_first($class_part, "category-");
				}
			}
		}
		$title = get_text_from_attribute($article_html, 'META[property="og:title"][content]', 'content', $url_article);
		$author = return_authors_as_string($article, 'DIV.cg_single_entry_title DIV.cg_author');
		if (is_null($date))
		{
			preg_match_all('/[0-9]+/', $url_article, $output_array);
			$date = $output_array[0][0]."-".$output_array[0][1]."-".$output_array[0][2]." 00:00:00+0000";
		}
		else
		{
			$date = date_format(date_timestamp_set(new DateTime(), $date)->setTimezone(new DateTimeZone('Europe/Warsaw')), 'c');
		}
		

		$article = replace_date($article, 'DIV.cg_date', $date);
		$article = replace_tag_and_class($article, 'H1.entry-title', 'single', 'H1', 'title');
		$article = replace_tag_and_class($article, 'DIV.cg_author', 'single', 'DIV', 'authors');

		$article = move_element($article, 'DIV.dates', 'H1.title', 'outertext', 'after');
		$article = move_element($article, 'DIV.authors', 'ARTICLE', 'outertext', 'after');
		$article = insert_html($article, 'DIV.authors', '<hr>');

		$selectors_array = array();
		$selectors_array[] = 'comment';
		$selectors_array[] = 'script';
		$selectors_array[] = 'SPAN[id^="more-"]';
		$selectors_array[] = 'NAV.navigation';
		$selectors_array[] = 'DIV.cg_article_toolbox';
		$selectors_array[] = 'DIV.tags';
		$selectors_array[] = 'DIV.cg_single_entry_meta_data';
		$article = foreach_delete_element_array($article, $selectors_array);

		$article = foreach_replace_outertext_with_innertext($article, 'DIV.cg_single_entry_title');
		$article = foreach_replace_outertext_with_innertext($article, 'DIV.cg_single_entry_content');
		$article = foreach_replace_outertext_with_innertext($article, 'DIV.single-entry');
		$article = foreach_replace_outertext_with_innertext($article, 'qqqqqqqqqqqqq');
		$article = foreach_replace_outertext_with_innertext($article, 'qqqqqqqqqqqqq');
		
		$attributes_array = array();
		$attributes_array[] = "id";
		$attributes_array[] = "fullversioncharlength";
		$article = remove_multiple_attributes($article, $attributes_array);
		$article = replace_attribute($article, "ARTICLE", "class", NULL);
		$article = format_article_photos($article, 'FIGURE', FALSE, 'src', 'FIGCAPTION');
		
		
	

		$article = add_style($article, 'FIGURE.photoWrapper', getStylePhotoParent());
		$article = add_style($article, 'FIGURE.photoWrapper IMG', getStylePhotoImg());
		$article = add_style($article, 'FIGCAPTION', getStylePhotoCaption());
		$article = add_style($article, 'BLOCKQUOTE', getStyleQuote());


		$this->items[] = array(
			'uri' => $url_article,
			'title' => $title,
			'timestamp' => $date,
			'author' => $author,
			'content' => $article,
			'categories' => $tags
		);
	}
}
