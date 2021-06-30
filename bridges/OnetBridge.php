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
		$this->setGlobalArticlesParams();
		
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

	private function setGlobalArticlesParams()
	{
		$GLOBALS['my_debug'] = FALSE;
		if (TRUE === $GLOBALS['my_debug'])
		{
			$GLOBALS['all_articles_time'] = 0;
			$GLOBALS['all_articles_counter'] = 0;
		}
		$GLOBALS['limit'] = intval($this->getInput('limit'));
		$GLOBALS['url_articles_list'] = $this->getInput('url');
		$url_array = parse_url($this->getInput('url'));
		$GLOBALS['prefix'] = $url_array["scheme"].'://'.$url_array["host"];
//		print_var_dump($url_array, "url_array");
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
				$html_articles_list = foreach_delete_element($html_articles_list, 'DIV.breadcrumbs');
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
		$amp_url = $this->getCustomizedLink($article_html, $url);
		$returned_array = my_get_html($amp_url);
		if (200 !== $returned_array['code'])
		{
			return;
		}
		else
		{
			$article_html = str_get_html(prepare_article($returned_array['html']));
		}
		$article_html = str_get_html(prepare_article($article_html));
		
		$article = $article_html->find('article', 0);
		$datePublished = get_json_value($article_html, 'SCRIPT[type="application/ld+json"]', 'datePublished');
		$dateModified = get_json_value($article_html, 'SCRIPT[type="application/ld+json"]', 'dateModified');
		$article = $this->removeElements($article);
		$article = replace_date($article, 'SPAN.date', $datePublished, $dateModified);
		$author = return_authors_as_string($article, 'DIV.dateAuthor SPAN.author');
		$title = get_text_plaintext($article, 'H1.name.headline', $amp_url);

		$article = replace_tag_and_class($article, 'ARTICLE P.hyphenate', 'single', 'STRONG', 'lead');
		$article = format_article_photos($article, 'FIGURE.lead', TRUE, 'src', 'SPAN.source');
		$article = format_article_photos($article, 'FIGURE[!class]', FALSE, 'src', 'SPAN.source');
		$article = move_element($article, 'FIGURE.photoWrapper.mainPhoto', 'STRONG.lead', 'outertext', 'after');
		$article = move_element($article, 'DIV.dateAuthor', 'H1.headline', 'outertext', 'after');
		$article = move_element($article, 'DIV.dateAuthor DIV.dates', 'DIV.dateAuthor', 'outertext', 'before');
		$article = replace_tag_and_class($article, 'DIV.dates', 'single', 'DIV', NULL);
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
			'timestamp' => $datePublished,
			'author' => $author,
			'categories' => $tags,
			'content' => $article
		);

//		echo "<br>Wszystkie $all_articles_counter artykulow zajelo $all_articles_time, <br>średnio ".$all_articles_time/$all_articles_counter ."<br>";
	}

	private function removeElements($article)
	{
		$selectors_array[] = 'comment';
		$selectors_array[] = 'script';
		$selectors_array[] = 'DIV.social-box';
		$selectors_array[] = 'DIV[style="margin:auto;width:300px;"]';
		$article = foreach_delete_element_array($article, $selectors_array);

		$article = foreach_delete_element_containing_elements_hierarchy($article, array('LI', 'A[href*="www.onet.pl/#utm_source"]'));
		$article = foreach_delete_element_containing_elements_hierarchy($article, array('LI', 'A[href="https://www.onet.pl/"]'));
		
		//https://wiadomosci-onet-pl.cdn.ampproject.org/v/s/wiadomosci.onet.pl/tylko-w-onecie/wybory-w-usa-2020-andrzej-stankiewicz-dzis-jest-czas-wielkiej-smuty-w-pis/fcktclw.amp?amp_js_v=0.1
		//https://wiadomosci-onet-pl.cdn.ampproject.org/v/s/wiadomosci.onet.pl/kraj/koronawirus-piotr-glinski-komentuje-milionowe-dofinansowania-dla-artystow/bv013rl.amp?amp_js_v=0.1
		//Gliński łumaczy się kryteriami obiektywnymi.
		//$article = clear_paragraphs_from_taglinks($article, 'P.hyphenate', array('/onet\.pl\/[^\/]*$/'));
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
		return str_get_html($article_str);
	}

	private function getCustomizedLink($article_html, $url)
	{
		if (!is_null($amp_link_element = $article_html->find('LINK[href][rel="amphtml"]', 0)))
		{
			$amp_link = $amp_link_element->href;
			$prefix_edit = str_replace(".", "-", $GLOBALS["prefix"]);
			$amp_link_edit = str_replace("https://", "", $amp_link);
			return $prefix_edit.".cdn.ampproject.org/c/s/".$amp_link_edit;
		}
		else
		{
			return $url;
		}
	}
}