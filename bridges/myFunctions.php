<?php

	function var_dump_print($variable, $name = "nazwa zmiennej", $prefix = "<br>")
	{
		echo $prefix;
		echo "Var dump zmiennej $name: <br><pre>"; var_dump($variable); echo "</pre>";
	}

	function html_print($variable, $name = "nazwa zmiennej", $prefix = "<br>")
	{
		echo $prefix;
		echo "Kod html zmiennej $name: <br><pre>".htmlspecialchars($variable)."</pre><br><br>";
	}

	function element_print($element, $name = "nazwa zmiennej", $prefix = "<br>")
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
//			'margin-bottom: 10px;'
		);
	}

	function getStylePhotoCaption()
	{
		return array(
//			'position: absolute;',
			'bottom: 0;',
			'left: 0;',
			'right: 0;',
			'text-align: center;',
			'color: #fff;',
//			'padding-top: 10px;',
			'padding-right: 10px;',
//			'padding-bottom: 10px;',
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

	function fixAmpArticles($article)
	{
		deleteAllDescendantsIfExist($article, 'amp-analytics');
		deleteAllDescendantsIfExist($article, 'amp-ad');
		deleteAllDescendantsIfExist($article, 'i-amphtml-sizer');
		deleteAllDescendantsIfExist($article, 'amp-image-lightbox');
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
			$photo_params = array('data-author', 'data-source');
			foreach($photo_params as $param_name)
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

	function formatAmpLinks($article)
	{
		foreach($article->find('amp-iframe') as $amp_iframe)
		{
			if(FALSE === is_null($src = ($amp_iframe->getAttribute('src'))))
			{
				$src = $amp_iframe->src;
				$amp_iframe->outertext = 
				'<strong><br>'
				.'<a href='.$src.'>'
				."Ramka - ".$src.'<br>'
				.'</a>'
				.'<br></strong>';
			}
		}
		foreach($article->find('amp-twitter') as $amp_twitter)
		{
			if(FALSE === is_null($data_tweetid = ($amp_twitter->getAttribute('data-tweetid'))))
			{
				$twitter_url = 'https://twitter.com/anyuser/status/'.$data_tweetid;
				$twitter_proxy_url = redirectUrl($twitter_url);
				$amp_twitter->outertext = 
					'<strong><br>'
					.'<a href='.$twitter_url.'>'
					."Ramka - ".$twitter_url.'<br>'
					.'</a>'
					.'<a href='.$twitter_proxy_url.'>'
					."Ramka - ".$twitter_proxy_url.'<br>'
					.'</a>'
					.'<br></strong>';
			}
		}
		foreach($article->find('amp-youtube') as $amp_youtube)
		{
			if(FALSE === is_null($data_videoid = ($amp_youtube->getAttribute('data-videoid'))))
			{
				$youtube_url = 'https://www.youtube.com/watch?v='.$data_videoid;
				$youtue_proxy_url = redirectUrl($youtube_url);
				$amp_youtube->outertext = 
					'<strong><br>'
					.'<a href='.$youtube_url.'>'
					."Ramka - ".$youtube_url.'<br>'
					.'</a>'
					.'<a href='.$youtue_proxy_url.'>'
					."Ramka - ".$youtue_proxy_url.'<br>'
					.'</a>'
					.'<br></strong>';
			}
		}
	}

	function clearParagraphsFromTaglinks($article, $paragrapghSearchString, $regexArray)
	{
//		echo "<br><br><br>";
		foreach($article->find($paragrapghSearchString) as $paragraph)
		{
//			echo "Paragraf przed: <br><pre>".htmlspecialchars($paragraph)."</pre><br>";
			foreach($paragraph->find('A') as $a_element)
			{
//				echo "a_element przed: <br><pre>".htmlspecialchars($a_element)."</pre><br>";
				foreach($regexArray as $regex)
				{
//					echo "Testowany regex: $regex, Testowany link: $a_element->href, <br>";
					if(1 === preg_match($regex, $a_element->href))
					{
						$paragraph->outertext = str_replace($a_element->outertext, $a_element->plaintext, $paragraph->outertext);
					}
				}
//				echo "a_element po: <br><pre>".htmlspecialchars($a_element)."</pre><br>";
			}
//			echo "Paragraf po: <br><pre>".htmlspecialchars($paragraph)."</pre><br>";
		}
	}
	
	function deleteAncestorIfContainsText($ancestor, $descendant_string)
	{
		if (FALSE === is_null($ancestor))
			if (FALSE !== strpos($ancestor->plaintext, $descendant_string))
				$ancestor->outertext = '';
	}
	
	function deleteAncestorIfContainsTextForEach($main, $ancestor_string, $descendant_string_array)
	{
		foreach($main->find($ancestor_string) as $ancestor)
		{
			foreach($descendant_string_array as $descendant_string)
			{
				if (FALSE !== strpos($ancestor->plaintext, $descendant_string))
				{
					$ancestor->outertext = '';
					break;
				}
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

	function deleteAncestorIfChildMatches($element, $hierarchy)
	{
		$last = count($hierarchy)-1;
		foreach($element->find($hierarchy[$last]) as $found)
		{
			$iterator = $last-1;
			while ($iterator >= 0 && strtolower($found->parent->tag) === strtolower($hierarchy[$iterator]))
			{
				$found = $found->parent;
				$iterator--;
			}
			if ($iterator === -1)
			{
				$found->outertext = '';
			}
		}
	}
	
	function getTwitterElement($twitter_url)
	{
		$twitter_proxy = 'nitter.net';
		$twitter_url = str_replace('twitter.com', $twitter_proxy, $twitter_url);
		$html_twitter = getSimpleHTMLDOM($twitter_url);
		$main_tweet = $html_twitter->find('DIV#m.main-tweet', 0);
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

	function deleteDescendantIfExists($ancestor, $descendant_string)
	{
		if (FALSE === is_null($descendant = $ancestor->find($descendant_string, 0)))
			$descendant->outertext = '';
	}
	
	function deleteAllDescendantsIfExist($ancestor, $descendant_string)
	{
		foreach($ancestor->find($descendant_string) as $descendant)
			$descendant->outertext = '';
	}

	function deleteAncestorIfDescendantExists($ancestor, $descendant_string)
	{
		if (FALSE === is_null($descendant = $ancestor->find($descendant_string, 0)))
			$ancestor->outertext = '';
	}

	function deleteAllAncestorsIfDescendantExists($main_element, $ancestor_string, $descendant_string)
	{
		foreach ($main_element->find($ancestor_string) as $ancestor_element)
		{
			if (FALSE === is_null($descendant_element = $ancestor_element->find($descendant_string, 0)))
				$ancestor_element->outertext = '';
		}
	}

	function replaceAllBiggerOutertextWithSmallerInnertext($main_element, $bigger_string, $smaller_string)
	{
		foreach($main_element->find($bigger_string) as $bigger_element)
		{
			if (FALSE === is_null($smaller_element = $bigger_element->find($smaller_string, 0)))
			{
				$bigger_element->outertext = $smaller_element->innertext;
			}
		}
	}

	function replaceAllBiggerOutertextWithSmallerOutertext($main_element, $bigger_string, $smaller_string)
	{
		foreach($main_element->find($bigger_string) as $bigger_element)
		{
			if (FALSE === is_null($smaller_element = $bigger_element->find($smaller_string, 0)))
			{
				$bigger_element->outertext = $smaller_element->outertext;
			}
		}
	}

	function replaceAllOutertextWithInnertext($main_element, $bigger_string)
	{
		foreach($main_element->find($bigger_string) as $bigger_element)
		{
			$bigger_element->outertext = $bigger_element->innertext;
		}
	}


	function redirectUrl($social_url)
	{
		$twitter_proxy = 'nitter.net';
		$instagram_proxy = 'bibliogram.art';
		$facebook_proxy = 'mbasic.facebook.com';
		$youtube_proxy = 'invidious.snopyta.org';
		$social_url = preg_replace('/.*[\.\/]twitter\.com(.*)/', 'https://'.$twitter_proxy.'${1}', $social_url);
		$social_url = preg_replace('/.*[\.\/]instagram\.com(.*)/', 'https://'.$instagram_proxy.'${1}', $social_url);
		$social_url = preg_replace('/.*[\.\/]facebook\.com(.*)/', 'https://'.$facebook_proxy.'${1}', $social_url);
		$social_url = preg_replace('/.*[\.\/]youtube\.com(.*)/', 'https://'.$youtube_proxy.'${1}', $social_url);
		return $social_url;
	}

	function returnTagsArray($article, $tag_selector)
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

	function returnAuthorsAsString($article, $author_selector)
	{
		$authors = '';
		foreach($article->find($author_selector) as $author_item)
		{
			$authors = $authors.', '.trim($author_item->plaintext);
		}
		$authors = substr_replace($authors, '', 0, strlen(', '));
		return $authors;
	}

	function addStyle($article_element, $search_string, $stylesArray)
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

	function removeSubstringIfExistsFirst($string, $substring)
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

	function removeSubstringIfExistsLast($string, $substring)
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
	

	function fix_article_photos($article, $str_selectror_photo_wrapper, $is_main = FALSE, $str_photo_url_attribute = 'src', $str_selectror_photo_caption = '')
	{
		foreach($article->find($str_selectror_photo_wrapper) as $old_photo_wrapper)
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
//					$caption_text = trim($caption_element->innertext);
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
				if($old_photo_element->hasAttribute('alt'))
				{
					if (TRUE !== ($img_alt = $old_photo_element->getAttribute('alt')))
					{
						if (0 !== strlen($img_alt))
						{
							$new_element_img->setAttribute('alt', $img_alt);
						}
					}
				}

				if($old_photo_element->hasAttribute('title'))
				{
					if (TRUE !== ($img_title = $old_photo_element->getAttribute('title')))
					{
						if (0 !== strlen($img_title))
						{
							$new_element_img->setAttribute('title', $img_title);
						}
					}
				}
				$old_photo_wrapper->outertext = $new_photo_wrapper;
			}
		}
	}

	function getTextPlaintext($article, $selector_string, $backup_value = "Tekst zapasowy")
	{
		if (FALSE === is_null($text_element = $article->find($selector_string, 0)))
			return trim($text_element->plaintext);
		else
			return $backup_value;
	}

	function getTextAttribute($article, $selector_string, $attribute_name, $backup_value = "Tekst zapasowy")
	{
		if (FALSE === is_null($element_selected = $article->find($selector_string, 0)))
		{
			if($element_selected->hasAttribute($attribute_name))
			{
				$attribute = $element_selected->getAttribute($attribute_name);
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

