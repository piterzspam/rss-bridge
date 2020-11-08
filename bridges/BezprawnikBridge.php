<?php
class BezprawnikBridge extends BridgeAbstract {
	const NAME = 'Bezprawnik - strona autora';
	const URI = 'https://bezprawnik.pl/author/';
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

	public function getIcon()
	{
		return 'https://c.disquscdn.com/uploads/forums/349/4323/favicon.png';
	}

	public function collectData()
	{
		$articles_list_url = $this->getInput('url');
		$articles_list_url = preg_replace('/(.*\/author\/([a-z]+)-([a-z]+)\/).*/', '$1', $articles_list_url);
		$number_of_wanted_articles = $this->getInput('wanted_number_of_articles');
		while (count($this->items) < $number_of_wanted_articles)
		{
			$articles_list_html = getSimpleHTMLDOM($articles_list_url);
			if (0 !== count($found_urls = $articles_list_html->find("A.linkbg")))
				foreach($found_urls as $article__link)
					if (count($this->items) < $number_of_wanted_articles)
					{
						$url = $article__link->getAttribute('href');
						$this->addArticle($url);
					}
					else
						break;
			else
				break;
		
			if (TRUE === is_null($articles_list_html->find('A.nextpostslink', 0)))
				break;
			else
			{
				$next_page_element = $articles_list_html->find('A.nextpostslink', 0);
				$articles_list_url = $next_page_element->getAttribute('href');
			}
		}

	}

	private function addArticle($url)
	{
		$html = getSimpleHTMLDOMCached($url, 864000);
		if (FALSE === is_null($html->find('ARTICLE', 0)))
		{
			$article = $html->find('ARTICLE', 0);
		}
		else
		{
			$this->items[] = array(
				'uri' => $url,
				'title' => 'ERROR - html->find(ARTICLE, 0) jest puste',
				'timestamp' => '',
				'author' => '',
				'content' => '',
				'categories' => ''
			);
		}
		//author
		$author = $article->find('P.autor-mobile', 0)->plaintext;
		//date
		$date = $article->find('DIV.article-cover', 0)->find('DIV.absolute', 0)->find('SPAN', 0)->plaintext;
		//title
		$title = $article->find('DIV.article-cover', 0)->find('DIV.absolute', 0)->find('H1.thin', 0)->plaintext;
		//tags
		$tags = array();
		foreach($article->find('DIV.tagi', 0)->find('A[rel="tag"]') as $tag_element)
		{
			$tags[] = trim($tag_element->plaintext);
		}
		$this->deleteAllDescendantsIfExist($article, 'A.kategoria');
		$this->deleteAllDescendantsIfExist($article, 'A.interakcje');
		$this->deleteAllDescendantsIfExist($article, 'DIV.tagi');
		$this->deleteAllDescendantsIfExist($article, 'DIV.left-fix');
		$this->deleteAllDescendantsIfExist($article, 'DIV.facebook-box');
		$this->deleteAllDescendantsIfExist($article, 'DIV#discussion');
		$this->deleteAllDescendantsIfExist($article, 'DIV.autor-mobile-img');
		$this->deleteAllDescendantsIfExist($article, 'FIGURE.wp-caption.alignright[style="width: 170px"]');
		$this->deleteAllDescendantsIfExist($article, 'DIV[id^="div-gpt-ad"]');
		$this->deleteAncestorIfChildMatches($article, array('ul', 'li', 'h3', 'A[href^="https://bezprawnik.pl/"]'));
		
		$this->items[] = array(
			'uri' => $url,
			'title' => $title,
			'timestamp' => $date,
			'author' => $author,
			'content' => $article,
			'categories' => $tags
		);
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
