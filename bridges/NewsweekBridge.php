<?php
class NewsweekBridge extends BridgeAbstract {
	const NAME = 'Newsweek';
	const URI = 'https://www.newsweek.pl/';
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
		)
	);
	public function getIcon()
	{
		return 'https://ocdn.eu/newsweekucs/static/ico/favicon-16x16.png';
	}

	public function collectData()
	{
		include 'myFunctions.php';
		$GLOBALS['number_of_wanted_articles'] = $this->getInput('wanted_number_of_articles');
		
		$urls = $this->getArticlesUrls();
		
		foreach($urls as $url)
		{
			$this->addArticle($url);
		}
	}
	
	public function getName()
	{
		if (FALSE === isset($GLOBALS['author_name']))
			return self::NAME;
		else
			$author_name = $GLOBALS['author_name'];

		$url = $this->getInput('url');
		if (is_null($url))
			return self::NAME;
		else
		{
			$url_array = parse_url($this->getInput('url'));
			$host_name = $url_array["host"];
			$host_name = str_replace('www.', '', $host_name);
			$host_name = ucwords($host_name);
		}
		return $host_name." - ".$author_name;
	}
	
	public function getURI()
	{
		$url = $this->getInput('url');
		if (is_null($url))
			return self::URI;
		else
			return $this->getInput('url');
	}

	private function addArticle($url)
	{
		$returned_array = $this->my_get_html($url);
		if (200 !== $returned_array['code'])
			return;
		else
			$article_html = $returned_array['html'];

		$article = $article_html->find('ARTICLE', 0);
		//title
		$title = getTextPlaintext($article, 'H1.detailTitle', $url);
		//authors
		$author = returnAuthorsAsString($article, 'DIV.authorBox H4.name');
		//tags
		$tags = returnTagsArray($article, 'DIV.tags A');
		//date
		$article_data = $article_html->find('SCRIPT[type="application/ld+json"]', 0)->innertext;
		$article_data_parsed = parse_article_data(json_decode($article_data));
		$date = trim($article_data_parsed["datePublished"]);
//		$title = trim($article->find('H1.detailTitle', 0)->innertext);

		if (FALSE === is_null($article_html->find('H1.detailTitle', 0)->find('SPAN.label', 0)))
		{
			$title = '[OPINIA] '.$title;
		}

		//zamiana ramek na linki
		foreach($article->find('DIV.embeddedApp DIV[data-run-module]') as $data_run_module)
		{
			$data_params = $data_run_module->getAttribute('data-params');
			$hrml_decoded = html_entity_decode($data_params);
			$params_decoded=json_decode($hrml_decoded, true);
			$link = redirectUrl($params_decoded['parameters']['url']);
			$link_element = str_get_html('<a href="'.$link.'">'.$link.'</a>');
			$data_run_module->outertext = $link_element->outertext;
		}
		foreach($article->find('DIV.embeddedApp') as $embeddedApp)
		{
			$previous = $embeddedApp->previousSibling();
			if ('script' === strtolower($previous->tag))
			{
				$previous->outertext = '';
				$embeddedApp->outertext = '';
			}
		}
		foreach($article->find('DIV.articleDetail SPAN.descPhoto') as $span)
		{
			while (FALSE === is_null($previous = $span->prev_sibling()))
			{
				if($previous->tag === 'span')
				{
					$next_text=trim($span->innertext);
					$previous_text=trim($previous->innertext);
					$new_text=trim($previous_text.' '.$next_text);
					$previous->innertext = $new_text;
					$span->outertext='';
					$span = $previous;
				}
				else
					break;
			}
			$span->innertext = trim($span->innertext);
		}
		foreach($article->find('P[class=""][data-scroll="paragraph"]') as $empty_class_element)
		{
			$empty_class_element->class = NULL;
			$empty_class_element->setAttribute('data-scroll', NULL);
		}
		replaceAllOutertextWithInnertext($article, '.contentPremium');
		
		//paragrafy, czytaj inne artykuly
		//https://www.newsweek.pl/polska/polityka/nowa-odslona-konfliktu-w-porozumieniu/d18e6y6
		//https://www.newsweek.pl/polska/polityka/paulina-hennig-kloska-w-szeregach-polska-2050-nowa-poslanka-szymona-holowni/5cj9tqx
		$article = str_get_html($article->save());
		deleteAncestorIfContainsTextForEach($article, 'P', array('Czytaj także:', 'Czytaj też:', 'Czytaj więcej:', 'Zobacz:', 'Zobacz także:', 'Zobacz też:', 'Zobacz więcej:'));
		$article = str_get_html($article->save());

		//Przenoszenie tresci premium poziom wyzej


		deleteAllDescendantsIfExist($article, 'comment');
		deleteAllDescendantsIfExist($article, 'script');
		deleteAllDescendantsIfExist($article, 'DIV.offerView');
		deleteAllDescendantsIfExist($article, 'DIV.articleSocials');
		deleteAllDescendantsIfExist($article, 'DIV.detailFeed');
		deleteAllDescendantsIfExist($article, 'DIV.bottomArtticleAds');
		deleteAllDescendantsIfExist($article, 'DIV.onet-ad');
		deleteAllDescendantsIfExist($article, 'DIV#fbComments');
		deleteAllDescendantsIfExist($article, 'UL.breadCrumb');
		deleteAllDescendantsIfExist($article, 'DIV.tags');
//		$article = str_get_html($article->save());

		fix_article_photos($article, 'DIV.artPhoto', FALSE, 'src', 'SPAN');
		$article = str_get_html($article->save());

		addStyle($article, 'P.lead', array('font-weight: bold;'));
		addStyle($article, 'FIGURE.photoWrapper', getStylePhotoParent());
		addStyle($article, 'FIGURE.photoWrapper IMG', getStylePhotoImg());
		addStyle($article, 'FIGCAPTION', getStylePhotoCaption());
		addStyle($article, 'BLOCKQUOTE', getStyleQuote());
//		$article = str_get_html($article->save());
	
		$this->items[] = array(
			'uri' => $url,
			'title' => $title,
			'timestamp' => $date,
			'author' => $author,
			'content' => $article,
			'categories' => $tags
		);
	}

	
	private function getArticlesUrls()
	{
		$articles_urls = array();
		$url_articles_list = $this->getInput('url');
		$GLOBALS['author_name'] = "";
		while (count($articles_urls) < $GLOBALS['number_of_wanted_articles'])
		{
			$returned_array = $this->my_get_html($url_articles_list);
			if (200 !== $returned_array['code'])
			{
				break;
			}
			else
			{
				$html_articles_list = $returned_array['html'];
				if (0 === count($found_hrefs = $html_articles_list->find('DIV.smallItem A.elemRelative[href]')))
				{
					break;
				}
				else
				{
					$GLOBALS['author_name'] = getTextPlaintext($html_articles_list, 'DIV.authorsInfo H1[itemprop="name"]', $GLOBALS['author_name']);
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
		return array_slice($articles_urls, 0, $GLOBALS['number_of_wanted_articles']);
	}

	private function getNextPageUrl($url_articles_list)
	{
		//https://www.newsweek.pl/autorzy/dominika-dlugosz?ajax=1&page=1
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
			preg_match('/.*newsweek\.pl\/[\/\w-]+/', $url_articles_list, $output_array);
			$url_articles_list = $output_array[0].'?ajax=1&page=1';
		}
		return $url_articles_list;
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