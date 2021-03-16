<?php
class DemagogBridge extends BridgeAbstract {
	const NAME = 'Demagog';
	const URI = 'https://demagog.org.pl/';
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
				'required' => true,
				'defaultValue' => 3,
			),
			'category' => array(
				'name' => 'Kategoria',
				'type' => 'list',
				'values' => array(
					'Analizy i raporty' => 'https://demagog.org.pl/analizy_i_raporty/',
					'Fake News' => 'https://demagog.org.pl/fake_news/',
					'Weryfikacja wypowiedzi' => 'https://demagog.org.pl/wypowiedzi/',
					'Obietnice wyborcze' => 'https://demagog.org.pl/analizy-obietnic/',
				 ),
				'title' => 'Kategoria',
				'defaultValue' => 'https://demagog.org.pl/fake_news/',
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
		
		if (TRUE === $GLOBALS['my_debug'])
		{
			$GLOBALS['all_articles_time'] = 0;
			$GLOBALS['all_articles_counter'] = 0;
		}
		switch($this->queriedContext)
		{
			case 'Kategoria':
				$this->collectCategoryArticles();
				break;
			case 'Najnowsze':
				$this->collectNewest();
				break;
		}
	}

	public function getName()
	{
		switch($this->queriedContext)
		{
			case 'Kategoria':
				if(FALSE === is_null($this->getInput('category')))
				{
					$url_array = parse_url($this->getInput('category'));
					$path = $url_array["path"];
					$path = str_replace('/', '', $path);
					$path = str_replace('-', ' ', $path);
					$path = str_replace('_', ' ', $path);
					$path = ucwords($path);
					return "Demagog.org.pl - ".$path;
				}
				else
					return parent::getName();
				break;
			case 'Najnowsze':
					return "Demagog.org.pl - Najnowsze";
				break;
			default:
				return parent::getName();
		}
	}

	public function getURI()
	{
		switch($this->queriedContext)
		{
			case 'Kategoria':
				if(FALSE === is_null($this->getInput('category')))
				{
					return $this->getInput('category');
				}
				else
					return parent::getURI();
				break;
			case 'Najnowsze':
				return parent::getURI();
				break;
			default:
				return parent::getURI();
		}
	}

	private function collectCategoryArticles()
	{
		$GLOBALS['limit'] = $this->getInput('limit');
		$GLOBALS['chosen_category_url'] = $this->getInput('category');
		$found_urls = $this->getArticlesUrlsCategoryArticles();
		foreach($found_urls as $url)
			$this->addArticle($url);

		if (TRUE === $GLOBALS['my_debug'])
			echo "<br>Wszystkie {$GLOBALS['all_articles_counter']} artykulow zajelo {$GLOBALS['all_articles_time']}, <br>średnio ".$GLOBALS['all_articles_time']/$GLOBALS['all_articles_counter'] ."<br>";
	}
	private function getArticlesUrlsCategoryArticles()
	{
		$articles_urls = array();
		$url_articles_list = $GLOBALS['chosen_category_url'];
		while (count($articles_urls) < $GLOBALS['limit'] && "empty" != $url_articles_list)
		{
			$returned_array = $this->my_get_html($url_articles_list);
			$html_articles_list = $returned_array['html'];
			if (200 !== $returned_array['code'] || 0 === count($found_hrefs = $html_articles_list->find('DIV#response ARTICLE[id^="post-"] H2.title-archive A[href]')))
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

	private function collectNewest()
	{
		$GLOBALS['limit'] = $this->getInput('limit');
		$found_urls = $this->getArticlesUrlsNewest();
		foreach($found_urls as $url)
			$this->addArticle($url);
		
		if (TRUE === $GLOBALS['my_debug'])
			echo "<br>Wszystkie {$GLOBALS['all_articles_counter']} artykulow zajelo {$GLOBALS['all_articles_time']}, <br>średnio ".$GLOBALS['all_articles_time']/$GLOBALS['all_articles_counter'] ."<br>";
	}
	private function getArticlesUrlsNewest()
	{
		$articles_urls = array();
		$GLOBALS['limit'] = $this->getInput('limit');

		$returned_array = $this->my_get_html('https://demagog.org.pl/');
		$html_articles_list = $returned_array['html'];
//		if (200 === $returned_array['code'] || 0 !== count($found_hrefs = $html_articles_list->find('DIV.slider-news-home.mt-5.mt-md-0 H2.title-archive A[href]')))
		if (200 === $returned_array['code'] && 0 !== count($found_hrefs = $html_articles_list->find('DIV.slider-news-home.mt-5.mt-md-0 H2.title-archive A[href]')))
		{
			foreach($found_hrefs as $href_element)
			{
				if(isset($href_element->href))
					$articles_urls[] = $href_element->href;
			}
		}
		return array_slice($articles_urls, 0, $GLOBALS['limit']);
	}

	private function getNextPageUrl($html_articles_list)
	{		
		$next_page_element = $html_articles_list->find('A.next.page-numbers[href]', 0);
		if (FALSE === is_null($next_page_element) && $next_page_element->hasAttribute('href'))
		{
			$url = $next_page_element->getAttribute('href');
			return $url;
		}
		else
			return "empty";
	}

	private function addArticle($url)
	{
		$returned_array = $this->my_get_html($url);
		if (200 !== $returned_array['code'])
		{
			return;
		}
		$article_html = $returned_array['html'];
		replace_attribute($article_html, 'IMG[src^="data:image/"][data-src^="http"]', 'src', 'data-src');
		fix_all_photos($article_html);
		fix_all_iframes($article_html);

		$article_html->outertext = str_replace("&nbsp;", '', $article_html->outertext);
		$article = $article_html->find('MAIN[role="main"] DIV.container', 0);

		//tytuł
		$title = get_text_plaintext($article, 'H1.w-100.mb-1', $url);
		//autor
		$author = "Demagog";
		//tagi
		$tags = return_tags_array($article, 'DIV.tags A[href]');
		//data
		$date = "";
		if (FALSE === is_null($article_data = $article_html->find('SCRIPT.yoast-schema-graph', 0)))
		{
			$article_data_parsed = parse_article_data(json_decode($article_data->innertext));
			if(isset($article_data_parsed["@graph"][3]["datePublished"]))
			{
				$date = $article_data_parsed["@graph"][3]["datePublished"];
			}
		}
		if ("" === $date || is_null($date))
		{
			$date = get_text_from_attribute($article_html, 'META[property="article:modified_time"][content]', 'content', "");
		}

		foreach_delete_element($article, 'DIV#share-fact');
		foreach_delete_element($article, 'DIV.row-custom.blue.mb-3.pb-2 P[!class]');
		foreach_delete_element($article, 'DIV.breadcrumbs');
		foreach_delete_element($article, 'DIV.mb-5.d-none.d-md-flex');
		foreach_replace_outertext_with_innertext($article, 'DIV.row-custom.blue.mb-3.pb-2');
		foreach_replace_outertext_with_innertext($article, 'DIV.mb-5.pb-3.count-text');
//		foreach_delete_element($article, 'NOSCRIPT');
		//https://demagog.org.pl/wypowiedzi/ilu-wnioskow-o-skargi-nadzwyczajne-wciaz-nie-rozpatrzono/
		fix_article_photos($article, 'DIV[id^="attachment_"], IMG.alignnone', FALSE, 'src', 'P.wp-caption-text');
		fix_article_photos($article, 'DIV.col-12.mb-4.px-0.w-img-100', TRUE);
		foreach($article->find('DIV.important-text') as $quote_element)
		{
			$new_outertext = '<blockquote>'.$quote_element->innertext.'</blockquote>';
			$quote_element->outertext = $new_outertext;
		}
		add_style($article, 'FIGURE.photoWrapper', getStylePhotoParent());
		add_style($article, 'FIGURE.photoWrapper IMG', getStylePhotoImg());
		add_style($article, 'FIGCAPTION', getStylePhotoCaption());
		add_style($article, 'BLOCKQUOTE', getStyleQuote());

		$this->items[] = array(
			'uri' => $url,
			'title' => $title,
			'timestamp' => $date,
			'author' => $author,
			'categories' => $tags,
			'content' => $article
		);
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
