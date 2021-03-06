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
				'required' => true
			),
			'limit' => array
			(
				'name' => 'Liczba artykułów',
				'type' => 'number',
				'required' => true,
				'defaultValue' => 3,
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
		include 'myFunctions.php';
		$GLOBALS['my_debug'] = FALSE;
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


	private function addArticle($url_article_link, $article_html)
	{
		$article = $article_html->find('article', 0);
		$article_data = $article_html->find('SCRIPT[type="application/ld+json"]', 0)->innertext;
		$article_data = str_replace('"width": ,'."\n", '"width": "",'."\n", $article_data);
		$article_data = str_replace('"height": '."\n", '"height": ""'."\n", $article_data);

		$article_data_parsed = parse_article_data(json_decode($article_data));
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
		$selectors_array[] = 'comment';
		$selectors_array[] = 'DIV.social-box';
		$selectors_array[] = 'amp-image-lightbox';
		$selectors_array[] = 'DIV.adBoxTop';
		$selectors_array[] = 'DIV.adBox';
		$selectors_array[] = 'DIV.widget.video';
		$article = foreach_delete_element_array($article, $selectors_array);
		$article = clear_paragraphs_from_taglinks($article, 'P.hyphenate', array('/forsal\.pl\/tagi\//'));


		$this->items[] = array(
			'uri' => $url_article_link,
			'title' => $title,
			'timestamp' => $date,
			'author' => $author,
			'content' => $article
		);
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
}
