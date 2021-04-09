<?php
class FacebookMobileBridge extends BridgeAbstract {
	const NAME = 'Facebook Mobile';
	const URI = 'https://m.facebook.com/';
	const CACHE_TIMEOUT = 3600;

	const PARAMETERS = array
	(
		'Parametry' => array
		(
			'url' => array
			(
				'name' => 'URL',
				'type' => 'text',
				'required' => true,
			),
		)
	);

	public function getName()
	{
		if (!isset($GLOBALS['author']))
		{
			return self::NAME;
		}
		else
		{
			$author_name = "Facebook - ". $GLOBALS['author'];
			return $author_name;
		}
	}
	
	public function getURI()
	{
		if (!isset($GLOBALS['url']))
		{
			return self::URI;
		}
		else
		{
			return $GLOBALS['url'];
		}
	}


	public function getIcon() {
//		return 'https://www.economist.com/sites/default/files/econfinal_favicon.ico';
	}

	public function collectData()
	{
		include 'myFunctions.php';
//		ini_set('user_agent', 'User-Agent:Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)\r\n');
		preg_match('/facebook\.com\/([^\/]*)/', $this->getInput('url'), $output_array);
//		print_var_dump($output_array, 'output_array');
//		$GLOBALS['url'] = 'https://m.facebook.com/'.$output_array[1].'/posts/';
		$GLOBALS['url'] = 'https://www.facebook.com/'.$output_array[1].'/posts?_fb_noscript=1';
//		$GLOBALS['url'] = 'https://m.facebook.com/'.$output_array[1].'/posts?_fb_noscript=1';
//		https://www.facebook.com/TygodnikNIE/posts?_fb_noscript=1
		$GLOBALS['my_debug'] = FALSE;
//		$GLOBALS['my_debug'] = TRUE;
		
		if (TRUE === $GLOBALS['my_debug'])
		{
			$GLOBALS['all_articles_time'] = 0;
			$GLOBALS['all_articles_counter'] = 0;
		}
//		$returned_array = $this->my_get_html($GLOBALS['url'], true);
		$returned_array = $this->my_get_html($GLOBALS['url']);
		if (200 !== $returned_array['code'])
		{
			return;
		}

		$article_html = $returned_array['html'];
//		$article_html = str_get_html(prepare_article($article_html, 'https://m.facebook.com'));
		$article_html = str_get_html(prepare_article($article_html, 'https://www.facebook.com'));
//		$article_html = str_get_html(prepare_article($article_html, $GLOBALS['url']));


		$selectors_array[] = 'STYLE';
		$selectors_array[] = 'SCRIPT';
		$selectors_array[] = 'NOSCRIPT';
		$selectors_array[] = 'DIV[style="height:40px"]';
		$selectors_array[] = 'DIV[id^="feed_subtitle_"] DIV[data-hover="tooltip"][data-tooltip-content]';
		$selectors_array[] = 'SPAN[role="presentation"]';
		$selectors_array[] = 'NOSCRIPT';
		$selectors_array[] = 'NOSCRIPT';
		
		//Wideo
		$selectors_array[] = 'DIV[style="height:282px;width:500px;"]';
		$selectors_array[] = 'FORM.commentable_item';
		foreach_delete_element_array($article_html, $selectors_array);
		foreach_replace_outertext_with_subelement_outertext($article_html, 'A[rel="theater"]', 'IMG.scaledImageFitWidth.img, IMG.scaledImageFitHeight.img');
		$article_html = str_get_html($this->remove_useless_classes($article_html->save()));
		$article_html = str_get_html($this->remove_useless_classes($article_html->save()));
		$article_html = str_get_html($this->remove_empty_elements($article_html->save(), "DIV"));
		$article_html = str_get_html($this->remove_empty_elements($article_html->save(), "SPAN"));

		$tags_array[] = "DIV=>DIV";
		$tags_array[] = "SPAN=>SPAN";
		$tags_array[] = "SPAN=>A";
		$tags_array[] = "DIV=>IMG";
		$tags_array[] = "DIV=>P";

		$excluded_classes[] = "userContentWrapper";
		$excluded_ids[] = "pagelet_timeline_main_column";

		$article_html_str = replace_single_children($article_html, $tags_array, $excluded_classes, $excluded_ids);
		$article_html = str_get_html($article_html_str);

		replace_attribute($article_html, '[data-ft]', 'data-ft', NULL);
		$article_html = str_get_html($article_html->save());
		replace_attribute($article_html, '[style=""]', 'style', NULL);
		$article_html = str_get_html($article_html->save());
		replace_attribute($article_html, '[data-shorten]', 'data-shorten', NULL);
		$article_html = str_get_html($article_html->save());
		replace_attribute($article_html, '[ajaxify]', 'ajaxify', NULL);
		$article_html = str_get_html($article_html->save());
		replace_attribute($article_html, '[aria-hidden]', 'aria-hidden', NULL);
		$article_html = str_get_html($article_html->save());
		replace_attribute($article_html, '[tabindex]', 'tabindex', NULL);
		$article_html = str_get_html($article_html->save());
		replace_attribute($article_html, '[target]', 'target', NULL);

		$article_html = str_get_html($article_html->save());

		$author = get_text_plaintext($article_html, 'TITLE#pageTitle', $GLOBALS['url']);
		if ($GLOBALS['url'] !== $author)
		{
			$author_array = explode(' - ', $author);
			unset($author_array[count($author_array) - 1]);
			$author = implode(' - ', $author_array);
		}
		$GLOBALS['author'] = $author;

		foreach($article_html->find('DIV#pagelet_timeline_main_column DIV.userContentWrapper') as $fb_post)
		{
			$this->addArticle($fb_post);
		}
	}
	
	private function addArticle($fb_post)
	{
//		print_element($fb_post, 'fb_post przed');
		//Fix zdjęć
		foreach($fb_post->find('A[href]') as $href_element)
		{
			$href_url = $href_element->href;
			$parsed_url = parse_url($href_url);
			if (isset($parsed_url["query"]))
			{
				$href_url = str_replace("?".$parsed_url["query"], "", $href_url);
				$href_element->href = $href_url;
			}
//			print_var_dump(parse_url($href_url), "parse_url($href_url)");
		}
		foreach($fb_post->find('IMG[src]') as $image)
		{
			$image_src = $image->getAttribute('src');
//			print_var_dump($image_src, "image_src przed");
			$image_src = htmlspecialchars_decode($image_src);
//			print_var_dump($image_src, "image_src po");
			$image_downloaded = file_get_contents($image_src);
			if ($image_downloaded !== false)
			{
	    		$image_src = 'data:image/jpg;base64,'.base64_encode($image_downloaded);
			}
			$image->setAttribute('src', NULL);
			$image->setAttribute('src', $image_src);
		}
//		print_element($fb_post, 'fb_post');
		//post url
		if (!is_null($href_element = $fb_post->find('DIV[id^="feed_subtitle_"] A[href]', 0)))
		{
			$post_url = $href_element->href;
		}
		$title = $GLOBALS['author'];
		//post date
		if (!is_null($date_element = $fb_post->find('ABBR[data-utime]', 0)))
		{
			$unix_timestamp = $date_element->getAttribute('data-utime');
			$post_date = date("Y-m-d\TH:i:s\Z", $unix_timestamp);
			$readable_date = date("Y-m-d H:i:s", $unix_timestamp);
			$title = $title.' - '.$readable_date;
		}
		
	
//		print_element($fb_post, 'fb_post po');
		$this->items[] = array(
			'uri' => $post_url,
			'title' => $title,
			'timestamp' => $post_date,
			'author' => $GLOBALS['author'],
//			'content' => $article,
			'content' => $fb_post,
		);
	}

	private function remove_useless_classes($main_element_str)
	{
		$main_element = str_get_html($main_element_str);
		foreach($main_element->find('[class*="_"]') as $element_to_clear)
		{
			$old_outertext = $element_to_clear->outertext;
			$class_string = $element_to_clear->getAttribute('class');
			$class_string_array = explode(" ", $class_string);
			foreach ($class_string_array as $key => $value)
			{
				if (FALSE !== strpos($value, "_") && 1 === preg_match('/[_0-9a-zA-Z]+/', $value, $output_array))
				{
					unset($class_string_array[$key]);
				}
			}
			$class_string = implode(" ", $class_string_array);
			if (0 === strlen($class_string))
			{
				$class_string = NULL;
			}
			$element_to_clear->setAttribute('class', $class_string);
			$new_outertext = $element_to_clear->outertext;
			$main_element_str = str_replace($old_outertext, $new_outertext, $main_element_str);
		}
		return $main_element_str;
	}
	private function remove_empty_elements($main_element_str, $tag)
	{
		$main_element = str_get_html($main_element_str);
		foreach($main_element->find($tag) as $empty_element)
		{
			if (0 === strlen($empty_element->innertext))
			{
				$main_element_str = str_replace($empty_element->outertext, "", $main_element_str);
			}
		}
		return $main_element_str;
	}
	
	private function my_get_html($url, $get_premium = FALSE)
	{
		if (TRUE === $get_premium)
		{
			$context = stream_context_create(
				array(
					'http' => array(
						'ignore_errors' => true,
					    'header'=>"User-Agent:Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)\r\n"
					)
				)
			);
		}
		else
		{
			$context = stream_context_create(
				array(
					'http' => array(
						'ignore_errors' => true
					)
				)
			);
		}
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
