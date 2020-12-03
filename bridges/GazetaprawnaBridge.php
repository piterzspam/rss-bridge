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
			if (0 !== count($found_urls = $html_articles_list->find('DIV.whiteListArt', 0)->find('H3')))
			{
				foreach($found_urls as $h3_element)
				{
					if (count($this->items) < $GLOBALS['number_of_wanted_articles'])
					{
						//link to articles
						$a_element = $h3_element->find('a', 0);
						$url_article_link = $a_element->href;
						$url_article_link = $this->getCustomizedLink($url_article_link);
						$GLOBALS['is_article_free'] = $this->isArticleFree($h3_element);
						$GLOBALS['is_article_opinion'] = $this->isArticleOpinion($h3_element);
						if ($this->meetsConditions() === TRUE && count($this->items) < $GLOBALS['number_of_wanted_articles'])
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
		if (TRUE === $GLOBALS['my_debug'])
			echo "<br>Wszystkie {$GLOBALS['all_articles_counter']} artykulow zajelo {$GLOBALS['all_articles_time']}, <br>średnio ".$GLOBALS['all_articles_time']/$GLOBALS['all_articles_counter'] ."<br>";
	}

	private function addArticle($amp_url)
	{
		if (TRUE === $GLOBALS['my_debug'])
		{
			$start_request = microtime(TRUE);
			$article_html = getSimpleHTMLDOMCached($amp_url, (864000/(count($this->items)+1)*$GLOBALS['number_of_wanted_articles']));
			$end_request = microtime(TRUE);
			echo "<br>Article  took " . ($end_request - $start_request) . " seconds to complete - url: $amp_url.";
			$GLOBALS['all_articles_counter']++;
			$GLOBALS['all_articles_time'] = $GLOBALS['all_articles_time'] + $end_request - $start_request;
		}
		else
			$article_html = getSimpleHTMLDOMCached($amp_url, (864000/(count($this->items)+1)*$GLOBALS['number_of_wanted_articles']));

//		echo "url_article_link: $url<br>";
		$article = $article_html->find('article', 0);
		$article_data = $article_html->find('SCRIPT[type="application/ld+json"]', 0)->innertext;
		$article_data_parsed = parse_article_data(json_decode($article_data));
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

		fixAmpArticles($article);
		formatAmpLinks($article);
		deleteAllDescendantsIfExist($article, 'DIV.widget-psav-share-box');
		deleteAllDescendantsIfExist($article, 'DIV.w2g');
		deleteAllDescendantsIfExist($article, 'DIV.psavSpecialLinks');
		deleteAllDescendantsIfExist($article, 'DIV.articleRelated');
		deleteAllDescendantsIfExist($article, 'DIV.articleNextPrev');
		deleteAllDescendantsIfExist($article, 'DIV.tags');
		deleteAllDescendantsIfExist($article, 'UL.psav-author-ul');

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
	private function getCustomizedLink($url)
	{
		$new_url = preg_replace('/.*\/artykuly\/(.*)/', "https://www-gazetaprawna-pl.cdn.ampproject.org/v/s/www.gazetaprawna.pl/amp/$1", $url);
		$new_url = str_replace('.html', '.amp?amp_js_v=0.1', $new_url);
		return $new_url;
	}
}
