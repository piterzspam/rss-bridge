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

		$wanted_number_of_articles = $this->getInput('wanted_number_of_articles');
		$url = $this->getInput('url');
		$author = preg_replace('/.*\/autor\/[0-9]+,([a-z]+)-([a-z]+).*/', '$1 $2', $url);
		$author = ucwords($author);

		$urls = array();
		$titles = array();
		while (count($this->items) < $wanted_number_of_articles)
		{
			$html = getSimpleHTMLDOM($url);
//			echo '<br><br>html:<br>'; echo $html;
			//Czy liczba linków niezerowa
//			echo '<br><br>test1: '.$url;
//			echo '<br><br>test2: '.$url;
			if (0 !== ($url_counter = count($found_urls = $html->find('DIV.boxArticleList', 0)->find('A[href][title]'))))
			{
				foreach($found_urls as $article__link)
				{
//					echo '<br><br>article__link: '.$article__link;
					$title = $article__link->getAttribute('title');
					$href = $article__link->getAttribute('href');
//					echo '<br><br>title: '.$title;
//					echo '<br><br>href: '.$href;
					//Czy pobierać ten artykuł
					$array = $this->meetsConditions($title, $href);
//					echo '<br><br>href: '.$href;
					if ($array['boolMeetsConditions'] === TRUE && count($this->items) < $wanted_number_of_articles)
					{
						$urls[] = $href;
						$titles[] = $title;
						$this->addArticle($array['html'], $title, $url, $author);
					}
				}
			}
			else
			{
				break;
			}
			$url = $html->find('A.next', 0)->getAttribute('href');
		}

//		echo 'urls:<br>';
//		echo '<pre>'.var_export($urls, true).'</pre>';
	}

	private function addArticle($html, $title, $url, $author)
	{
		$article = $html->find('ARTICLE.articleDetail', 0);

		//date
		$article_data = $html->find('SCRIPT[type="application/ld+json"]', 0)->innertext;
		$json_decoded = (array)json_decode($article_data);
		$date = $json_decoded['datePublished'];
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
		if (FALSE === $this->isArticleFree($html))
		{
			$title = '[PREMIUM] '.$title;
		}


//		echo 'article:<br>';
//		echo $article;

//		echo 'article_data4:<br><pre>'.var_export((array)json_decode($article_data), TRUE).'</pre>';
//		echo 'article_data5:<br><pre>'.var_dump(json_decode(html_entity_decode($article_data))).'</pre>';


		$this->items[] = array(
			'uri' => $url,
			'title' => $title,
			'timestamp' => $date,
			'author' => $author,
			'content' => $article,
			'categories' => $tags
		);
	}
	private function meetsConditions($title, $url)
	{
		$only_opinions = $this->getInput('tylko_opinie');
		$only_free = $this->getInput('tylko_darmowe');
		$html = getSimpleHTMLDOM($url);

		if(FALSE === $only_opinions && FALSE === $only_free)
			return array('boolMeetsConditions' => TRUE, 'html' => $html);
		else if(FALSE === $only_opinions && TRUE === $only_free)
		{
			if ($this->isArticleFree($html))
				return array('boolMeetsConditions' => TRUE, 'html' => $html);
		}
		else if(TRUE === $only_opinions && FALSE === $only_free)
		{
			if ($this->isArticleOpinion($title))
				return array('boolMeetsConditions' => TRUE, 'html' => $html);
		}
		else if(TRUE === $only_opinions && TRUE === $only_free)
		{
			if ($this->isArticleOpinion($title) && $this->isArticleFree($html))
				return array('boolMeetsConditions' => TRUE, 'html' => $html);
		}
		return array(
			'boolMeetsConditions' => FALSE,
			'html' => $html
		);
	}
	private function isArticleFree($html)
	{
		$article = $html->find('ARTICLE.articleDetail', 0);
		//Jeżeli element istneje (FALSE === is_null), to jest to artykul platny
		if (is_null($article->find('A[id][href*="edgp.gazetaprawna.pl"]', 0)))
		{
//			echo "<br>Article is free <br>";
			return TRUE;
		}
		else
		{
//			echo "<br>Article is not free <br>";
			return FALSE;	
		}
	}
	private function isArticleOpinion($title)
	{
		if (FALSE !== strpos($title, '[OPINIA]'))
		{
//			echo "<br>Article is opinion <br>";
			return TRUE;
		}
		else
		{
//			echo "<br>Article is not opinion <br>";
			return FALSE;
		}
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