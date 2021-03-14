<?php
class LiberteBridge extends BridgeAbstract {
	const NAME = 'Liberte';
	const URI = 'https://liberte.pl/';
	const DESCRIPTION = 'No description provided';
	const MAINTAINER = 'No maintainer';
	const CACHE_TIMEOUT = 86400; // Can be omitted!

	const PARAMETERS = array
	(
		'Kategoria' => array
		(
			'limit' => array
			(
				'name' => 'Liczba artykułów',
				'type' => 'number',
				'required' => true
			),
			'category' => array(
				'name' => 'Kategoria',
				'type' => 'list',
				'values' => array(
					'Polityka' => 'https://liberte.pl/category/polityka-2/',
					'Społeczeństwo otwarte' => 'https://liberte.pl/category/spoleczenstwo-otwarte/',
					'Kultura' => 'https://liberte.pl/category/kultura/',
					'Wolny rynek' => 'https://liberte.pl/category/wolny-rynek/',
				 ),
				'title' => 'Kategoria',
				'defaultValue' => 'https://liberte.pl/category/polityka-2/',
			),
		),
		'Wydania' => array
		(
			'limit' => array
			(
				'name' => 'Liczba wydań',
				'type' => 'number',
				'required' => true
			),
		),
		'Najnowsze' => array
		(
			'limit' => array
			(
				'name' => 'Liczba artykułów',
				'type' => 'number',
				'required' => true
			),
		)
	);

	public function collectData()
	{
		include 'myFunctions.php';
		$GLOBALS['my_debug'] = FALSE;
//		$GLOBALS['my_debug'] = TRUE;
		switch($this->queriedContext)
		{
			case 'Kategoria':
				$this->collectCategoryArticles();
				break;
			case 'Wydania':
				$this->collectIssues();
				break;
			case 'Najnowsze':
				$this->collectNewest();
				break;
		}
	}

	public function collectCategoryArticles()
	{
		$GLOBALS['limit'] = $this->getInput('limit');
		$GLOBALS['chosen_category_url'] = $this->getInput('category');
		$found_urls = $this->getArticlesUrlsCategoryArticles();
		foreach($found_urls as $url)
			$this->addArticle($url);
	}
	private function getArticlesUrlsCategoryArticles()
	{
		$articles_urls = array();
		$url_articles_list = $GLOBALS['chosen_category_url'];
		while (count($articles_urls) < $GLOBALS['limit'] && "empty" != $url_articles_list)
		{
			$returned_array = $this->my_get_html($url_articles_list);
			$html_articles_list = $returned_array['html'];
			if (200 !== $returned_array['code'] || 0 === count($found_hrefs = $html_articles_list->find('DIV#wrapper DIV.row H3 A[href]')))
			{
				break;
			}
			else
			{
				foreach($found_hrefs as $href_element)
				{
					if(isset($href_element->href))
						$articles_urls[] = $href_element->href;
				}
			}
			$url_articles_list = $this->getNextPageUrl($html_articles_list);
		}
		return array_slice($articles_urls, 0, $GLOBALS['limit']);
	}
	private function getNextPageUrl($html_articles_list)
	{		
		$next_page_element = $html_articles_list->find('A.nextpostslink[href]', 0);
		if (FALSE === is_null($next_page_element) && $next_page_element->hasAttribute('href'))
		{
			$url = $next_page_element->getAttribute('href');
			return $url;
		}
		else
			return "empty";
	}

	public function collectNewest()
	{
		$GLOBALS['limit'] = $this->getInput('limit');
		$found_urls = $this->getArticlesUrlsNewest();
		foreach($found_urls as $url)
			$this->addArticle($url);
	}
	private function getArticlesUrlsNewest()
	{
		$articles_urls = array();
		$GLOBALS['limit'] = $this->getInput('limit');

		$returned_array = $this->my_get_html('https://liberte.pl/');
		$html_articles_list = $returned_array['html'];
		if (200 !== $returned_array['code'] || 0 === count($found_hrefs = $html_articles_list->find('SECTION[!class] DIV#anchor-link-2 ARTICLE.entry.box-white H2 A[href], SECTION[!class] DIV#anchor-link-2 ARTICLE.entry.box-white H3 A[href]')))
		{
			return $articles_urls;
		}
		else
		{
			foreach($found_hrefs as $href_element)
			{
				if(isset($href_element->href))
					$articles_urls[] = $href_element->href;
			}
		}
		return array_slice($articles_urls, 0, $GLOBALS['limit']);
	}

	public function collectIssues()
	{
		$GLOBALS['limit'] = $this->getInput('limit');
		$found_urls = $this->getArticlesUrlsIssues();
//		var_dump_print($found_urls);
		foreach($found_urls as $url)
			$this->addArticle($url);
	}

	private function getArticlesUrlsIssues()
	{
		$articles_urls = array();
		$returned_array = $this->my_get_html('https://liberte.pl/miesiecznik/');
		$html_issues_list = $returned_array['html'];
		if (200 !== $returned_array['code'] || 0 === count($found_issues_hrefs = $html_issues_list->find('DIV#wrapper SECTION DIV.row ARTICLE.entry.box-white H2 A[href]')))
		{
			return $articles_urls;
		}
		else
		{
			$issues_urls = array();
			foreach($found_issues_hrefs as $href_element)
			{
				$issues_urls[] = $href_element->href;
			}
		}
		$issues_urls = array_slice($issues_urls, 0, $GLOBALS['limit']);

		foreach($issues_urls as $issue_url)
		{
			$returned_array = $this->my_get_html($issue_url);
			$html_articles_list = $returned_array['html'];
//			if (200 === $returned_array['code'] || 0 !== count($found_articles_hrefs = $html_articles_list->find('H3.title-spistresci A[href]')))
			if (200 === $returned_array['code'] && 0 !== count($found_articles_hrefs = $html_articles_list->find('H3.title-spistresci A[href]')))
			{
				foreach($found_articles_hrefs as $href_element)
				{
					if(isset($href_element->href))
						$articles_urls[] = $href_element->href;
				}
			}
		}
//		var_dump_print($articles_urls);
		return $articles_urls;
	}



	private function addArticle($url)
	{
		$returned_array = $this->my_get_html($url);
		if (200 !== $returned_array['code'])
		{
			return;
		}
		$article_html = $returned_array['html'];


		$article = $article_html->find('DIV#wrapper', 0);

		//tytuł
		$title = "";
		if (FALSE === is_null($title_element = $article->find('SECTION.single-top DIV.row.align-center DIV.large-8.medium-12.small-12.columns.text-center H1', 0)))
		{
			$title = trim($title_element->plaintext);
		}
		//autor
		$author = returnAuthorsAsString($article, 'DIV.entry-autor H5 A.big.black.bold[href*="/author/"]');
		//tagi
		$tags = returnTagsArray($article, 'DIV.entry-autor A.light[href*="/tag/"]');	
		if (FALSE === is_null($category_element = $article_html->find('META[property="article:section"][content]', 0)))
		{
			$tags[] = $category_element->getAttribute('content');
		}
		//data
		$date = "";
		if (FALSE === is_null($date_element = $article_html->find('META[property="article:published_time"][content]', 0)))
		{
//			element_print($date_element, "date_element", "<br>");
			$date = $date_element->getAttribute('content');
		}
//		element_print($date, "date_element", "<br>");
//		var_dump_print($date);

		if (FALSE === is_null($header = $article->find('SECTION.single-top', 0)) && FALSE === is_null($article_text = $article->find('DIV.margin-bottom30 DIV.row', 0)))
		{
			$article->outertext = $header->innertext.$article_text->innertext;
		}

		$article = str_get_html($article->save());
		deleteAllDescendantsIfExist($article, 'DIV[data-sticky-container]');
		deleteAllDescendantsIfExist($article, 'DIV.single-bottom.border-top.padding-top30');
		deleteAllDescendantsIfExist($article, 'DIV.show-for-large');
		deleteAllAncestorsIfDescendantExists($article, 'DIV.row', 'DIV#disqus_thread');
		$article = str_get_html($article->save());
		replaceAllBiggerOutertextWithSmallerInnertext($article, 'DIV#anchor-link', 'DIV.flexible');
		replaceAllBiggerOutertextWithSmallerOutertext($article, 'DIV.large-8.medium-12.small-12.columns.text-center', 'H1');
		replaceAllBiggerOutertextWithSmallerInnertext($article, 'DIV.large-8.medium-12.small-12.columns', 'DIV.row.collapse.align-middle.entry-autor');
		replaceAllBiggerOutertextWithSmallerInnertext($article, 'qqqqqqqqqqqqqqqq', 'qqqqqqqqqqqqqqqq');


		$article = str_get_html($article->save());

		$this->fix_main_photo($article);
		$this->fix_article_photos_sources($article);

		fix_article_photos($article, 'IMG.alignleft[src^="http"], IMG.alignright[src^="http"], IMG.alignnone[src^="http"]', FALSE);


		
		foreach($article->find('DIV.important-text') as $quote_element)
		{
			$new_outertext = '<blockquote>'.$quote_element->innertext.'</blockquote>';
			$quote_element->outertext = $new_outertext;
		}

		$article = str_get_html($article->save());
		addStyle($article, 'FIGURE.photoWrapper', getStylePhotoParent());
		addStyle($article, 'FIGURE.photoWrapper IMG', getStylePhotoImg());
		addStyle($article, 'FIGCAPTION', getStylePhotoCaption());
		addStyle($article, 'BLOCKQUOTE', getStyleQuote());
		$article = str_get_html($article->save());

		$this->items[] = array(
			'uri' => $url,
			'title' => $title,
			'timestamp' => $date,
			'author' => $author,
			'categories' => $tags,
			'content' => $article
		);
	}

	private function fix_main_photo($article)
	{
		foreach($article->find('DIV.large-12 DIV.photobg.margin-bottom30[style^="background-image: url("]') as $article_element)
		{
			$style_string = $article_element->getAttribute('style');
			$style_string = str_replace('background-image:', '', $style_string);
			$style_string = trim($style_string);
			$style_string = removeSubstringIfExistsFirst($style_string, 'url(');
			$style_string = removeSubstringIfExistsLast($style_string, ');');
			$style_string = trim($style_string);
			$img_src = $style_string;
			$new_outertext = '<figure class="photoWrapper mainPhoto"><img src="'.$img_src.'"></figure>';
			$article_element->parent->outertext = $new_outertext;
		}
	}

	private function fix_article_photos_sources($article)
	{
		foreach($article->find('IMG[src^="http"]') as $photo_element)
		{
			$img_src = $photo_element->getAttribute('src');
			$img_src = str_replace('-300x200', '', $img_src);
			if($photo_element->hasAttribute('srcset'))
			{
				$img_srcset = $photo_element->getAttribute('srcset');
				$srcset_array  = explode(',', $img_srcset);
				$last = count($srcset_array) - 1;
				$last_url_string = trim($srcset_array[$last]);
				$last_url_array  = explode(' ', $last_url_string);
				$img_src = $last_url_array[0];
			}
			$photo_element->setAttribute('src', $img_src);
		}
	}

	private function my_get_html($url)
	{
		$context = stream_context_create(array('http' => array('ignore_errors' => true)));

		if (TRUE === $GLOBALS['my_debug'])
		{
			$start_request = microtime(TRUE);
			$page_content = file_get_contents($url, false, $context);
			$end_request = microtime(TRUE);
			echo "<br>Article  took " . ($end_request - $start_request) . " seconds to complete - url: $url.";
			$GLOBALS['all_articles_counter']++;
			$GLOBALS['all_articles_time'] = $GLOBALS['all_articles_time'] + $end_request - $start_request;
		}
		else
			$page_content = file_get_contents($url, false, $context);

		$code = getHttpCode($http_response_header);
		if (200 !== $code)
		{
			$html_error = createErrorContent($http_response_header);
			$date = new DateTime("now", new DateTimeZone('Europe/Warsaw'));
			$date_string = date_format($date, 'Y-m-d H:i:s');
			$this->items[] = array(
				'uri' => $url,
				'title' => "Error ".$code.": ".$url,
				'timestamp' => $date_string,
				'content' => $html_error
			);
		}
		$page_html = str_get_html($page_content);

		$return_array = array(
			'code' => $code,
			'html' => $page_html,
		);
		return $return_array;
	}
}
