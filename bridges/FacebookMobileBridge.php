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
		$returned_array = $this->my_get_html($GLOBALS['url'], TRUE);
//		$returned_array = $this->my_get_html($GLOBALS['url'], FALSE);
		if (200 !== $returned_array['code'])
		{
			return;
		}
		$article_html = str_get_html($returned_array['page_content']);

		$author = get_text_plaintext($article_html, 'TITLE#pageTitle', $GLOBALS['url']);
		if ($GLOBALS['url'] !== $author)
		{
			$author_array = explode(' - ', $author);
			unset($author_array[count($author_array) - 1]);
			$author = implode(' - ', $author_array);
		}
		$GLOBALS['author'] = $author;
		///////////////////////////////PRZED
		$article_html = $this->format_facebook_page($article_html);
		////////////////////////////PO
		$photoID = get_json_value($article_html, 'SCRIPT', 'photoID');
		$old_profil_page_photo_url = get_text_from_attribute($article_html, 'META[property="og:image"][content]', 'content', '');
		$new_profil_page_photo_url = "https://lookaside.fbsbx.com/lookaside/crawler/media/?media_id=".$photoID;
		$article_html_str = $article_html->save();

		if (strlen($photoID) > 1 && strlen($old_profil_page_photo_url) > 1 && !is_null($profile_image = $article_html->find('IMG[src*="'.$old_profil_page_photo_url.'"]', 0)))
		{
			$returned_pic_array = $this->my_get_html($new_profil_page_photo_url, TRUE);
			if (200 === $returned_pic_array['code'])
			{
				$image_downloaded = $returned_pic_array['page_content'];
				if ($image_downloaded !== false)
				{
					if (is_int(strpos($new_profil_page_photo_url, ".png")))
			    		$image_src_base64 = 'data:image/png;base64,'.base64_encode($image_downloaded);
					else
			    		$image_src_base64 = 'data:image/jpg;base64,'.base64_encode($image_downloaded);
					
/*					$im = imagecreatefromstring($image_downloaded);
					$width = imagesx($im);
					$height = imagesy($im);
					$newwidth = 100;
					$newheight = 100;
					$thumb = imagecreatetruecolor($newwidth, $newheight);
					// Resize
					imagecopyresized($thumb, $im, 0, 0, 0, 0, $newwidth, $newheight, $width, $height);
					ob_start();
					imagejpeg($thumb);
					$output = base64_encode(ob_get_contents());
					ob_end_clean();
					$image_src_base64 = "data:image/jpg;base64,".$output;*/
//					$article_html_str = str_replace($old_profil_page_photo_url, $image_src_base64, $article_html_str);
					foreach($article_html->find('IMG[src*="'.$old_profil_page_photo_url.'"]') as $profile_image_2)
					{
						//jeżeli już jest zmienione, to jest to zdjęcie profilowe
						$profile_image_2->setAttribute('style', "height: 100px;width: 100px;");
						$profile_image_2->setAttribute('src', $image_src_base64);
					}
				}
			}
		}
		$article_html = str_get_html($article_html->save());
//		$article_html = str_get_html($article_html_str);
		

/*
		foreach($article_html->find('DIV#pagelet_timeline_main_column DIV.userContent') as $fb_post)
		{
			if (!is_null($href_link = $fb_post->find("SPAN.text_exposed_link A[href][!class]", 0)))
			{
				$full_post_link = 'https://www.facebook.com'.$href_link->href;
//				$full_post_link = 'https://m.facebook.com'.$href_link->href;
				$output_array = explode("?", $full_post_link);
				$full_post_link = $output_array[0];
				$post_returned_array = $this->my_get_html($full_post_link, TRUE);
				if (200 !== $post_returned_array['code'])
				{
					return;
				}
				//DIV.msg > DIV[class]
				$post_article_html = $post_returned_array['html'];
//				print_html($post_article_html, "post_article_html: $full_post_link");
//				print_element($post_article_html, "post_article_html: $full_post_link");
//				if (!is_null($full_post = $post_article_html->find('DIV.msg DIV[class=""]', 0)))

//do strony mobilnej	if (!is_null($full_post = $post_article_html->find('DIV.story_body_container', 0)))
				if (!is_null($full_post = $post_article_html->find('DIV.userContent', 0)))
				{
					$article_html_str = str_replace($fb_post->outertext, '<div class="userContent">'.$full_post->outertext.'</div>', $article_html_str);
//					print_html($full_post, "full_post: $full_post_link");
//					$fb_post->outertext = $full_post->outertext;
				}
//				break;
			}
		}
*/

		$article_html = str_get_html($article_html->save());
		foreach_delete_element_array($article_html, array('SCRIPT'));
		$article_html = str_get_html($article_html->save());

		foreach($article_html->find('DIV#pagelet_timeline_main_column DIV.userContentWrapper, DIV#pages_msite_body_contents ARTICLE[id]') as $fb_post)
		{
			$this->addArticle($fb_post);
		}
	}
	
	private function addArticle($fb_post)
	{
		//post url
		if (!is_null($href_element = $fb_post->find('DIV[id^="feed_subtitle_"] A[href]', 0)))
		{
			$post_url = $href_element->href;
		}
		foreach($fb_post->find('A[href]') as $href_element)
		{
			$href_url = $href_element->href;
			$parsed_url = parse_url($href_url);
			if (isset($parsed_url["query"]))
			{
				$href_url = str_replace("?".$parsed_url["query"], "", $href_url);
				$href_element->href = $href_url;
			}
		}
		//Fix zdjęć
		foreach($fb_post->find('IMG[src]') as $image)
		{
			$image_src = $image->getAttribute('src');
			$image_src = htmlspecialchars_decode($image_src);
			if (is_int(strpos($image_src, "https")))
			{
				$returned_pic_array = $this->my_get_html($image_src, TRUE);
				if (200 === $returned_pic_array['code'])
				{
					$image_downloaded = $returned_pic_array['page_content'];
					if ($image_downloaded !== false)
					{
						if (is_int(strpos($image_src, ".png")))
				    		$image_src = 'data:image/png;base64,'.base64_encode($image_downloaded);
						else
				    		$image_src = 'data:image/jpg;base64,'.base64_encode($image_downloaded);
					}
					$image->setAttribute('src', NULL);
					$image->setAttribute('src', $image_src);
				}
			}
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
		$this->items[] = array(
			'uri' => $post_url,
			'title' => $title,
			'timestamp' => $post_date,
			'author' => $GLOBALS['author'],
			'content' => $fb_post,
		);
	}
	
	private function format_facebook_page($article_html)
	{
//		print_var_dump(count($article_html->find('SPAN.text_exposed_link A[href]')), "SPAN.text_exposed_link A[href]");
//		print_var_dump(count($article_html->find('SPAN.text_exposed_link A[href][!class]')), "SPAN.text_exposed_link A[href][!class]");
//		$article_html = str_get_html(prepare_article($article_html, 'https://m.facebook.com'));
//		$article_html = str_get_html(prepare_article($article_html, $GLOBALS['url']));
		$article_html = str_get_html(prepare_article($article_html, 'https://www.facebook.com'));
		$selectors_array[] = 'STYLE';
		
//		$selectors_array[] = 'SCRIPT';
		$selectors_array[] = 'HEADER';
		$selectors_array[] = 'NOSCRIPT';
		$selectors_array[] = 'IMG[src*="https\\3a //"]';
		
		
		$selectors_array[] = 'DIV[style="height:40px"]';
		$selectors_array[] = 'DIV[id^="feed_subtitle_"] DIV[data-hover="tooltip"][data-tooltip-content]';
		$selectors_array[] = 'SPAN[role="presentation"]';
		$selectors_array[] = 'A[onclick][href*="facebook.com"]';
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
		foreach($article_html->find('A[href^="https://l.facebook.com/l.php?u="]') as $href_element)
		{
			$href_url = $href_element->href;
			$href_url = str_replace("https://l.facebook.com/l.php?u=", "", $href_url);
			$new_href_url_array = explode('&amp;h=', $href_url);
			$href_url = urldecode($new_href_url_array[0]);
			$href_element->href = $href_url;
		}
		$article_html = str_get_html($article_html->save());
		$attributes_array[] = "data-ft";
		$attributes_array[] = "data-shorten";
		$attributes_array[] = "ajaxify";
		$attributes_array[] = "aria-hidden";
		$attributes_array[] = "tabindex";
		$attributes_array[] = "target";
		$attributes_array[] = "style";
		$article_html_str = remove_multiple_attributes($article_html, $attributes_array);
		$article_html = str_get_html($article_html_str);
		//DIV[style="width:500px; height:500px;"]
		//jeżeli jest kilka zdjęć w poście jak tu: https://www.facebook.com/wojownicyklawiatury/posts/236861054861137

		foreach($article_html->find('P') as $paragraph)
		{
			foreach($paragraph->find('SPAN') as $span)
			{
				if ("..." === $span->plaintext)
				{
					$next_element = $span->next_sibling();
					$span->outertext = "";
					$next_element->outertext = $next_element->innertext;
				}
			}
		}
//		$article_html = str_get_html($article_html->save());
//		$selectors_array[] = 'DIV[data-gt]';
//		foreach_delete_element_array($article_html, $selectors_array);
		$article_html = str_get_html($article_html->save());
		return $article_html;
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
/*
		$context = stream_context_create(
				array(
					'http' => array(
						'ignore_errors' => true,
					    'header'=>"User-Agent:Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)\r\n"
					)
				)
			);
*/				
			$context = stream_context_create(
				array(
					'http'=>array(
						'ignore_errors' => true,
			    		'method'=>"GET",
			    		'header'=>"Accept-language: en\r\n" .
			            	"Cookie: foo=bar\r\n" .  // check function.stream-context-create on php.net
							"User-Agent: Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)\r\n"
//						    'header'=>"User-Agent:Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)\r\n"
//			            	"User-Agent: Mozilla/5.0 (iPad; U; CPU OS 3_2 like Mac OS X; en-us) AppleWebKit/531.21.10 (KHTML, like Gecko) Version/4.0.4 Mobile/7B334b Safari/531.21.102011-10-16 20:23:10\r\n" // i.e. An iPad 
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

		$return_array = array(
			'code' => $code,
			'page_content' => $page_content,
		);
		return $return_array;
	}
}
