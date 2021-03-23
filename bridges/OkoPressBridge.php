<?php
class OkoPressBridge extends FeedExpander {

	const MAINTAINER = 'No maintainer';
	const NAME = 'OKO.press';
	const URI = 'https://oko.press/';
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
		$GLOBALS['my_debug'] = FALSE;
//		$GLOBALS['my_debug'] = TRUE;
		if (TRUE === $GLOBALS['my_debug'])
		{
			$GLOBALS['all_articles_time'] = 0;
			$GLOBALS['all_articles_counter'] = 0;
		}
		$this->setGlobalArticlesParams();
        $this->collectExpandableDatas('https://oko.press/feed/');
		if (TRUE === $GLOBALS['my_debug'])
			echo "<br>Wszystkie {$GLOBALS['all_articles_counter']} artykulow zajelo {$GLOBALS['all_articles_time']}, <br>średnio ".$GLOBALS['all_articles_time']/$GLOBALS['all_articles_counter'] ."<br>";

    }

	private function setGlobalArticlesParams()
	{
		$GLOBALS['limit'] = intval($this->getInput('limit'));
		if (TRUE === $this->getInput('include_not_downloaded'))
			$GLOBALS['include_not_downloaded'] = TRUE;
		else
			$GLOBALS['include_not_downloaded'] = FALSE;
	}

	protected function parseItem($newsItem)
	{
		$item = parent::parseItem($newsItem);
//		print_var_dump($item, 'item');
		if (count($this->items) >= $GLOBALS['limit'])
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
		if (200 === $returned_array['code'])
		{
			$article_html = $returned_array['html'];
		}
		else
		{
			return $item;
		}
		$author = return_authors_as_string($article_html, 'SPAN.meta-section__autor A[href*="/autor/"]');
		$tags = array();
		if (FALSE === is_null($script_element = $article_html->find('HEAD SCRIPT[type="application/ld+json"]', 1)))
		{
			$script_text = $script_element->innertext;
			$article_data_parsed = parse_article_data(json_decode($script_text));
			foreach($article_data_parsed["itemListElement"] as $element)
			{
				$item_element = $element["item"];
				if (FALSE !== strpos($item_element["@id"], 'oko.press/kategoria/'))
				{
					$tags[] = $item_element["name"];
				}
			}
		}
		$tags[] = get_json_value($article_html, 'SCRIPT', 'pageCategory');
		$tags_links = return_tags_array($article_html, 'DIV.entry-content DIV.tags A[rel="tag"]');
		$tags = array_unique(array_merge($tags, $tags_links));
		set_biggest_photo_size_from_attribute($article_html, 'IMG.lazy[data-srcset]', 'data-srcset');
		$article_html = str_get_html($article_html->save());
		set_biggest_photo_size_from_attribute($article_html, 'IMG.lazy[data-src]', 'data-src');
		$article_html = str_get_html($article_html->save());
		$header_photo = $article_html->find('DIV#main-image IMG.lazy[src]', 0);
		if (FALSE === is_null($header_photo))
		{
			$src_header_image = $header_photo->getAttribute('src');
		}
		$article = $article_html->find('ARTICLE[id^="post-"] DIV.large-9', 0);
		foreach_delete_element($article, 'DIV.cr-paragraph-additions[data-cookie="HasLogged"]');
		foreach_delete_element($article, 'DIV.cr-login-block.oko-widget-frame');
		foreach_delete_element($article, 'comment');
		foreach_delete_element($article, 'SCRIPT');
		foreach_replace_outertext_with_innertext($article, 'DIV.socialwall');
		$article = str_get_html($article->save());
		foreach_delete_element($article, 'DIV.related_image_open');
		foreach_delete_element($article, 'DIV.row.js-display-random-element');
		foreach_delete_element($article, 'DIV.tags');
		foreach_delete_element($article, 'DIV.row.large-collapse');
		foreach_delete_element($article, 'DIV#banner-after-excerpt');
		foreach_delete_element($article, 'DIV#intertext-banners');
		foreach_delete_element($article, 'DIV.powiazany-artykul-shortcode');
		$article->find('hr',-1)->outertext = '';
		if (isset($src_header_image))
		{
			$article = str_get_html('<h1 class="title">'.$item['title'].'</h1>'.'<figure class="photoWrapper mainPhoto"><img src="'.$src_header_image.'"></figure>'.$article->save());
		}
		else
		{
			$article = str_get_html('<h1 class="title">'.$item['title'].'</h1>'.$article->save());
		}
		$article = str_get_html($article->save());
		
		//https://oko.press/astra-zeneca-ema/
		convert_iframes_to_links($article);
		$article = str_get_html($article->save());
		//https://oko.press/stalo-sie-przemyslaw-radzik-symbol-dobrej-zmiany-w-sadach-dostal-awans-od-prezydenta/
//		$this->format_article_photos_sources($article);
		$article = str_get_html($article->save());
		add_style($article, 'DIV.excerpt', array('font-weight: bold;'));
		$article = str_get_html($article->save());
		fix_all_photos_attributes($article);
		$article = str_get_html($article->save());
		foreach($article->find('P') as $paragraph)
		{
			$paragraph_photo = $paragraph->find('IMG[src]', 0);
			if (FALSE === is_null($paragraph_photo))
			{
				$next_sibling = $paragraph->next_sibling();
				if ('p' == strtolower($next_sibling->tag))
				{
					$paragraph_description = $next_sibling->find('EM', 0);
					$paragraph->outertext = '<div class="photo">'.$paragraph->innertext.$next_sibling->innertext.'</div>';
					$next_sibling->outertext='';
				}
			}
		}
		$article = str_get_html($article->save());
		format_article_photos($article, 'FIGURE.photoWrapper.mainPhoto', TRUE, 'src');
		$article = str_get_html($article->save());
		format_article_photos($article, 'DIV.photo', FALSE, 'src', 'EM');
		$article = str_get_html($article->save());
		format_article_photos($article, 'FIGURE[id^="attachment_"]', FALSE, 'src', 'FIGCAPTION');
		$article = str_get_html($article->save());
		//https://opinie.wp.pl/kataryna-zyjemy-w-okrutnym-swiecie-ale-aborcja-embriopatologiczna-musi-pozostac-opinia-6567085945505921a?amp=1&_js_v=0.1
		add_style($article, 'FIGURE.photoWrapper', getStylePhotoParent());
		add_style($article, 'FIGURE.photoWrapper IMG', getStylePhotoImg());
		add_style($article, 'FIGCAPTION', getStylePhotoCaption());
		add_style($article, 'BLOCKQUOTE', getStyleQuote());
		$article = str_get_html($article->save());
		
		
//		$item['title'] = $title;
		$item['content'] = $article;
		$item['author'] = $author;
		$item['categories'] = $tags;

		return $item;
	}
	

	private function format_article_photos_sources($article)
	{
/*		foreach($article->find('IMG.lazy[data-srcset]') as $photo_element)
		{
			$img_src = $photo_element->getAttribute('src');
			if($photo_element->hasAttribute('data-srcset'))
			{
				$img_srcset = $photo_element->getAttribute('data-srcset');
				$srcset_array  = explode(',', $img_srcset);
				$last = count($srcset_array) - 1;
				$last_url_string = trim($srcset_array[$last]);
				$last_url_array  = explode(' ', $last_url_string);
				$img_src = $last_url_array[0];
			}
			$photo_element->setAttribute('src', $img_src);
		}*/
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