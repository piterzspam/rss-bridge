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
//		$url_articles_list = preg_replace('/(.*\/autor\/[0-9]+,([a-z]+)-([a-z]+)).*/', '$1', $url_articles_list);

		$GLOBALS['articles_urls'] = array();
		$GLOBALS['articles_titles'] = array();
		$GLOBALS['opinions_params'] = array(
			'OPINIA',
			'KOMENTARZ'
		);
		$this->setGlobalArticlesParams();
		$GLOBALS['url_articles_list'] = $url_articles_list;
/*
		$new_urls = array();
//		$new_urls[] = 'https://www.gazetaprawna.pl/magazyn-na-weekend/artykuly/8094787,chinski-blad-europy-opinia-piotr-arak.html';
		$new_urls[] = 'https://www.gazetaprawna.pl/magazyn-na-weekend/artykuly/8094155,economicus-2020-oto-nominowani-w-kategorii-najlepszy-poradnik-biznesowy.html';
//		$new_urls[] = 'https://www.gazetaprawna.pl/magazyn-na-weekend/artykuly/8094315,rzad-uslugi-publiczne-transfery-likwidacja-nierownosci-opinia.html';
		
		foreach ($new_urls as $url)
		{
			$article_html = getSimpleHTMLDOMCached($url, 86400 * 14);
			$this->addArticle($article_html, $url);
		}
*/

		while (count($this->items) < $GLOBALS['number_of_wanted_articles'])
		{
			$new_urls = $this->getNewUrls();
//			$new_urls = array();
//			$new_urls[] = 'https://serwisy.gazetaprawna.pl/zdrowie/artykuly/8076239,lista-antywywozowa-szczepionka-przeciw-covid-19.html';
			if ("empty" === $new_urls)
			{
				break;
			}
			foreach ($new_urls as $url)
			{
				$returned_array = $this->my_get_html($url, FALSE);
				if (200 === $returned_array['code'])
				{
					$article_html = $returned_array['html'];
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
//			break;
		}
	}

	private function getNewUrls()
	{
		$new_urls = array();
		$returned_array = $this->my_get_html($GLOBALS['url_articles_list'], TRUE);
		$html_articles_list = $returned_array['html'];
		if (200 !== $returned_array['code'] || 0 === count($found_leads = $html_articles_list->find('DIV.itarticle')))
		{
			return "empty";
		}
		else
		{
			foreach($found_leads as $lead)
			{
				//element_print($lead, 'lead', '<br>');
				$title_element = $lead->find('.itemTitle', 0);
				$href_element = $lead->find('A[href]', 0);
				if (FALSE === is_null($title_element) && FALSE === is_null($href_element))
				{
					$title = $title_element->plaintext;
					$url = $href_element->href;
//					var_dump_print($title);
//					var_dump_print($url);
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
//		element_print($new_urls, 'new_urls', '<br>');
//		var_dump_print($new_urls);
//		html_print($new_urls);
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
		$title_element = $article->find('H1.mainTitle', 0);
		$title = $title_element->plaintext;
		$title = $this->getChangedTitle($title, $price_param);
		//authors
		$author = returnAuthorsAsString($article, 'DIV.authBox A[href*="/autor/"]');
		//date
		if (FALSE === is_null($date_element = $article_html->find('META[property="article:published_time"][content]', 0)))
		{
			$date = $date_element->getAttribute('content');
		}
		
		//dla porządku
		foreach ($article->find('[data-item-uuid]') as $element)
		{
			$element->setAttribute('data-item-uuid', NULL);
		}
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

		//https://www.gazetaprawna.pl/magazyn-na-weekend/artykuly/8094155,economicus-2020-oto-nominowani-w-kategorii-najlepszy-poradnik-biznesowy.html
		foreach ($article->find('DIV.image') as $photo_container)
		{
			$img_src = "";
			$img_alt = "";
			$figcaption_text = "";
			if (FALSE === is_null($photo_element = $photo_container->find('IMG[data-original^="http"][src^="data:image/"]', 0)))
			{
				$img_src = $photo_element->getAttribute('data-original');
				if($photo_element->hasAttribute('alt'))
					$img_alt = $photo_element->getAttribute('alt');
			}
			if (FALSE === is_null($image_caption = $photo_container->find('SPAN.caption', 0)))
			{//jeżeli jest podpis zdjęcia
				$figcaption_text = trim($image_caption->plaintext);
				if (0 === strlen($figcaption_text))
				{//jeżeli podpis ma dlugosc 0
					if (FALSE === is_null($next_element = $photo_container->next_sibling()))
					{//jeżeli jest nastepny element
						if (FALSE === is_null($next_element_caption = $next_element->find('DIV.articleImageDescription', 0)))
						{//jeżeli w następnym w elemencie jest podpis to zamiana
							$figcaption_text = $next_element_caption->plaintext;
							$next_element->outertext = '';
						}
					}
				}
			}
			foreach ($article->find('DIV.frameWrap DIV.articleImageDescription') as $description_container)
			{
				$description_container->parent->outertext = '';
			}

			$char_to_replace = chr(hexdec(20));
			$string_to_replace = $char_to_replace.$char_to_replace;
			while (FALSE !== strpos($figcaption_text, $string_to_replace))
			{
				$figcaption_text = str_replace($string_to_replace, $char_to_replace, $figcaption_text);
			}
			$figcaption_text = str_replace('/'.$char_to_replace, '/', $figcaption_text);

			if (0 !== strlen($img_src) && 0 !== strlen($figcaption_text) && 0 === strlen($img_alt))
				$photo_container->outertext = '<figure class="photoWrapper photo"><img src="'.$img_src.'"><figcaption>'.$figcaption_text.'</figcaption></figure>';
			else if (0 !== strlen($img_src) && 0 !== strlen($figcaption_text) && 0 !== strlen($img_alt))
				$photo_container->outertext = '<figure class="photoWrapper photo"><img src="'.$img_src.'" alt="'.$img_alt.'"><figcaption>'.$figcaption_text.'</figcaption></figure>';
			else if (0 !== strlen($img_src))
				$photo_container->outertext = '<figure class="photoWrapper photo"><img src="'.$img_src.'"></figure>';
		}
		foreach ($article->find('FIGURE.mainPhoto') as $main_photo_container)
		{
			$img_src = "";
			$img_alt = "";
			$figcaption_text = "";
			if (FALSE === is_null($photo_element = $main_photo_container->find('IMG[src^="http"]', 0)))
			{
				$img_src = $photo_element->getAttribute('src');
				if($photo_element->hasAttribute('alt'))
					$img_alt = $photo_element->getAttribute('alt');
			}
			if (FALSE === is_null($image_caption = $main_photo_container->find('FIGCAPTION', 0)))
			{//jeżeli jest podpis zdjęcia
				$figcaption_text = trim($image_caption->plaintext);
			}

			$char_to_replace = chr(hexdec(20));
			$string_to_replace = $char_to_replace.$char_to_replace;
			while (FALSE !== strpos($figcaption_text, $string_to_replace))
			{
				$figcaption_text = str_replace($string_to_replace, $char_to_replace, $figcaption_text);
			}
			$figcaption_text = str_replace('/'.$char_to_replace, '/', $figcaption_text);

			if (0 !== strlen($img_src) && 0 !== strlen($figcaption_text) && 0 !== strlen($img_alt))
				$main_photo_container->outertext = '<figure class="photoWrapper mainPhoto"><img src="'.$img_src.'" alt="'.$img_alt.'"><figcaption>'.$figcaption_text.'</figcaption></figure>';
			else if (0 !== strlen($img_src) && 0 !== strlen($figcaption_text) && 0 === strlen($img_alt))
				$main_photo_container->outertext = '<figure class="photoWrapper mainPhoto"><img src="'.$img_src.'"><figcaption>'.$figcaption_text.'</figcaption></figure>';
			else if (0 !== strlen($img_src))
				$main_photo_container->outertext = '<figure class="photoWrapper mainPhoto"><img src="'.$img_src.'"></figure>';
		}

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
//			$element->innertext = '<br>'.$element->innertext;
		}

		//https://www.gazetaprawna.pl/firma-i-prawo/artykuly/8077582,konkurs-na-facebooku-polubienie-posta-kara-od-skarbowki.html
		//https://www.gazetaprawna.pl/magazyn-na-weekend/artykuly/8094785,sekularyzacja-przyspiesza-takze-w-polsce-wywiad.html
		$bold_style = array(
			'font-weight: bold;'
		);
		addStyle($article, 'DIV.frameArea.srodtytul, DIV.frameArea.pytanie, DIV#lead', $bold_style);

		//https://www.gazetaprawna.pl/magazyn-na-weekend/artykuly/8079800,sztuczna-inteligencja-rezolucja-ue-azja-usa-slowik.html
		//https://www.gazetaprawna.pl/magazyn-na-weekend/artykuly/8094315,rzad-uslugi-publiczne-transfery-likwidacja-nierownosci-opinia.html
		$frameWrap_style = array(
			'margin-bottom: 18px;'
		);
		addStyle($article, 'DIV.frameWrap, DIV#lead', $frameWrap_style);

//https://www.gazetaprawna.pl/magazyn-na-weekend/artykuly/8094155,economicus-2020-oto-nominowani-w-kategorii-najlepszy-poradnik-biznesowy.html
		$str = $article->save();
		$article = str_get_html($str);
		addStyle($article, 'FIGURE.photoWrapper', getStylePhotoParent());
		addStyle($article, 'FIGURE.photoWrapper IMG', getStylePhotoImg());
		addStyle($article, 'FIGCAPTION', getStylePhotoCaption());

		//Splaszczenie struktury
		$detailContentWrapper = $article->find('DIV.detailContentWrapper', 0);
		$detailContent = $article->find('DIV.detailContent', 0);
		if (FALSE === is_null($detailContentWrapper) && FALSE === is_null($detailContent))
		{
			$detailContentWrapper->outertext = $detailContent->innertext;
		}

		$leftColumnBox = $article->find('ARTICLE#leftColumnBox', 0);
		$whitelistPremium = $article->find('DIV.whitelistPremium', 0);
		if (FALSE === is_null($leftColumnBox) && FALSE === is_null($whitelistPremium))
		{
			$leftColumnBox->outertext = $whitelistPremium->innertext;
		}
		
		if (FALSE === is_null($detailSection = $article->find('SECTION.detailSection', 0)))
		{
			$article->outertext = $detailSection->innertext;
		}
//		element_print($article, 'article', '<br>');


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
