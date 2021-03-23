<?php
class OutridersBridge extends FeedExpander {

	const MAINTAINER = 'No maintainer';
	const NAME = 'Outriders Brief';
	const URI = 'https://outride.rs/';
	const DESCRIPTION = 'No description provided';
	const CACHE_TIMEOUT = 86400;

	const PARAMETERS = array
	(
		'Brief' => array
		(
			'limit' => array
			(
				'name' => 'Liczba briefów',
				'type' => 'number',
				'required' => true,
				'title' => 'Liczba briefów',
				'defaultValue' => 3,
			)
		),
		'Rss' => array
		(
			'limit' => array
			(
				'name' => 'Liczba artykułów',
				'type' => 'number',
				'required' => true,
				'title' => 'Liczba artykułów',
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

	public function getName()
	{
		switch($this->queriedContext)
		{
			case 'Brief':
				return "Outriders - Briefy";
				break;
			case 'Rss':
				return "Outriders - Artykuły";
				break;
			default: return parent::getName();
		}
	}

	public function collectData()
	{
		$GLOBALS['name'] = "test1";
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
			case 'Brief':
				$this->getArticlesBriefs();
				break;
			case 'Rss':
				$this->getArticlesRss();
				break;
		}		
		if (TRUE === $GLOBALS['my_debug'])
			echo "<br>Wszystkie {$GLOBALS['all_articles_counter']} artykulow zajelo {$GLOBALS['all_articles_time']}, <br>średnio ".$GLOBALS['all_articles_time']/$GLOBALS['all_articles_counter'] ."<br>";

	}

	private function getArticlesBriefs()
	{
		$GLOBALS['limit'] = $this->getInput('limit');
		$articles_urls = array();
		$returned_array = $this->my_get_html('https://brief.outride.rs/pl');
		$html_issues_list = $returned_array['html'];
		if (200 !== $returned_array['code'] || 0 === count($found_issues_hrefs = $html_issues_list->find('DIV.issues A.issue-box[href]')))
		{
			return $articles_urls;
		}
		else
		{
			$issues_urls = array();
			foreach($found_issues_hrefs as $href_element)
			{
				$issues_urls[] = 'https://brief.outride.rs'.$href_element->href;
			}
		}
		$issues_urls = array_slice($issues_urls, 0, $GLOBALS['limit']);
		foreach($issues_urls as $issue_url)
		{
			$returned_array = $this->my_get_html($issue_url);
			$html_articles_list = $returned_array['html'];
			if (200 === $returned_array['code'] && 0 !== count($found_articles_hrefs = $html_articles_list->find('UL.issue-nav LI.issue-nav__item A.issue-nav__link[href]')))
			{
				$date = '';
				if (FALSE === is_null($data_script = $html_articles_list->find('DIV[id$="nuxt"] + SCRIPT', 0)))
				{
					preg_match('/[0-9]{4}-[0-9]{2}-[0-9]{2}T[0-9]{2}:[0-9]{2}:[0-9]{2}\+[0-9]{2}:[0-9]{2}/', $data_script->outertext, $output_array);
					$date = $output_array[0];
				}
				foreach($found_articles_hrefs as $href_element)
				{
					if(isset($href_element->href))
					{
						$articles_urls[] = array
						(
							'url' => 'https://brief.outride.rs'.$href_element->href,
							'date' => $date
						);
					}
				}
			}
		}
		foreach($articles_urls as $url_array)
		{
			$this->addArticleBrief($url_array['url'], $url_array['date']);
		}
	}

	private function addArticleBrief($url, $date)
	{
		$returned_array = $this->my_get_html($url);
		if (200 !== $returned_array['code'])
		{
			return;
		}
		$article_html = $returned_array['html'];
		$article = $article_html->find('ARTICLE[id].post', 0);
		//tytuł
		$title = $url;
		if (FALSE === is_null($title_element = $article->find('H1.post__title', 0)))
		{
			$title = trim($title_element->plaintext);
		}
		
		preg_match('/brief-[0-9]*/', $url, $output_array);
		$brief_number = ucwords($output_array[0]);
		//tagi
		$tags = return_tags_array($article, 'DIV.categories A[href]');
		$tags = array_merge(array($brief_number), $tags);
		foreach_delete_element($article, 'DIV.categories');
		$this->items[] = array(
			'uri' => $url,
			'title' => $brief_number.': '.$title,
			'timestamp' => $date,
			'author' => 'Outriders',
			'categories' => $tags,
			'content' => $article
		);
	}

	private function getArticlesRss()
	{
		if (TRUE === $this->getInput('include_not_downloaded'))
			$GLOBALS['include_not_downloaded'] = TRUE;
		else
			$GLOBALS['include_not_downloaded'] = FALSE;
        $this->collectExpandableDatas('https://outride.rs/feed');
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
		$returned_array = $this->my_get_html($item['uri']);
		if (200 !== $returned_array['code'])
		{
			return;
		}
		$article_html = $returned_array['html'];
		//outride.rs##BODY.post-template > ARTICLE.gallery
		//https://outride.rs/pl/minsk-decentralizacja-protestu/
		//outride.rs##BODY > DIV#\5f _nuxt
		//https://outride.rs/pl/wspolnota-odpowiedz-na-wylesianie/
		//outride.rs##BODY.post-template > ARTICLE.article
		//https://outride.rs/pl/rezim-nie-lubi-tego-co-robimy/
		$js_element = $article_html->find('DIV[id$="_nuxt"][data-server-rendered="true"]', 0);
		if (FALSE === is_null($js_element))
		{
//			echo "Artykul z js: ".$item['uri']."<br>";
			return $item;
		}
		$article = $article_html->find('BODY.single-post ARTICLE', 0);
		$this->format_article_photos_sources($article);
//		echo "numer: ".count($this->items).", url: ".$item['uri']."<br><br>";
//		print_html($article);
		foreach_delete_element($article, 'SCRIPT');
		foreach_delete_element($article, 'NOSCRIPT');
		foreach_delete_element($article, 'LINK');
		foreach_delete_element($article, 'DIV.context-modal');
		foreach_delete_element($article, 'AUDIO.or-player');
		//FIGCAPTION
		format_article_photos($article, 'DIV.article__thumbnail', TRUE);
		format_article_photos($article, 'FIGURE.wp-block-image', FALSE, 'src', 'FIGCAPTION');
		//https://outride.rs/pl/konflikt-w-gorskim-karabachu/
		format_article_photos($article, 'DIV.gallery-photo', FALSE, 'src', 'DIV.gallery-photo__text.text-under');
		$article = str_get_html($article->save());
		add_style($article, 'FIGURE.photoWrapper', getStylePhotoParent());
		add_style($article, 'FIGURE.photoWrapper IMG', getStylePhotoImg());
		add_style($article, 'FIGCAPTION', getStylePhotoCaption());
		add_style($article, 'BLOCKQUOTE', getStyleQuote());
		$article = str_get_html($article->save());

		$item['content'] = $article;
		return $item;
	}



	private function format_article_photos_sources($article)
	{
		foreach($article->find('IMG[srcset]') as $photo_element)
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
// Imaginary empty line!