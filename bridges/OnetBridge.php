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
			$returned_array = $this->my_get_html($url_articles_list);
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
		$returned_array = $this->my_get_html($url);
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
		$returned_array = $this->my_get_html($amp_url);
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

		$article = $article_html->find('article', 0);
//		foreach_delete_element($article, 'AMP-IMG[src] IMG');
		convert_iframes_to_links($article);
		$article = str_get_html($article->save());
		convert_amp_photos($article);
		$article = str_get_html($article->save());
		fix_all_photos_attributes($article);
		$article = str_get_html($article->save());

//		print_element($article, 'article');

		$author = return_authors_as_string($article, 'DIV.dateAuthor SPAN.author');
		$title = get_text_plaintext($article, 'H1.name.headline', $amp_url);
		$title = $this->getChangedTitle($title);

		foreach_delete_element($article, 'comment');
		foreach_delete_element($article, 'script');
		foreach_delete_element($article, 'DIV.social-box');
		foreach_delete_element($article, 'DIV[style="margin:auto;width:300px;"]');
//https://wiadomosci-onet-pl.cdn.ampproject.org/v/s/wiadomosci.onet.pl/tylko-w-onecie/wybory-w-usa-2020-andrzej-stankiewicz-dzis-jest-czas-wielkiej-smuty-w-pis/fcktclw.amp?amp_js_v=0.1
//https://wiadomosci-onet-pl.cdn.ampproject.org/v/s/wiadomosci.onet.pl/kraj/koronawirus-piotr-glinski-komentuje-milionowe-dofinansowania-dla-artystow/bv013rl.amp?amp_js_v=0.1
//Glińskłumaczy się kryteriami obiektywnymi.
		clear_paragraphs_from_taglinks($article, 'P.hyphenate', array('/onet\.pl\/[^\/]*$/'));
		$article = str_get_html($article->save());
		foreach_delete_element_containing_elements_hierarchy($article, array('ul', 'li', 'A[href*="onet.pl"][target="_top"]'));
		foreach_delete_element_containing_text_from_array($article, 'LI', array('Więcej informacji i podcastów znajdziesz na stronie głównej Onet.pl'));
		foreach_delete_element_containing_text_from_array($article, 'P.hyphenate', 
			array(
				'Poniżej lista wszystkich dotychczasowych odcinków podcastu',
				'Cieszymy się, że jesteś z nami. Zapisz się na newsletter Onetu, aby otrzymywać od nas najbardziej wartościowe treści'
				)
		);
		$article = str_get_html($article->save());

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
		convert_amp_frames_to_links($article);
		$article = str_get_html($article->save());
		format_article_photos($article, 'FIGURE.lead', TRUE, 'src', 'SPAN.source');
		$article = str_get_html($article->save());
		format_article_photos($article, 'FIGURE[!class]', FALSE, 'src', 'SPAN.source');
		$article = str_get_html($article->save());
		//https://opinie.wp.pl/kataryna-zyjemy-w-okrutnym-swiecie-ale-aborcja-embriopatologiczna-musi-pozostac-opinia-6567085945505921a?amp=1&_js_v=0.1
		add_style($article, 'FIGURE.photoWrapper', getStylePhotoParent());
		add_style($article, 'FIGURE.photoWrapper IMG', getStylePhotoImg());
		add_style($article, 'FIGCAPTION', getStylePhotoCaption());
		add_style($article, 'BLOCKQUOTE', getStyleQuote());
		$article = str_get_html($article->save());

		$this->items[] = array(
			'uri' => $amp_url,
			'title' => $title,
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
	
	private function my_get_html($url)
	{
		$context = stream_context_create(array('http' => array('ignore_errors' => true)));
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
}