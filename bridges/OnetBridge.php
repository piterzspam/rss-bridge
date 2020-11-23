<?php
class OnetBridge extends BridgeAbstract {
	const NAME = 'Onet Autor';
	const URI = 'https://wiadomosci.onet.pl/autorzy/';
	const DESCRIPTION = 'No description provided';
	const MAINTAINER = 'No maintainer';
	const CACHE_TIMEOUT = 3600; // Can be omitted!

	const PARAMETERS = array
	(
		'Tekst pogrubiony' => array
		(
			'url' => array
			(
				'name' => 'URL',
				'type' => 'text',
				'required' => true
			),
			'wanted_number_of_articles' => array
			(
				'name' => 'Liczba artykułów',
				'type' => 'text',
				'required' => true
			),
		)
	);

	
	public function collectData()
	{
		$url_articles_list = $this->getInput('url');
		$url_articles_list = preg_replace('/(.*\/autorzy\/[a-z]+-[a-z]+).*/', '$1', $url_articles_list);
		$GLOBALS['number_of_wanted_articles'] = $this->getInput('wanted_number_of_articles');

		$urls = array();
		$page_number = 0;
//		$all_articles_time = 0;
//		$all_articles_counter = 0;
		while (count($urls) < $GLOBALS['number_of_wanted_articles'])
		{
			$current_url = $url_articles_list.'?ajax=1&page='.$page_number;
			$html_articles_list = getSimpleHTMLDOM($current_url);
			$this->deleteAllDescendantsIfExist($html_articles_list, 'DIV.breadcrumbs');

			if (0 !== ($url_counter = count($found_urls = $html_articles_list->find("DIV.listItem A[href][title]"))))
			{
				foreach($found_urls as $article__link)
				{
					if (count($urls) < $GLOBALS['number_of_wanted_articles'])
					{
						$url = $article__link->getAttribute('href');
						$amp_url = $url.'.amp?amp_js_v=0.1';
						$amp_url = str_replace('https://', 'https://wiadomosci-onet-pl.cdn.ampproject.org/v/s/', $amp_url);
						$urls[] = $amp_url;
					}
				}
				$page_number++;
			}
			else
			{
				break;
			}
		}
		foreach($urls as $url_article_link)
		{
//			$start_request = microtime(TRUE);
			$article_html = getSimpleHTMLDOMCached($url_article_link, (864000/(count($this->items)+1)*$GLOBALS['number_of_wanted_articles']));
//			$end_request = microtime(TRUE);
//			echo "<br>Article  took " . ($end_request - $start_request) . " seconds to complete - url: $url_article_link.";
//			$all_articles_counter++;
//			$all_articles_time = $all_articles_time + $end_request - $start_request;
			if (is_bool($article_html))
			{
				$this->items[] = array(
					'uri' => $url_article_link,
					'title' => "getSimpleHTMLDOM($url_article_link) jest booleml",
					'timestamp' => '',
					'author' => '',
					'content' => $article_html,
					'categories' => ''
				);
				continue;
			}
			$article = $article_html->find('article', 0);
			$article_data = $article_html->find('SCRIPT[type="application/ld+json"]', 0)->innertext;
			$article_data_parsed = $this->parse_article_data(json_decode($article_data));
			$date = trim($article_data_parsed["datePublished"]);
			$title = trim($article_data_parsed["headline"]);
			$author = trim($article_data_parsed["author"]["name"]);
//			echo "<br><br><br>article_data_parsed:<pre>";var_dump($article_data_parsed);echo "</pre>";
			foreach($article->find('amp-img') as $ampimg)
				$ampimg->tag = "img";
			foreach($article->find('amp-img, img') as $photo_element)
			{
				if(isset($photo_element->width)) $photo_element->width = NULL;
				if(isset($photo_element->height)) $photo_element->height = NULL;
			}
			$this->deleteAllDescendantsIfExist($article, 'amp-analytics');
			$this->deleteAllDescendantsIfExist($article, 'amp-ad');
			$this->deleteAllDescendantsIfExist($article, 'i-amphtml-sizer');
			$this->deleteAllDescendantsIfExist($article, 'amp-image-lightbox');
			$this->deleteAllDescendantsIfExist($article, 'comment');
			$this->deleteAllDescendantsIfExist($article, 'script');
			$this->deleteAllDescendantsIfExist($article, 'DIV.social-box');
			$this->deleteAllDescendantsIfExist($article, 'DIV[style="margin:auto;width:300px;"]');
//https://wiadomosci-onet-pl.cdn.ampproject.org/v/s/wiadomosci.onet.pl/tylko-w-onecie/wybory-w-usa-2020-andrzej-stankiewicz-dzis-jest-czas-wielkiej-smuty-w-pis/fcktclw.amp?amp_js_v=0.1
//Gliński tłumaczy się kryteriami obiektywnymi.
			$this->clearParagraphsFromTaglinks($article, 'P.hyphenate', array('/onet\.pl\/[^\/]*$/'));
			$this->deleteAncestorIfChildMatches($article, array('ul', 'li', 'A[href*="onet.pl"][target="_top"]'));
			
			foreach($article->find('P.hyphenate') as $paragraph)
			{
				$this->deleteAncestorIfContainsText($paragraph, 'Poniżej lista wszystkich dotychczasowych odcinków podcastu:');
				$this->deleteAncestorIfContainsText($paragraph, 'Cieszymy się, że jesteś z nami. Zapisz się na newsletter Onetu, aby otrzymywać od nas najbardziej wartościowe treści');
			}

			foreach($article->find('LI') as $li)
			{
				$this->deleteAncestorIfContainsText($li, 'Więcej informacji i podcastów znajdziesz na stronie głównej Onet.pl');
			}
			$this->formatAampLinks($article);


/*			foreach ($article->find('A') as $a_element)
			{
				echo htmlspecialchars($a_element->outertext)."<br><br>";
			}
*/
			$this->items[] = array(
				'uri' => $url_article_link,
				'title' => $title,
				'timestamp' => $date,
				'author' => $author,
				'content' => $article
			);
		}
//		echo "<br>Wszystkie $all_articles_counter artykulow zajelo $all_articles_time, <br>średnio ".$all_articles_time/$all_articles_counter ."<br>";
	}

	private function formatAampLinks($article)
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
				$twitter_proxy_url = $this->redirectUrl($twitter_url);
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
				$youtue_proxy_url = $this->redirectUrl($youtube_url);
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

	private function clearParagraphsFromTaglinks($article, $paragrapghSearchString, $regexArray)
	{
		foreach($article->find($paragrapghSearchString) as $paragraph)
			foreach($paragraph->find('A') as $a_element)
				foreach($regexArray as $regex)
					if(1 === preg_match($regex, $a_element->href))
						$a_element->outertext = $a_element->plaintext;
	}
	
	private function deleteAncestorIfContainsText($ancestor, $descendant_string)
	{
		if (FALSE === is_null($ancestor))
			if (FALSE !== strpos($ancestor->plaintext, $descendant_string))
				$ancestor->outertext = '';
	}

	private function parse_article_data($article_data)
	{
		if (TRUE === is_object($article_data))
		{
			$article_data = (array)$article_data;
			foreach ($article_data as $key => $value)
				$article_data[$key] = $this->parse_article_data($value);
			return $article_data;
		}
		else if (TRUE === is_array($article_data))
		{
			foreach ($article_data as $key => $value)
				$article_data[$key] = $this->parse_article_data($value);
			return $article_data;
		}
		else
			return $article_data;
	}
	private function deleteDescendantIfExists($ancestor, $descendant_string)
	{
		if (FALSE === is_null($descendant = $ancestor->find($descendant_string, 0)))
			$descendant->outertext = '';
	}

	private function deleteAncestorIfDescendantExists($ancestor, $descendant_string)
	{
		if (FALSE === is_null($descendant = $ancestor->find($descendant_string, 0)))
			$ancestor->outertext = '';
	}

	private function deleteAllDescendantsIfExist($ancestor, $descendant_string)
	{
		foreach($ancestor->find($descendant_string) as $descendant)
			$descendant->outertext = '';
	}

	private function redirectUrl($social_url)
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

	private function deleteAncestorIfChildMatches($element, $hierarchy)
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
}