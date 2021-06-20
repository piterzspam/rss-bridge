<?php
class DziennikBridge extends BridgeAbstract {
	const NAME = 'Dziennik.pl';
	const URI = 'https://www.dziennik.pl/';
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
				if(isset($GLOBALS['author_name']) && 1 < strlen($GLOBALS['author_name']))
				{
					return "Dziennik.pl - ".ucfirst($GLOBALS['author_name']);
				}
				else
				{
					return parent::getName();
				}
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

/*
	public function getIcon()
	{
		return 'https://c.disquscdn.com/uploads/forums/349/4323/favicon.png';
	}
*/


	public function collectData()
	{
		include 'myFunctions.php';
		$this->setGlobalArticlesParams();
		$articles_data = $this->getArticlesUrls();
		foreach ($articles_data as $article_data)
		{
			$this->addArticleCanonical(str_get_html($article_data['html']), $article_data['url']);
		}
	}

	private function setGlobalArticlesParams()
	{
		$GLOBALS['limit'] = intval($this->getInput('limit'));
		$GLOBALS['my_debug'] = FALSE;
//		$GLOBALS['my_debug'] = TRUE;
//		$GLOBALS['ignore_number'] = 10;
		if (TRUE === $GLOBALS['my_debug'])
		{
			$GLOBALS['all_articles_time'] = 0;
			$GLOBALS['all_articles_counter'] = 0;
		}
		$GLOBALS['opinions_params'] = array(
			'OPINIA',
			'KOMENTARZ',
			'FELIETON',
		);
		$GLOBALS['url_articles_list'] = $this->getInput('url');
		$GLOBALS['wanted_only_opinions'] = $this->getInput('tylko_opinie');
		if (TRUE === is_null($GLOBALS['wanted_only_opinions']))
		{
			$GLOBALS['wanted_only_opinions'] = FALSE;
		}
		$GLOBALS['wanted_article_type'] = $this->getInput('type');
		if (TRUE === is_null($GLOBALS['wanted_article_type']))
		{
			$GLOBALS['wanted_article_type'] = "both";
		}
	}

	private function getArticlesUrls()
	{
		$GLOBALS['author_name'] = "";
		$articles_urls = array();
		$articles_titles = array();
		$articles_data = array();
		$url_articles_list = $GLOBALS['url_articles_list'];
//		$ignored_counter = 0;
		while (count($articles_data) < $GLOBALS['limit'] && "empty" != $url_articles_list)
		{
			$returned_array = my_get_html($url_articles_list);
			$html_articles_list = $returned_array['html'];
			if (200 !== $returned_array['code'] || 0 === count($found_leads = $html_articles_list->find('DIV.boxArticleList DIV.itarticle')))
			{
				break;
			}
			else
			{
				$GLOBALS['author_name'] = get_text_plaintext($html_articles_list, 'SECTION.stream DIV.titleBox H1, SECTION.stream HEADER.authorInfo H1', $GLOBALS['author_name']);
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
						$title = trim($title_element->plaintext);
						$url = $href_element->href;
//						$url = "https://wiadomosci.dziennik.pl/opinie/artykuly/8129369,mobbing-szkola-filmowa-przemoc-aktor-studia-piotr-bartuszek.html";
//						$url = "https://film.dziennik.pl/recenzje/artykuly/8137058,wytepic-cale-to-bydlo-czego-nauczyla-mnie-osmiornica-maggie-vod-piec-smakow-netflix-hbo-dobrycynk.html";
//						if (FALSE === in_array($title, $articles_titles) && FALSE === strpos($url, '/dgp/') && FALSE !== strpos($title, 'linii'))
						if (!in_array($title, $articles_titles))
						{
/*							$ignored_counter++;
							if ($ignored_counter > $GLOBALS['ignore_number'])
							{
								$GLOBALS['ignore_number'] = 20;*/
								$returned_array = my_get_html($url);
								if (200 === $returned_array['code'])
								{
									$article_html = $returned_array['html'];
//									$article_html = str_get_html($this->remove_useless_elements($article_html));
									if ($this->meetsConditions($article_html))
									{
										$articles_urls[] = $url;
										$articles_titles[] = $title;
										$articles_data[] = array
										(
											'url' => $url,
											'html' => $article_html->save(),
										);
									}
								}
//							}
						}
					}
				}
			}
			$url_articles_list = $this->getNextPageUrl($html_articles_list);
		}
		return array_slice($articles_data, 0, $GLOBALS['limit']);
//		return array_slice($articles_data, 5, $GLOBALS['limit']);
	}
	
	private function getNextPageUrl($html_articles_list)
	{		
		$next_page_element = $html_articles_list->find('DIV.pagination A.next[href]', 0);
		if (FALSE === is_null($next_page_element) && $next_page_element->hasAttribute('href'))
		{
			return $next_page_element->getAttribute('href');
		}
		else
			return "empty";
	}

	private function edit_gazetaprawna_article($main_element)
	{
//POCZĄTEK kopiowania z gazety prawnej
//Przenoszenie podpisów zdjęć do jednego elementu
		//https://www.gazetaprawna.pl/magazyn-na-weekend/artykuly/8094155,economicus-2020-oto-nominowani-w-kategorii-najlepszy-poradnik-biznesowy.html
		//https://www.gazetaprawna.pl/magazyn-na-weekend/artykuly/8123606,marek-kajs-wilki-jak-sie-zachowac-odstrzal-gdos.html
		$main_element = foreach_combine_two_elements($main_element, 'DIV.frameWrap DIV.articleImageAuthor', 1, 1, 'DIV', 'frameWrap', 'DIV.articleImageDescription', 'innertext', 'innertext', 'DIV', 'frameWrap');
		$main_element = foreach_combine_two_elements($main_element, 'DIV.image DIV.imageCaptionWrapper', 1, 1, 'DIV', 'frameWrap', 'DIV.articleImageAuthor, DIV.articleImageDescription', 'outertext', 'outertext', 'DIV', 'intext_photo');
		foreach($main_element->find('DIV.intext_photo DIV.frameWrap') as $element_frameWrap)
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
		$main_element = format_article_photos($main_element, 'FIGURE.mainPhoto', TRUE, 'src', 'SPAN.imageDescription');
		$main_element = format_article_photos($main_element, 'DIV.intext_photo', FALSE, 'src', 'DIV.frameWrap');
		$main_element = format_article_photos($main_element, 'DIV.image', FALSE);//jeżeli było zdjęcie bez podpisu, to nie zostało zamienione na intext_photo
//Poprawienie formatowanie treści artykułu		
		//https://www.gazetaprawna.pl/magazyn-na-weekend/artykuly/8137260,granica-usa-meksyk-jak-rozwiazac-kwestie-migracji.html
		$main_element = foreach_replace_outertext_with_innertext($main_element, 'DIV.frameWrap');
		//https://www.gazetaprawna.pl/magazyn-na-weekend/artykuly/8137260,granica-usa-meksyk-jak-rozwiazac-kwestie-migracji.html
		$main_element = replace_tag_and_class($main_element, 'DIV.frameArea.srodtytul', 'multiple', 'H2', NULL);
		//https://www.gazetaprawna.pl/magazyn-na-weekend/artykuly/8137260,granica-usa-meksyk-jak-rozwiazac-kwestie-migracji.html
		$main_element = replace_tag_and_class($main_element, 'DIV#lead', 'single', 'STRONG', NULL);
		//https://www.gazetaprawna.pl/magazyn-na-weekend/artykuly/8137260,granica-usa-meksyk-jak-rozwiazac-kwestie-migracji.html
		$main_element = replace_tag_and_class($main_element, 'DIV.frameArea.wazne', 'multiple', 'BLOCKQUOTE', NULL);
		//https://www.gazetaprawna.pl/magazyn-na-weekend/artykuly/8123557,zycie-w-pandemii-lockdown-wplyw-na-czlowieka.html
		$main_element = replace_tag_and_class($main_element, 'DIV.frameArea.wyroznienie', 'multiple', 'BLOCKQUOTE', NULL);
		//https://www.gazetaprawna.pl/magazyn-na-weekend/artykuly/8094785,sekularyzacja-przyspiesza-takze-w-polsce-wywiad.html
		$main_element = replace_tag_and_class($main_element, 'DIV.frameArea.pytanie', 'multiple', 'STRONG', NULL);
		$main_element = replace_tag_and_class($main_element, 'DIV.frameArea.tresc', 'multiple', 'P', NULL);
		$main_element = replace_part_of_class($main_element, '.frameArea', 'multiple', 'frameArea ', '');
//KONIEC kopiowania z gazety prawnej
		return str_get_html($main_element->save());
	}

	private function remove_useless_elements($main_element)
	{
		$selectors_array[] = 'comment';
		$selectors_array[] = 'SCRIPT';
		$selectors_array[] = 'NOSCRIPT';
		$selectors_array[] = 'DIV.social-container';
		$selectors_array[] = 'DIV.bottomAdsBox';
		$selectors_array[] = 'DIV#googleAdsCont';
		$selectors_array[] = 'DIV#relatedTopics';
		$selectors_array[] = 'DIV.detailAllBoxes';
		$selectors_array[] = 'FIGURE.seeAlso';
		$selectors_array[] = 'DIV.infor-ad';
		$selectors_array[] = 'DIV.commentsBox';
		$selectors_array[] = 'DIV.streamNews';
		$selectors_array[] = 'DIV#adoceangplyncgfnrlgo';
		$selectors_array[] = 'DIV.inforAdsRectSrod';
		$selectors_array[] = 'DIV#banner_art_video_out';
		$selectors_array[] = 'DIV.widget.video.videoScrollClass';
		$selectors_array[] = 'DIV#widgetStop';
		$selectors_array[] = 'SOURCE[srcset]';
		$selectors_array[] = 'DIV.inforAdsRectSrod';
		$selectors_array[] = 'DIV.articleHeading DIV.authBox';
		$selectors_array[] = 'DIV.articleHeading DIV.source';
		$selectors_array[] = 'DIV.authorSourceProfile DIV.clear';
		$selectors_array[] = 'DIV.licenceInfo';
		$selectors_array[] = 'DIV.articleFooter';
		$selectors_array[] = 'SPAN.dateModified';
		
//		$selectors_array[] = 'comment';
//		$selectors_array[] = 'comment';
		foreach_delete_element_array($main_element, $selectors_array);
		$main_element = foreach_delete_element_containing_subelement($main_element, 'DIV.frameWrap', 'DIV.promoFrame.pulse2PromoFrame.withDescription.article');
		$main_element = clear_paragraphs_from_taglinks($main_element, 'P.hyphenate, DIV.frameArea', array(
			'/dziennik\.pl\/tagi\//',
			'/dziennik\.pl\/[a-z]*$/',
		));
		$attributes_array = array();
		$attributes_array[] = "data-text-len";
		$attributes_array[] = "data-scroll";
		$attributes_array[] = "data-async-ad-slot";
		$main_element = remove_multiple_attributes($main_element, $attributes_array);
		return str_get_html($main_element->save());
	}

	private function addArticleCanonical($article_html, $url_article_link)
	{
//		$article_html = str_get_html(prepare_article($article_html));
		//https://film.dziennik.pl/recenzje/artykuly/8137058,wytepic-cale-to-bydlo-czego-nauczyla-mnie-osmiornica-maggie-vod-piec-smakow-netflix-hbo-dobrycynk.html
		$article_html = replace_attribute($article_html, 'IMG[data-original^="http"][src^="data:image/"]', 'src', 'data-original');
		$datePublished = get_json_value($article_html, 'SCRIPT[type="application/ld+json"]', 'datePublished');
		$dateModified = get_json_value($article_html, 'SCRIPT[type="application/ld+json"]', 'dateModified');
		$article_html = $this->remove_useless_elements($article_html);
		$price_param = $this->getArticlePriceParam($article_html);
		$article_html = $this->edit_gazetaprawna_article($article_html);
		$article_html = str_get_html(prepare_article($article_html));
		$article_html = move_element($article_html, 'DIV#page DIV.tags', 'DIV#page ARTICLE', 'innertext', 'after');
		$article = $article_html->find('ARTICLE.articleDetail', 0);
		$article = replace_date($article, 'SPAN.datePublished', $datePublished, $dateModified);
//title
		$title = get_text_plaintext($article, 'H1.mainTitle', $url_article_link);
		$title = getChangedTitle($title);
		$title = $this->getChangedTitle($title, $price_param);
//tags
		$tags = return_tags_array($article, 'DIV.relatedTopicWrapper SPAN.relatedTopic');
//authors
		$author = return_authors_as_string($article, 'DIV.authBox SPAN.name');
//date
		$date = get_text_from_attribute($article_html, 'META[property="article:published_time"][content]', 'content', "");


		
		foreach($article->find('DIV.embeddedApp') as $embeddedApp)
		{
			if (!is_null($params_element = $embeddedApp->find("DIV[data-params]", 0)))
			{
				$params = $params_element->getAttribute("data-params");
				$frame_url = get_json_value_from_given_text(html_entity_decode($params), 'url');
				$frame_outertext = get_frame_outertext($frame_url);
				$embeddedApp->outertext = $frame_outertext;
			}
		}
		$article = format_article_photos($article, 'FIGURE.mainPhoto', TRUE, 'src', 'FIGCAPTION');

		$article = replace_part_of_class($article, '.adSlotSibling', 'multiple', ' adSlotSibling', '');
		$article = replace_tag_and_class($article, 'DIV#lead', 'single', 'STRONG', NULL);
		$article = replace_tag_and_class($article, 'SPAN.readingTime', 'single', 'DIV', 'readingTime');
		$article = replace_tag_and_class($article, 'P.hyphenate', 'multiple', 'P', "");

		$article = move_element($article, 'STRONG.lead', 'DIV.readingTime', 'outertext', 'before');
//		$article = foreach_replace_outertext_with_innertext($article, 'DIV.authorSourceProfile HEADER');
		$article = foreach_replace_outertext_with_innertext($article, 'DIV#detail');
		$article = foreach_replace_outertext_with_innertext($article, 'DIV.detailContent');
		$article = foreach_replace_outertext_with_innertext($article, 'DIV.detailContentWrapper');
		$article = foreach_replace_outertext_with_innertext($article, 'DIV.whitelistPremium');
		$article = foreach_replace_outertext_with_innertext($article, 'DIV.dateWrapper');
		$article = foreach_replace_outertext_with_innertext($article, 'DIV.articleHeading');
		$article = foreach_replace_outertext_with_innertext($article, 'HEADER.articleTop');
		$article = foreach_replace_outertext_with_innertext($article, 'qqqqqqqqqqqqqqqqqq');
		$article = foreach_replace_outertext_with_innertext($article, 'qqqqqqqqqqqqqqqqqq');
		$article = foreach_replace_outertext_with_innertext($article, 'qqqqqqqqqqqqqqqqqq');
		$article = foreach_replace_outertext_with_innertext($article, 'qqqqqqqqqqqqqqqqqq');
		$article = foreach_replace_outertext_with_innertext($article, 'qqqqqqqqqqqqqqqqqq');
		$article = foreach_replace_outertext_with_innertext($article, 'qqqqqqqqqqqqqqqqqq');
		$article = foreach_replace_outertext_with_innertext($article, 'qqqqqqqqqqqqqqqqqq');
		$article = foreach_replace_outertext_with_innertext($article, 'qqqqqqqqqqqqqqqqqq');
		$article = foreach_replace_outertext_with_innertext($article, 'qqqqqqqqqqqqqqqqqq');
		$article = insert_html($article, 'DIV.authorSourceProfile', '<HR>');
		$attributes_array = array();
		$attributes_array[] = "id";
		$attributes_array[] = "data-read-time";
		$attributes_array[] = "data-read-time-text";
		$attributes_array[] = "itemprop";
		$article = remove_multiple_attributes($article, $attributes_array);


		$article = add_style($article, 'FIGURE.photoWrapper', getStylePhotoParent());
		$article = add_style($article, 'FIGURE.photoWrapper IMG', getStylePhotoImg());
		$article = add_style($article, 'FIGCAPTION', getStylePhotoCaption());
		$article = add_style($article, 'BLOCKQUOTE', getStyleQuote());
		
//		$article = replace_tag_and_class($article, '[class]', 'multiple', NULL, "");
//		foreach 
//		get_json_value_from_given_text($string, $variable_name);

		$amp_project_url = $this->getCustomizedLink($url_article_link);
		$this->items[] = array(
			'uri' => $amp_project_url,
			'title' => $title,
			'timestamp' => $date,
			'author' => $author,
			'categories' => $tags,
			'content' => $article,
		);
	}

	private function getCustomizedLink($url)
	{
		preg_match('/https?:\/\/(([^\.]*)\..*)/', $url, $output_array);
		return ('https://'.$output_array[2].'-dziennik-pl.cdn.ampproject.org/v/s/'.$output_array[1].'.amp?amp_js_v=0.1');
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

	private function getArticlePriceParam($article_html)
	{
		//Żeby ostatni element był sensowny
//		$selectors_array[] = 'comment';
//		$selectors_array[] = 'SCRIPT';
//		$selectors_array[] = 'NOSCRIPT';
//		$article = foreach_delete_element_array($article_html, $selectors_array);
		//Link nie będący premium (nie jest ostatni) CZYTAJ WIĘCEJ TUTAJ>>>
		//https://wiadomosci.dziennik.pl/opinie/artykuly/8137333,transplciowe-dziecko-krystyna-pawlowicz-titanic-dzieci-kody-kulturowe-iii-rp-pis-po-donald-tusk-zbigniew-ziobro.html
		//Link będący premium CZYTAJ WIĘCEJ w MAGAZYNIE DGP >>>
		//https://wiadomosci.dziennik.pl/opinie/artykuly/8136820,liberal-polityka-zagranica-cywilizacja-spoleczenstwo.html
		//Link będący premium CZYTAJ CAŁOŚĆ NA GAZETAPRAWNA.PL>>>
		//https://wiadomosci.dziennik.pl/opinie/artykuly/8137603,wojna-antoni-macierewicz-katastrofa-smolenska.html
		//Link będący premium CAŁY WYWIAD Z ANDŻELIKĄ BORYS PRZECZYTASZ W ŚRODOWYM WYDANIU DGP
		//https://wiadomosci.dziennik.pl/opinie/artykuly/8127257,andzelika-borys-zatrzymanie-bialorus-wywiad-dgp.html
		//Link będący premium w ostatnim paragrafie przed DIV.inforAdsRectSrod
		//https://wiadomosci.dziennik.pl/opinie/artykuly/8129369,mobbing-szkola-filmowa-przemoc-aktor-studia-piotr-bartuszek.html
		//Napis będący premium w ostatnim paragrafie CZYTAJ WIĘCEJ WE WTORKOWYM "DZIENNIKU GAZECIE PRAWNEJ">>> 
		//https://wiadomosci.dziennik.pl/opinie/artykuly/8138497,sedziowie-lubia-sanki.html
		//Napis będący premium w ostatnim paragrafie CAŁY TEKST DOSTĘPNY W INTERNETOWYM WYDANIU MAGAZYNU "DZIENNIKA GAZETY PRAWNEJ">>>
		//https://wiadomosci.dziennik.pl/opinie/artykuly/8143430,dzieci-spadek-dzietnosci-zjawisko-kara-za-dziecko.html
		//Napis będący premium w ostatnim paragrafie CZYATJ WIĘCEJ W E-DGP>>>
		//https://wiadomosci.dziennik.pl/opinie/artykuly/8182317,czechy-rosja-wybory.html
		//Czytaj więcej w weekendowym wydaniu "Dziennika Gazety Prawnej" 
		//https://wiadomosci.dziennik.pl/opinie/artykuly/8179764,aktywnosc-zawodowa-kobiety-gender-mezczyzni-plec.html
		//Cały wywiad dostępny w internetowym wydaniu "Dziennika Gazety Prawnej" 
		//https://wiadomosci.dziennik.pl/opinie/artykuly/8179724,perspektywy-i-zastosowania-kryptografii-kwantowej.html
		//Napis będący premium w przedostatnim paragrafie CZYTAJ WIĘCEJ WE WTORKOWYM WYDANIU DZIENNIKA GAZETY PRAWNEJ>>>
		//https://wiadomosci.dziennik.pl/opinie/artykuly/8183873,antysemityzm.html
		if (!is_null($detail = $article_html->find("DIV#detail", 0)))
		{
			//https://wiadomosci.dziennik.pl/opinie/artykuly/8179759,kultura-gierek-magazyn.html
			foreach($detail->find('DIV.frameWrap') as $frameArea_tresc)
			{
				if (!is_null($maybe_premium_element = $frameArea_tresc->find('A[href*="gazetaprawna.pl"], STRONG', 0)))
				{
					$maybe_premium_text = $maybe_premium_element->plaintext;
					$maybe_premium_text_decoded = html_entity_decode($maybe_premium_text);
/*					echo "maybe_premium_element=$maybe_premium_element<br>";
					print_element($maybe_premium_element, "maybe_premium_element-element");
//					$last_link_text = $maybe_premium_element->plaintext;
					//https://wiadomosci.dziennik.pl/opinie/artykuly/8182317,czechy-rosja-wybory.html
					print_var_dump($maybe_premium_text, "maybe_premium_text");
					print_var_dump(strpos($maybe_premium_text, ">>>"), 'strpos($maybe_premium_text, ">>>")');
					print_var_dump(strpos($maybe_premium_text, '>>>'), 'strpos($maybe_premium_text, \'>>>\')');
					print_var_dump(strpos($maybe_premium_text_decoded, ">>>"), 'strpos($maybe_premium_text_decoded, ">>>")');
					print_var_dump(strpos($maybe_premium_text_decoded, '>>>'), 'strpos($maybe_premium_text_decoded, \'>>>\')');
					hex_dump($maybe_premium_text);
					hex_dump($maybe_premium_text_decoded);
					hex_dump(">>>");*/
					if(check_string_contains_needle_from_array($maybe_premium_text_decoded, array("CZYTAJ CAŁOŚĆ", "CZYTAJ WIĘCEJ", "Czytaj więcej", "CAŁY WYWIAD", "Cały wywiad", "CAŁY TEKST", ">>>")))
					{
						return "premium";
					}
				}
			}
/*			$last_child = $detail->last_child();
			while (0 === strlen($last_child->plaintext))
			{
				$last_child = $last_child->prev_sibling();
			}*/
		}
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
}
