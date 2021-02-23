<?php
class DemagogBridge extends BridgeAbstract {
	const NAME = 'Demagog';
	const URI = 'https://demagog.org.pl/';
	const DESCRIPTION = 'No description provided';
	const MAINTAINER = 'No maintainer';
	const CACHE_TIMEOUT = 86400; // Can be omitted!

	const PARAMETERS = array
	(
		'Artykuły kategorii' => array
		(
			'wanted_number_of_articles' => array
			(
				'name' => 'Liczba artykułów',
				'type' => 'number',
				'required' => true
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
			'wanted_number_of_articles' => array
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
			case 'Artykuły kategorii':
				$this->collectCategoryArticles();
				break;
			case 'Najnowsze':
				$this->collectNewest();
				break;
		}
	}

	private function collectCategoryArticles()
	{
		$GLOBALS['number_of_wanted_articles'] = $this->getInput('wanted_number_of_articles');
		$GLOBALS['chosen_category_url'] = $this->getInput('category');
		$found_urls = $this->getArticlesUrlsCategoryArticles();
		foreach($found_urls as $url)
			$this->addArticle($url);
	}
	private function getArticlesUrlsCategoryArticles()
	{
		$articles_urls = array();
		$url_articles_list = $GLOBALS['chosen_category_url'];
		while (count($articles_urls) < $GLOBALS['number_of_wanted_articles'] && "empty" != $url_articles_list)
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
		return array_slice($articles_urls, 0, $GLOBALS['number_of_wanted_articles']);
	}

	private function collectNewest()
	{
		$GLOBALS['wanted_number_of_articles'] = $this->getInput('wanted_number_of_articles');
		$found_urls = $this->getArticlesUrlsNewest();
		foreach($found_urls as $url)
			$this->addArticle($url);
	}
	private function getArticlesUrlsNewest()
	{
		$articles_urls = array();
		$GLOBALS['wanted_number_of_articles'] = $this->getInput('wanted_number_of_articles');

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
		return array_slice($articles_urls, 0, $GLOBALS['wanted_number_of_articles']);
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

		$article_html->outertext = str_replace("&nbsp;", '', $article_html->outertext);
//		echo "<br>article_html:<br>|$article_html|<br><br><br><br><br><br>";
		$article = $article_html->find('MAIN[role="main"] DIV.container', 0);
		$article = str_get_html($article->save());

/*

		if (FALSE === is_null($title_element = $article->find('META[property="og:title"][content]', 0)))
		{
			if($title_element->hasAttribute('content'))
				$title = trim($title_element->getAttribute('content'));
		}
*/


		//tytuł
		$title = "";
		if (FALSE === is_null($title_element = $article_html->find('TITLE', 0)))
		{
			$title = trim($title_element->plaintext);
		}
		else
		{
			$title = $url;
		}
		//autor
		$author = "Demagog";
		//tagi
		$tags = returnTagsArray($article, 'DIV.tags A[href]');
		//data
		$date = "";
		if (FALSE === is_null($article_data = $article_html->find('SCRIPT.yoast-schema-graph--main', 0)))
		{
			$article_data_parsed = parse_article_data(json_decode($article_data->innertext));
			$date = $article_data_parsed["@graph"][2]["datePublished"];
		}

		deleteAllDescendantsIfExist($article, 'DIV#share-fact');
		deleteAllDescendantsIfExist($article, 'DIV.row-custom.blue.mb-3.pb-2 P[!class]');
		deleteAllDescendantsIfExist($article, 'DIV.breadcrumbs');
		deleteAllDescendantsIfExist($article, 'DIV.mb-5.d-none.d-md-flex');

		if (FALSE === is_null($article_element = $article->find('DIV.row-custom.blue.mb-3.pb-2', 0)))
		{
			$article_element->outertext = $article_element->innertext;
		}
		if (FALSE === is_null($article_element = $article->find('DIV.mb-5.pb-3.count-text', 0)))
		{
			$article_element->outertext = $article_element->innertext;
		}

		$article = str_get_html($article->save());

		$this->fix_main_photo($article);
		$this->fix_article_photos($article);
		
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
		foreach($article->find('DIV.col-12.mb-4.px-0.w-img-100 IMG[src]') as $article_element)
		{
			$img_src = "";
			$img_alt = "";
			$img_src = $article_element->getAttribute('src');
			if($article_element->hasAttribute('alt'))
				$img_alt = trim($article_element->getAttribute('alt'));
			if (0 === strlen($img_alt))
				$new_outertext = '<figure class="photoWrapper mainPhoto"><img src="'.$img_src.'"></figure>';
			else
				$new_outertext = '<figure class="photoWrapper mainPhoto"><img src="'.$img_src.'" alt="'.$img_alt.'"></figure>';
//			$article->outertext = str_replace($article_element->parent->outertext, $new_outertext, $article->outertext);
			$article_element->outertext = $new_outertext;
		}
	}

	private function fix_article_photos($article)
	{
		foreach($article->find('DIV[id^="attachment_"]') as $article_element)
		{
			if (FALSE === is_null($photo_element = $article_element->find('IMG[src^="http"]', 0)))
			{
				if (FALSE === is_null($caption_element = $article_element->find('P.wp-caption-text', 0)))
				{
					$caption_text = trim($caption_element->plaintext);
				}
				$img_src = "";
				$img_src = $photo_element->getAttribute('src');

				$img_alt = "";
				if($photo_element->hasAttribute('alt'))
					$img_alt = trim($photo_element->getAttribute('alt'));

				if (0 === strlen($img_alt) && 0 === strlen($caption_text))
					$new_outertext = '<figure class="photoWrapper photo"><img src="'.$img_src.'"></figure>';
				else if (0 === strlen($img_alt) && 0 !== strlen($caption_text))
					$new_outertext = '<figure class="photoWrapper photo"><img src="'.$img_src.'"><figcaption>'.$caption_text.'</figcaption></figure>';
				else if (0 !== strlen($img_alt) && 0 === strlen($caption_text))
					$new_outertext = '<figure class="photoWrapper photo"><img src="'.$img_src.'" alt="'.$img_alt.'"></figure>';
				else if (0 !== strlen($img_alt) && 0 !== strlen($caption_text))
					$new_outertext = '<figure class="photoWrapper photo"><img src="'.$img_src.'" alt="'.$img_alt.'"><figcaption>'.$caption_text.'</figcaption></figure>';
				$article_element->outertext = $new_outertext;
			}
			if (FALSE === is_null($href_element = $article_element->find('A[href]', 0)))
			{
				$href = $href_element->getAttribute('href');
				if (0 === strlen($img_alt) && 0 === strlen($caption_text))
					$new_outertext = '<figure class="photoWrapper photo"><a href="'.$href.'"><img src="'.$img_src.'"></a></figure>';
				else if (0 === strlen($img_alt) && 0 !== strlen($caption_text))
					$new_outertext = '<figure class="photoWrapper photo"><a href="'.$href.'"><img src="'.$img_src.'"></a><figcaption>'.$caption_text.'</figcaption></figure>';
				else if (0 !== strlen($img_alt) && 0 === strlen($caption_text))
					$new_outertext = '<figure class="photoWrapper photo"><a href="'.$href.'"><img src="'.$img_src.'" alt="'.$img_alt.'"></a></figure>';
				else if (0 !== strlen($img_alt) && 0 !== strlen($caption_text))
					$new_outertext = '<figure class="photoWrapper photo"><a href="'.$href.'"><img src="'.$img_src.'" alt="'.$img_alt.'"></a><figcaption>'.$caption_text.'</figcaption></figure>';
				$article_element->outertext = $new_outertext;
			}
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
