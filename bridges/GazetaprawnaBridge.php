<?php
class GazetaprawnaBridge extends BridgeAbstract {
	const NAME = 'Gazetaprawna.pl';
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
				'required' => true,
				'defaultValue' => 3,
			),
			'limit' => array
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
    			    'Darmowe i premium' => 'both',
    			    'Tylko darmowe' => 'free',
    			    'Tylko premium' => 'premium'
    			 )
			),
		)
	);

	public function collectData()
	{
		include 'myFunctions.php';
		$GLOBALS['limit'] = $this->getInput('limit');
		$GLOBALS['my_debug'] = FALSE;
//		$GLOBALS['my_debug'] = TRUE;
		if (TRUE === $GLOBALS['my_debug'])
		{
			$GLOBALS['all_articles_time'] = 0;
			$GLOBALS['all_articles_counter'] = 0;
		}
		$url_articles_list = $this->getInput('url');

		$GLOBALS['opinions_params'] = array(
			'OPINIA',
			'KOMENTARZ'
		);
		$this->setGlobalArticlesParams();
		$GLOBALS['url_articles_list'] = $url_articles_list;

//		$new_urls = array();
//		$new_urls[] = 'https://www.gazetaprawna.pl/magazyn-na-weekend/artykuly/8094787,chinski-blad-europy-opinia-piotr-arak.html';
//		$new_urls[] = 'https://www.gazetaprawna.pl/magazyn-na-weekend/artykuly/8094155,economicus-2020-oto-nominowani-w-kategorii-najlepszy-poradnik-biznesowy.html';
//		$new_urls[] = 'https://www.gazetaprawna.pl/magazyn-na-weekend/artykuly/8094315,rzad-uslugi-publiczne-transfery-likwidacja-nierownosci-opinia.html';
//		$new_urls[] = 'https://serwisy.gazetaprawna.pl/zdrowie/artykuly/8076239,lista-antywywozowa-szczepionka-przeciw-covid-19.html';
		$articles_data = $this->getArticlesUrls();
		foreach ($articles_data as $article_data)
		{
			$this->addArticle(str_get_html($article_data['html']), $article_data['url']);
		}
	}

	public function getName()
	{
		switch($this->queriedContext)
		{
			case 'Parametry':
				if(isset($GLOBALS['author_name']) && "" !== $GLOBALS['author_name'])
				{
					return "Gazetaprawna.pl - ".$GLOBALS['author_name'];
				}
				else
					return parent::getName();
				break;
			default:
				return parent::getName();
		}
	}

	public function getURI()
	{
		switch($this->queriedContext)
		{
			case 'Parametry':
					return $this->getInput('url');
				break;
			default:
				return parent::getURI();
		}
	}

	private function getArticlesUrls()
	{
		$GLOBALS['author_name'] = "";
		$articles_urls = array();
		$articles_titles = array();
		$articles_data = array();
		$url_articles_list = $this->getInput('url');
		while (count($articles_data) < $GLOBALS['limit'] && "empty" != $url_articles_list)
		{
			$returned_array = $this->my_get_html($url_articles_list);
			$html_articles_list = $returned_array['html'];
			if (200 !== $returned_array['code'] || 0 === count($found_leads = $html_articles_list->find('DIV.itarticle')))
			{
				break;
			}
			else
			{
				if (FALSE === strpos($url_articles_list, '/autor/'))
				{
					$GLOBALS['author_name'] = getTextPlaintext($html_articles_list, 'DIV.titleBox H1', $GLOBALS['author_name']);
				}
				else
				{
					$GLOBALS['author_name'] = getTextPlaintext($html_articles_list, 'SPAN.name', $GLOBALS['author_name']);
				}
				foreach($found_leads as $lead)
				{
					if (count($articles_data) >= $GLOBALS['limit'])
					{
						break;
					}
					$title_element = $lead->find('.itemTitle', 0);
					$href_element = $lead->find('A[href]', 0);
					if (FALSE === is_null($title_element) && FALSE === is_null($href_element))
					{
						$title = $title_element->plaintext;
						$url = $href_element->href;
						if (FALSE === in_array($title, $articles_titles) && FALSE === strpos($url, '/dgp/'))
						{
							$returned_array = $this->my_get_html($url, FALSE);
							if (200 === $returned_array['code'] && TRUE === $this->meetsConditions($returned_array['html']))
							{
								$articles_urls[] = $url;
								$articles_titles[] = $title;
								$articles_data[] = array
								(
									'url' => $url,
									'html' => $returned_array['html']->save()
								);
							}
						}
					}
				}
			}
			$url_articles_list = $this->getNextPageUrl($html_articles_list);
		}
		return array_slice($articles_data, 0, $GLOBALS['limit']);
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
			$returned_array = $this->my_get_html($url_article_link, TRUE);
			if (200 !== $returned_array['code'])
			{
				return;
			}
			else
			{
				$article_html = $returned_array['html'];
			}
		}
//		element_print($article_html, 'article_html', '<br>');
		$article = $article_html->find('SECTION.detailSection', 0);
		//title
		$title = getTextPlaintext($article, 'H1.mainTitle', $url_article_link);
		$title = $this->getChangedTitle($title, $price_param);
		//authors
		$author = returnAuthorsAsString($article, 'DIV.authBox A[href*="/autor/"]');
		//date
		$date = getTextAttribute($article_html, 'META[property="article:published_time"][content]', 'content', "");

		$str = $article->save();
		$char_to_replace = chr(hexdec(20));
		$string_to_replace = $char_to_replace.$char_to_replace;
		while (FALSE !== strpos($str, $string_to_replace))
		{
			$str = str_replace($string_to_replace, $char_to_replace, $str);
		}
		$article = str_get_html($str);
		

		deleteAllDescendantsIfExist($article, 'comment');
		deleteAllDescendantsIfExist($article, 'SCRIPT');
		deleteAllDescendantsIfExist($article, 'DIV.widget.video.videoScrollClass');
		deleteAllDescendantsIfExist($article, 'NOSCRIPT');
		deleteAllDescendantsIfExist($article, 'ASIDE#rightColumnBox');
		deleteAllDescendantsIfExist($article, 'DIV#banner_art_video_out');
		deleteAllDescendantsIfExist($article, 'DIV#relatedTopics');
		deleteAllDescendantsIfExist($article, 'DIV#widgetStop');
		deleteAllDescendantsIfExist($article, 'DIV.authorSourceProfile');
		deleteAllDescendantsIfExist($article, 'DIV.streamNews');
		deleteAllDescendantsIfExist($article, 'DIV.plistaDetailDesktop');
		deleteAllDescendantsIfExist($article, 'DIV.commentsBox');
		deleteAllDescendantsIfExist($article, 'DIV.detailAllBoxes');
		deleteAllDescendantsIfExist($article, 'DIV.social-container');
		deleteAllDescendantsIfExist($article, 'DIV.serviceLogoWrapper');
		deleteAllDescendantsIfExist($article, 'DIV.infor-ad');
		deleteAllDescendantsIfExist($article, 'DIV.bottomAdsBox');
		deleteAllDescendantsIfExist($article, 'DIV.promoFrame.pulse2PromoFrame.withDescription.article');
		//https://www.gazetaprawna.pl/magazyn-na-weekend/artykuly/8118990,lowcy-przygod-w-dalekich-krainach-raimund-schulz-rok-1000-valerie-hansen.html
		replaceAttribute($article, '[data-item-uuid]', 'data-item-uuid', NULL);
		//https://www.gazetaprawna.pl/magazyn-na-weekend/artykuly/8118990,lowcy-przygod-w-dalekich-krainach-raimund-schulz-rok-1000-valerie-hansen.html
		replaceAttribute($article, 'IMG[data-original^="http"][src^="data:image/"]', 'src', 'data-original');

		$article = str_get_html($article->save());
		//https://www.gazetaprawna.pl/magazyn-na-weekend/artykuly/8094155,economicus-2020-oto-nominowani-w-kategorii-najlepszy-poradnik-biznesowy.html
		foreach ($article->find('DIV.image') as $photo_container)
		{
			$figcaption_text = "";
			if (FALSE === is_null($next_element = $photo_container->next_sibling()))
			{//jeżeli jest nastepny element
				if (FALSE === is_null($next_element_caption = $next_element->find('DIV.articleImageDescription', 0)))
				{//jeżeli w następnym w elemencie jest podpis to zamiana
					$figcaption_text = $next_element_caption->innertext;
					$next_element->outertext = '';
					$photo_container->innertext = $photo_container->innertext.'<figcaption>'.$figcaption_text.'</figcaption>';
				}
			}
		}
		$article = str_get_html($article->save());


		//https://prawo.gazetaprawna.pl/artykuly/8054640,nowelizacja-ustawy-o-wlasnosci-lokali-eksmisja-utrata-mieszkania.html.amp?amp_js_v=0.1
		//https://www.gazetaprawna.pl/magazyn-na-weekend/artykuly/8094787,chinski-blad-europy-opinia-piotr-arak.html
		//https://www.gazetaprawna.pl/magazyn-na-weekend/artykuly/8094155,economicus-2020-oto-nominowani-w-kategorii-najlepszy-poradnik-biznesowy.html
		//https://www.gazetaprawna.pl/magazyn-na-weekend/artykuly/8094315,rzad-uslugi-publiczne-transfery-likwidacja-nierownosci-opinia.html
		//https://www.gazetaprawna.pl/magazyn-na-weekend/artykuly/8080209,paszport-covidowy-przywileje-podroze-szczepionka-covid.html
		clearParagraphsFromTaglinks($article, 'P.hyphenate, DIV.frameArea', array(
			'/gazetaprawna\.pl\/tagi\//',
			'/gazetaprawna\.pl\/$/',
			'/gazetaprawna\.pl$/',
			'/serwisy\.gazetaprawna\.pl\/.*\/tematy\//'
		));

		//https://serwisy.gazetaprawna.pl/orzeczenia/artykuly/8078983,antonik-burmistrz-prezes-spoldzielnia-mieszkaniowa-brodno-porozumienie-etyka.html
		foreach($article->find('H2') as $element)
		{
			$element->outertext = '<br>'.$element->outertext;
		}

		//https://www.gazetaprawna.pl/firma-i-prawo/artykuly/8077582,konkurs-na-facebooku-polubienie-posta-kara-od-skarbowki.html
		//https://www.gazetaprawna.pl/magazyn-na-weekend/artykuly/8094785,sekularyzacja-przyspiesza-takze-w-polsce-wywiad.html
		addStyle($article, 'DIV.frameArea.srodtytul, DIV.frameArea.pytanie, DIV#lead', array('font-weight: bold;'));

		//https://www.gazetaprawna.pl/magazyn-na-weekend/artykuly/8079800,sztuczna-inteligencja-rezolucja-ue-azja-usa-slowik.html
		//https://www.gazetaprawna.pl/magazyn-na-weekend/artykuly/8094315,rzad-uslugi-publiczne-transfery-likwidacja-nierownosci-opinia.html
		addStyle($article, 'DIV.frameWrap, DIV#lead', array('margin-bottom: 18px;'));

		//https://www.gazetaprawna.pl/magazyn-na-weekend/artykuly/8094155,economicus-2020-oto-nominowani-w-kategorii-najlepszy-poradnik-biznesowy.html
		$article = str_get_html($article->save());
		fix_article_photos($article, 'FIGURE.mainPhoto', TRUE, 'src', 'SPAN.imageDescription');
		//https://wiadomosci.gazeta.pl/wiadomosci/7,114884,26873712,sondazowe-eldorado-polski-2050-i-szymona-holowni-trwa-to-oni.html
		fix_article_photos($article, 'DIV.image', FALSE, 'src', 'FIGCAPTION');
		$article = str_get_html($article->save());
		addStyle($article, 'FIGURE.photoWrapper', getStylePhotoParent());
		addStyle($article, 'FIGURE.photoWrapper IMG', getStylePhotoImg());
		addStyle($article, 'FIGCAPTION', getStylePhotoCaption());

		//Splaszczenie struktury
		replaceAllBiggerOutertextWithSmallerInnertext($article, 'DIV.detailContentWrapper', 'DIV.detailContent');
		replaceAllBiggerOutertextWithSmallerInnertext($article, 'ARTICLE#leftColumnBox', 'DIV.whitelistPremium');
		replaceAllOutertextWithInnertext($article, 'SECTION.detailSection');


		$this->items[] = array(
			'uri' => $url_article_link,
			'title' => $title,
			'timestamp' => $date,
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

	
	private function my_get_html($url, $get_premium = FALSE)
	{
		if (TRUE === $get_premium)
		{
			$context = stream_context_create(
				array(
					'http' => array(
						'ignore_errors' => true,
					    'header'=>"User-Agent:Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)\r\n"
					)
				)
			);
		}
		else
		{
			$context = stream_context_create(
				array(
					'http' => array(
						'ignore_errors' => true
					)
				)
			);
		}
		$page_content = file_get_contents($url, false, $context);
		$code = getHttpCode($http_response_header);
		if (200 !== $code)
		{
			$html_error = createErrorContent($http_response_header);
			$date = new DateTime("now", new DateTimeZone('Europe/Warsaw'));
			$date_string = date_format($date, 'Y-m-d H:i:s');
			$this->items[] = array(
				'uri' => $url,
				'title' => "Error ".$code.": ".$url,
				'timestamp' => $date_string,
				'content' => $html_error
			);
		}
		$page_html = str_get_html($page_content);

		$return_array = array(
			'code' => $code,
			'html' => $page_html,
		);
		return $return_array;
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
