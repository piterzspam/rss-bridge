<?php
class GazetaprawnaBridge extends BridgeAbstract {
	const NAME = 'Gazetaprawna.pl - Strona autora';
	const URI = 'https://www.gazetaprawna.pl/';
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
			'wanted_number_of_articles' => array
			(
				'name' => 'Liczba artykułów',
				'type' => 'number',
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

		$GLOBALS['articles_urls'] = array();
		$GLOBALS['articles_titles'] = array();
		$GLOBALS['opinions_params'] = array(
			'OPINIA',
			'KOMENTARZ'
		);
		$this->setGlobalArticlesParams();
		$GLOBALS['url_articles_list'] = $url_articles_list;
		
		while (count($this->items) < $GLOBALS['number_of_wanted_articles'])
		{
			$new_urls = $this->getNewUrls();
			if ("empty" === $new_urls)
			{
				break;
			}
			foreach ($new_urls as $url)
			{
				$article_html = getSimpleHTMLDOMCached($url, 86400 * 14);
				if (TRUE === $this->meetsConditions($article_html))
				{
					$this->addArticle($article_html, $url);
					if (count($this->items) >= $GLOBALS['number_of_wanted_articles'])
					{
						break;
					}
				}
			}
		}
	}

	private function getNewUrls()
	{
		$new_urls = array();
		$html_articles_list = getSimpleHTMLDOM($GLOBALS['url_articles_list']);
		if (0 === count($found_leads = $html_articles_list->find('DIV.itarticle')))
		{
			return "empty";
		}
		else
		{
			foreach($found_leads as $lead)
			{
				$title_element = $lead->find('H3.itemTitle', 0);
				$href_element = $lead->find('A[href]', 0);
				if (FALSE === is_null($title_element) && FALSE === is_null($href_element))
				{
					$title = $title_element->plaintext;
					$url = $href_element->href;
					if (FALSE === in_array($title, $GLOBALS['articles_titles']) && FALSE === strpos($url, '/dgp/'))
					{
						$GLOBALS['articles_urls'][] = $url;
						$GLOBALS['articles_titles'][] = $title;
						$new_urls[] = $url;
					}
				}
			}
		}
		$GLOBALS['url_articles_list'] = $this->getNextPageUrl($html_articles_list);
		return $new_urls;
	}

	private function getNextPageUrl($html_articles_list)
	{		
		$next_page_element = $html_articles_list->find('A.next', 0);
		if (FALSE === is_null($next_page_element) && $next_page_element->hasAttribute('href'))
		{
			return $next_page_element->getAttribute('href');
		}
		else
			return "empty";
	}


	private function addArticle($article_html, $url_article_link)
	{
		$tags = returnTagsArray($article_html, 'DIV#relatedTopics A[href]');
		$price_param = $this->getArticlePriceParam($article_html);
		if ("premium" === $price_param)
		{
			$article_html = $this->getFullArticlePage($url_article_link);
		}
		$article = $article_html->find('SECTION.detailSection', 0);
		//title
		$title_element = $article->find('H1.mainTitle', 0);
		$title = $title_element->plaintext;
		$title = $this->getChangedTitle($title, $price_param);
		//authors
		$author = returnAuthorsAsString($article, 'DIV.authBox A[href*="/autor/"]');

		deleteAllDescendantsIfExist($article, 'comment');
		deleteAllDescendantsIfExist($article, 'SCRIPT');
		deleteAllDescendantsIfExist($article, 'DIV.streamNews');
		deleteAllDescendantsIfExist($article, 'DIV.plistaDetailDesktop');
		deleteAllDescendantsIfExist($article, 'DIV.commentsBox');
		deleteAllDescendantsIfExist($article, 'DIV.detailAllBoxes');
		deleteAllDescendantsIfExist($article, 'DIV.social-container');
		deleteAllDescendantsIfExist($article, 'DIV#relatedTopics');
		deleteAllDescendantsIfExist($article, 'DIV.serviceLogoWrapper');
		deleteAllDescendantsIfExist($article, 'DIV.infor-ad');
		deleteAllDescendantsIfExist($article, 'DIV.bottomAdsBox');
		deleteAllDescendantsIfExist($article, 'ASIDE#rightColumnBox');
		deleteAllDescendantsIfExist($article, 'DIV.promoFrame.pulse2PromoFrame.withDescription.article');
		deleteAllDescendantsIfExist($article, 'DIV#banner_art_video_out');

		//https://www.gazetaprawna.pl/firma-i-prawo/artykuly/8077582,konkurs-na-facebooku-polubienie-posta-kara-od-skarbowki.html
		$paragraph_title_style = array(
			'font-weight: bold;'
		);
		addStyle($article, 'DIV.frameArea.srodtytul', $paragraph_title_style);
		foreach($article->find('DIV.frameArea.srodtytul') as $srodtytul)
		{
			$srodtytul->outertext = '<br>'.$srodtytul->outertext;
		}
		//https://serwisy.gazetaprawna.pl/orzeczenia/artykuly/8078983,antonik-burmistrz-prezes-spoldzielnia-mieszkaniowa-brodno-porozumienie-etyka.html
		foreach($article->find('H2') as $h2)
		{
			$h2->outertext = '<br>'.$h2->outertext;
		}

		$lead_style = array(
			'font-weight: bold;'
		);
		addStyle($article, 'DIV#lead', $lead_style);


		$figure_style = array(
			'position: relative;'
		);
		addStyle($article, 'figure', $figure_style);

		$img_style = array(
			'vertical-align: bottom;'
		);
		addStyle($article, 'img', $img_style);

		$figcaption_style = array(
			'position: absolute;',
			'bottom: 0;',
			'left: 0;',
			'right: 0;',
			'text-align: center;',
			'color: #fff;',
			'padding-top: 10px;',
			'padding-right: 10px;',
			'padding-bottom: 10px;',
			'padding-left: 10px;',
			'background-color: rgba(0, 0, 0, 0.7);'
		);
		addStyle($article, 'figcaption', $figcaption_style);
		
		//https://www.gazetaprawna.pl/magazyn-na-weekend/artykuly/8079800,sztuczna-inteligencja-rezolucja-ue-azja-usa-slowik.html
		$frameWrap_style = array(
			'margin-bottom: 18px;'
		);
		addStyle($article, 'DIV.frameWrap', $frameWrap_style);

		//https://prawo.gazetaprawna.pl/artykuly/8054640,nowelizacja-ustawy-o-wlasnosci-lokali-eksmisja-utrata-mieszkania.html.amp?amp_js_v=0.1
		clearParagraphsFromTaglinks($article, 'P.hyphenate, DIV.frameArea', array(
			'/gazetaprawna\.pl\/tagi\//',
			'/prawo\.gazetaprawna\.pl\/$/',
			'/serwisy\.gazetaprawna\.pl\/.*\/tematy\//'
		));
		
		$this->items[] = array(
			'uri' => $url_article_link,
			'title' => $title,
			'author' => $author,
			'categories' => $tags,
			'content' => $article
		);
	}

	private function getChangedTitle($title, $price_param)
	{
		preg_match_all('/\[[^\]]*\]/', $title, $title_categories);
		$title_prefix = "";
		foreach($title_categories[0] as $category)
		{
			$title = str_replace($category, '', $title);
			$title_prefix = $title_prefix.$category;
		}
		$new_title = '['.strtoupper($price_param).']'.$title_prefix.' '.trim($title);
		return $new_title;
	}

	private function getFullArticlePage($url)
	{
		$opts = array(
		  'http'=>array(
		    'header'=>"User-Agent:Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)\r\n"
		  )
		);
		$context = stream_context_create($opts);
		$article_html = file_get_html($url, false, $context);
		return $article_html;
	}

	private function setGlobalArticlesParams()
	{
		$GLOBALS['wanted_only_opinions'] = $this->getInput('tylko_opinie');
		if (TRUE === is_null($GLOBALS['wanted_only_opinions']))
			$GLOBALS['wanted_only_opinions'] = FALSE;

		$GLOBALS['wanted_article_type'] = $this->getInput('type');
		if (TRUE === is_null($GLOBALS['wanted_article_type']))
			$GLOBALS['wanted_article_type'] = "both";
	}

	private function getArticlePriceParam($article_html)
	{
		$premium_element = $article_html->find('ARTICLE#leftColumnBox DIV.paywall', 0);
		if (FALSE === is_null($premium_element))
			return "premium";
		else
			return "free";
	}

	private function getArticleOpinionParam($article_html)
	{
		$title_element = $article_html->find('H1.mainTitle', 0);
		if (FALSE === is_null($title_element))
		{
			$title = $title_element->plaintext;
			foreach($GLOBALS['opinions_params'] as $param)
			{
				if (FALSE !== strpos($title, $param))
					return TRUE;
			}
			return FALSE;
		}
		else
			return FALSE;
	}

	private function meetsConditions($article_html)
	{
		$article_price_param = $this->getArticlePriceParam($article_html);
		$article_opinion_param = $this->getArticleOpinionParam($article_html);
		$is_price_param_fullfiled = FALSE;
		$is_opinion_param_fullfiled = FALSE;
		if ('both' === $GLOBALS['wanted_article_type'])
		{
			$is_price_param_fullfiled = TRUE;
		}
		else if ($article_price_param === $GLOBALS['wanted_article_type'])
		{
			$is_price_param_fullfiled = TRUE;
		}
		if (FALSE === $GLOBALS['wanted_only_opinions'])
		{
			$is_opinion_param_fullfiled = TRUE;
		}
		else if ($article_opinion_param === $GLOBALS['wanted_only_opinions'])
		{
			$is_opinion_param_fullfiled = TRUE;
		}
		if (TRUE === $is_price_param_fullfiled && TRUE === $is_opinion_param_fullfiled)
		{
			return TRUE;
		}
		else
		{
			return FALSE;
		}
	}

	private function getCustomizedLink($url)
	{
		preg_match('/https?:\/\/(([^\.]*)\..*)/', $url, $output_array);
		return ('https://'.$output_array[2].'-gazetaprawna-pl.cdn.ampproject.org/v/s/'.$output_array[1].'.amp?amp_js_v=0.1');
	}
}
