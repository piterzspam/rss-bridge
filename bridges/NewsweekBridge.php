<?php
class NewsweekBridge extends BridgeAbstract {
	const NAME = 'Newsweek Autor';
	const URI = 'https://www.newsweek.pl/autorzy/';
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

		if (FALSE === is_null($article_html->find('ARTICLE', 0)))
		{
			$article = $article_html->find('ARTICLE', 0);
		}
		else
		{
			return;
		}

		//author
		$author = $article->find('H4.name', 0)->plaintext;
		//authors
		$author = returnAuthorsAsString($article, 'DIV.authorBox H4.name');
		//tags
		$tags = returnTagsArray($article, 'DIV.tags A');
		//date
		$article_data = $article_html->find('SCRIPT[type="application/ld+json"]', 0)->innertext;
		$article_data_parsed = parse_article_data(json_decode($article_data));
		$date = trim($article_data_parsed["datePublished"]);
		//title
		$title = trim($article->find('H1.detailTitle', 0)->innertext);
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
		//paragrafy, czytaj inne artykuly
		foreach($article->find('P') as $paragraph)
		{
			$paragraph->innertext = trim($paragraph->innertext);
			deleteAncestorIfContainsText($paragraph, 'Czytaj też: ');
			deleteAncestorIfContainsText($paragraph, 'Czytaj także: ');
			deleteAncestorIfContainsText($paragraph, 'Zobacz też: ');
//https://www.newsweek.pl/polska/polityka/nowa-odslona-konfliktu-w-porozumieniu/d18e6y6
			deleteAncestorIfContainsText($paragraph, 'Zobacz więcej: ');
			deleteAncestorIfContainsText($paragraph, 'Zobacz także: ');
			deleteAncestorIfContainsText($paragraph, 'Czytaj więcej: ');
//https://www.newsweek.pl/polska/polityka/paulina-hennig-kloska-w-szeregach-polska-2050-nowa-poslanka-szymona-holowni/5cj9tqx
			deleteAncestorIfContainsText($paragraph, 'Zobacz: ');
		}
		//Przenoszenie tresci premium poziom wyzej
		if (FALSE === is_null($offerView = $article->find('DIV.offerView', 0)) && FALSE === is_null($article->find('DIV.contentPremium', 0)))
		{
			$offerView = $article->find('DIV.offerView', 0);
			foreach($article->find('DIV.contentPremium', 0)->childNodes() as $element)
			{
				$offerView->outertext = $offerView->outertext.$element->outertext;
			}
			deleteDescendantIfExists($article, 'DIV.contentPremium');
		}

		deleteAllDescendantsIfExist($article, 'comment');
		deleteAllDescendantsIfExist($article, 'script');
		deleteAllDescendantsIfExist($article, 'DIV.articleSocials');
		deleteAllDescendantsIfExist($article, 'DIV.detailFeed');
		deleteAllDescendantsIfExist($article, 'DIV.bottomArtticleAds');
		deleteAllDescendantsIfExist($article, 'DIV.onet-ad');
		deleteAllDescendantsIfExist($article, 'DIV#fbComments');
		deleteAllDescendantsIfExist($article, 'UL.breadCrumb');
		deleteAllDescendantsIfExist($article, 'DIV.tags');

		$lead_style = array(
			'font-weight: bold;'
		);
		addStyle($article, 'P.lead', $lead_style);
		addStyle($article, 'DIV.artPhoto', getStylePhotoParent());
		addStyle($article, 'PICTURE', getStylePhotoImg());
		addStyle($article, 'SPAN.descPhoto', getStylePhotoCaption());
		
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