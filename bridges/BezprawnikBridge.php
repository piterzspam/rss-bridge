<?php
class BezprawnikBridge extends BridgeAbstract {
	const NAME = 'Bezprawnik - strona autora';
	const URI = 'https://bezprawnik.pl/author/';
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
		)
	);

	public function getIcon()
	{
		return 'https://c.disquscdn.com/uploads/forums/349/4323/favicon.png';
	}

	public function collectData()
	{
		include 'myFunctions.php';
		$url_articles_list = $this->getInput('url');
		$url_articles_list = preg_replace('/(.*\/author\/([a-z]+)-([a-z]+)\/).*/', '$1', $url_articles_list);
		$GLOBALS['number_of_wanted_articles'] = $this->getInput('wanted_number_of_articles');
		while (count($this->items) < $GLOBALS['number_of_wanted_articles'])
		{
//			$html_articles_list = getSimpleHTMLDOM($url_articles_list);
			$html_articles_list = getSimpleHTMLDOMCached($url_articles_list, 86400 * 14);
			
			if (0 !== count($found_urls = $html_articles_list->find("A.linkbg")))
				foreach($found_urls as $article__link)
					if (count($this->items) < $GLOBALS['number_of_wanted_articles'])
					{
						$url_article = $article__link->getAttribute('href');
						$amp_url = $this->getCustomizedLink($url_article);
						$this->addArticle($amp_url);
					}
					else
						break;
			else
				break;
		
			if (TRUE === is_null($html_articles_list->find('A.nextpostslink', 0)))
				break;
			else
			{
				$next_page_element = $html_articles_list->find('A.nextpostslink', 0);
				$url_articles_list = $next_page_element->getAttribute('href');
			}
		}

	}

	private function addArticle($url_article)
	{
//		$article_html = getSimpleHTMLDOMCached($url_article, (86400/(count($this->items)+1)*$GLOBALS['number_of_wanted_articles']));
		$article_html = getSimpleHTMLDOMCached($url_article, 86400 * 14);
		$article = $article_html->find('article', 0);
		$article_data = $article_html->find('SCRIPT[type="application/ld+json"]', 0)->innertext;
		$article_data_parsed = parse_article_data(json_decode($article_data));
		$title = $article_data_parsed["@graph"][2]["name"];
		$date = $article_data_parsed["@graph"][2]["datePublished"];
		$author = $article_data_parsed["@graph"][3]["name"];
//		$title = $article_html->find('META[property="og:title"]', 0)->content;
//		$date = $article_html->find('META[property="article:published_time]', 0)->content;
		
		$tags = array();
		foreach($article_html->find('DIV.amp-wp-tax-tag A[href*="bezprawnik.pl/tag/"][rel="tag"]') as $tag_element)
			$tags[] = trim($tag_element->plaintext);
		fixAmpArticles($article);
		formatAmpLinks($article);
		deleteAllDescendantsIfExist($article, 'comment');
		//może pomoże na drugie zdjęcie pod zdjęciem głównynm w czytniku
		deleteAllDescendantsIfExist($article, 'script');
		//może pomoże na drugie zdjęcie pod zdjęciem głównynm w czytniku - 2
		deleteAllDescendantsIfExist($article, 'NOSCRIPT');
		deleteAllDescendantsIfExist($article, 'DIV.amp-autor');
		deleteAllDescendantsIfExist($article, 'FIGURE[id^="attachment_"]');
		deleteAllDescendantsIfExist($article, 'FOOTER');

		foreach($article->find('amp-img, img') as $photo_element)
		{
			if(isset($photo_element->layout)) $photo_element->layout = NULL;
			if(isset($photo_element->srcset)) $photo_element->srcset = NULL;
		}
		
		clearParagraphsFromTaglinks($article, 'P', array('/bezprawnik.pl\/tag\//'));
//https://bezprawnik.pl/korwin-mikke-wyrzucony-z-facebooka/amp/
//https://bezprawnik.pl/rzad-zmienil-ustroj-polski/amp/
		deleteAncestorIfChildMatches($article, array('ul', 'li', 'h3', 'a'));

		$this->items[] = array(
			'uri' => $url_article,
			'title' => $title,
			'timestamp' => $date,
			'author' => $author,
			'content' => $article,
			'categories' => $tags
		);
	}

	







	private function getCustomizedLink($url)
	{
		$new_url = $url."amp/";
		$new_url = str_replace('https://', 'https://bezprawnik-pl.cdn.ampproject.org/c/s/', $new_url);
		return $new_url;
	}
}
