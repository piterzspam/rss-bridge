<?php
class GazetaprawnaBridge extends BridgeAbstract {
	const NAME = 'Gazetaprawna.pl - Strona autora';
	const URI = 'https://www.gazetaprawna.pl/';
	const DESCRIPTION = 'No description provided';
	const MAINTAINER = 'No maintainer';
	const CACHE_TIMEOUT = 1; // Can be omitted!

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
			'type' => array
			(
				'name' => 'Czy płatne?',
				'type' => 'list',
				'required' => true,
				'values' => array(
    			    'Tylko darmowe' => 'free',
    			    'Tylko premium' => 'premium',
    			    'Darmowe i premium' => 'both'
    			 )
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
		$GLOBALS['number_of_wanted_articles'] = $this->getInput('wanted_number_of_articles');
		$GLOBALS['my_debug'] = FALSE;
//		$GLOBALS['my_debug'] = TRUE;
		if (TRUE === $GLOBALS['my_debug'])
		{
			$GLOBALS['all_articles_time'] = 0;
			$GLOBALS['all_articles_counter'] = 0;
		}
		$url_articles_list = $this->getInput('url');
		$url_articles_list = preg_replace('/(.*\/autor\/[0-9]+,([a-z]+)-([a-z]+)).*/', '$1', $url_articles_list);


		while (count($this->items) < $GLOBALS['number_of_wanted_articles'])
		{
			$html_articles_list = getSimpleHTMLDOM($url_articles_list);
//				echo "<br>html_articles_list<br><br>$html_articles_list";
			if (0 !== count($found_urls = $html_articles_list->find('.solrList A[href*="gazetaprawna.pl"][title]')))
			{
//				echo "<br>found_urls<br><br>$found_urls";
				$found_urls_hrefs = array();
				$found_urls_titles = array();
//				echo "<br><br>Array - found_urls:";
				foreach($found_urls as $url_element)
				{
					$title = $url_element->title;
					if (FALSE === in_array($title, $found_urls_titles))
					{
						$found_urls_hrefs[] = $url_element->href;
						$found_urls_titles[] = $title;
					}
//					echo "<br>url_element->href: $url_element->href";
				}
//				echo "<br><br>Array - found_urls2:";
				foreach($found_urls_hrefs as $url_article_link)
				{
//					echo "<br>url_article_link: <br>$url_article_link";
				}
//				break;
//				echo "<br><br>Array - found_urls3:";
				foreach($found_urls_hrefs as $url_article_link)
				{
					if (count($this->items) < $GLOBALS['number_of_wanted_articles'])
					{
						//link to articles
						$url_article_link = $this->getCustomizedLink($url_article_link);
//						echo "<br>url_article_link: <br>$url_article_link";
//						$GLOBALS['is_article_free'] = $this->isArticleFree($h3_element);
//						$GLOBALS['is_article_opinion'] = $this->isArticleOpinion($h3_element);
						$article_html = getSimpleHTMLDOMCached($url_article_link, 86400 * 14);
						$GLOBALS['is_article_free'] = $this->isArticleFree($article_html);
						$GLOBALS['is_article_opinion'] = $this->isArticleOpinion($article_html);
						if ($this->meetsConditions() === TRUE && count($this->items) < $GLOBALS['number_of_wanted_articles'])
						{
							$this->addArticle($url_article_link);
						}
					}
				}
				break;
			}
			else
			{
				break;
			}
			$url_articles_list = $html_articles_list->find('A[title="następna"]', 0)->getAttribute('href');
		}
		if (TRUE === $GLOBALS['my_debug'])
			echo "<br>Wszystkie {$GLOBALS['all_articles_counter']} artykulow zajelo {$GLOBALS['all_articles_time']}, <br>średnio ".$GLOBALS['all_articles_time']/$GLOBALS['all_articles_counter'] ."<br>";
	}

	private function addArticle($amp_url)
	{
		if (TRUE === $GLOBALS['my_debug'])
		{
			$start_request = microtime(TRUE);
//			$article_html = getSimpleHTMLDOMCached($amp_url, (86400/(count($this->items)+1)*$GLOBALS['number_of_wanted_articles']));
			$article_html = getSimpleHTMLDOMCached($amp_url, 86400 * 14);
			$end_request = microtime(TRUE);
			echo "<br>Article  took " . ($end_request - $start_request) . " seconds to complete - url: $amp_url.";
			$GLOBALS['all_articles_counter']++;
			$GLOBALS['all_articles_time'] = $GLOBALS['all_articles_time'] + $end_request - $start_request;
		}
		else
		{
//			$article_html = getSimpleHTMLDOMCached($amp_url, (86400/(count($this->items)+1)*$GLOBALS['number_of_wanted_articles']));
			$article_html = getSimpleHTMLDOMCached($amp_url, 86400 * 14);
		}

//		echo "url_article_link: $url<br>";
		$article = $article_html->find('article', 0);
		$article_data = $article_html->find('SCRIPT[type="application/ld+json"]', 0)->innertext;
		$article_data_parsed = parse_article_data(json_decode($article_data));
		$date = trim($article_data_parsed["datePublished"]);
		$title = trim($article_data_parsed["headline"]);
		$author = trim($article_data_parsed["author"]["0"]["name"]);

		if ($GLOBALS['is_article_opinion'])
			$title = '[OPINIA] '.str_replace('[OPINIA]', '', $title);

/*		if ($GLOBALS['is_article_free'])
			$title = '[FREE] '.$title;
		else
			$title = '[PREMIUM] '.$title;*/
//		echo "<br><br><br>article_data_parsed:<pre>";var_dump($article_data_parsed);echo "</pre>";

/*		//tags
		$tags = array();
		foreach($article->find('DIV.tags', 0)->find('A[href*="/tagi/"]') as $tag_link)
			$tags[] = trim($tag_link->plaintext);*/

		fixAmpArticles($article);
		formatAmpLinks($article);
/*		deleteAllDescendantsIfExist($article, 'DIV.widget-psav-share-box');
		deleteAllDescendantsIfExist($article, 'DIV.w2g');
		deleteAllDescendantsIfExist($article, 'DIV.psavSpecialLinks');
		deleteAllDescendantsIfExist($article, 'DIV.articleRelated');
		deleteAllDescendantsIfExist($article, 'DIV.articleNextPrev');
		deleteAllDescendantsIfExist($article, 'DIV.tags');
		deleteAllDescendantsIfExist($article, 'UL.psav-author-ul');*/
		
		deleteAllDescendantsIfExist($article, 'DIV.promoFrame');
		deleteAllDescendantsIfExist($article, 'DIV.social-box');
		deleteAllDescendantsIfExist($article, 'DIV.adBoxTop');
		deleteAllDescendantsIfExist($article, 'DIV.serviceLogoBox');
		//https://prawo.gazetaprawna.pl/artykuly/8054640,nowelizacja-ustawy-o-wlasnosci-lokali-eksmisja-utrata-mieszkania.html.amp?amp_js_v=0.1
		clearParagraphsFromTaglinks($article, 'P.hyphenate', array(
			'/gazetaprawna\.pl\/tagi\//',
			'/prawo\.gazetaprawna\.pl\/$/',
			'/serwisy\.gazetaprawna\.pl\/msp\/tematy\//'
		));

		//https://www-gazetaprawna-pl.cdn.ampproject.org/v/s/www.gazetaprawna.pl/firma-i-prawo/artykuly/8054663,uokik-postepowanie-kaufland-polska-markety-eurocash-i-sca-pr-polska-intermarche.html.amp?amp_js_v=0.1
		clearParagraphsFromTaglinks($article, 'DIV.frameArea', array(
			'/gazetaprawna\.pl\/tagi\//',
			'/prawo\.gazetaprawna\.pl\/$/',
			'/serwisy\.gazetaprawna\.pl\/.*\/tematy\/p\//'
		));
/*
		https://www.gazetaprawna.pl/tagi/ustawa
		https://www.gazetaprawna.pl/tagi/prawo
		https://www.gazetaprawna.pl/tagi/sadownictwo
		https://prawo.gazetaprawna.pl/
		https://serwisy.gazetaprawna.pl/msp/tematy/p/przedsiebiorca
		https://serwisy.gazetaprawna.pl/nieruchomosci/artykuly/8054104,spoldzielcy-status-nowelizacja-rpo-tk-czlonkostwo-w-spoldzielni-mieszkaniowej.html.amp?amp_js_v=0.1
		https://serwisy.gazetaprawna.pl/poradnik-konsumenta/tematy/p/prawo
		https://www.gazetaprawna.pl/tagi/prawo
		https://www.gazetaprawna.pl/tagi/przedsiebiorca
*/

		$interview_question_style = array(
			'font-weight: bold;'
		);
		addStyle($article, 'P.pytanie', $interview_question_style);

		$this->items[] = array(
			'uri' => $amp_url,
			'title' => $title,
			'timestamp' => $date,
			'author' => $author,
			'content' => $article,
			'categories' => $tags
		);
	}

	private function meetsConditions()
	{
		$wanted_only_opinions = $this->getInput('tylko_opinie');
		if (TRUE === is_null($wanted_only_opinions)) $wanted_only_opinions = FALSE;
		$wanted_article_type = $this->getInput('type');
		if (TRUE === is_null($wanted_article_type)) $wanted_article_type = "both";

		if ($wanted_article_type === 'both')
		{
			if ($wanted_only_opinions)
			{
				if ($GLOBALS['is_article_opinion'])
					return TRUE;
				else
					return FALSE;
			}
			else
				return TRUE;
		}
		else if ($wanted_article_type === 'free' && $GLOBALS['is_article_free'] === TRUE)
		{
			if ($wanted_only_opinions)
			{
				if ($GLOBALS['is_article_opinion'])
					return TRUE;
				else
					return FALSE;
			}
			else
				return TRUE;
		}
		else if ($wanted_article_type === 'premium' && $GLOBALS['is_article_free'] === FALSE)
		{
			if ($wanted_only_opinions)
			{
				if ($GLOBALS['is_article_opinion'])
					return TRUE;
				else
					return FALSE;
			}
			else
				return TRUE;
		}
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
		if (FALSE !== strpos($title, '[OPINIA]') || FALSE !== strpos($title, '[KOMENTARZ]'))
			return TRUE;
		else
			return FALSE;
	}

	private function getCustomizedLink($url)
	{
		$new_url = str_replace('https://www.gazetaprawna.pl', 'https://www-gazetaprawna-pl.cdn.ampproject.org/v/s/www.gazetaprawna.pl', $url);
		
		
//		$new_url = preg_replace('/.*\/artykuly\/(.*)/', "https://www-gazetaprawna-pl.cdn.ampproject.org/v/s/www.gazetaprawna.pl/amp/$1", $url);
		$new_url = str_replace('.html', '.html.amp?amp_js_v=0.1', $new_url);
		return $new_url;
	}
}
