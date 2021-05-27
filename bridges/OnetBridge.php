<?php
class OnetBridge extends BridgeAbstract {
	const NAME = 'Onet';
	const URI = 'https://wiadomosci.onet.pl/';
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
	
	public function getName()
	{
		if (FALSE === isset($GLOBALS['author_name']))
			return self::NAME;
		else
			$author_name = $GLOBALS['author_name'];

		return "Onet.pl - ".$author_name;
	}
	
	public function getURI()
	{
		$url = $this->getInput('url');
		if (is_null($url))
			return self::URI;
		else
			return $this->getInput('url');
	}

	
	public function collectData()
	{
		include 'myFunctions.php';
		$url_articles_list = $this->getInput('url');
		$GLOBALS['limit'] = intval($this->getInput('limit'));
		$GLOBALS['my_debug'] = FALSE;
//		$GLOBALS['my_debug'] = TRUE;
		if (TRUE === $GLOBALS['my_debug'])
		{
			$GLOBALS['all_articles_time'] = 0;
			$GLOBALS['all_articles_counter'] = 0;
		}
		
		$urls = $this->getArticlesUrls();
//		$urls[] = 'https://wiadomosci-onet-pl.cdn.ampproject.org/v/s/wiadomosci.onet.pl/kraj/koronawirus-piotr-glinski-komentuje-milionowe-dofinansowania-dla-artystow/bv013rl.amp?amp_js_v=0.1';
//		$urls[] = 'https://wiadomosci.onet.pl/tylko-w-onecie/michal-cholewinski-krytykowal-orzeczenie-tk-ws-aborcji-zostal-zdjety-z-anteny/31zq2s2';
//		$urls[] = 'https://wiadomosci.onet.pl/kraj/20-lecie-platformy-obywatelskiej-partia-oklejona-nekrologami-analiza/7cwsve3';
//		$urls[] = 'https://wiadomosci.onet.pl/kraj/koronawirus-piotr-glinski-komentuje-milionowe-dofinansowania-dla-artystow/bv013rl';
		
		foreach($urls as $url)
		{
			$this->addArticle($url);
		}
	
		if (TRUE === $GLOBALS['my_debug'])
			echo "<br>Wszystkie {$GLOBALS['all_articles_counter']} artykulow zajelo {$GLOBALS['all_articles_time']}, <br>średnio ".$GLOBALS['all_articles_time']/$GLOBALS['all_articles_counter'] ."<br>";

	}
	
	private function getArticlesUrls()
	{
		$articles_urls = array();
		$url_articles_list = $this->getInput('url');
		$GLOBALS['author_name'] = "";
		while (count($articles_urls) < $GLOBALS['limit'])
		{
			$returned_array = my_get_html($url_articles_list);
			if (200 !== $returned_array['code'])
			{
				break;
			}
			else
			{
				$html_articles_list = $returned_array['html'];
				foreach_delete_element($html_articles_list, 'DIV.breadcrumbs');
				if (0 === count($found_hrefs = $html_articles_list->find('DIV.listItem A[href][title]')))
				{
					break;
				}
				else
				{
					$GLOBALS['author_name'] = get_text_plaintext($html_articles_list, 'SPAN.name[itemprop="name"]', $GLOBALS['author_name']);
					foreach($found_hrefs as $href_element)
					{
						if(isset($href_element->href) && FALSE === in_array($href_element->href, $articles_urls))
						{
							$articles_urls[] = $href_element->href;
						}
					}
				}
				$url_articles_list = $this->getNextPageUrl($url_articles_list);
			}
		}
		return array_slice($articles_urls, 0, $GLOBALS['limit']);
	}

	private function getNextPageUrl($url_articles_list)
	{
		//https://wiadomosci.onet.pl/autorzy/andrzej-stankiewicz
		//https://wiadomosci.onet.pl/autorzy/andrzej-stankiewicz?ajax=1&page=1
		if (FALSE !== strpos($url_articles_list, '&page='))
		{
			preg_match('/(.*page=)([0-9]+)/', $url_articles_list, $output_array);
			$url_without__page_number = $output_array[1];
			$page_number = $output_array[2];
			$page_number++;
			$url_articles_list = $url_without__page_number.$page_number;
		}
		else
		{
			preg_match('/.*onet\.pl\/[\/\w-]+/', $url_articles_list, $output_array);
			$url_articles_list = $output_array[0].'?ajax=1&page=1';
		}
		return $url_articles_list;
	}

	private function addArticle($url)
	{
		$returned_array = my_get_html($url);
		if (200 !== $returned_array['code'])
		{
			return;
		}
		else
		{
			$article_html = $returned_array['html'];
		}
		$tags = return_tags_array($article_html, 'DIV#relatedTopics A[href]');
		$amp_url = $this->getCustomizedLink($url);
		$returned_array = my_get_html($amp_url);
		if (200 !== $returned_array['code'])
		{
			return;
		}
		else
		{
			$article_html = $returned_array['html'];
		}
		//date
		$article_data = $article_html->find('SCRIPT[type="application/ld+json"]', 0)->innertext;
		$article_data_parsed = parse_article_data(json_decode($article_data));
		$date = trim($article_data_parsed["datePublished"]);
		
		$article_html = str_get_html(prepare_article($article_html));
		$article = $article_html->find('article', 0);
/*
//		foreach_delete_element($article, 'AMP-IMG[src] IMG');
		$article = convert_iframes_to_links($article);
		convert_amp_photos($article);
		$article = str_get_html($article->save());
		$article = fix_all_photos_attributes($article);*/
		$article_html = str_get_html(prepare_article($article_html));
		
//		print_element($article, 'article');

		$author = return_authors_as_string($article, 'DIV.dateAuthor SPAN.author');
		$title = get_text_plaintext($article, 'H1.name.headline', $amp_url);
//		$title = $this->getChangedTitle($title);

		$selectors_array[] = 'comment';
		$selectors_array[] = 'script';
		$selectors_array[] = 'DIV.social-box';
		$selectors_array[] = 'DIV[style="margin:auto;width:300px;"]';
		$article = foreach_delete_element_array($article, $selectors_array);
//https://wiadomosci-onet-pl.cdn.ampproject.org/v/s/wiadomosci.onet.pl/tylko-w-onecie/wybory-w-usa-2020-andrzej-stankiewicz-dzis-jest-czas-wielkiej-smuty-w-pis/fcktclw.amp?amp_js_v=0.1
//https://wiadomosci-onet-pl.cdn.ampproject.org/v/s/wiadomosci.onet.pl/kraj/koronawirus-piotr-glinski-komentuje-milionowe-dofinansowania-dla-artystow/bv013rl.amp?amp_js_v=0.1
//Glińskłumaczy się kryteriami obiektywnymi.
//		$article = clear_paragraphs_from_taglinks($article, 'P.hyphenate', array('/onet\.pl\/[^\/]*$/'));
		$article = foreach_delete_element_containing_text_from_array($article, 'LI', array('Więcej informacji i podcastów znajdziesz na stronie głównej Onet.pl'));
		$article = foreach_delete_element_containing_text_from_array($article, 'P.hyphenate', 
			array(
				'Poniżej lista wszystkich dotychczasowych odcinków podcastu',
				'Cieszymy się, że jesteś z nami. Zapisz się na newsletter Onetu, aby otrzymywać od nas najbardziej wartościowe treści'
				)
		);
		

//https://wiadomosci-onet-pl.cdn.ampproject.org/v/s/wiadomosci.onet.pl/tylko-w-onecie/michal-cholewinski-krytykowal-orzeczenie-tk-ws-aborcji-zostal-zdjety-z-anteny/31zq2s2.amp?amp_js_v=0.1
//https://wiadomosci-onet-pl.cdn.ampproject.org/v/s/wiadomosci.onet.pl/kraj/20-lecie-platformy-obywatelskiej-partia-oklejona-nekrologami-analiza/7cwsve3.amp?amp_js_v=0.1
//https://wiadomosci-onet-pl.cdn.ampproject.org/v/s/wiadomosci.onet.pl/tylko-w-onecie/usa-donald-trump-andrzej-stankiewicz-jedyny-donald-ktorego-pokochalo-pis/xkyb0n7.amp?amp_js_v=0.1
//Dalsza część tekstu znajduje się pod wideo
//Agent Tomek w "Onet Rano" przeprasza Beatę Sawicką. Ciąg dalszy tekstu pod wideo:
//Pozostała część tekstu pod materiałem wideo
		$my_array = array(
			'pod materiałem wideo',
			'pod wideo',
			'poniżej materiału wideo'
		);
		foreach($article->find('P.hyphenate') as $paragraph)
		{
			if(str_replace($my_array, '', $paragraph->plaintext) !== $paragraph->plaintext)
			{
				$next_sibling = $paragraph->next_sibling();
				if ('amp-iframe' == strtolower($next_sibling->tag))
				{
					$next_sibling->outertext='';
				}
				$paragraph->outertext='';
			}
		}
		$article = str_get_html($article->save());
		$article = replace_tag_and_class($article, 'ARTICLE P.hyphenate', 'single', 'STRONG', 'lead');
		convert_amp_frames_to_links($article);
		$article = format_article_photos($article, 'FIGURE.lead', TRUE, 'src', 'SPAN.source');
		$article = format_article_photos($article, 'FIGURE[!class]', FALSE, 'src', 'SPAN.source');
		$article = move_element($article, 'FIGURE.photoWrapper.mainPhoto', 'STRONG.lead', 'outertext', 'after');
		$article = move_element($article, 'DIV.dateAuthor', 'H1.headline', 'outertext', 'after');
		$article = foreach_delete_element_containing_elements_hierarchy($article, array('LI', 'A[href*="www.onet.pl/#utm_source"]'));
		$article = foreach_delete_element_containing_elements_hierarchy($article, array('LI', 'A[href="https://www.onet.pl/"]'));
		$article_str = $article->save();
		foreach($article->find('UL LI A[href*="onet.pl/"][target="_top"]') as $element)
		{
			$parent = $element->parent;
			if (is_null($parent->prev_sibling()) && is_null($parent->next_sibling()))
			{
				$article_str = str_replace($parent->parent->outertext, '', $article_str);
			}
		}
		if (FALSE === is_null($data_element = $article->find('DIV.dateAuthor', 0)))
		{
			$data_element_innertext = $data_element->innertext;
			$span_string = "";
			foreach($article->find('DIV.dateAuthor SPAN') as $element)
			{
				$span_string = $span_string.$element->outertext;
			}
			$article_str = str_replace($data_element_innertext, $span_string, $article_str);
		}
		$article = str_get_html($article_str);
		$article = move_element($article, 'DIV.dateAuthor SPAN.date', 'DIV.dateAuthor', 'outertext', 'before');
		$article = replace_tag_and_class($article, 'SPAN.date', 'single', 'DIV', NULL);
		$article = replace_tag_and_class($article, 'SPAN.author', 'multiple', 'DIV', NULL);
		$article = move_element($article, 'DIV.dateAuthor', 'ARTICLE', 'innertext', 'after');
		$article = insert_html($article, 'DIV.dateAuthor', '<HR>', '');

		//https://opinie.wp.pl/kataryna-zyjemy-w-okrutnym-swiecie-ale-aborcja-embriopatologiczna-musi-pozostac-opinia-6567085945505921a?amp=1&_js_v=0.1
		$article = add_style($article, 'FIGURE.photoWrapper', getStylePhotoParent());
		$article = add_style($article, 'FIGURE.photoWrapper IMG', getStylePhotoImg());
		$article = add_style($article, 'FIGCAPTION', getStylePhotoCaption());
		$article = add_style($article, 'BLOCKQUOTE', getStyleQuote());

		$this->items[] = array(
			'uri' => $amp_url,
			'title' => getChangedTitle($title),
			'timestamp' => $date,
			'author' => $author,
			'categories' => $tags,
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