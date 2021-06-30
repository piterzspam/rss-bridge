<?php
class TygodnikInteriaBridge extends BridgeAbstract {
	const NAME = 'Tygodnik Interia';
	const URI = 'https://tygodnik.interia.pl/';
	const DESCRIPTION = 'No description provided';
	const MAINTAINER = 'No maintainer';
	const CACHE_TIMEOUT = 86400;

	const PARAMETERS = array
	(
		'Parametry' => array
		(
			'limit' => array
			(
				'name' => 'Liczba artykułów',
				'type' => 'number',
				'required' => true,
				'title' => 'Liczba artykułów',
				'defaultValue' => 3,
			),
		)
	);

	public function collectData()
	{
		include 'myFunctions.php';
		$GLOBALS['limit'] = $this->getInput('limit');		
		$found_urls = $this->getArticlesUrls();
		foreach($found_urls as $url)
		{
			$this->addArticle($url);
		}
	}
	
	private function my_get_html($url, $more_pages = FALSE)
	{
		if (TRUE === $more_pages)
		{
			$context = stream_context_create(
				array(
					'http' => array(
						'ignore_errors' => true,
    					'header' => "X-Requested-With: XMLHttpRequest\r\n"
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
		if (FALSE === $more_pages)
		{
			$page_content = str_get_html($page_content);
		}

		$return_array = array(
			'code' => $code,
			'html' => $page_content,
		);
		return $return_array;
	}

	private function getArticlesUrls()
	{
		$articles_urls = array();
//		$articles_urls[] = 'https://tygodnik.interia.pl/news-kolejowy-efekt-motyla-jedna-zmiana-wplywa-na-cala-polske,nId,5029602';
		$url_articles_list = 'https://tygodnik.interia.pl/frontpage-mixer-ajax-elements,nPack,1';
		$if_next_page_exists = TRUE;

		while (count($articles_urls) < $GLOBALS['limit'] && TRUE === $if_next_page_exists)
		{
			$returned_array = $this->my_get_html($url_articles_list, TRUE);
			if (200 !== $returned_array['code'])
			{
				break;
			}
			$json_articles_list = $returned_array['html'];
			$parsed_article_data = parse_article_data(json_decode($json_articles_list));
			$if_next_page_exists = $parsed_article_data["hasMoreItems"];
			$html_articles_list = str_get_html($parsed_article_data["html"]);

			if (0 === count($found_hrefs = $html_articles_list->find('A.tile-magazine-thumb[href]')))
			{
				break;
			}
			else
			{
				foreach($found_hrefs as $href_element)
				{
					if(isset($href_element->href))
						$articles_urls[] = 'https://tygodnik.interia.pl'.$href_element->href;
				}
			}
			$url_articles_list = $this->getNextPageUrl($url_articles_list);
		}
		return array_slice($articles_urls, 0, $GLOBALS['limit']);
	}

	private function getNextPageUrl($url_articles_list)
	{
		//https://tygodnik.interia.pl/frontpage-mixer-ajax-elements,nPack,1
		preg_match('/(.*nPack,)([0-9]+)/', $url_articles_list, $output_array);
		$url_without__page_number = $output_array[1];
		$page_number = $output_array[2];
		$page_number++;
		$url_articles_list = $url_without__page_number.$page_number;
		return $url_articles_list;
	}

	private function addArticle($url_article)
	{
		$returned_array = $this->my_get_html($url_article);
		if (200 !== $returned_array['code'])
		{
			return;
		}
		$article = $returned_array['html']->find('BODY', 0);
		
		//ustawienie urla zdjęcia głównego
		foreach($article->find('script') as $script)
		{
			if (strtolower($script->tag) === 'script')
			{
				if(FALSE !== strpos($script->innertext, 'bgUrl'))
				{
					preg_match_all('/(http.*?)\'/', $script->innertext, $output_array);
					$main_photo_url = $output_array[1][0];
					$script->outertext = '';
					$script->outertext = '<img src="'.$main_photo_url.'">';
				}
			}
		}

		//ustawienie urli zdjęć w tekście
		foreach($article->find('ASIDE[id^="embed-photo"]') as $photo_element)
		{
			$embed_type = $photo_element->find('SPAN.embed-type', 0);
			$embed_work_detail = $photo_element->find('DIV.embed-work-detail', 0);
			$embed_work_detail->innertext = $embed_type->outertext.$embed_work_detail->innertext;
			$embed_type->outertext = '';
			$photo_script = $photo_element->find('script', 0);
			preg_match_all('/(http.*?)\'/', $photo_script->innertext, $output_array);
			$photo_url = $output_array[1][0];
			$photo_script->outertext = '';
			$photo_script->outertext = '<img src="'.$photo_url.'">';
		}
		//lead
		$article = foreach_delete_element($article, 'SPAN.article-lead-bg-letter');
		$lead = $article->find('P.article-lead', 0);
		$new_lead_text_part1 = trim($article->find('SPAN.article-lead-first-letter', 0)->plaintext);
		$new_lead_text_part2 = trim($article->find('SPAN.article-lead-without-first-letter', 0)->plaintext);
		$lead->innertext = '<span>'.$new_lead_text_part1.$new_lead_text_part2.'</span>';

		//tytul
		if (FALSE === is_null($title_element = $article->find('H1.top-title', 0)))
			$title = trim($title_element->plaintext);
		else
			$title = $url_article;

		//autor
		if (FALSE === is_null($author_element = $article->find('SPAN.top-author', 0)))
			$author = trim($author_element->plaintext);
		else
			$author = "";

		//data
		if (FALSE === is_null($date_element = $article->find('SPAN.top-date', 0)))
			$date = trim($date_element->plaintext);
		else
			$date = "";
		$date_array = explode('.', $date);
		$last = count($date_array)-1;
		$date_array[$last] = '20'.$date_array[$last];
		$date = implode('.', $date_array);
		
		$selectors_array[] = 'DIV.box.ad';
		$selectors_array[] = 'SPAN.embed-photo-img-container';
		$selectors_array[] = 'SPAN.embed-photo-square';
		$selectors_array[] = 'comment';
		$selectors_array[] = 'script';
		$selectors_array[] = 'SPAN.page-header';
		$selectors_array[] = 'DIV#nav-bar';
		$selectors_array[] = 'SPAN.top-bg';
		$selectors_array[] = 'SPAN#top-icon1';
		$selectors_array[] = 'DIV#fb-root';
		$selectors_array[] = 'FOOTER.page-footer';
		$selectors_array[] = 'DIV.container.page-footer-main';
		$selectors_array[] = 'ASIDE.embed-article-list';
		$selectors_array[] = 'DIV.main-content.col-md-8.col-lg-8';
		$selectors_array[] = 'SPAN.page-header';
		$selectors_array[] = 'DIV#adBox625';
		$selectors_array[] = 'FOOTER.article-footer';
		$selectors_array[] = 'HEADER.article-header';
		$article = foreach_delete_element_array($article, $selectors_array);

		//Przesunięcie artykułu wyżej w drzewie
		if (FALSE === is_null($container_outer = $article->find('DIV.container-outer', 0)) && FALSE === is_null($article_body = $article->find('DIV.article-body', 0)))
		{
			$container_outer->outertext = $article_body->outertext;
		}


		
		$article = replace_tag_and_class($article, 'P.article-lead', 'single', 'STRONG', NULL);
		$article = add_style($article, 'ASIDE[id^="embed-photo"]', getStylePhotoParent());
		$article = add_style($article, 'DIV.embed-thumbnail', getStylePhotoImg());
		$article = add_style($article, 'DIV.embed-work-detail', getStylePhotoCaption());
		//https://tygodnik.interia.pl/news-kolejowy-efekt-motyla-jedna-zmiana-wplywa-na-cala-polske,nId,5029602
		$article = add_style($article, 'BLOCKQUOTE', getStyleQuote());
		//Ramka IG
		//https://tygodnik.interia.pl/news-mowili-ze-boli-przez-stres-lata-blednych-diagnoz,nId,5029594
		//https://tygodnik.interia.pl/news-z-bolu-nie-wiedzialam-co-robic-gryzlam-sciany-dominika-strac,nId,4984520

		$this->items[] = array(
			'uri' => $url_article,
			'title' => $title,
			'timestamp' => $date,
			'author' => $author,
			'content' => $article
		);
	}
}