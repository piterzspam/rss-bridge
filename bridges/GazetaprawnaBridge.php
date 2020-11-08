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
						if ($this->meetsConditions($url_article_link) === TRUE && count($this->items) < $GLOBALS['number_of_wanted_articles'])
							{
								$this->addArticle($url_article_link);
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

	private function addArticle($url_article_link)
	{
		$article_html = getSimpleHTMLDOMCached($url_article_link, (864000/(count($this->items)+1)*$GLOBALS['number_of_wanted_articles']));
		$article = $article_html->find('DIV.article', 0);
/*		foreach($article_html->find('STYLE') as $style)
			$article->last_child()->outertext=$article->last_child()->outertext.$style->outertext;
*/
		//author
		$author = $article->find('DIV.date', 0)->find('STRONG', 0)->plaintext;
		//title
		$title = $article->find('H1[itemprop="headline"]', 0)->plaintext;
		if ($this->isArticleFree($url_article_link))
			$title = '[FREE] '.$title;
		else
			$title = '[PREMIUM] '.$title;
		//date
		$date = $article->find('META[itemprop="datePublished"]', 0)->content;
		//tags
		$tags = array();
		if (FALSE === is_null($article->find('DIV.tagiArt', 0)))
			foreach($article->find('DIV.tagiArt', 0)->find('A') as $tag_element)
				$tags[] = trim($tag_element->title);

		$this->deleteAllDescendantsIfExist($article, 'comment');
		$this->deleteAllDescendantsIfExist($article, 'script');
		$this->deleteAllDescendantsIfExist($article, 'DIV.shareArticleButtons');
		$this->deleteAllDescendantsIfExist($article, 'DIV.smsGate');
		$this->deleteAllDescendantsIfExist($article, 'DIV.clr');
		$this->deleteAllDescendantsIfExist($article, 'IMG.obrazek-statystyki');
		$this->deleteAllDescendantsIfExist($article, 'DIV#a_201');
		$this->deleteAllDescendantsIfExist($article, 'DIV.zobacz-takze');
		$this->deleteAllDescendantsIfExist($article, 'DIV.widget-fbLike-box');
		$this->deleteAllDescendantsIfExist($article, 'DIV#adform-outstream');
		$this->deleteAllDescendantsIfExist($article, 'DIV#articlePurchaseContainer');
		$this->deleteAllDescendantsIfExist($article, 'DIV.art-cp-box');
		$this->deleteAllDescendantsIfExist($article, 'DIV.artPaywallWrapper');
		$this->deleteAllDescendantsIfExist($article, 'DIV.art-cp-box');
		$this->deleteAllDescendantsIfExist($article, 'DIV.boxRelated');
		$this->deleteAllDescendantsIfExist($article, 'DIV.gazetaOnly');
		$this->deleteAllDescendantsIfExist($article, 'META[itemprop="interactionCount"]');
		$this->deleteAllDescendantsIfExist($article, 'META[itemprop="mainEntityOfPage"]');
		$this->deleteAllDescendantsIfExist($article, 'META[itemprop="thumbnailUrl"]');
		$this->deleteAllDescendantsIfExist($article, 'DIV[class*="oxRelate"]');
		$this->deleteAllDescendantsIfExist($article, 'DIV.autorBox');
		$this->deleteAllDescendantsIfExist($article, 'DIV.tagiArt');

		foreach($article->find('IMG[itemprop="contentURL"][data-src][!src]') as $img)
		{
			$img->src=$img->getAttribute('data-src');
		}

		foreach($article->find('P') as $paragraph)
		{
			if (FALSE === is_null($descendant = $paragraph->find('A[href*="gazetaprawna.pl/tagi/"]', 0)))
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
				if (preg_match("/.*gazetaprawna\.pl\/[a-z]+$/", $href))
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

	private function meetsConditions($url_article_link)
	{
		$only_free = $this->getInput('tylko_darmowe');
		$isArticleFree = $this->isArticleFree($url_article_link);

		if(FALSE === $only_free)
			return TRUE;
		else if(TRUE === $only_free && TRUE === $isArticleFree)
			return TRUE;
		return FALSE;
	}
	private function isArticleFree($url_article)
	{
		$article_html = getSimpleHTMLDOMCached($url_article, (864000/(count($this->items)+1)*$GLOBALS['number_of_wanted_articles']));
		if (TRUE === is_null($article_html->find('H1.gold.title', 0)))
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