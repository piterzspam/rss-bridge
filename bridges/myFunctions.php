<?php

	function var_dump_print ($variable)
	{
		echo "Zmienna: <br><pre>"; var_dump($variable); echo "</pre>";
	}
	
	function fixAmpArticles($article)
	{
		foreach($article->find('amp-img') as $ampimg)
			$ampimg->tag = "img";
		foreach($article->find('amp-img, img') as $photo_element)
		{
			if(isset($photo_element->width)) $photo_element->width = NULL;
			if(isset($photo_element->height)) $photo_element->height = NULL;
		}
		deleteAllDescendantsIfExist($article, 'amp-analytics');
		deleteAllDescendantsIfExist($article, 'amp-ad');
		deleteAllDescendantsIfExist($article, 'i-amphtml-sizer');
		deleteAllDescendantsIfExist($article, 'amp-image-lightbox');
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
		foreach($article->find($paragrapghSearchString) as $paragraph)
			foreach($paragraph->find('A') as $a_element)
				foreach($regexArray as $regex)
					if(1 === preg_match($regex, $a_element->href))
						$a_element->outertext = $a_element->plaintext;
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
		$counter = 0;
		foreach($element->find($hierarchy[$last]) as $found)
		{			
			$counter++;
			$iterator = $last-1;
			while ($iterator >= 0 && $found->parent->tag === $hierarchy[$iterator])
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
			$tags[] = trim($tags_item->plaintext);
		}
		return $tags;
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
			$element->style = $element->style.$styleString;
		}
	}
