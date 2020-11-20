<?php
class GazetaprawnaBridge extends BridgeAbstract {
	const NAME = 'Gazetaprawna.pl - Strona autora';
	const URI = 'https://www.gazetaprawna.pl/';
	const DESCRIPTION = 'No description provided';
	const MAINTAINER = 'No maintainer';
	const CACHE_TIMEOUT = 3600; // Can be omitted!

	const PARAMETERS = array
	(
		'Parametry' => array
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
		$GLOBALS['number_of_wanted_articles'] = $this->getInput('wanted_number_of_articles');
		$url_articles_list = $this->getInput('url');
		$url_articles_list = preg_replace('/(.*\/autor\/[0-9]+,([a-z]+)-([a-z]+)).*/', '$1', $url_articles_list);


		while (count($this->items) < $GLOBALS['number_of_wanted_articles'])
		{
			$html_articles_list = getSimpleHTMLDOM($url_articles_list);
			if (0 !== count($found_urls = $html_articles_list->find('DIV.whiteListArt', 0)->find('H3')))
			{
				foreach($found_urls as $h3_element)
				{
					if (count($this->items) < $GLOBALS['number_of_wanted_articles'])
					{
						//link to articles
						$a_element = $h3_element->find('a', 0);
						$url_article_link = $a_element->href;
						$amp_url = preg_replace('/.*\/artykuly\/(.*)/', "https://www.gazetaprawna.pl/amp/$1", $url_article_link);
//						echo "amp_url: $amp_url<br>";
						$GLOBALS['is_article_free'] = $this->isArticleFree($h3_element);
						$GLOBALS['is_article_opinion'] = $this->isArticleOpinion($h3_element);
						if ($this->meetsConditions() === TRUE && count($this->items) < $GLOBALS['number_of_wanted_articles'])
						{
							$this->addArticle($amp_url);
						}
					}
				}
			}
			else
			{
				break;
			}
			$url_articles_list = $html_articles_list->find('A[title="następna"]', 0)->getAttribute('href');
		}
	}

	private function addArticle($amp_url)
	{
		$article_html = getSimpleHTMLDOMCached($amp_url, (864000/(count($this->items)+1)*$GLOBALS['number_of_wanted_articles']));

//		echo "url_article_link: $url<br>";
		$article = $article_html->find('article', 0);
		$article_data = $article_html->find('SCRIPT[type="application/ld+json"]', 0)->innertext;
		$article_data_parsed = $this->parse_article_data(json_decode($article_data));
		$date = trim($article_data_parsed["datePublished"]);
		$title = trim($article_data_parsed["headline"]);
		$author = trim($article_data_parsed["author"]["0"]["name"]);

		if ($GLOBALS['is_article_opinion'])
			$title = '[OPINIA] '.str_replace('[OPINIA]', '', $title);

		if ($GLOBALS['is_article_free'])
			$title = '[FREE] '.$title;
		else
			$title = '[PREMIUM] '.$title;
//		echo "<br><br><br>article_data_parsed:<pre>";var_dump($article_data_parsed);echo "</pre>";

		//tags
		$tags = array();
		foreach($article->find('DIV.tags', 0)->find('A[href*="/tagi/"]') as $tag_link)
			$tags[] = trim($tag_link->plaintext);

		$this->deleteAllDescendantsIfExist($article, 'comment');
		$this->deleteAllDescendantsIfExist($article, 'script');
		$this->deleteAllDescendantsIfExist($article, 'DIV.w2g');
		$this->deleteAllDescendantsIfExist($article, 'DIV.articleNextPrev');
		$this->deleteAllDescendantsIfExist($article, 'UL.psav-author-ul');
		$this->deleteAllDescendantsIfExist($article, 'DIV.widget-psav-share-box');
		$this->deleteAllDescendantsIfExist($article, 'A.arrowLink');
		$this->addStyle($article, 'P.pytanie', array('font-weight: bold;'));
		$this->addStyle($article, 'DIV.articleReadMoreHead', array('font-weight: bold;'));


		foreach($article->find('amp-img') as $ampimg)
			$ampimg->tag = "img";

		foreach($article->find('amp-img, img') as $photo_element)
		{
			if(isset($photo_element->width)) $photo_element->width = NULL;
			if(isset($photo_element->height)) $photo_element->height = NULL;
		}

		$this->items[] = array(
			'uri' => $amp_url,
			'title' => $title,
			'timestamp' => $date,
			'author' => $author,
			'content' => $article,
			'categories' => $tags
		);
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

	private function addStyle($article_element, $search_string, $stylesArray)
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

	private function isArticleFree($h3_element)
	{
		if ($h3_element->class === "open")
			return TRUE;
		else if ($h3_element->class === "gold")
			return FALSE;
		else 
			return FALSE;
	}

	private function isArticleOpinion($h3_element)
	{
		$title = $h3_element->plaintext;
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
