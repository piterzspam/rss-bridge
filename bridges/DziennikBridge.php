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
									$article_html = str_get_html($this->remove_useless_elements($article_html));
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
		foreach_delete_element_array($main_element, $selectors_array);
		return $main_element->save();
	}

	private function addArticleCanonical($article_html, $url_article_link)
	{
//		$article_html = str_get_html(prepare_article($article_html));
		//https://film.dziennik.pl/recenzje/artykuly/8137058,wytepic-cale-to-bydlo-czego-nauczyla-mnie-osmiornica-maggie-vod-piec-smakow-netflix-hbo-dobrycynk.html
		replace_attribute($article_html, 'IMG[data-original^="http"][src^="data:image/"]', 'src', 'data-original');
		$article_html = str_get_html(prepare_article($article_html));
		$article = $article_html->find('ARTICLE.articleDetail', 0);
		$price_param = $this->getArticlePriceParam($article);
//title
		$title = get_text_plaintext($article, 'H1.mainTitle', $url_article_link);
		$title = getChangedTitle($title);
		$title = $this->getChangedTitle($title, $price_param);
//tags
		$tags = return_tags_array($article, 'DIV.relatedTopicWrapper SPAN.relatedTopic');
//authors
		$author = return_authors_as_string($article, 'DIV.authBox DIV.authDesc');
//date
		$date = get_text_from_attribute($article_html, 'META[property="article:published_time"][content]', 'content', "");


//		$article = str_get_html($article->save());
		foreach_delete_element_containing_subelement($article, 'DIV.frameWrap', 'DIV.promoFrame.pulse2PromoFrame.withDescription.article');
//		$article = str_get_html($article->save());

		clear_paragraphs_from_taglinks($article, 'P.hyphenate, DIV.frameArea', array(
			'/dziennik\.pl\/tagi\//',
			'/dziennik\.pl\/[a-z]*$/',
		));
		$article = str_get_html($article->save());
		$attributes_array[] = "data-text-len";
		$attributes_array[] = "data-scroll";
		$attributes_array[] = "data-async-ad-slot";
		$article = str_get_html(remove_multiple_attributes($article, $attributes_array));
//		$article = str_get_html($this->remove_empty_elements($article->save(), "DIV"));
//		$article = str_get_html($this->remove_empty_elements($article->save(), "SPAN"));
//		$article = str_get_html($article_str);
		replace_part_of_class($article, '.adSlotSibling', 'multiple', ' adSlotSibling', '');
		replace_tag_and_class($article, 'DIV#lead', 'single', 'STRONG', NULL);
		replace_tag_and_class($article, 'P.hyphenate', 'multiple', 'P', "");
		$article = str_get_html($article->save());
//POCZĄTEK kopiowania z gazety prawnej
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
		$article = str_get_html($article->save());
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
//KONIEC kopiowania z gazety prawnej		
		$article = str_get_html($article->save());
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
		format_article_photos($article, 'FIGURE.mainPhoto', TRUE, 'src', 'FIGCAPTION');
		$article = str_get_html($article->save());
		add_style($article, 'FIGURE.photoWrapper', getStylePhotoParent());
		add_style($article, 'FIGURE.photoWrapper IMG', getStylePhotoImg());
		add_style($article, 'FIGCAPTION', getStylePhotoCaption());
		add_style($article, 'BLOCKQUOTE', getStyleQuote());
		insert_html($article, 'DIV#detail', '', '<HR>');
		
		$article = str_get_html($article->save());
//		replace_tag_and_class($article, '[class]', 'multiple', NULL, "");
//		$article = str_get_html($article->save());
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
//		foreach_delete_element_array($article_html, $selectors_array);
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
		if (!is_null($detail = $article_html->find("DIV#detail", 0)))
		{
			$last_child = $detail->last_child();
			while (0 === strlen($last_child->plaintext))
			{
				$last_child = $last_child->prev_sibling();
			}
			if (!is_null($last_link = $last_child->find('A[href*="gazetaprawna.pl"], STRONG', 0)))
			{
				if(check_string_contains_needle_from_array($last_link->plaintext, array("CZYTAJ CAŁOŚĆ", "CZYTAJ WIĘCEJ", "CAŁY WYWIAD", "CAŁY TEKST")))
				{
					return "premium";
				}
			}
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
