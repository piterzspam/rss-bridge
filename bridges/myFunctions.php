<?php

	function get_photo_attributes_caption()
	{
		return array(
			'data-author', 
			'data-source', 
			'attribution',
		);
	}
	
	function get_photo_attributes_img()
	{
		return array(
			'class',
			'src',
			'alt',
			'title',
		);
	}

	function print_var_dump($variable, $name = "nazwa zmiennej", $prefix = "<br>")
	{
		echo $prefix;
		echo "Var dump zmiennej $name: <br><pre>"; var_dump($variable); echo "</pre>";
	}

	function print_html($variable, $name = "nazwa zmiennej", $prefix = "<br>")
	{
		echo $prefix;
		echo "Kod html zmiennej $name: <br><pre>".htmlspecialchars($variable)."</pre><br><br>";
	}

	function print_element($element, $name = "nazwa zmiennej", $prefix = "<br>")
	{
		echo $prefix;
		echo "Element $name:<br>$element<br><br>";
	}

	function getStylePhotoParent()
	{
		return array(
			'position: relative;',
			'width: -moz-fit-content;',
			'margin-bottom: 10px;'
		);
	}

	function getStylePhotoImg()
	{
		return array(
			'vertical-align: bottom;',
		);
	}

	function getStylePhotoCaption()
	{
		return array(
			'bottom: 0;',
			'left: 0;',
			'right: 0;',
			'text-align: center;',
			'color: #fff;',
			'padding-right: 10px;',
			'padding-left: 10px;',
			'background-color: rgba(0, 0, 0, 0.7);'
		);
	}
	
	function getStyleQuote()
	{
		return array(
			'border-top-width: 0px;',
			'border-right-width: 0px;',
			'border-bottom-width: 0px;',
			'border-left-width: 7px;',
			'margin: 16px 24px;',
			'margin-top: 16px;',
			'margin-right: 24px;',
			'margin-bottom: 16px;',
			'margin-left: 24px;',
			'padding: 10px 12px;',
			'padding-top: 10px;',
			'padding-right: 12px;',
			'padding-bottom: 10px;',
			'padding-left: 12px;',
//			'background-color: rgb(248, 248, 248);',
			'border-style: solid;',
			'border-top-style: solid;',
			'border-right-style: solid;',
			'border-bottom-style: solid;',
			'border-left-style: solid;'
		);
	}

	function convert_amp_photos($article)
	{
		/*
		echo "Przed<br>";
		$article = str_get_html($article->save());
		echo "Po<br>";
		*/
		/*
		print_element($article, 'article przed');
		foreach($article->find('AMP-IMG[src]') as $key=>$amp_photo_element)
		{
			echo "Element $key<br>";
			if (FALSE === is_null($img_photo_element = $amp_photo_element->find('IMG[src]', 0)))
			{
				echo "img_photo_element: ".$img_photo_element->getAttribute('src')."<br>";
				echo "amp_photo_element: ".$amp_photo_element->getAttribute('src')."<br>";
				if($img_photo_element->getAttribute('src') === $amp_photo_element->getAttribute('src'))
				{
					echo "usunieto: ".$img_photo_element->getAttribute('src')."<br>";
					$img_photo_element->outertext = '';
				}
			}
		}
		print_element($article, 'article po');*/
		foreach_delete_element($article, 'amp-analytics');
		foreach_delete_element($article, 'amp-ad');
		foreach_delete_element($article, 'i-amphtml-sizer');
		foreach_delete_element($article, 'amp-image-lightbox');
		foreach($article->find('amp-img, img') as $photo_element)
		{
			if(isset($photo_element->width)) $photo_element->width = NULL;
			if(isset($photo_element->height)) $photo_element->height = NULL;
		}

		
		//https://opinie.wp.pl/wielka-zmiana-mezczyzn-wirus-ja-tylko-przyspieszyl-6604913984391297a?amp=1&_js_v=0.1
		foreach($article->find('amp-img') as $ampimg)
		{
			if ('p' === strtolower($ampimg->parent->tag))
				$ampimg->parent->tag = "DIV";
			if (FALSE === $ampimg->parent->class)
				$ampimg->parent->class = "photo from amp";
			
			$img_new_element = '<img ';
			foreach($ampimg->getAllAttributes() as $key=>$element)
			{
				if (FALSE === is_null($element))
					$img_new_element = $img_new_element.' '.$key.'="'.$element.'"';
			}
			$img_new_element = $img_new_element.'>';
			
			$new_amp_caption = "";
			//https://wiadomosci-onet-pl.cdn.ampproject.org/v/s/wiadomosci.onet.pl/tylko-w-onecie/daniel-obajtek-skonczyl-studia-w-orlenie/8ge0e1e.amp?amp_js_v=0.1

			foreach(get_photo_attributes_caption() as $param_name)
			{
				if($ampimg->hasAttribute($param_name))
				{
					$param = $ampimg->getAttribute($param_name);
					if ("" === $new_amp_caption)
						$new_amp_caption = $param;
					else
						$new_amp_caption = $new_amp_caption.'/'.$param;
				}
			}
			if ("" !== $new_amp_caption)
				$ampimg->outertext = $img_new_element.'<figcaption>'.$new_amp_caption.'</figcaption>';
			else
				$ampimg->outertext = $img_new_element;
		}
	}

	function convert_amp_frames_to_links($article)
	{
		foreach($article->find('amp-iframe') as $amp_iframe)
		{
			if(FALSE === is_null($src = ($amp_iframe->getAttribute('src'))))
			{
				$src = $amp_iframe->src;
				$amp_iframe->outertext = get_frame_outertext($src);
			}
		}
		foreach($article->find('amp-twitter') as $amp_twitter)
		{
			if(FALSE === is_null($data_tweetid = ($amp_twitter->getAttribute('data-tweetid'))))
			{
				$twitter_url = 'https://twitter.com/anyuser/status/'.$data_tweetid;
				$amp_twitter->outertext = get_frame_outertext($twitter_url);
			}
		}
		foreach($article->find('amp-youtube') as $amp_youtube)
		{
			if(FALSE === is_null($data_videoid = ($amp_youtube->getAttribute('data-videoid'))))
			{
				$youtube_url = 'https://www.youtube.com/watch?v='.$data_videoid;
				$amp_youtube->outertext = get_frame_outertext($youtube_url);
			}
		}
	}

	function parse_article_data($article_data)
	{
		if (TRUE === is_object($article_data))
		{
			$article_data = (array)$article_data;
			foreach ($article_data as $key => $value)
				$article_data[$key] = parse_article_data($value);
			return $article_data;
		}
		else if (TRUE === is_array($article_data))
		{
			foreach ($article_data as $key => $value)
				$article_data[$key] = parse_article_data($value);
			return $article_data;
		}
		else
			return $article_data;
	}

	function clear_paragraphs_from_taglinks($article, $paragrapgh_search_string, $regexArray)
	{
		foreach($article->find($paragrapgh_search_string) as $paragraph)
		{
			foreach($paragraph->find('A') as $a_element)
			{
				foreach($regexArray as $regex)
				{
					if(1 === preg_match($regex, $a_element->href))
					{
						$paragraph->outertext = str_replace($a_element->outertext, $a_element->plaintext, $paragraph->outertext);
					}
				}
			}
		}
	}
	
	function single_delete_element_containing_text($element, $subelement_search_textstring)
	{
		if (FALSE === is_null($element))
		{
			if (FALSE !== strpos($element->plaintext, $subelement_search_textstring))
			{
				$element->outertext = '';
			}
		}
	}
	
	function foreach_delete_element_containing_text_from_array($article, $element_search_string, $subelement_search_textstring_array)
	{
		foreach($article->find($element_search_string) as $element)
		{
			foreach($subelement_search_textstring_array as $subelement_search_textstring)
			{
				if (FALSE !== strpos($element->plaintext, $subelement_search_textstring))
				{
					$element->outertext = '';
					break;
				}
			}
		}
	}

	function foreach_delete_element_containing_elements_hierarchy($article, $subelements_hierarchy_array)
	{
		$last = count($subelements_hierarchy_array)-1;
		foreach($article->find($subelements_hierarchy_array[$last]) as $subelement)
		{
			$iterator = $last-1;
			while ($iterator >= 0 && strtolower($subelement->parent->tag) === strtolower($subelements_hierarchy_array[$iterator]))
			{
				$subelement = $subelement->parent;
				$iterator--;
			}
			if ($iterator === -1)
			{
				$subelement->outertext = '';
			}
		}
	}

	function single_delete_element_containing_subelement($element, $subelement_search_string)
	{
		if (FALSE === is_null($subelement = $element->find($subelement_search_string, 0)))
		{
			$element->outertext = '';
		}
	}
	
	function foreach_delete_element($article, $element_search_string)
	{
		foreach($article->find($element_search_string) as $element)
		{
			$element->outertext = '';
		}
	}

	function single_delete_subelement($element, $subelement_search_string)
	{
		if (FALSE === is_null($subelement = $element->find($subelement_search_string, 0)))
		{
			$subelement->outertext = '';
		}
	}

	function foreach_delete_element_containing_subelement($article, $element_search_string, $subelement_search_string)
	{
		foreach ($article->find($element_search_string) as $element)
		{
			if (FALSE === is_null($subelement = $element->find($subelement_search_string, 0)))
			{
				$element->outertext = '';
			}
		}
	}

	function foreach_replace_outertext_with_subelement_innertext($article, $element_search_string, $subelement_search_string)
	{
		foreach($article->find($element_search_string) as $element)
		{
			if (FALSE === is_null($subelement = $element->find($subelement_search_string, 0)))
			{
				$element->outertext = $subelement->innertext;
			}
		}
	}

	function foreach_replace_outertext_with_subelement_outertext($article, $element_search_string, $subelement_search_string)
	{
		foreach($article->find($element_search_string) as $element)
		{
			if (FALSE === is_null($subelement = $element->find($subelement_search_string, 0)))
			{
				$element->outertext = $subelement->outertext;
			}
		}
	}

	function foreach_replace_outertext_with_innertext($article, $element_search_string)
	{
		foreach($article->find($element_search_string) as $element)
		{
			$element->outertext = $element->innertext;
		}
	}

	function get_proxy_url($social_url)
	{
		$twitter_proxy = 'nitter.snopyta.org';
		$instagram_proxy = 'bibliogram.snopyta.org';
		$youtube_proxy = 'invidious.snopyta.org';
		$facebook_proxy = 'mbasic.facebook.com';
		$social_url = preg_replace('/.*[\.\/]twitter\.com(.*)/', 'https://'.$twitter_proxy.'${1}', $social_url);
		$social_url = preg_replace('/.*[\.\/]instagram\.com(.*)/', 'https://'.$instagram_proxy.'${1}', $social_url);
		$social_url = preg_replace('/.*[\.\/]facebook\.com(.*)/', 'https://'.$facebook_proxy.'${1}', $social_url);
		$social_url = preg_replace('/.*[\.\/]youtube\.com(.*)/', 'https://'.$youtube_proxy.'${1}', $social_url);
		return $social_url;
	}

	function return_tags_array($article, $tag_selector)
	{
		$tags = array();
		foreach($article->find($tag_selector) as $tags_item)
		{
			$tag_text = $tags_item->plaintext;
			$tag_text = str_replace("&nbsp;", '', $tag_text);
			$tag_text = trim($tag_text);
			$tag_text = trim($tag_text, ',;');
			$tags[] = $tag_text;
		}
		return array_unique($tags);
	}

	function return_authors_as_string($article, $author_selector)
	{
		$authors = '';
		foreach($article->find($author_selector) as $author_item)
		{
			$authors = $authors.', '.trim($author_item->plaintext);
		}
		$authors = substr_replace($authors, '', 0, strlen(', '));
		return $authors;
	}

	function add_style($article_element, $search_string, $stylesArray)
	{
		$styleString = "";
		foreach ($stylesArray as $style)
		{
			$styleString = $styleString.$style;
		}
		foreach ($article_element->find($search_string) as $element)
		{
			if(FALSE === $element->hasAttribute('style'))
				$element->setAttribute('style', $styleString);
			else
				$element->style = $element->style.$styleString;
		}
	}
	
	function hex_dump($data, $newline='<br>')
	{
		static $from = '';
		static $to = '';
	
		static $width = 16; # number of bytes per line

		static $pad = '.'; # padding for non-visible characters

		if ($from==='')
		{
			for ($i=0; $i<=0xFF; $i++)
			{
				$from .= chr($i);
				$to .= ($i >= 0x20 && $i <= 0x7E) ? chr($i) : $pad;
			}
		}

		$hex = str_split(bin2hex($data), $width*2);
		$chars = str_split(strtr($data, $from, $to), $width);

		$offset = 0;
		foreach ($hex as $i => $line)
		{
			echo sprintf('%6X',$offset).' : '.implode(' ', str_split($line,2)) . ' [' . $chars[$i] . ']' . $newline;
			$offset += $width;
		}
	}

	function getHttpCode($http_response_header)
	{
		if (is_array($http_response_header))
		{
			$parts = explode(' ', $http_response_header[0]);
			if (count($parts) > 1) //HTTP/1.0 <code> <text>
				return intval($parts[1]); //Get code
		}
		return 0;
	}

	function createErrorContent($http_response_header)
	{
		$content = '<div>';
		foreach($http_response_header as $response_header)
		{
			$content = $content.$response_header.'<br>';
		}
		$content = $content.'</div>';
		return $content;
	}

	function remove_substring_if_exists_first($string, $substring)
	{
		$length_of_substring_to_remove = strlen($substring);
		$offset = 0;
		$length = $length_of_substring_to_remove;
		$first_substring = substr($string, $offset, $length);
		if ($first_substring === $substring)
		{
			$replacement = '';
			$string = substr_replace($string, $replacement, $offset, $length);
		}
		return $string;
	}

	function remove_substring_if_exists_last($string, $substring)
	{
		$length_of_string = strlen($string);
		$length_of_substring_to_remove = strlen($substring);
		$offset = $length_of_string - $length_of_substring_to_remove;
		$length = $length_of_substring_to_remove;
		$last_substring = substr($string, $offset, $length);
		if ($last_substring === $substring)
		{
			$replacement = '';
			$string = substr_replace($string, $replacement, $offset, $length);
		}
		return $string;
	}
	

	function get_text_plaintext($article, $element_search_string, $backup_value = "Tekst zapasowy")
	{
		if (FALSE === is_null($text_element = $article->find($element_search_string, 0)))
			return trim($text_element->plaintext);
		else
			return $backup_value;
	}

	function get_text_from_attribute($article, $element_search_string, $attribute_name, $backup_value = "Tekst zapasowy")
	{
		if (FALSE === is_null($element = $article->find($element_search_string, 0)))
		{
			if($element->hasAttribute($attribute_name))
			{
				$attribute = $element->getAttribute($attribute_name);
				return trim($attribute);
			}
			else
			{
				return $backup_value;
			}
		}
		else
			return $backup_value;
	}

	function replace_attribute($article, $element_search_string, $attribute_to_replace, $attribute_to_replace_with = NULL)
	{
		foreach($article->find($element_search_string) as $element)
		{
			if($element->hasAttribute($attribute_to_replace) && is_null($attribute_to_replace_with))
			{
				$element->removeAttribute($attribute_to_replace);
			}
			else if($element->hasAttribute($attribute_to_replace_with))
			{
				$new_attribute_value = $element->getAttribute($attribute_to_replace_with);
				$element->setAttribute($attribute_to_replace, $new_attribute_value);
			}
		}
	}

	function fix_all_photos($article)
	{
		$array_allowed_attributes = array_merge(get_photo_attributes_caption(), get_photo_attributes_img());
		foreach($article->find('IMG') as $photo_element)
		{
			$img_new_element = '<img ';
			foreach($photo_element->getAllAttributes() as $key => $element)
			{
				if (FALSE === is_null($element) && TRUE === in_array($key, $array_allowed_attributes))
				{
					$img_new_element = $img_new_element.' '.$key.'="'.$element.'"';
				}
			}
			$img_new_element = $img_new_element.'>';
			$photo_element->outertext = $img_new_element;
		}
	}

	function fix_article_photos($article, $element_search_string, $is_main = FALSE, $str_photo_url_attribute = 'src', $str_selectror_photo_caption = '')
	{
		$array_allowed_attributes = array_merge(get_photo_attributes_caption(), get_photo_attributes_img());

		foreach($article->find($element_search_string) as $old_photo_wrapper)
		{
			if ('img' === strtolower($old_photo_wrapper->tag))
			{
				$old_photo_element = $old_photo_wrapper;
			}
			else 
			{
				$old_photo_element = $old_photo_wrapper->find('IMG', 0);
			}
			if (FALSE === is_null($old_photo_element))
			{
				$img_src = "";
				$img_src = $old_photo_element->getAttribute($str_photo_url_attribute);
				if (TRUE === $img_src || 0 === strlen($img_src))
				{
					continue;
				}
				$caption_text = '';
				if (0 !== strlen($str_selectror_photo_caption) && FALSE === is_null($caption_element = $old_photo_wrapper->find($str_selectror_photo_caption, 0)))
				{
					$caption_text = trim($caption_element->plaintext);
					//na nauka o klimacie w opisach są linki
					//https://naukaoklimacie.pl/aktualnosci/jak-nam-idzie-realizacja-porozumienia-paryskiego-jak-pokazuje-raport-emissions-gap-bardzo-zle-460
					$caption_innertext = $caption_element->innertext;
				}
				$href = '';
				if (FALSE === is_null($href_element = $old_photo_wrapper->find('A[href]', 0)))
				{
					$href = $href_element->getAttribute('href');
					if (TRUE === $href)
					{
						$href = '';
					}
				}

				if (TRUE === $is_main)
				{
					$class_string = 'photoWrapper mainPhoto';
				}
				else
				{
					$class_string = 'photoWrapper photo';
				}
				if (0 !== strlen($href) && 0 !== strlen($caption_text))
				{
					$new_photo_wrapper = str_get_html('<figure class="'.$class_string.'"><a href="'.$href.'"><img src="'.$img_src.'" ></a><figcaption>'.$caption_innertext.'</figcaption></figure>');
				}
				else if (0 !== strlen($href) && 0 === strlen($caption_text))
				{
					$new_photo_wrapper = str_get_html('<figure class="'.$class_string.'"><a href="'.$href.'"><img src="'.$img_src.'" ></a></figure>');
				}
				else if (0 === strlen($href) && 0 !== strlen($caption_text))
				{
					$new_photo_wrapper = str_get_html('<figure class="'.$class_string.'"><img src="'.$img_src.'" ><figcaption>'.$caption_innertext.'</figcaption></figure>');
				}
				else if (0 === strlen($href) && 0 === strlen($caption_text))
				{
					$new_photo_wrapper = str_get_html('<figure class="'.$class_string.'"><img src="'.$img_src.'" ></figure>');
				}
				$new_element_img = $new_photo_wrapper->find('IMG', 0);

				//dla atrybutu bez wartosci zwracane jest true
				foreach($old_photo_element->getAllAttributes() as $key => $element)
				{
					if (FALSE === is_null($element) && TRUE === in_array($key, $array_allowed_attributes))
					{
						$new_element_img->setAttribute($key , $element);
					}
				}
				$old_photo_wrapper->outertext = $new_photo_wrapper;
			}
		}
	}

	function fix_all_iframes($article)
	{
		foreach($article->find('IFRAME[src^="http"]') as $frame_element)
		{
			$url = $frame_element->getAttribute('src');
			$frame_element->outertext = get_frame_outertext($url);
		}
	}
	
	function get_Twitter_element($twitter_url)
	{
		$twitter_proxy = 'nitter.snopyta.org';
		$twitter_url = str_replace('twitter.com', $twitter_proxy, $twitter_url);
		$html_twitter = getSimpleHTMLDOM($twitter_url);
		$main_tweet = $html_twitter->find('DIV#m.article-tweet', 0);
		foreach($main_tweet->find('a') as $element)
		{
			$element_url = $element->getAttribute('href');
			if(strpos($element_url, '/') === 0)
			{
				$element_url = "https://".$twitter_proxy.$element_url;
				$element->setAttribute('href', $element_url);
			}
		}
		$date_text = $main_tweet->find('p.tweet-published', 0)->plaintext;
		$main_tweet->find('p.tweet-published', 0)->outertext = '<a href="'.$twitter_url.'" title="'.$date_text.'">'.$date_text.'</a>';
		$main_tweet->find('SPAN.tweet-date', 0)->outertext = '';
		$main_tweet->find('DIV.tweet-stats', 0)->outertext = '';
		$main_tweet->find('A.fullname', 0)->outertext = '';
		return $main_tweet;
	}

	function get_frame_outertext($url)
	{
		$outertext_to_return = "";
		$proxy_url = get_proxy_url($url);
		if ("" !== $url)
		{
			if ($proxy_url !== $url)
			{
				$outertext_to_return = 
					'<strong><br>'
					.'<a href='.$url.'>'
					."Ramka - ".$url.'<br>'
					.'</a>'
					.'<a href='.$proxy_url.'>'
					."Ramka - ".$proxy_url.'<br>'
					.'</a>'
					.'<br></strong>';
			}
			else
			{
				$outertext_to_return = 
					'<strong><br>'
					.'<a href='.$url.'>'
					."Ramka - ".$url.'<br>'
					.'</a>'
					.'<br></strong>';
			}
		}
		return $outertext_to_return;
	}

