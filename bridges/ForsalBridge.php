<?php
class ForsalBridge extends BridgeAbstract {
	const NAME = 'Forsal.pl - Strona autora';
	const URI = 'https://forsal.pl/';
	const DESCRIPTION = 'No description provided';
	const MAINTAINER = 'No maintainer';
	const CACHE_TIMEOUT = 86400; // Can be omitted!

	const PARAMETERS = array
	(
		'Parametry' => array
		(
			'url' => array
			(
				'name' => 'URL',
				'type' => 'text',
				'required' => true,
				'defaultValue' => 3,
			),
			'limit' => array
			(
				'name' => 'Liczba artykułów',
				'type' => 'number',
				'required' => true
			),
			'tylko_opinie' => array
			(
				'name' => 'Tylko opinie',
				'type' => 'checkbox',
				'required' => false,
				'title' => 'Tylko opinie'
			),
			'tylko_darmowe' => array
			(
				'name' => 'Tylko darmowe',
				'type' => 'checkbox',
				'required' => false,
				'title' => 'Tylko darmowe'
			),
		)
	);
/*
	public function getIcon()
	{
		return 'https://c.disquscdn.com/uploads/forums/349/4323/favicon.png';
	}
*/
	public function collectData()
	{
		$GLOBALS['limit'] = $this->getInput('limit');
		$url_articles_list = $this->getInput('url');
		$url_articles_list = preg_replace('/(.*\/autor\/[0-9]+,([a-z]+)-([a-z]+)).*/', '$1', $url_articles_list);

		while (count($this->items) < $GLOBALS['limit'])
		{
			$html_articles_list = getSimpleHTMLDOM($url_articles_list);
			if (0 !== count($found_urls = $html_articles_list->find('DIV.boxArticleList', 0)->find('A[href][title]')))
			{
				foreach($found_urls as $a_element)
				{
					if (count($this->items) < $GLOBALS['limit'])
					{
						//link to articles
						$url_article_link = $a_element->href;
						$url_article_link = $url_article_link.".amp";
//						$article_html = getSimpleHTMLDOMCached($url_article_link, (86400/(count($this->items)+1)*$GLOBALS['limit']));
						$article_html = getSimpleHTMLDOMCached($url_article_link, 86400 * 14);
						
						$GLOBALS['is_article_free'] = $this->isArticleFree($article_html);
						$GLOBALS['is_article_opinion'] = $this->isArticleOpinion($article_html);
						if (TRUE === $this->meetsConditions($article_html))
						{
//							echo "<br>url_article_link: $url_article_link";
							$this->addArticle($url_article_link, $article_html);
						}
					}
				}
			}
			else
			{
				break;
			}
			$url_articles_list = $html_articles_list->find('A.next', 0)->getAttribute('href');
		}
	}

	private function hex_dump($data, $newline="\n")
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
	private function addArticle($url_article_link, $article_html)
	{
		$article = $article_html->find('article', 0);
		$article_data = $article_html->find('SCRIPT[type="application/ld+json"]', 0)->innertext;
		$article_data = str_replace('"width": ,'."\n", '"width": "",'."\n", $article_data);
		$article_data = str_replace('"height": '."\n", '"height": ""'."\n", $article_data);

		$article_data_parsed = $this->parse_article_data(json_decode($article_data));
		$date = trim($article_data_parsed["datePublished"]);
		$title = trim($article_data_parsed["headline"]);
		$author = trim($article_data_parsed["author"]["name"]);

		if ($GLOBALS['is_article_opinion'])
			$title = '[OPINIA] '.str_replace('[OPINIA]', '', $title);

		if ($GLOBALS['is_article_free'])
			$title = '[FREE] '.$title;
		else
			$title = '[PREMIUM] '.$title;
		foreach($article->find('amp-img') as $ampimg)
			$ampimg->tag = "img";

		foreach($article->find('amp-img, img') as $photo_element)
		{
			if(isset($photo_element->width)) $photo_element->width = NULL;
			if(isset($photo_element->height)) $photo_element->height = NULL;
		}
		$this->deleteAllDescendantsIfExist($article, 'comment');
		$this->deleteAllDescendantsIfExist($article, 'DIV.social-box');
		$this->deleteAllDescendantsIfExist($article, 'amp-image-lightbox');
		$this->deleteAllDescendantsIfExist($article, 'DIV.adBoxTop');
		$this->deleteAllDescendantsIfExist($article, 'DIV.adBox');
		$this->deleteAllDescendantsIfExist($article, 'DIV.widget.video');
		$this->clearParagraphsFromTaglinks($article, 'P.hyphenate', array('/forsal\.pl\/tagi\//'));


		$this->items[] = array(
			'uri' => $url_article_link,
			'title' => $title,
			'timestamp' => $date,
			'author' => $author,
			'content' => $article
		);
	}
	private function clearParagraphsFromTaglinks($article, $paragrapghSearchString, $regexArray)
	{
		foreach($article->find($paragrapghSearchString) as $paragraph)
			foreach($paragraph->find('A') as $a_element)
				foreach($regexArray as $regex)
					if(1 === preg_match($regex, $a_element->href))
						$a_element->outertext = $a_element->plaintext;
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

	private function meetsConditions($article_html)
	{
		$only_opinions = $this->getInput('tylko_opinie');
		$only_free = $this->getInput('tylko_darmowe');

		if(FALSE === $only_opinions && FALSE === $only_free)
			return TRUE;
		else if(FALSE === $only_opinions && TRUE === $only_free)
			if ($GLOBALS['is_article_free'])
				return TRUE;
		else if(TRUE === $only_opinions && FALSE === $only_free)
			if ($GLOBALS['is_article_opinion'])
				return TRUE;
		else if(TRUE === $only_opinions && TRUE === $only_free)
			if ($GLOBALS['is_article_opinion'] && $GLOBALS['is_article_free'])
				return TRUE;
		else
			return FALSE;
	}

	private function isArticleFree($article_html)
	{
		//Jeżeli element istneje (FALSE === is_null), to jest to artykul platny
		$premium_element = $article_html->find('A[href*="edgp.gazetaprawna.pl"]', 0);
		if (FALSE === is_null($premium_element))
			return FALSE;
		else
			return TRUE;
	}

	private function isArticleOpinion($article_html)
	{
		$title = $article_html->find('H1.headline', 0)->plaintext;
		if (FALSE !== strpos($title, '[OPINIA]'))
			return TRUE;
		else
			return FALSE;
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

	private function deleteAncestorIfContainsText($ancestor, $descendant_string)
	{
		if (FALSE === is_null($ancestor))
			if (FALSE !== strpos($ancestor->plaintext, $descendant_string))
				$ancestor->outertext = '';
	}

	private function deleteAllDescendantsIfExist($ancestor, $descendant_string)
	{
		foreach($ancestor->find($descendant_string) as $descendant)
			$descendant->outertext = '';
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

	private function redirectUrl($url)
	{
		$twitter_proxy = 'nitter.net';
		$instagram_proxy = 'bibliogram.art';
		$facebook_proxy = 'mbasic.facebook.com';
		$url = preg_replace('/.*[\.\/]twitter\.com(.*)/', 'https://'.$twitter_proxy.'${1}', $url);
		$url = preg_replace('/.*[\.\/]instagram\.com(.*)/', 'https://'.$instagram_proxy.'${1}', $url);
		$url = preg_replace('/.*[\.\/]facebook\.com(.*)/', 'https://'.$facebook_proxy.'${1}', $url);
		return $url;
	}
}
