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

	public function collectData()
	{
		include 'myFunctions.php';
		$GLOBALS['limit'] = $this->getInput('limit');
		$GLOBALS['my_debug'] = FALSE;
//		$GLOBALS['my_debug'] = TRUE;
//		$GLOBALS['ignore_number'] = 10;
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
			$this->addArticleCanonical(str_get_html($article_data['html']), $article_data['url']);
		}
	}


	private function addArticleAmp($article_html, $url_article_link)
	{
//		$url_article_link = 'https://www.gazetaprawna.pl/magazyn-na-weekend/artykuly/8094155,economicus-2020-oto-nominowani-w-kategorii-najlepszy-poradnik-biznesowy.html';
		$tags = return_tags_array($article_html, 'DIV#relatedTopics A[href]');
		$price_param = $this->getArticlePriceParam($article_html);
		$amp_project_url = $this->getCustomizedLink($url_article_link);
//		$amp_project_url = $this->getCustomizedLink('https://www.gazetaprawna.pl/magazyn-na-weekend/artykuly/8094155,economicus-2020-oto-nominowani-w-kategorii-najlepszy-poradnik-biznesowy.html');
		
//		echo "<br>url: <br>$url_article_link <br>amp_project_url: <br>$amp_project_url<br>";
//		return;
		if ("premium" === $price_param)
		{
			$returned_array = my_get_html($amp_project_url);
			if (200 !== $returned_array['code'])
			{
				return;
			}
			else
			{
				$article_html = $returned_array['html'];
			}
		}
		$article_html = str_get_html(prepare_article($article_html));
		$article = $article_html->find('DIV#page', 0);

		//title
		$title = get_text_plaintext($article, 'H1.headline', $amp_project_url);
		$title = $this->getChangedTitle($title, $price_param);
		//authors
		$author = return_authors_as_string($article, 'DIV.dateAuthor SPAN.author');
		//
		$date = get_json_value($article_html, 'SCRIPT[type="application/ld+json"]', 'datePublished');


		$selectors_array[] = 'DIV.adBox';
		$selectors_array[] = 'DIV.listArticle.listArticlePopular';
		$selectors_array[] = 'DIV.social-box';
		$selectors_array[] = 'qqqqqqqqqqqqq';
		$selectors_array[] = 'qqqqqqqqqqqqq';
		$selectors_array[] = 'qqqqqqqqqqqqq';
		$selectors_array[] = 'qqqqqqqqqqqqq';
		foreach_delete_element_array($article, $selectors_array);
		$article = str_get_html($article->save());
		replace_tag_and_class($article, 'DIV.frameArea', 'multiple', 'P', 'frameArea');
		$article = str_get_html($article->save());

		//https://prawo.gazetaprawna.pl/artykuly/8054640,nowelizacja-ustawy-o-wlasnosci-lokali-eksmisja-utrata-mieszkania.html.amp?amp_js_v=0.1
		//https://www.gazetaprawna.pl/magazyn-na-weekend/artykuly/8094787,chinski-blad-europy-opinia-piotr-arak.html
		//https://www.gazetaprawna.pl/magazyn-na-weekend/artykuly/8094155,economicus-2020-oto-nominowani-w-kategorii-najlepszy-poradnik-biznesowy.html
		//https://www.gazetaprawna.pl/magazyn-na-weekend/artykuly/8094315,rzad-uslugi-publiczne-transfery-likwidacja-nierownosci-opinia.html
		//https://www.gazetaprawna.pl/magazyn-na-weekend/artykuly/8080209,paszport-covidowy-przywileje-podroze-szczepionka-covid.html
		//https://www.gazetaprawna.pl/magazyn-na-weekend/artykuly/8123606,marek-kajs-wilki-jak-sie-zachowac-odstrzal-gdos.html
		//https://www.gazetaprawna.pl/magazyn-na-weekend/artykuly/8123578,problemy-z-astrazeneca-o-co-chodzi.html
		clear_paragraphs_from_taglinks($article, 'P.hyphenate, DIV.frameArea', array(
			'/gazetaprawna\.pl\/tagi\//',
			'/gazetaprawna\.pl\/$/',
			'/gazetaprawna\.pl$/',
			'/serwisy\.gazetaprawna\.pl\/.*\/tematy\//',
			'/gazetaprawna\.pl\/[a-z]*$/',
		));
		$article = str_get_html($article->save());

		$this->items[] = array(
			'uri' => $amp_project_url,
			'title' => $title,
			'timestamp' => $date,
			'author' => $author,
			'categories' => $tags,
			'content' => $article
		);
	}


	private function addArticleCanonical($article_html, $url_article_link)
	{
//		$url_article_link = "https://serwisy.gazetaprawna.pl/samorzad/artykuly/8134645,rynek-mafii-smieciowej-inspektor-ochrony-srodowiska-audyt.html";
		$tags = return_tags_array($article_html, 'DIV#relatedTopics A[href]');
		$price_param = $this->getArticlePriceParam($article_html);
		if ("premium" === $price_param)
		{
			$returned_array = my_get_html($url_article_link, TRUE);
			if (200 !== $returned_array['code'])
			{
				return;
			}
			else
			{
				$article_html = $returned_array['html'];
			}
		}
		//https://www.gazetaprawna.pl/magazyn-na-weekend/artykuly/8118990,lowcy-przygod-w-dalekich-krainach-raimund-schulz-rok-1000-valerie-hansen.html
		//https://www.gazetaprawna.pl/magazyn-na-weekend/artykuly/8123606,marek-kajs-wilki-jak-sie-zachowac-odstrzal-gdos.html
		replace_attribute($article_html, 'IMG[data-original^="http"][src^="data:image/"]', 'src', 'data-original');
		$article_html = str_get_html(prepare_article($article_html));

		$article = $article_html->find('SECTION.detailSection', 0);
		//title
		$title = get_text_plaintext($article, 'H1.mainTitle', $url_article_link);
		$title = $this->getChangedTitle($title, $price_param);
		//authors
		$author = return_authors_as_string($article, 'DIV.authBox A[href*="/autor/"]');
		//date
		$date = get_text_from_attribute($article_html, 'META[property="article:published_time"][content]', 'content', "");

		//Usuwanie zbędnych elementów
		$selectors_array = array();
		$selectors_array[] = 'comment';
		$selectors_array[] = 'SCRIPT';
		$selectors_array[] = 'DIV.widget.video.videoScrollClass';
		$selectors_array[] = 'NOSCRIPT';
		$selectors_array[] = 'ASIDE#rightColumnBox';
		$selectors_array[] = 'DIV#banner_art_video_out';
		$selectors_array[] = 'DIV#relatedTopics';
		$selectors_array[] = 'DIV#widgetStop';
		$selectors_array[] = 'DIV.authorSourceProfile';
		$selectors_array[] = 'DIV.streamNews';
		$selectors_array[] = 'DIV.plistaDetailDesktop';
		$selectors_array[] = 'DIV.commentsBox';
		$selectors_array[] = 'DIV.detailAllBoxes';
		$selectors_array[] = 'DIV.social-container';
		$selectors_array[] = 'DIV.serviceLogoWrapper';
		$selectors_array[] = 'DIV.infor-ad';
		$selectors_array[] = 'DIV.bottomAdsBox';
		$selectors_array[] = 'DIV.promoFrame.pulse2PromoFrame.withDescription.article';
		$selectors_array[] = 'qqqqqqqqqqqqq';
		$selectors_array[] = 'qqqqqqqqqqqqq';
		$selectors_array[] = 'qqqqqqqqqqqqq';
//		$selectors_array[] = '.articleImageDescription';
		foreach_delete_element_array($article, $selectors_array);
		//https://www.gazetaprawna.pl/magazyn-na-weekend/artykuly/8118990,lowcy-przygod-w-dalekich-krainach-raimund-schulz-rok-1000-valerie-hansen.html
		replace_attribute($article, '[data-item-uuid]', 'data-item-uuid', NULL);
		$article = str_get_html($article->save());
//Przeniesienie zdjęcia autora na koniec, żeby się ładnie pobierało pierwsze zdjęcie główne widoczne na liście artykułów
		move_element($article, 'DIV.articleHeading DIV.dateWrapper', 'DIV.articleHeading', 'innertext', 'before');
		$article = str_get_html($article->save());
		move_element($article, 'DIV.articleHeading DIV.author', 'DIV#articleFooterAuthorSource', 'outertext', 'after');
		$article = str_get_html($article->save());
		insert_html($article, 'DIV.author', '<hr>', '', '', '');
		$article = str_get_html($article->save());
//Usuwanie linków do słów kluczowych		
		//https://prawo.gazetaprawna.pl/artykuly/8054640,nowelizacja-ustawy-o-wlasnosci-lokali-eksmisja-utrata-mieszkania.html.amp?amp_js_v=0.1
		//https://www.gazetaprawna.pl/magazyn-na-weekend/artykuly/8094787,chinski-blad-europy-opinia-piotr-arak.html
		//https://www.gazetaprawna.pl/magazyn-na-weekend/artykuly/8094155,economicus-2020-oto-nominowani-w-kategorii-najlepszy-poradnik-biznesowy.html
		//https://www.gazetaprawna.pl/magazyn-na-weekend/artykuly/8094315,rzad-uslugi-publiczne-transfery-likwidacja-nierownosci-opinia.html
		//https://www.gazetaprawna.pl/magazyn-na-weekend/artykuly/8080209,paszport-covidowy-przywileje-podroze-szczepionka-covid.html
		//https://www.gazetaprawna.pl/magazyn-na-weekend/artykuly/8123606,marek-kajs-wilki-jak-sie-zachowac-odstrzal-gdos.html
		//https://www.gazetaprawna.pl/magazyn-na-weekend/artykuly/8123578,problemy-z-astrazeneca-o-co-chodzi.html
		clear_paragraphs_from_taglinks($article, 'P.hyphenate, DIV.frameArea', array(
			'/gazetaprawna\.pl\/tagi\//',
			'/gazetaprawna\.pl\/$/',
			'/gazetaprawna\.pl$/',
			'/serwisy\.gazetaprawna\.pl\/.*\/tematy\//',
			'/gazetaprawna\.pl\/[a-z]*$/',
		));
		$article = str_get_html($article->save());
//Przenoszenie podpisów zdjęć do jednego elementu
		//https://www.gazetaprawna.pl/magazyn-na-weekend/artykuly/8094155,economicus-2020-oto-nominowani-w-kategorii-najlepszy-poradnik-biznesowy.html
		//https://www.gazetaprawna.pl/magazyn-na-weekend/artykuly/8123606,marek-kajs-wilki-jak-sie-zachowac-odstrzal-gdos.html
		foreach_combine_two_elements($article, 'DIV.frameWrap DIV.articleImageAuthor', 1, 1, 'DIV', 'frameWrap', 'DIV.articleImageDescription', 'innertext', 'innertext', 'DIV', 'frameWrap');
		foreach_combine_two_elements($article, 'DIV.image DIV.imageCaptionWrapper', 1, 1, 'DIV', 'frameWrap', 'DIV.articleImageAuthor, DIV.articleImageDescription', 'outertext', 'outertext', 'DIV', 'intext_photo');
		$article = str_get_html($article->save());
		foreach($article->find('DIV.intext_photo DIV.frameWrap') as $element_frameWrap)
		{
			$next_element = $element_frameWrap->find('.frameArea', 0);
			$new_html = '';
			if (FALSE === is_null($next_element))
			{
				$new_html = $new_html.$next_element->innertext;
				while (FALSE === is_null($next_element = $next_element->next_sibling()))
				{
					$new_html = $new_html.'<br>'.$next_element->innertext;
				}
			}
			$element_frameWrap->innertext = $new_html;
		}
//Poprawienie formatowania zdjęć
		//https://www.gazetaprawna.pl/magazyn-na-weekend/artykuly/8094155,economicus-2020-oto-nominowani-w-kategorii-najlepszy-poradnik-biznesowy.html
		format_article_photos($article, 'FIGURE.mainPhoto', TRUE, 'src', 'SPAN.imageDescription');
		format_article_photos($article, 'DIV.intext_photo', FALSE, 'src', 'DIV.frameWrap');
		format_article_photos($article, 'DIV.image', FALSE);//jeżeli było zdjęcie bez podpisu, to nie zostało zamienione na intext_photo
		$article = str_get_html($article->save());
//Poprawienie formatowanie treści artykułu		
		//https://www.gazetaprawna.pl/magazyn-na-weekend/artykuly/8137260,granica-usa-meksyk-jak-rozwiazac-kwestie-migracji.html
		foreach_replace_outertext_with_innertext($article, 'DIV.frameWrap');
		$article = str_get_html($article->save());
		//https://www.gazetaprawna.pl/magazyn-na-weekend/artykuly/8137260,granica-usa-meksyk-jak-rozwiazac-kwestie-migracji.html
		replace_tag_and_class($article, 'DIV.frameArea.srodtytul', 'multiple', 'H2', NULL);
		//https://www.gazetaprawna.pl/magazyn-na-weekend/artykuly/8137260,granica-usa-meksyk-jak-rozwiazac-kwestie-migracji.html
		replace_tag_and_class($article, 'DIV#lead', 'single', 'STRONG', NULL);
		//https://www.gazetaprawna.pl/magazyn-na-weekend/artykuly/8137260,granica-usa-meksyk-jak-rozwiazac-kwestie-migracji.html
		replace_tag_and_class($article, 'DIV.frameArea.wazne', 'multiple', 'BLOCKQUOTE', NULL);
		//https://www.gazetaprawna.pl/magazyn-na-weekend/artykuly/8123557,zycie-w-pandemii-lockdown-wplyw-na-czlowieka.html
		replace_tag_and_class($article, 'DIV.frameArea.wyroznienie', 'multiple', 'BLOCKQUOTE', NULL);
		//https://www.gazetaprawna.pl/magazyn-na-weekend/artykuly/8094785,sekularyzacja-przyspiesza-takze-w-polsce-wywiad.html
		replace_tag_and_class($article, 'DIV.frameArea.pytanie', 'multiple', 'STRONG', NULL);
		replace_tag_and_class($article, 'DIV.frameArea.tresc', 'multiple', 'P', NULL);
		replace_part_of_class($article, '.frameArea', 'multiple', 'frameArea ', '');
		$article = str_get_html($article->save());
/////////////////////////////////////////////////////////////////
/*
		TODO To DO
		//https://serwisy.gazetaprawna.pl/orzeczenia/artykuly/8078983,antonik-burmistrz-prezes-spoldzielnia-mieszkaniowa-brodno-porozumienie-etyka.html
		foreach($article->find('H2') as $element)
		{
			$element->outertext = '<br>'.$element->outertext;
		}
*/
		//https://www.gazetaprawna.pl/firma-i-prawo/artykuly/8077582,konkurs-na-facebooku-polubienie-posta-kara-od-skarbowki.html
		//https://www.gazetaprawna.pl/magazyn-na-weekend/artykuly/8094785,sekularyzacja-przyspiesza-takze-w-polsce-wywiad.html
		//https://www.gazetaprawna.pl/magazyn-na-weekend/artykuly/8123557,zycie-w-pandemii-lockdown-wplyw-na-czlowieka.html
//		add_style($article, '.srodtytul, .pytanie, .wyroznienie, DIV#lead', array('font-weight: bold;'));

		//https://www.gazetaprawna.pl/magazyn-na-weekend/artykuly/8079800,sztuczna-inteligencja-rezolucja-ue-azja-usa-slowik.html
		//https://www.gazetaprawna.pl/magazyn-na-weekend/artykuly/8094315,rzad-uslugi-publiczne-transfery-likwidacja-nierownosci-opinia.html
//		add_style($article, 'DIV#lead', array('margin-bottom: 18px;'));


		add_style($article, 'FIGURE.photoWrapper', getStylePhotoParent());
		add_style($article, 'FIGURE.photoWrapper IMG', getStylePhotoImg());
		add_style($article, 'FIGCAPTION', getStylePhotoCaption());
		add_style($article, 'BLOCKQUOTE', getStyleQuote());

		//Splaszczenie struktury
		foreach_replace_outertext_with_subelement_innertext($article, 'DIV.detailContentWrapper', 'DIV.detailContent');
		foreach_replace_outertext_with_subelement_innertext($article, 'ARTICLE#leftColumnBox', 'DIV.whitelistPremium');
		foreach_replace_outertext_with_innertext($article, 'SECTION.detailSection');


		$amp_project_url = $this->getCustomizedLink($url_article_link);
		$this->items[] = array(
			'uri' => $amp_project_url,
			'title' => $title,
			'timestamp' => $date,
			'author' => $author,
			'categories' => $tags,
			'content' => $article
		);
	}

	private function getArticlesUrls()
	{
		$GLOBALS['author_name'] = "";
		$articles_urls = array();
		$articles_titles = array();
		$articles_data = array();
		$url_articles_list = $this->getInput('url');
//		$ignored_counter = 0;
		while (count($articles_data) < $GLOBALS['limit'] && "empty" != $url_articles_list)
		{
			$returned_array = my_get_html($url_articles_list);
			$html_articles_list = $returned_array['html'];
			if (200 !== $returned_array['code'] || 0 === count($found_leads = $html_articles_list->find('DIV.itarticle')))
			{
				break;
			}
			else
			{
				if (FALSE === strpos($url_articles_list, '/autor/'))
				{
					$GLOBALS['author_name'] = get_text_plaintext($html_articles_list, 'DIV.titleBox H1', $GLOBALS['author_name']);
				}
				else
				{
					$GLOBALS['author_name'] = get_text_plaintext($html_articles_list, 'SPAN.name', $GLOBALS['author_name']);
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
//						if (FALSE === in_array($title, $articles_titles) && FALSE === strpos($url, '/dgp/') && FALSE !== strpos($title, 'linii'))
						if (FALSE === in_array($title, $articles_titles) && FALSE === strpos($url, '/dgp/'))
						{
/*							$ignored_counter++;
							if ($ignored_counter > $GLOBALS['ignore_number'])
							{
								$GLOBALS['ignore_number'] = 20;*/
								$returned_array = my_get_html($url, FALSE);
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
//							}
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
