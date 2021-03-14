<?php
class DziennikBridge extends BridgeAbstract {
	const NAME = 'Dziennik.pl - Strona autora';
	const URI = 'https://www.dziennik.pl/';
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
		$url_articles_list = $this->getInput('url');
		$GLOBALS['limit'] = $this->getInput('limit');

		$titles = array();
		while (count($this->items) < $GLOBALS['limit'])
		{
			$html_articles_list = getSimpleHTMLDOM($url_articles_list);
			if (0 !== count($found_urls = $html_articles_list->find('DIV.boxArticleList', 0)->find('A[href][title]')))
			{
				foreach($found_urls as $article_link)
				{
					if (count($this->items) < $GLOBALS['limit'])
					{
						$title = $article_link->getAttribute('title');
						$href = $article_link->getAttribute('href');
						$href = $href.".amp";
//						$article_html = getSimpleHTMLDOMCached($href, (86400/(count($this->items)+1)*$GLOBALS['limit']));
						$article_html = getSimpleHTMLDOMCached($href, 86400 * 14);
						$GLOBALS['is_article_free'] = $this->isArticleFree($article_html);
						$GLOBALS['is_article_opinion'] = $this->isArticleOpinion($article_html);
						if ($this->meetsConditions() === TRUE && FALSE === in_array($title, $titles))
						{
							$titles[] = $title;
							$this->addArticle($href, $article_html);
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

	private function addArticle($href, $article_html)
	{
		$article = $article_html->find('article', 0);
		$article_data = $article_html->find('SCRIPT[type="application/ld+json"]', 0)->innertext;
		$article_data_parsed = $this->parse_article_data(json_decode($article_data));
		
		$date = trim($article_data_parsed["datePublished"]);
		$title = trim($article_data_parsed["headline"]);
		$author = trim($article_data_parsed["author"]["name"]);

		$this->deleteAllDescendantsIfExist($article, 'comment');
		$this->deleteAllDescendantsIfExist($article, 'script');
		$this->deleteAllDescendantsIfExist($article, 'DIV.social-box');
		$this->deleteAllDescendantsIfExist($article, 'DIV#lightbox');
		$this->deleteAllDescendantsIfExist($article, 'DIV#lightbox2');
		$this->deleteAllDescendantsIfExist($article, 'DIV.adBoxTop');
		$this->deleteAllDescendantsIfExist($article, 'DIV.adBox');
		$this->deleteAllDescendantsIfExist($article, 'DIV.widget.video');
		$this->deleteAllDescendantsIfExist($article, 'amp-analytics');
		$this->deleteAllDescendantsIfExist($article, 'DIV.listArticle');
		$this->clearParagraphsFromTaglinks($article, 'P.hyphenate', array('/dziennik\.pl\/tagi\//'));

		foreach($article->find('amp-img') as $ampimg)
			$ampimg->tag = "img";
		foreach($article->find('amp-img, img') as $photo_element)
		{
			if(isset($photo_element->width)) $photo_element->width = NULL;
			if(isset($photo_element->height)) $photo_element->height = NULL;
		}

		if ($GLOBALS['is_article_opinion'])
		{
			$title = str_replace('[OPINIA]', '', $title);
			$title = '[OPINIA] '.$title;
		}

		if ($GLOBALS['is_article_free'])
			$title = '[FREE] '.$title;
		else
			$title = '[PREMIUM] '.$title;

		$this->items[] = array(
			'uri' => $href,
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
	

	private function meetsConditions()
	{
		$only_opinions = $this->getInput('tylko_opinie');
		if (TRUE === is_null($only_opinions)) $only_opinions = FALSE;
		$only_free = $this->getInput('tylko_darmowe');
		if (TRUE === is_null($only_free)) $only_free = FALSE;

		if(FALSE === $only_opinions && FALSE === $only_free)
		{
			return TRUE;
		}
		else if(FALSE === $only_opinions && TRUE === $only_free)
		{
			if ($GLOBALS['is_article_free'])
				return TRUE;
		}
		else if(TRUE === $only_opinions && FALSE === $only_free)
		{
			if ($GLOBALS['is_article_opinion'])
				return TRUE;
		}
		else if(TRUE === $only_opinions && TRUE === $only_free)
		{
			if ($GLOBALS['is_article_opinion'] && $GLOBALS['is_article_free'])
				return TRUE;
		}
		else
		{
			return FALSE;
		}
	}

	private function isArticleFree($article_html)
	{
		$article = $article_html->find('ARTICLE', 0);
		//Jeżeli element istneje (FALSE === is_null), to jest to artykul platny
		if (is_null($article->find('A[id][href*="edgp.gazetaprawna.pl"]', 0)))
			return TRUE;
		else
			return FALSE;	
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
