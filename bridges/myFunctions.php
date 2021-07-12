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

	function convert_amp_photos($main_element)
	{
		$main_element = foreach_delete_element($main_element, 'amp-analytics');
		$main_element = foreach_delete_element($main_element, 'amp-ad');
		$main_element = foreach_delete_element($main_element, 'i-amphtml-sizer');
		$main_element = foreach_delete_element($main_element, 'amp-image-lightbox');
		foreach($main_element->find('amp-img, img') as $photo_element)
		{
			if(isset($photo_element->width)) $photo_element->width = NULL;
			if(isset($photo_element->height)) $photo_element->height = NULL;
		}

		
		//https://opinie.wp.pl/wielka-zmiana-mezczyzn-wirus-ja-tylko-przyspieszyl-6604913984391297a?amp=1&_js_v=0.1
		foreach($main_element->find('amp-img') as $ampimg)
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
		return str_get_html($main_element->save());
	}

	function convert_amp_frames_to_links($main_element)
	{
		foreach($main_element->find('amp-iframe') as $amp_iframe)
		{
			if(FALSE === is_null($src = ($amp_iframe->getAttribute('src'))))
			{
				$src = $amp_iframe->src;
				$amp_iframe->outertext = get_frame_outertext($src);
			}
		}
		foreach($main_element->find('amp-twitter') as $amp_twitter)
		{
			if(FALSE === is_null($data_tweetid = ($amp_twitter->getAttribute('data-tweetid'))))
			{
				$twitter_url = 'https://twitter.com/anyuser/status/'.$data_tweetid;
				$amp_twitter->outertext = get_frame_outertext($twitter_url);
			}
		}
		foreach($main_element->find('amp-youtube') as $amp_youtube)
		{
			if(FALSE === is_null($data_videoid = ($amp_youtube->getAttribute('data-videoid'))))
			{
				$youtube_url = 'https://www.youtube.com/watch?v='.$data_videoid;
				$amp_youtube->outertext = get_frame_outertext($youtube_url);
			}
		}
		return str_get_html($main_element->save());
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

	function clear_paragraphs_from_taglinks($main_element, $paragrapgh_search_string, $regexArray)
	{
		foreach($main_element->find($paragrapgh_search_string) as $paragraph)
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
		return str_get_html($main_element->save());
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
	
	function foreach_delete_element_containing_text_from_array($main_element, $element_search_string, $subelement_search_textstring_array)
	{
		foreach($main_element->find($element_search_string) as $element)
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
		return str_get_html($main_element->save());
	}

	function foreach_delete_element_containing_elements_hierarchy($main_element, $subelements_hierarchy_array)
	{
		$last = count($subelements_hierarchy_array)-1;
		foreach($main_element->find($subelements_hierarchy_array[$last]) as $subelement)
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
		return str_get_html($main_element->save());
	}

	function single_delete_element_containing_subelement($element, $subelement_search_string)
	{
		if (FALSE === is_null($subelement = $element->find($subelement_search_string, 0)))
		{
			$element->outertext = '';
		}
	}
	
	function foreach_delete_element($main_element, $element_search_string)
	{
		foreach($main_element->find($element_search_string) as $element)
		{
			$element->outertext = '';
		}
		return str_get_html($main_element->save());
	}

	function single_delete_subelement($element, $subelement_search_string)
	{
		if (FALSE === is_null($subelement = $element->find($subelement_search_string, 0)))
		{
			$subelement->outertext = '';
		}
	}

	function foreach_delete_element_containing_subelement($main_element, $element_search_string, $subelement_search_string)
	{
		foreach ($main_element->find($element_search_string) as $element)
		{
			if (FALSE === is_null($subelement = $element->find($subelement_search_string, 0)))
			{
				$element->outertext = '';
			}
		}
		return str_get_html($main_element->save());
	}

	function foreach_replace_outertext_with_subelement_innertext($main_element, $element_search_string, $subelement_search_string)
	{
		foreach($main_element->find($element_search_string) as $element)
		{
			if (FALSE === is_null($subelement = $element->find($subelement_search_string, 0)))
			{
				$element->outertext = $subelement->innertext;
			}
		}
		return str_get_html($main_element->save());
	}

	function foreach_replace_outertext_with_subelement_outertext($main_element, $element_search_string, $subelement_search_string)
	{
		foreach($main_element->find($element_search_string) as $element)
		{
			if (FALSE === is_null($subelement = $element->find($subelement_search_string, 0)))
			{
				$element->outertext = $subelement->outertext;
			}
		}
		return str_get_html($main_element->save());
	}

	function foreach_replace_outertext_with_single_child_outertext($main_element, $element_search_string, $subelement_search_string)
	{
		foreach($main_element->find($element_search_string) as $element)
		{
			if (1 === count($element->children()) && !is_null($subelement = $element->find($subelement_search_string, 0)) && $element === $subelement->parent())
			{
				$element->outertext = $subelement->outertext;
			}
		}
		return str_get_html($main_element->save());
	}

	function foreach_replace_outertext_with_innertext($main_element, $element_search_string)
	{
/*
		$main_element_str = $main_element->save();
		$counter = 0;
		if ($GLOBALS['my_debug']) print_element($main_element, "cały artykuł nr 1");
		foreach($main_element->find($element_search_string) as $element)
		{
			if ($GLOBALS['my_debug']) print_element($element, "element nr $counter");
			if ($GLOBALS['my_debug']) print_html($element, "element nr $counter");
			if ($GLOBALS['my_debug']) echo "Przed zamianą<br> <br><pre>".htmlspecialchars($main_element_str)."</pre><br>";
			$counter++;
			$main_element_str = str_replace($element->outertext, $element->innertext, $main_element_str);
			if ($GLOBALS['my_debug']) echo "Po zamianie<br> <br><pre>".htmlspecialchars($main_element_str)."</pre><br>";
		}
		$main_element = str_get_html($main_element_str);
		if ($GLOBALS['my_debug']) print_element($main_element, "cały artykuł nr 2");
		return str_get_html($main_element_str);
*/
		foreach($main_element->find($element_search_string) as $element)
		{
			$element->outertext = $element->innertext;
		}
		return str_get_html($main_element->save());
	}

	function foreach_replace_outertext_with_plaintext($main_element, $element_search_string)
	{
		foreach($main_element->find($element_search_string) as $element)
		{
			$element->outertext = $element->plaintext;
		}
		return str_get_html($main_element->save());
	}

	function foreach_replace_innertext_with_plaintext($main_element, $element_search_string)
	{
		foreach($main_element->find($element_search_string) as $element)
		{
			$element->innertext = $element->plaintext;
		}
		return str_get_html($main_element->save());
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

	function return_tags_array($main_element, $tag_selector)
	{
		$tags = array();
		foreach($main_element->find($tag_selector) as $tags_item)
		{
			$tag_text = $tags_item->plaintext;
			$tag_text = str_replace("&nbsp;", '', $tag_text);
			$tag_text = trim($tag_text);
			$tag_text = trim($tag_text, ',;');
//			print_var_dump($tag_text, 'tag_text');
			if (strlen($tag_text) > 0)
			{
				$tags[] = $tag_text;
			}
		}
		return array_unique($tags);
	}

	function return_authors_as_string($main_element, $author_selector)
	{
		$authors = '';
		foreach($main_element->find($author_selector) as $author_item)
		{
			$authors = $authors.', '.trim($author_item->plaintext);
		}
		$authors = substr_replace($authors, '', 0, strlen(', '));
		return $authors;
	}

	function add_style($main_element, $search_string, $stylesArray)
	{
		$styleString = "";
		foreach ($stylesArray as $style)
		{
			$styleString = $styleString.$style;
		}
		foreach ($main_element->find($search_string) as $element)
		{
			if(FALSE === $element->hasAttribute('style'))
				$element->setAttribute('style', $styleString);
			else
				$element->style = $element->style.$styleString;
		}
		return str_get_html($main_element->save());
	}
	
	function hex_dump($data, $name = NULL, $newline='<br>')
	{
		if (isset($name))
		{
			echo "hex_dump zmiennej $name: <br>";
		}
		static $from = '';
		static $to = '';
	
		static $width = 16; # number of bytes per line
//		static $width = 4; # number of bytes per line

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
	

	function get_text_plaintext($main_element, $element_search_string, $backup_value = "Tekst zapasowy")
	{
		if (FALSE === is_null($text_element = $main_element->find($element_search_string, 0)))
			return trim($text_element->plaintext);
		else
			return $backup_value;
	}

	function get_text_from_attribute($main_element, $element_search_string, $attribute_name, $backup_value = "Tekst zapasowy")
	{
		if (FALSE === is_null($element = $main_element->find($element_search_string, 0)))
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

	function replace_attribute($main_element, $element_search_string, $attribute_to_replace, $attribute_to_replace_with = NULL)
	{
		foreach($main_element->find($element_search_string) as $element)
		{
			if($element->hasAttribute($attribute_to_replace) && is_null($attribute_to_replace_with))
			{
				$element->removeAttribute($attribute_to_replace);
			}
			else if($element->hasAttribute($attribute_to_replace_with))
			{
				$new_attribute_value = $element->getAttribute($attribute_to_replace_with);
				$element->setAttribute($attribute_to_replace, $new_attribute_value);
				$element->removeAttribute($attribute_to_replace_with);
			}
		}
		return str_get_html($main_element->save());
	}

	function remove_multiple_attributes($main_element, $attributes_array)
	{
		$attributes_array = array_unique($attributes_array);
		$selectors_array = array();
		foreach ($attributes_array as $key => $value)
		{
			$selectors_array[] = "[".$value."]";
		}
		$string_selector = implode(', ', $selectors_array);
		foreach($main_element->find($string_selector) as $element)
		{
			foreach ($attributes_array as $attribute)
			{
				if($element->hasAttribute($attribute))
				{
					$element->removeAttribute($attribute);
				}
			}
		}
		return str_get_html($main_element->save());
	}

	function fix_all_photos_attributes($main_element)
	{
		$array_allowed_attributes = array_merge(get_photo_attributes_caption(), get_photo_attributes_img());
		foreach($main_element->find('IMG') as $photo_element)
		{
			$img_new_element = '<img ';
			foreach($photo_element->getAllAttributes() as $key => $element)
			{
				if ("" !== $element && FALSE === is_null($element) && TRUE === in_array($key, $array_allowed_attributes))
				{
					$img_new_element = $img_new_element.' '.$key.'="'.$element.'"';
				}
				
			}
			$img_new_element = $img_new_element.'>';
			$photo_element->outertext = $img_new_element;
		}
		return str_get_html($main_element->save());
	}

	function format_article_photos($main_element, $element_search_string, $is_main = FALSE, $str_photo_url_attribute = 'src', $str_selector_photo_caption = NULL)
	{
		//Zdjęcia stąd: https://klubjagiellonski.pl/2021/03/19/polacy-nie-chca-wegla-a-co-trzeci-jest-gotow-placic-wiecej-za-transformacje-energetyczna/
		//miały link: https://klubjagiellonski.pl/temat/zielony-konserwatyzm/
		//$article = format_article_photos($article, 'IMG[class*="wp-image-"]', FALSE);
		$main_element_str = $main_element->save();
		$array_allowed_attributes = array_merge(get_photo_attributes_caption(), get_photo_attributes_img());
		if ($GLOBALS['my_debug']) $counter = 0;
		foreach($main_element->find($element_search_string) as $old_photo_wrapper)
		{
			if ($GLOBALS['my_debug']) echo "<br><br><br><br><br><br><br><br><br><br>";
			if ($GLOBALS['my_debug']) $counter++;
			//if ($GLOBALS['my_debug']) print_element($old_photo_wrapper, "element nr $counter");
			if ($GLOBALS['my_debug']) print_html($old_photo_wrapper, "element nr $counter, old_photo_wrapper");
			$img_src = NULL;
			$img_src_temp = NULL;
			$href = NULL;
			$href_temp = NULL;
			$caption_text_temp = NULL;
			$caption_innertext = NULL;
			if ('img' === strtolower($old_photo_wrapper->tag))
			{
				$old_photo_element = $old_photo_wrapper;
			}
			else 
			{
				$old_photo_element = $old_photo_wrapper->find('IMG', 0);
			}
			if (!is_null($old_photo_element))
			{
				if ($GLOBALS['my_debug']) echo "element nr $counter, old_photo_element nie jest nullem<br>";
				$img_src_temp = trim($old_photo_element->getAttribute($str_photo_url_attribute));
				if ($GLOBALS['my_debug']) print_var_dump($img_src_temp, "element img_src_temp nr $counter");
				
				if (FALSE !== strpos($img_src_temp, '//'))
				{
					$img_src = $img_src_temp;
				}
				else
				{
					continue;
				}
				if ($GLOBALS['my_debug']) print_var_dump($img_src, "element img_src nr $counter");
				$old_photo_wrapper = str_get_html($old_photo_wrapper->save());
				if (isset($str_selector_photo_caption) && !is_null($caption_element = $old_photo_wrapper->find($str_selector_photo_caption, 0)))
				{
					$caption_text_temp = trim($caption_element->plaintext);
					//na nauka o klimacie w opisach są linki
					//https://naukaoklimacie.pl/aktualnosci/jak-nam-idzie-realizacja-porozumienia-paryskiego-jak-pokazuje-raport-emissions-gap-bardzo-zle-460
					if (0 < strlen($caption_text_temp))
					{
						$caption_innertext = $caption_element->innertext;
					}
				}
				
				$old_photo_wrapper = str_get_html($old_photo_wrapper->save());
				if ($GLOBALS['my_debug']) print_html($old_photo_wrapper, "element old_photo_wrapper nr $counter");

				if (!is_null($href_element = $old_photo_wrapper->find('A[href]', 0)))
				{
					if ($GLOBALS['my_debug']) echo "element nr $counter jest href<br>";
					$href_temp = trim($href_element->getAttribute('href'));
					if (FALSE !== strpos($href_temp, '//'))
					{
						if ($GLOBALS['my_debug']) echo "element nr $counter href jest ok<br>";
						$href = $href_temp;
					}
				}

				//print_html($old_photo_wrapper, "old_photo_wrapper 3");
				if (TRUE === $is_main)
				{
					$class_string = 'photoWrapper mainPhoto';
				}
				else
				{
					$class_string = 'photoWrapper photo';
				}
				if (isset($href) && isset($caption_innertext))
				{
					if ($GLOBALS['my_debug']) echo 'if (isset($href) && isset($caption_text))'."<br>";
					$new_photo_wrapper_outertext = '<figure class="'.$class_string.'"><a href="'.$href.'"><img src="'.$img_src.'" ></a><figcaption>'.$caption_innertext.'</figcaption></figure>';
					$new_photo_wrapper = str_get_html($new_photo_wrapper_outertext);
					if ($GLOBALS['my_debug']) print_html($new_photo_wrapper_outertext, "new_photo_wrapper_outertext");
				}
				else if (isset($href) && !isset($caption_innertext))
				{
					if ($GLOBALS['my_debug']) echo 'else if (isset($href) && !isset($caption_text))'."<br>";
					$new_photo_wrapper_outertext = '<figure class="'.$class_string.'"><a href="'.$href.'"><img src="'.$img_src.'" ></a></figure>';
					$new_photo_wrapper = str_get_html($new_photo_wrapper_outertext);
					if ($GLOBALS['my_debug']) print_html($new_photo_wrapper_outertext, "new_photo_wrapper_outertext");
				}
				else if (!isset($href) && isset($caption_innertext))
				{
					if ($GLOBALS['my_debug']) echo 'else if (!isset($href) && isset($caption_text))'."<br>";
					$new_photo_wrapper_outertext = '<figure class="'.$class_string.'"><img src="'.$img_src.'" ><figcaption>'.$caption_innertext.'</figcaption></figure>';
					$new_photo_wrapper = str_get_html($new_photo_wrapper_outertext);
					if ($GLOBALS['my_debug']) print_html($new_photo_wrapper_outertext, "new_photo_wrapper_outertext");
				}
				else if (!isset($href) && !isset($caption_innertext))
				{
					if ($GLOBALS['my_debug']) echo 'else if (!isset($href) && !isset($caption_text))'."<br>";
					$new_photo_wrapper_outertext = '<figure class="'.$class_string.'"><img src="'.$img_src.'" ></figure>';
					$new_photo_wrapper = str_get_html($new_photo_wrapper_outertext);
					if ($GLOBALS['my_debug']) print_html($new_photo_wrapper_outertext, "new_photo_wrapper_outertext");
				}
				else
				{
					echo "Coś nie pykło<br>";
				}
				$new_element_img = $new_photo_wrapper->find('IMG', 0);
				
				if ($GLOBALS['my_debug']) print_var_dump($href, "element href nr $counter");
				if ($GLOBALS['my_debug']) print_var_dump($caption_innertext, "element caption_innertext nr $counter");
				if ($GLOBALS['my_debug']) print_var_dump($img_src, "element img_src nr $counter");
				$new_element_img_before_changes = $new_element_img->outertext;
				
				if ($GLOBALS['my_debug']) print_html($new_element_img_before_changes, "new_element_img_before_changes");

				//dla atrybutu bez wartosci zwracane jest true
				foreach($old_photo_element->getAllAttributes() as $attribute => $value)
				{
					//print_var_dump($value, "$attribute-value");
					if ("" !== $value && FALSE === is_null($value) && TRUE === in_array($attribute, $array_allowed_attributes))
					{
						//print_var_dump($value, "weszlo: $attribute-value");
						$new_element_img->setAttribute($attribute, $value);
					}
				}
				$new_element_img_after_changes = $new_element_img->outertext;
				if ($GLOBALS['my_debug']) print_html($new_element_img_after_changes, "new_element_img_after_changes");
				$new_photo_wrapper_outertext = str_replace($new_element_img_before_changes, $new_element_img_after_changes, $new_photo_wrapper_outertext);
				if ($GLOBALS['my_debug']) print_html($new_photo_wrapper_outertext, "new_photo_wrapper_outertext");

				$main_element_str = str_replace($old_photo_wrapper->outertext, $new_photo_wrapper_outertext, $main_element_str);
//				$old_photo_wrapper->outertext = $new_photo_wrapper;
			}
			else
			{
				if ($GLOBALS['my_debug']) echo "element nr $counter, old_photo_element jest nullem<br>";
			}
		}
		return str_get_html($main_element_str);
	}

	function convert_iframes_to_links($main_element)
	{
		foreach($main_element->find('IFRAME[src^="http"]') as $frame_element)
		{
			$url = $frame_element->getAttribute('src');
			if($frame_element->hasAttribute('title'))
			{
				$title = $frame_element->getAttribute('title');
				$frame_element->outertext = get_frame_outertext($url, $title);
			}
			else
			{
				$frame_element->outertext = get_frame_outertext($url);
			}
		}
		foreach($main_element->find('IFRAME[data-src^="http"]') as $frame_element)
		{
			$url = $frame_element->getAttribute('data-src');
			if($frame_element->hasAttribute('title'))
			{
				$title = $frame_element->getAttribute('title');
				$frame_element->outertext = get_frame_outertext($url, $title);
			}
			else
			{
				$frame_element->outertext = get_frame_outertext($url);
			}
		}
		return str_get_html($main_element->save());
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

	function get_frame_outertext($url, $title = NULL)
	{
		$outertext_to_return = "";
		$proxy_url = get_proxy_url($url);
		
		$title_outertext = "";
		$url_outertext = "";
		$url_proxy_outertext = "";
		if (isset($title) && strlen($title) > 0)
		{
			$title_outertext = "Ramka - ".$title.'<br>';
		}
		if (isset($url) && strlen($url) > 0)
		{
			$url_outertext = 
			'<a href='.$url.'>'
			."Ramka - ".$url.'<br>'
			.'</a>';
		}
		if (isset($proxy_url) && strlen($proxy_url) > 0 && $proxy_url !== $url)
		{
			$url_proxy_outertext = 
			'<a href='.$proxy_url.'>'
			."Ramka - ".$proxy_url.'<br>'
			.'</a>';
		}
		if (strlen($title_outertext) > 0 || strlen($url_outertext) > 0 || strlen($url_proxy_outertext) > 0)
		{
			$outertext_to_return = '<strong><br>'.$title_outertext.$url_outertext.$url_proxy_outertext.'<br></strong>';
		}
		return $outertext_to_return;
	}
	
	function get_values_from_json($main_element, $variable_with_data, $selector, $wanted_variable_name, $output_type = array(0))
	{
		$variable_as_array = get_json_variable_as_array($main_element, $variable_with_data, $selector);
		$subarrays_by_key = get_subarrays_by_key($variable_as_array, $wanted_variable_name);
		$flattened_array = flatten_array($subarrays_by_key, $wanted_variable_name);
		if (FALSE === $output_type)
		{
			return $flattened_array;
		}
		else if (TRUE === $output_type)
		{
			foreach ($flattened_array as $subarray)
			{
				if(isset($subarray[$wanted_variable_name]))
				{
					$new_array[] = $subarray[$wanted_variable_name];
				}
			}
			return $new_array;
		}
		else if (is_array($output_type))
		{
			foreach ($output_type as $wanted_position)
			{
				if(isset($flattened_array[$output_type][$wanted_variable_name]))
				{
					$new_array[] = $flattened_array[$wanted_position][$wanted_variable_name];
				}
			}
			return $new_array;
		}
		return NULL;
	}
	
	function get_json_value_from_given_text($string, $variable_name)
	{
		preg_match('/["\']'.$variable_name.'["\'] *: *["\'](.*?)["\']/', $string, $output_array);
		if (isset($output_array[1]))
		{
			$value = $output_array[1];
			return $value;
		}
		else
		{
			return NULL;
		}
	}


	function get_json_value($main_element, $string_selector, $search_string)
	{
		$value = "";
		foreach($main_element->find($string_selector) as $script_element)
		{
			$script_text = $script_element->innertext;
//			preg_match('/["\']'.$search_string.'["\'] *: *["\'](.*)["\']/', $script_text, $output_array);
			preg_match('/["\']'.$search_string.'["\'] *: *["\'](.*?)["\']/', $script_text, $output_array);
			
			if (isset($output_array[1]))
			{
//				print_var_dump($script_text, 'script_text');
				$value = $output_array[1];
				return $value;
			}
		}
	}

	function get_json_variable_as_array($article_html, $variable_name = NULL, $selector = 'SCRIPT')
	{
		$json_string = get_json_variable($article_html, $variable_name, $selector);
		$article_data_parsed = parse_article_data(json_decode($json_string));
//		$article_data_parsed = parse_article_data(json_decode('zie{{mniak'));
		return $article_data_parsed;
	}
	
	function get_subarrays_by_key($current_variable, $include_index, $key = NULL)
	{
		$array_to_return = array();
		if (!is_array($current_variable))
		{
			return null;
		}
		else if (isset($current_variable[$include_index]))
		{
			$array_to_return[] = array($include_index => $current_variable[$include_index]);
			unset($current_variable[$include_index]);
		}
		if (is_array($current_variable))
		{
			foreach ($current_variable as $key => $item)
			{
				if (is_array($item))
				{
					$returned_array = get_subarrays_by_key($item, $include_index, $key);
					if (!is_null($returned_array) && count($returned_array) > 0)
					{
						$array_to_return[] = $returned_array;
					}
				}
			}
		}
		return $array_to_return;
	}

	
	function flatten_array($current_variable, $include_index)
	{
		$array_to_return = array();
		if (!is_array($current_variable))
		{
			return null;
		}
		else if (isset($current_variable[$include_index]))
		{
			return $current_variable;
		}
		else if (is_array($current_variable))
		{
			foreach ($current_variable as $key => $item)
			{
				if (is_array($item))
				{
					$returned_array = flatten_array($item, $include_index);
					if (!is_null($returned_array) && count($returned_array) > 0)
					{
						if (isset($returned_array[$include_index]))
						{
							$array_to_return[] = $returned_array;
						}
						else
						{
							foreach ($returned_array as $returned_subarray_key => $returned_subarray_item)
							{
								$array_to_return[] = $returned_subarray_item;
							}
						}
					}
				}
			}
		}
		return $array_to_return;
	}
	
	function getArray($array, $index)
	{
	    if (!is_array($array))
		{
			return null;
		}
	    if (isset($array[$index]))
		{
			return $array[$index];
		}
	    foreach ($array as $item)
		{
	        $return = getArray($item, $index);
	        if (!is_null($return))
			{
	            return $return;
	        }
	    }
	    return null;
	}
/*
function getArray($array, $index) {
    $arrayIt = new RecursiveArrayIterator($array);
    $it = new RecursiveIteratorIterator(
        $arrayIt, 
        RecursiveIteratorIterator::SELF_FIRST
    );
    foreach ($it as $key => $value) {
        if ($key == $index) {
            return $value;
        }
    }
    return null;
}
*/
	function get_json_variable($article_html, $variable_name = NULL, $selector = 'SCRIPT')
	{
		foreach($article_html->find($selector) as $script_element)
		{
			$script_text = $script_element->innertext;
			if (isset($variable_name))
			{
				$position = strpos($script_text, $variable_name);
			}
			else
			{
				//jeżeli cała zawartość skryptu jest żądana
				$position = 0;
			}
//			print_var_dump($script_text, 'script_text');
//			print_var_dump($position, 'position');
			if (FALSE !== $position)
			{
				$rest = substr($script_text, $position);
				$rest = trim($rest);
//				print_var_dump($rest, 'rest');
				$opening_bracket_counter = 0;
				$closing_bracket_counter = 0;
				$end_position = 0;
				$start_position = 0;
				for ($i = 0; $i < strlen($rest); $i++)
				{
//					print_var_dump($rest[$i], 'rest[i]');
					if ('{' === $rest[$i])
					{
						$opening_bracket_counter++;
						if ($opening_bracket_counter == 1)
						{
							$start_position = $i;
						}
					}
					else if ('}' === $rest[$i])
					{
						$closing_bracket_counter++;
					}
					if ($opening_bracket_counter >= 1 && $opening_bracket_counter === $closing_bracket_counter)
					{
						$end_position = $i;
						break;
					}
				}
				/*
				print_var_dump($opening_bracket_counter, 'opening_bracket_counter');
				print_var_dump($closing_bracket_counter, 'closing_bracket_counter');
				print_var_dump($start_position, 'start_position');
				print_var_dump($end_position, 'end_position');
				*/
				if ($end_position > 0)
				{
					return substr($rest, $start_position, $end_position - $start_position + 1);
				}
			}
		}
		return "";
	}

	function set_biggest_photo_size_from_sources($main_element)
	{
		foreach($main_element->find('PICTURE') as $picture_element)
		{
			if (!is_null($image_element = $picture_element->find('IMG', 0)))
			{
				$photo_sizes_data = array();
				foreach($picture_element->find('SOURCE[media][srcset]') as $source_element)
				{
					$source_srcset = $source_element->getAttribute('srcset');
					$source_media = $source_element->getAttribute('media');
					preg_match('/([0-9]+)/', $source_media, $output_array);
					$img_size_int = intval($output_array[1]);
//					print_var_dump($output_array, 'output_array');
					$photo_sizes_data[] = array(
						'size' => $img_size_int,
						'src' => $source_srcset
					);
				}
				if (isset($photo_sizes_data[0]))
				{
					$biggest_size = 0;
					$biggest_position = 0;
					foreach($photo_sizes_data as $key => $element)
					{
						if ($element['size'] > $biggest_size)
						{
							$biggest_size = $element['size'];
							$biggest_position = $key;
						}
					}
					$image_element->setAttribute('src', $photo_sizes_data[$biggest_position]['src']);
					foreach($picture_element->find('SOURCE[media][srcset]') as $source_element)
					{
						$source_element->outertext = '';
					}
				}
			}
		}
		return str_get_html($main_element->save());
	}
	
	function set_biggest_photo_size_from_attribute($main_element, $string_selector, $attribute_name)
	{
		foreach($main_element->find($string_selector) as $photo_element)
		{
			if($photo_element->hasAttribute($attribute_name))
			{
//				print_element($photo_element, 'photo_element przed');
//				print_html($photo_element, 'photo_element przed');
				$img_srcset = $photo_element->getAttribute($attribute_name);
				$photo_sizes_data = array();
				$multiple_sizes_array  = explode(',', $img_srcset);
				$multiple_sizes_array = preg_split('/( [0-9]+w),?/', $img_srcset, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);

//				print_var_dump($img_srcset, 'img_srcset');
//				print_var_dump($multiple_sizes_array, 'multiple_sizes_array');
				if (count($multiple_sizes_array) > 1)
				{
					
					for ($i = 0; $i < count($multiple_sizes_array); $i=$i+2)
					{
						$img_size_src = $multiple_sizes_array[$i];
						$img_size_string = $multiple_sizes_array[$i+1];
						preg_match('/([0-9]+)/', $img_size_string, $output_array);
						$img_size_int = intval($output_array[1]);
						$photo_sizes_data[] = array(
							'size' => $img_size_int,
							'src' => $img_size_src
						);
					}
//					print_var_dump($photo_sizes_data, 'photo_sizes_data');
					$biggest_size = 0;
					$biggest_position = 0;
					foreach($photo_sizes_data as $key => $element)
					{
						if ($element['size'] > $biggest_size)
						{
							$biggest_size = $element['size'];
							$biggest_position = $key;
						}
					}
					$photo_element->setAttribute('src', $photo_sizes_data[$biggest_position]['src']);
				}
				else
				{
					$photo_element->setAttribute('src', $img_srcset);
				}
				$photo_element->setAttribute($attribute_name, NULL);
//				print_element($photo_element, 'photo_element po');
			}
		}
		return str_get_html($main_element->save());
	}

	function foreach_combine_two_elements($main_element, $element_to_stay_selector, $up_counter, $forward_counter, $element_to_move_tag, $element_to_move_class, $element_to_move_child_selector, $element_to_stay_code = 'outertext', $element_to_move_code = 'outertext', $parent_tag = NULL, $parent_class = NULL)
	{
		foreach ($main_element->find($element_to_stay_selector) as $element_to_stay)
		{
			//echo "test 001<br>";
			for ($i = 0; $i < $up_counter; $i++)
			{
				//echo "test 002<br>";
				$element_to_stay = $element_to_stay->parent;
			}
			$next_element = $element_to_stay->next_sibling();
			if (!is_null($next_element))
			{
				//echo "test 003<br>";
				for ($i = 0; $i < $forward_counter-1; $i++)
				{
					//echo "test 004<br>";
					$next_element = $next_element->next_sibling();
					if (is_null($next_element))
						break;
				}
				if (!is_null($next_element))
				{
					//echo "test 005<br>";
					$element_to_move_tag = strtolower($element_to_move_tag);
					$element_to_move_class = strtolower($element_to_move_class);
					$next_element_tag = strtolower($next_element->tag);
					$next_element_class = strtolower($next_element->class);
					//print_var_dump($element_to_move_tag, 'element_to_move_tag');
					//print_var_dump($element_to_move_class, 'element_to_move_class');
					//print_var_dump($next_element_tag, 'next_element_tag');
					//print_var_dump($next_element_class, 'next_element_class');
					if (FALSE !== strpos($element_to_move_tag, $next_element_tag) && FALSE !== strpos($element_to_move_class, $next_element_class))
					{
						//echo "test 006<br>";
						$element_to_move_child = $next_element->find($element_to_move_child_selector, 0);
						if (FALSE === is_null($element_to_move_child))
						{
							//echo "test 007<br>";
							if ('outertext' === $element_to_stay_code)
							{
								$element_to_stay_html = $element_to_stay->outertext;
							}
							else
							{
								$element_to_stay_html = $element_to_stay->innertext;
							}

							if ('outertext' === $element_to_move_code)
							{
								$element_to_move_html = $next_element->outertext;
							}
							else
							{
								$element_to_move_html = $next_element->innertext;
							}

							if (is_null($parent_tag) || is_null($parent_class))
							{
								$element_to_stay->outertext = $element_to_stay_html.$element_to_move_html;
								$next_element->outertext = '';
							}
							else
							{
								$element_to_stay->outertext = '<'.$parent_tag.' class="'.$parent_class.'">'.$element_to_stay_html.$element_to_move_html.'</'.$parent_tag.'>';
								$next_element->outertext = '';
							}
						}
					}
				}
			}
		}
		return str_get_html($main_element->save());
	}

	function iterator($copy_counter)
	{
//		print_var_dump($copy_counter, "copy_counter przed");
		$copy_counter++;
//		print_var_dump($copy_counter, "copy_counter po");
	}

	function replace_tag_and_class($main_element, $element_selector, $count = 'single', $new_tag = NULL, $new_class = NULL)
	{
		if ('single' === $count)
		{
			$element = $main_element->find($element_selector, 0);
			if (FALSE === is_null($element))
			{
				if (isset($new_tag))
				{
					$element->tag = $new_tag;
				}
				if (isset($new_class))
				{
					if (strlen($new_class) > 0)
					{
						$element->class = $new_class;
					}
					else
					{
						$element->class = NULL;
					}
				}
			}
		}
		else if ('multiple' === $count)
		{
			foreach ($main_element->find($element_selector) as $element)
			{
				if (isset($new_tag))
				{
					$element->tag = $new_tag;
				}
				if (isset($new_class))
				{
					if (strlen($new_class) > 0)
					{
						$element->class = $new_class;
					}
					else
					{
						$element->class = NULL;
					}
				}
			}
		}
//		$article = str_get_html($main_element->save());
		return str_get_html($main_element->save());
	}

	function replace_part_of_class($main_element, $element_selector, $count = 'single', $part_to_replace, $part_to_insert)
	{
		if ('single' === $count)
		{
			$element = $main_element->find($element_selector, 0);
			if (FALSE === is_null($element))
			{
				if (isset($part_to_insert))
				{
//					print_html($element, 'element_to_stay przed');
					$element->class = trim(str_replace($part_to_replace, $part_to_insert, $element->class));
//					print_html($element, 'element_to_stay po');
				}
			}
		}
		else if ('multiple' === $count)
		{
			foreach ($main_element->find($element_selector) as $element)
			{
				if (isset($part_to_insert))
				{
					$element->class = str_replace($part_to_replace, $part_to_insert, $element->class);
				}
			}
		}
		return str_get_html($main_element->save());
	}

	function insert_text($main_element, $element_selector, $count = 'single', $where_to_put = 'after', $text_to_insert)
	{
		if ('single' === $count)
		{
			$element = $main_element->find($element_selector, 0);
			if (FALSE === is_null($element))
			{
				if ('before' === $where_to_put)
				{
					$element->plaintext = $text_to_insert.$element->plaintext;
				}
				else if ('after' === $where_to_put)
				{
					$element->plaintext = $element->plaintext.$text_to_insert;
				}
			}
		}
		else if ('multiple' === $count)
		{
			foreach ($main_element->find($element_selector) as $element)
			{
				if ('before' === $where_to_put)
				{
					$element->plaintext = $text_to_insert.$element->plaintext;
				}
				else if ('after' === $where_to_put)
				{
					$element->plaintext = $element->plaintext.$text_to_insert;
				}
			}
		}
		return str_get_html($main_element->save());
	}

	function combine_two_elements($main_element, $first_element_selector, $second_element_selector, $parent_tag = NULL, $parent_class = NULL)
	{
		$first_element = $main_element->find($first_element_selector, 0);
		$second_element = $main_element->find($second_element_selector, 0);
		if (FALSE === is_null($first_element) && FALSE === is_null($second_element))
		{
			if (is_null($parent_tag) || is_null($parent_class))
			{
				$first_element->outertext = $first_element->outertext.$second_element->outertext;
				$second_element->outertext = '';
			}
			else
			{
				$first_element->outertext = '<'.$parent_tag.' class="'.$parent_class.'">'.$first_element->outertext.$second_element->outertext.'</'.$parent_tag.'>';
				$second_element->outertext = '';
			}
		}
		return str_get_html($main_element->save());
	}

	function fix_background_image($main_element, $where_to_move_children = 1)
	{
//		print_var_dump(gettype($main_element), "gettype(main_element)");
//		print_element($main_element, 'main_element przed');
		foreach ($main_element->find('[style^="background-image:"]') as $background_element)
		{
			$children_outertext = '';
//			print_html($background_element->innertext, 'background_element->innertext');
//			print_html($background_element->outertext, 'background_element->outertext');
			foreach ($background_element->children() as $child)
			{
				$children_outertext = $children_outertext.$child->outertext;
			}
			$background_element->innertext = '';
			$string_style = $background_element->getAttribute('style');
			preg_match('/background-image: *url\(([^\)]*)/', $string_style, $output_array);
			$img_src = trim($output_array[1]);
			//https://www.tygodnikpowszechny.pl/z-drugiej-strony-internetu-166922
			$img_src = str_replace("'", "", $img_src);
			$background_element->setAttribute('style', NULL);
			$background_element->setAttribute('src', $img_src);
			$background_element->tag = 'IMG';
//			print_html($children_outertext, 'children_outertext');
			if(strlen($children_outertext) > 0 && 1 == $where_to_move_children)
			{
				$background_element->outertext = $background_element->outertext.$children_outertext;
			}
			else if (strlen($children_outertext) > 0)
			{
				$background_element->outertext = $children_outertext.$background_element->outertext;
			}
		}
//		print_element($main_element, 'main_element po');
//		print_html($main_element, 'main_element po children_outertext');
	}

	
	function move_element($main_element, $element_to_move_selector, $element_to_stay_selector, $where_to_put = 'outertext', $position = 'before')
	{
		$element_to_move = $main_element->find($element_to_move_selector, 0);
		$element_to_stay = $main_element->find($element_to_stay_selector, 0);
//		print_element($element_to_stay, 'element_to_stay przed');
//		print_html($element_to_stay, 'element_to_stay przed');
		if (FALSE === is_null($element_to_move) && FALSE === is_null($element_to_stay))
		{
			$element_to_move_outertext = $element_to_move->outertext;
			$element_to_move->outertext = '';
			if ('outertext' === $where_to_put && 'before' === $position)
			{
//				print_html($element_to_stay, 'Wszedłem element_to_stay przed');
				$element_to_stay->outertext = $element_to_move_outertext.$element_to_stay->outertext;
//				print_html($element_to_stay, 'Wszedłem element_to_stay po');
			}
			else if ('outertext' === $where_to_put && 'after' === $position)
			{
				$element_to_stay->outertext = $element_to_stay->outertext.$element_to_move_outertext;
			}
			else if ('innertext' === $where_to_put && 'before' === $position)
			{
				$element_to_stay->innertext = $element_to_move_outertext.$element_to_stay->innertext;
			}
			else if ('innertext' === $where_to_put && 'after' === $position)
			{
				$element_to_stay->innertext = $element_to_stay->innertext.$element_to_move_outertext;
			}
		}
//		print_element($element_to_stay, 'element_to_stay po');
//		print_html($element_to_stay, 'element_to_stay po');
		return str_get_html($main_element->save());
	}

	function foreach_delete_element_array($main_element, $selectors_array)
	{
		$selectors_array = array_unique($selectors_array);
		$slectors_string = implode(', ', $selectors_array);
		foreach($main_element->find($slectors_string) as $element)
		{
			$element->outertext = '';
		}
		return str_get_html($main_element->save());
	}

	function check_string_contains_needle_from_array($string, $needles)
	{
		if (is_array($needles) && is_string($string))
		{
			foreach ($needles as $needle)
			{
				if (FALSE !== strpos($string, $needle))
				{
					return TRUE;
				}
			}
			return FALSE;
		}
		else
		{
			return FALSE;
		}
	}

	function replace_single_children($main_element, $tags_array, $excluded_classes, $excluded_ids)
	{
		foreach ($tags_array as $tag_pair)
		{
			$tag_pair_array = explode("=>", $tag_pair);
			if (2 === count($tag_pair_array))
			{
				$parent_tag = strtolower($tag_pair_array[0]);
				$child_tag = strtolower($tag_pair_array[1]);
				$changes_counter = 1;
				while (0 < $changes_counter)
				{
					$changes_counter=0;
					$main_element_str = $main_element->save();
					foreach($main_element->find($parent_tag) as $parent_element)
					{
						if (1 === count($parent_element->childNodes()))
						{
							$first_child = $parent_element->first_child();
							if ($child_tag === strtolower($first_child->tag))
							{
								if (!check_string_contains_needle_from_array($parent_element->id, $excluded_ids) && !check_string_contains_needle_from_array($parent_element->class, $excluded_classes))
								{
									$changes_counter++;
									$main_element_str = str_replace($parent_element->outertext, $first_child->outertext, $main_element_str);
								}
								else if (!check_string_contains_needle_from_array($first_child->id, $excluded_ids) && !check_string_contains_needle_from_array($first_child->class, $excluded_classes))
								{
									$changes_counter++;
									$main_element_str = str_replace($parent_element->innertext, $first_child->innertext, $main_element_str);
								}
							}
						}
					}
					$main_element = str_get_html($main_element_str);
				}
			}
		}
		return $main_element->save();
	}

	function insert_html($main_element, $element_selector, $outertext_before = '', $outertext_after = '', $innertext_before = '', $innertext_after = '')
	{
		foreach($main_element->find($element_selector) as $element)
		{
			if(strlen($outertext_before) > 0)
			{
				$element->outertext = $outertext_before.$element->outertext;
			}
			if(strlen($outertext_after) > 0) 
			{
				$element->outertext = $element->outertext.$outertext_after;
			}
			if(strlen($innertext_before) > 0) 
			{
				$element->innertext = $innertext_before.$element->innertext;
			}
			if(strlen($innertext_after) > 0)
			{
				$element->innertext = $element->outertext.$innertext_after;
			}
		}
		return str_get_html($main_element->save());
	}

	
	
	function my_get_html($url, $get_premium = FALSE)
	{
		$url = htmlspecialchars_decode($url);
		$url = htmlspecialchars_decode($url);
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

		$code = 0;
		$counter301 = 0;
		while ($code !== 200 && 2 > $counter301)
		{
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
			{
				$page_content = file_get_contents($url, false, $context);
			}
			$code = getHttpCode($http_response_header);
//			print_var_dump($http_response_header, "http_response_header");

			foreach($http_response_header as $header)
			{
				if (FALSE !== strpos($header, "Location: "))
				{
					$redirection_link = $header;
//					print_var_dump($redirection_link, "redirection_link");
					$redirection_link = str_replace("Location: ", "", $redirection_link);
					$redirection_link = str_replace("https:", "", $redirection_link);
					
					$redirection_link = remove_substring_if_exists_first($redirection_link, "https:");
					$redirection_link = "https:".$redirection_link;
//					print_var_dump($redirection_link, "redirection_link");
					$url = $redirection_link;
				}
			}
			if (301 == $code)
			{
				$counter301++;
			}
			else
			{
				break;
			}
		}
		$page_html = str_get_html($page_content);

		if (200 !== $code)
		{
			$html_error = createErrorContent($http_response_header);
//			print_var_dump($http_response_header, "http_response_header");
//			print_html($page_content, "page_content");
			$date = new DateTime("now", new DateTimeZone('Europe/Warsaw'));
			$date_string = date_format($date, 'Y-m-d H:i:s');
			$page_html = array(
				'uri' => $url,
				'title' => "Error ".$code.": ".$url,
				'timestamp' => $date_string,
				'content' => '<h1 class="error">Error:</h1>'.$html_error.'<br><br><h1 class="content">Content:</h1><br>'.$page_html
			);
		}

		$return_array = array(
			'code' => $code,
			'html' => $page_html,
			'url' => $url,
		);
		return $return_array;
	}
	

	function remove_empty_elements($main_element, $tag)
	{
		$main_element_str = $main_element->save();
		foreach($main_element->find($tag) as $empty_element)
		{
/*			print_html($empty_element->outertext, "empty_element->outertext");
			print_html(trim($empty_element->outertext), "trim(empty_element->outertext)");
			print_html($empty_element->innertext, "empty_element->innertext");
			print_html(trim($empty_element->innertext), "trim(empty_element->innertext)");
			print_html($empty_element->plaintext, "empty_element->plaintext");
			print_html(trim($empty_element->plaintext), "trim(empty_element->plaintext)");
			print_var_dump($empty_element->outertext, "empty_element->outertext");
			print_var_dump(trim($empty_element->outertext), "trim(empty_element->outertext)");
			print_var_dump($empty_element->innertext, "empty_element->innertext");
			print_var_dump(trim($empty_element->innertext), "trim(empty_element->innertext)");
			print_var_dump($empty_element->plaintext, "empty_element->plaintext");
			print_var_dump(trim($empty_element->plaintext), "trim(empty_element->plaintext)");
			hex_dump($empty_element->outertext, "empty_element->outertext");
			hex_dump(trim($empty_element->outertext), "trim(empty_element->outertext)");
			hex_dump($empty_element->innertext, "empty_element->innertext");
			hex_dump(trim($empty_element->innertext), "trim(empty_element->innertext)");
			hex_dump($empty_element->plaintext, "empty_element->plaintext");
			hex_dump(trim($empty_element->plaintext), "trim(empty_element->plaintext)");*/
			if (0 === strlen(trim($empty_element->innertext)) && 0 === strlen(trim($empty_element->plaintext)))
			{
				$main_element_str = str_replace($empty_element->outertext, "", $main_element_str);
			}
		}
		return str_get_html($main_element_str);
	}

	function getChangedTitle($title)
	{
		preg_match_all('/\(([^\) ]*)\)/', $title, $output_array);
		foreach($output_array[0] as $key => $value)
		{
			$title = str_replace($value, "[".$output_array[1][$key]."]", $title);
		}
		preg_match_all('/\[[^\]]*\]/', $title, $title_categories);
		foreach($title_categories[0] as $category)
		{
			$title = trim(str_replace($category, '', $title));
		}
		$title_prefixes = $title_categories[0];
		foreach($title_prefixes as $key => $title_prefix)
		{
			$title_prefixes[$key] = mb_strtoupper($title_prefix,"UTF-8");
		}
		$title_prefixes = array_unique($title_prefixes);
		$prefixes_combined = "";
		foreach($title_prefixes as $title_prefix)
		{
			$prefixes_combined = $title_prefix.$prefixes_combined;
		}
		while (FALSE !== strpos($title, '  '))
		{
			$title = str_replace('  ', ' ', $title);
		}
		$new_title = trim($prefixes_combined.' '.trim($title));
		return $new_title;
	}
	
	function replace_date($main_element, $date_element_selector, $date_published, $date_modified = NULL)
	{
		$main_element_str = $main_element->save();
		if(isset($date_published) && 1 < strlen($date_published))
		{
			if (FALSE === is_null($date_element = $main_element->find($date_element_selector, 0)))
			{
				if(isset($date_modified) && 1 < strlen($date_modified) && $date_published !== $date_modified)
				{
					$new_date_outertext = get_date_outertext($date_published, $date_modified);
				}
				else
				{
					$new_date_outertext = get_date_outertext($date_published, NULL);
				}
				$main_element_str = str_replace($date_element->outertext, $new_date_outertext, $main_element_str);
			}
		}
		return str_get_html($main_element_str);
	}
	
	function get_date_outertext($date_published, $date_modified = NULL)
	{
		if(isset($date_published) && 1 < strlen($date_published))
		{
			$new_date_outertext = '<DIV class="dates">';
			$text_published = localStrftime('%d %F %Y, %H:%M', strtotime($date_published));
			$text_published = "Data publikacji: ".$text_published;
			$new_date_outertext = $new_date_outertext.'<DIV class="date published">'.$text_published.'</DIV>';
			if(isset($date_modified) && 1 < strlen($date_modified) && $date_published !== $date_modified)
			{
				$text_modified = localStrftime('%d %F %Y, %H:%M', strtotime($date_modified));
				$text_modified = "Data aktualizacji: ".$text_modified;
				$new_date_outertext = $new_date_outertext.'<DIV class="date modified">'.$text_modified.'</DIV>';
			}
			$new_date_outertext = $new_date_outertext.'</DIV>';
		}
		return $new_date_outertext;
	}
	
	function localStrftime($format, $timestamp = 0)
	{
/*
		if($timestamp == 0)
		{
			// Sytuacja, gdy czas nie jest podany - używamy aktualnego.
			$timestamp = time();
		}
*/
		setlocale(LC_TIME, 'pl');
		// Nowy kod - %F dla odmienionej nazwy miesiąca
		if(strpos($format, '%F') !== false)
		{
			$mies = date('m', $timestamp);
			
			// odmienianie
			switch($mies)
			{
				case 1:
					$mies = 'stycznia';
					break;
				case 2:
					$mies = 'lutego';
					break;
				case 3:
					$mies = 'marca';
					break;
				case 4:
					$mies = 'kwietnia';
					break;
				case 5:
					$mies = 'maja';
					break;
				case 6:
					$mies = 'czerwca';
					break;
				case 7:
					$mies = 'lipca';
					break;
				case 8:
					$mies = 'sierpnia';
					break;
				case 9:
					$mies = 'września';
					break;
				case 10:
					$mies = 'października';
					break;
				case 11:
					$mies = 'listopada';
					break;
				case 12:
					$mies = 'grudnia';
					break;			
			}
			// dodawanie formatowania
			return strftime(str_replace('%F', $mies, $format), $timestamp);		
		}
		return strftime($format, $timestamp);	
	}

	function prepare_article($main_element, $page_url = NULL)
	{
//		print_var_dump(gettype($main_element), "gettype(main_element)");
//		print_element($main_element, 'main_element przed');
		fix_background_image($main_element, -1);
		$main_element = str_get_html($main_element->save());
		$main_element_str = $main_element->save();
		$main_element_str = str_replace('&nbsp;', ' ', $main_element_str);
		//to chyba to samo, to wyżej nie działa, to działa
		$main_element_str = str_replace("\xc2\xa0", ' ', $main_element_str);
		while (FALSE !== strpos($main_element_str, '  '))
		{
			$main_element_str = str_replace('  ', ' ', $main_element_str);
		}
		$main_element = str_get_html($main_element_str);
		$main_element = set_biggest_photo_size_from_sources($main_element);
		//$main_element = str_get_html($main_element->save());
		$main_element = set_biggest_photo_size_from_attribute($main_element, 'IMG[data-srcset]', 'data-srcset');
		$main_element = set_biggest_photo_size_from_attribute($main_element, 'IMG[data-src]', 'data-src');
		$main_element = set_biggest_photo_size_from_attribute($main_element, 'IMG[srcset]', 'srcset');
		$main_element = convert_amp_photos($main_element);
		$main_element = fix_all_photos_attributes($main_element);
		$main_element = convert_amp_frames_to_links($main_element);
		$main_element = convert_iframes_to_links($main_element);
		if (isset($page_url))
		{
			foreach ($main_element->find('IMG[src^="//"]') as $image_with_bad_source)
			{
				$img_src = $image_with_bad_source->getAttribute('src');
				$image_with_bad_source->setAttribute('src', 'https:'.$img_src);
			}
			$main_element = str_get_html($main_element->save());
			foreach ($main_element->find('IMG[src^="/"]') as $image_with_bad_source)
			{
				$img_src = $image_with_bad_source->getAttribute('src');
				$image_with_bad_source->setAttribute('src', $page_url.$img_src);
			}
			$main_element = str_get_html($main_element->save());
			foreach ($main_element->find('A[href^="//"]') as $link_with_bad_href)
			{
				$href = $link_with_bad_href->getAttribute('href');
				$link_with_bad_href->setAttribute('href', 'https:'.$href);
			}
			$main_element = str_get_html($main_element->save());
			foreach ($main_element->find('A[href^="/"]') as $link_with_bad_href)
			{
				$href = $link_with_bad_href->getAttribute('href');
				$link_with_bad_href->setAttribute('href', $page_url.$href);
			}
			$main_element = str_get_html($main_element->save());
		}
		$main_element = str_get_html($main_element->save());
//		print_element($main_element, 'main_element po');
		return $main_element->save();
	}

