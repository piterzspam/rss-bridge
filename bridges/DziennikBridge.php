<?php
class DziennikBridge extends BridgeAbstract {
	const NAME = 'Dziennik.pl - Strona autora';
	const URI = 'https://www.dziennik.pl/';
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
		$url_articles_list = $this->getInput('url');
		$GLOBALS['number_of_wanted_articles'] = $this->getInput('wanted_number_of_articles');

		while (count($this->items) < $GLOBALS['number_of_wanted_articles'])
		{
			$html_articles_list = getSimpleHTMLDOM($url_articles_list);
			if (0 !== count($found_urls = $html_articles_list->find('DIV.boxArticleList', 0)->find('A[href][title]')))
			{
				foreach($found_urls as $article_link)
				{
					$title = $article_link->getAttribute('title');
					$href = $article_link->getAttribute('href');

					if ($this->meetsConditions($title, $href) === TRUE && count($this->items) < $GLOBALS['number_of_wanted_articles'])
					{
						$this->addArticle($href);
					}
				}
			}
			else
			{
				break;
			}
			$url_articles_list = $html_articles_list->find('A.next', 0)->getAttribute('href');
		}

//		echo 'urls:<br>';
//		echo '<pre>'.var_export($urls, true).'</pre>';
	}

	private function addArticle($url_article)
	{
		$article_html = getSimpleHTMLDOMCached($url_article, (864000/(count($this->items)+1)*$GLOBALS['number_of_wanted_articles']));
		$article = $article_html->find('ARTICLE.articleDetail', 0);

		//date
		$article_data = $article_html->find('SCRIPT[type="application/ld+json"]', 0)->innertext;
		$json_decoded = (array)json_decode($article_data);
		$date = $json_decoded['datePublished'];
		//author
		$author = $article_html->find('SPAN[itemprop="name"]', 0)->plaintext;
		//title
		$title = $article_html->find('H1.mainTitle', 0)->plaintext;
		//tags
		$tags = array();
		foreach($article->find('DIV.relatedTopicWrapper', 0)->find('A') as $tag_element)
		{
			$tags[] = trim($tag_element->getAttribute('title'));
		}


		$this->deleteAllDescendantsIfExist($article, 'comment');
		$this->deleteAllDescendantsIfExist($article, 'script');
		$this->deleteAllDescendantsIfExist($article, 'DIV.social-container');
		$this->deleteAllDescendantsIfExist($article, 'DIV.infor-ad');
		$this->deleteAllDescendantsIfExist($article, 'DIV.bottomAdsBox');
		$this->deleteAllDescendantsIfExist($article, 'DIV#googleAdsCont');
		$this->deleteAllDescendantsIfExist($article, 'DIV#relatedTopics');
		$this->deleteAllDescendantsIfExist($article, 'DIV.detailAllBoxes');
		$this->deleteAllDescendantsIfExist($article, 'FIGURE.seeAlso');
		$this->deleteAllDescendantsIfExist($article, 'DIV.commentsBox');
		$this->deleteAllDescendantsIfExist($article, 'DIV.nextClick-article');
		$this->deleteAllDescendantsIfExist($article, 'DIV.streamNews');
		$this->deleteAllDescendantsIfExist($article, 'DIV#banner_art_video_out');
		$this->deleteAllDescendantsIfExist($article, 'DIV#widgetStop');
		$this->deleteAllDescendantsIfExist($article, 'DIV.videoScrollClass');
		$this->deleteAllDescendantsIfExist($article, 'DIV.frameWrap');
		$this->deleteAllDescendantsIfExist($article, 'DIV#adoceangplyncgfnrlgo');

		foreach($article->find('P.hyphenate  A[id][href*="/tagi/"][title]') as $paragraph)
		{
			$paragraph->parent->innertext = $paragraph->parent->plaintext;
		}
		if ($this->isArticleOpinion($title))
		{
			$title = str_replace('[OPINIA]', '', $title);
			$title = '[OPINIA] '.$title;
		}
		if (FALSE === $this->isArticleFree($url_article))
		{
			$title = '[PREMIUM] '.$title;
		}

		$this->items[] = array(
			'uri' => $url_article,
			'title' => $title,
			'timestamp' => $date,
			'author' => $author,
			'content' => $article,
			'categories' => $tags
		);
	}

	private function meetsConditions($title, $url_article)
	{
		$only_opinions = $this->getInput('tylko_opinie');
		$only_free = $this->getInput('tylko_darmowe');

		if(FALSE === $only_opinions && FALSE === $only_free)
			return TRUE;
		else if(FALSE === $only_opinions && TRUE === $only_free)
		{
			if ($this->isArticleFree($url_article))
				return TRUE;
		}
		else if(TRUE === $only_opinions && FALSE === $only_free)
		{
			if ($this->isArticleOpinion($title))
				return TRUE;
		}
		else if(TRUE === $only_opinions && TRUE === $only_free)
		{
			if ($this->isArticleOpinion($title) && $this->isArticleFree($url_article))
				return TRUE;
		}
		return FALSE;
	}

	private function isArticleFree($url_article)
	{
		$article_html = getSimpleHTMLDOMCached($url_article, (864000/(count($this->items)+1)*$GLOBALS['number_of_wanted_articles']));
		$article = $article_html->find('ARTICLE.articleDetail', 0);
		//Jeżeli element istneje (FALSE === is_null), to jest to artykul platny
		if (is_null($article->find('A[id][href*="edgp.gazetaprawna.pl"]', 0)))
			return TRUE;
		else
			return FALSE;	
	}
	private function isArticleOpinion($title)
	{
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