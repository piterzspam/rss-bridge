<?php
class ForsalBridge extends BridgeAbstract {
	const NAME = 'Forsal.pl - Strona autora';
	const URI = 'https://forsal.pl/';
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
			if (0 !== count($found_urls = $html_articles_list->find('DIV.boxArticleList', 0)->find('A[href][title]')))
			{
				foreach($found_urls as $a_element)
				{
					if (count($this->items) < $GLOBALS['number_of_wanted_articles'])
					{
						//link to articles
						$url_article_link = $a_element->href;
						if ($this->meetsConditions($url_article_link) === TRUE && count($this->items) < $GLOBALS['number_of_wanted_articles'])
						{
//							echo "<br>url_article_link: $url_article_link";
							$this->addArticle($url_article_link);
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

	private function addArticle($url_article_link)
	{
		$article_html = getSimpleHTMLDOMCached($url_article_link, (864000/(count($this->items)+1)*$GLOBALS['number_of_wanted_articles']));
		$article = $article_html->find('ARTICLE.articleDetail', 0);

		//author
		$author = $article->find('SPAN.name', 0)->plaintext;
		//title
		$title = $article->find('H1.mainTitle', 0)->plaintext;
		//title
		if ($this->isArticleOpinion($url_article_link))
		{
			$title = str_replace('[OPINIA]', '', $title);
			$title = '[OPINIA] '.$title;
		}
		if ($this->isArticleFree($url_article_link))
			$title = '[FREE] '.$title;
		else
			$title = '[PREMIUM] '.$title;
		//date
		$article_data = $article_html->find('SCRIPT[type="application/ld+json"]', 0)->innertext;
		$json_decoded = (array)json_decode($article_data);
		$date = $json_decoded['datePublished'];
		//tags
		$tags = array();
		if (FALSE === is_null($article->find('DIV#relatedTopics', 0)))
			foreach($article->find('DIV#relatedTopics', 0)->find('A') as $tag_element)
				$tags[] = trim($tag_element->title);

		$this->deleteAllDescendantsIfExist($article, 'comment');
		$this->deleteAllDescendantsIfExist($article, 'script');
		$this->deleteAllDescendantsIfExist($article, 'DIV.social-container');
		$this->deleteAllDescendantsIfExist($article, 'DIV.widget.video');
		$this->deleteAllDescendantsIfExist($article, 'DIV.inforAdsRectSrod');
		$this->deleteAllDescendantsIfExist($article, 'DIV.widgetStop');
		$this->deleteAllDescendantsIfExist($article, 'DIV.bottomAdsBox');
		$this->deleteAllDescendantsIfExist($article, 'DIV#relatedTopics');
		$this->deleteAllDescendantsIfExist($article, 'DIV.detailAllBoxes');
		$this->deleteAllDescendantsIfExist($article, 'DIV.infor-ad');
		$this->deleteAllDescendantsIfExist($article, 'DIV.commentsBox');
		$this->deleteAllDescendantsIfExist($article, 'DIV.plistaDetailDesktop');
		$this->deleteAllDescendantsIfExist($article, 'DIV.streamNews');
		$this->deleteAllDescendantsIfExist($article, 'DIV.frameWrap');
		$this->deleteAllDescendantsIfExist($article, 'DIV#adoceangplwdqlgnngfg');
		$this->deleteAllDescendantsIfExist($article, 'DIV.afterDetailModules');
		$this->deleteAllDescendantsIfExist($article, 'DIV.widgetStop');
		$this->deleteAllDescendantsIfExist($article, 'DIV#banner_art_video_out');

		foreach($article->find('IMG[data-original][src^="data:image"]') as $img)
		{
			$img->src=$img->getAttribute('data-original');
		}

		foreach($article->find('P') as $paragraph)
		{
			if (FALSE === is_null($descendant = $paragraph->find('A[href*="forsal.pl/tagi/"]', 0)))
			{
				$paragraph->innertext = $paragraph->plaintext;
				continue;
			}
			else if (FALSE === is_null($descendant = $paragraph->find('A[href*="/tematy/"]', 0)))
			{
				$paragraph->innertext = $paragraph->plaintext;
				continue;
			}
			else if (FALSE === is_null($a_elem = $paragraph->find('A', 0)))
			{
				$href = $a_elem->getAttribute('href');
				if (preg_match("/.*forsal\.pl\/[a-z]+$/", $href))
				{
					$paragraph->innertext = $paragraph->plaintext;
					continue;
				}
			}
		}
		
		$this->items[] = array(
			'uri' => $url_article_link,
			'title' => $title,
			'timestamp' => $date,
			'author' => $author,
			'content' => $article,
			'categories' => $tags
		);
	}

	private function meetsConditions($url_article)
	{
		$only_opinions = $this->getInput('tylko_opinie');
		$only_free = $this->getInput('tylko_darmowe');
		$isArticleFree = $this->isArticleFree($url_article);
		$isArticleOpinion = $this->isArticleOpinion($url_article);

		if(FALSE === $only_opinions && FALSE === $only_free)
			return TRUE;
		else if(FALSE === $only_opinions && TRUE === $only_free)
		{
			if ($isArticleFree)
				return TRUE;
		}
		else if(TRUE === $only_opinions && FALSE === $only_free)
		{
			if ($isArticleOpinion)
				return TRUE;
		}
		else if(TRUE === $only_opinions && TRUE === $only_free)
		{
			if ($isArticleOpinion && $isArticleFree)
				return TRUE;
		}
		return FALSE;
	}

	private function isArticleFree($url_article)
	{
		$article_html = getSimpleHTMLDOMCached($url_article, (864000/(count($this->items)+1)*$GLOBALS['number_of_wanted_articles']));
		//Jeżeli element istneje (FALSE === is_null), to jest to artykul platny
		$premium_element = $article_html->find('A[id][href*="edgp.gazetaprawna.pl"]', 0);
		if (FALSE === is_null($premium_element))
			return FALSE;
		else
			return TRUE;
	}

	private function isArticleOpinion($url_article)
	{
		$article_html = getSimpleHTMLDOMCached($url_article, (864000/(count($this->items)+1)*$GLOBALS['number_of_wanted_articles']));
//		echo "<br><br>HEADER:<br>".$article_html->find('HEADER', 0);
//		echo "<br><br>url_article:<br>".$url_article;
//		echo "<br><br>mainTitle:<br>".$article_html->find('H1.mainTitle', 0);
//		echo "<br><br>mainTitle:<br>".$article_html->find('H1', 0);
//		echo "<br><br>mainTitle:<br>".$article_html->find('H1[data-scroll="tytul"]', 0);
//		echo "<br><br>HEADER.articleTop:<br>".$article_html->find('HEADER.articleTop', 0);
//		echo "<br><br>article_html:<br>".$article_html;

		$title = $article_html->find('H1.mainTitle', 0)->plaintext;
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