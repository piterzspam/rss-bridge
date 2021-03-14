<?php
class OnetBridge extends BridgeAbstract {
	const NAME = 'Onet Autor';
	const URI = 'https://wiadomosci.onet.pl/autorzy/';
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
		)
	);

	
	public function collectData()
	{
		include 'myFunctions.php';
		$url_articles_list = $this->getInput('url');
		$url_articles_list = preg_replace('/(.*\/autorzy\/[a-z]+-[a-z]+).*/', '$1', $url_articles_list);
		$GLOBALS['limit'] = $this->getInput('limit');
		$GLOBALS['my_debug'] = FALSE;
//		$GLOBALS['my_debug'] = TRUE;
		if (TRUE === $GLOBALS['my_debug'])
		{
			$GLOBALS['all_articles_time'] = 0;
			$GLOBALS['all_articles_counter'] = 0;
		}
		$urls = array();
		$page_number = 0;
		while (count($urls) < $GLOBALS['limit'])
		{
			$current_url = $url_articles_list.'?ajax=1&page='.$page_number;
			$html_articles_list = getSimpleHTMLDOM($current_url);
			$page_number++;
			deleteAllDescendantsIfExist($html_articles_list, 'DIV.breadcrumbs');

			if (0 !== ($url_counter = count($found_urls = $html_articles_list->find("DIV.listItem A[href][title]"))))
			{
				foreach($found_urls as $article__link)
				{
					if (count($urls) < $GLOBALS['limit'])
					{
						$url = $article__link->getAttribute('href');
						$amp_url = $this->getCustomizedLink($url);
						$urls[] = $amp_url;
						$this->addArticle($amp_url);
					}
				}
			}
			else
			{
				break;
			}
		}
		if (TRUE === $GLOBALS['my_debug'])
			echo "<br>Wszystkie {$GLOBALS['all_articles_counter']} artykulow zajelo {$GLOBALS['all_articles_time']}, <br>średnio ".$GLOBALS['all_articles_time']/$GLOBALS['all_articles_counter'] ."<br>";

	}
	private function addArticle($url_article)
	{
		if (TRUE === $GLOBALS['my_debug'])
		{
			$start_request = microtime(TRUE);
//			$article_html = getSimpleHTMLDOMCached($url_article, (86400/(count($this->items)+1)*$GLOBALS['limit']));
			$article_html = getSimpleHTMLDOMCached($url_article, 86400 * 14);
			$end_request = microtime(TRUE);
			echo "<br>Article  took " . ($end_request - $start_request) . " seconds to complete - url: $url_article.";
			$GLOBALS['all_articles_counter']++;
			$GLOBALS['all_articles_time'] = $GLOBALS['all_articles_time'] + $end_request - $start_request;
		}
		else
		{
//			$article_html = getSimpleHTMLDOMCached($url_article, (86400/(count($this->items)+1)*$GLOBALS['limit']));
			$article_html = getSimpleHTMLDOMCached($url_article, 86400 * 14);
		}
		if (is_bool($article_html))
		{
			$this->items[] = array(
				'uri' => $url_article,
				'title' => "getSimpleHTMLDOM($url_article) jest booleml",
				'timestamp' => '',
				'author' => '',
				'content' => $article_html,
				'categories' => ''
			);
			return;
		}
		$article = $article_html->find('article', 0);
		$article_data = $article_html->find('SCRIPT[type="application/ld+json"]', 0)->innertext;
		$article_data_parsed = parse_article_data(json_decode($article_data));
		$date = trim($article_data_parsed["datePublished"]);
		$title = trim($article_data_parsed["headline"]);
		$title = $this->getChangedTitle($title);
		$author = trim($article_data_parsed["author"]["name"]);

		deleteAllDescendantsIfExist($article, 'comment');
		deleteAllDescendantsIfExist($article, 'script');
		deleteAllDescendantsIfExist($article, 'DIV.social-box');
		deleteAllDescendantsIfExist($article, 'DIV[style="margin:auto;width:300px;"]');
//https:iadomosci-onet-pl.cdn.ampproject.org/v/s/wiadomosci.onet.pl/tylko-w-onecie/wybory-w-usa-2020-andrzej-stankiewicz-dzis-jest-czas-wielkiej-smuty-w-pis/fcktclw.amp?amp_js_v=0.1
//Glińskłumaczy się kryteriami obiektywnymi.
		clearParagraphsFromTaglinks($article, 'P.hyphenate', array('/onet\.pl\/[^\/]*$/'));
		deleteAncestorIfChildMatches($article, array('ul', 'li', 'A[href*="onet.pl"][target="_top"]'));
		
		foreach($article->find('P.hyphenate') as $paragraph)
		{
			deleteAncestorIfContainsText($paragraph, 'Poniżej lista wszystkich dotychczasowych odcinków podcastu:');
			deleteAncestorIfContainsText($paragraph, 'Cieszymy się, że jesteś z nami. Zapisz się na newsletter Onetu, aby otrzymywać od nas najbardziej wartościowe treści');
//https://wiadomosci-onet-pl.cdn.ampproject.org/v/s/wiadomosci.onet.pl/tylko-w-onecie/michal-cholewinski-krytykowal-orzeczenie-tk-ws-aborcji-zostal-zdjety-z-anteny/31zq2s2.amp?amp_js_v=0.1
//https://wiadomosci-onet-pl.cdn.ampproject.org/v/s/wiadomosci.onet.pl/kraj/20-lecie-platformy-obywatelskiej-partia-oklejona-nekrologami-analiza/7cwsve3.amp?amp_js_v=0.1
//Dalsza część tekstu znajduje się pod wideo
//Agent Tomek w "Onet Rano" przeprasza Beatę Sawicką. Ciąg dalszy tekstu pod wideo:
//Pozostała część tekstu pod materiałem wideo
			if (FALSE !== strpos($paragraph->plaintext, 'pod materiałem wideo') || FALSE !== strpos($paragraph->plaintext, 'pod wideo'))
			{
				$next_sibling = $paragraph->next_sibling();
				if ('amp-iframe' == strtolower($next_sibling->tag))
				{
					$next_sibling->outertext='';
					$paragraph->next_sibling()->outertext='';
					$paragraph->next_sibling()->innertext='';
					$paragraph->next_sibling()->plaintext='';
				}
				$paragraph->outertext='';
			}
		}
		fixAmpArticles($article);
		formatAmpLinks($article);

		foreach($article->find('LI') as $li)
		{
			deleteAncestorIfContainsText($li, 'Więcej informacji i podcastów znajdziesz na stronie głównej Onet.pl');
		}

		foreach($article->find('IMG') as $photo_element)
		{
			if($photo_element->hasAttribute('i-amphtml-layout')) $photo_element->setAttribute('i-amphtml-layout', NULL);
			if($photo_element->hasAttribute('layout')) $photo_element->setAttribute('layout', NULL);
			if($photo_element->hasAttribute('on')) $photo_element->setAttribute('on', NULL);
			if($photo_element->hasAttribute('role')) $photo_element->setAttribute('role', NULL);
			if($photo_element->hasAttribute('srcset')) $photo_element->setAttribute('srcset', NULL);
			if($photo_element->hasAttribute('style')) $photo_element->setAttribute('style', NULL);
			if($photo_element->hasAttribute('tabindex')) $photo_element->setAttribute('tabindex', NULL);
		}

//		addStyle($article, 'BLOCKQUOTE', getStyleQuote());
		addStyle($article, 'FIGURE DIV.wrapper', getStylePhotoParent());
		addStyle($article, 'FIGURE DIV.wrapper IMG', getStylePhotoImg());
		$caption_style = array(
			'bottom: 0;',
			'left: 0;',
			'right: 0;',
			'text-align: center;',
			'color: #fff;',
			'background-color: rgba(0, 0, 0, 0.7);'
		);
		addStyle($article, 'FIGURE DIV.wrapper SPAN', $caption_style);


		$this->items[] = array(
			'uri' => $url_article,
			'title' => $title,
			'timestamp' => $date,
			'author' => $author,
			'content' => $article
		);

//		echo "<br>Wszystkie $all_articles_counter artykulow zajelo $all_articles_time, <br>średnio ".$all_articles_time/$all_articles_counter ."<br>";
	}

	private function getChangedTitle($title)
	{
		preg_match_all('/\[[^\]]*\]/', $title, $title_categories);
		$title_prefix = "";
		foreach($title_categories[0] as $category)
		{
			$title = str_replace($category, '', $title);
			$title_prefix = $title_prefix.$category;
		}
		$new_title = $title_prefix.' '.trim($title);
		return $new_title;
	}


	private function getCustomizedLink($url)
	{
		$new_url = $url.'.amp?amp_js_v=0.1';
		$new_url = str_replace('https://', 'https://wiadomosci-onet-pl.cdn.ampproject.org/v/s/', $new_url);
		
		return $new_url;
	}
}