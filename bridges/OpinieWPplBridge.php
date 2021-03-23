<?php
class OpinieWPplBridge extends BridgeAbstract {
	const NAME = 'Opinie WP.pl';
	const URI = 'https://opinie.wp.pl/';
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
			)
		)
	);



	public function collectData()
	{
		include 'myFunctions.php';
		$GLOBALS['limit'] = intval($this->getInput('limit'));
		$GLOBALS['my_debug'] = FALSE;
//		$GLOBALS['my_debug'] = TRUE;
		if (TRUE === $GLOBALS['my_debug'])
		{
			$GLOBALS['all_articles_time'] = 0;
			$GLOBALS['all_articles_counter'] = 0;
		}

		$found_urls = $this->getArticlesUrls();
//		print_var_dump($found_urls);

//		$found_urls[] = 'https://opinie.wp.pl/kataryna-chcialabym-umiec-sie-tym-bawic-gdyby-to-nie-bylo-takie-grozne-6150491372804225a';
//		$found_urls[] = 'https://opinie.wp.pl/zakazac-demonstracji-onr-kataryna-problemy-z-demokracja-6119008400492673a';
//		$found_urls[] = 'https://opinie.wp.pl/apel-o-zawieszenie-stosunkow-dyplomatycznych-z-polska-kataryna-jestem-wsciekla-6222729167030401a';
		foreach($found_urls as $canonical_url)
		{
			$ampproject_url = $this->getAmpprojectLink($canonical_url);
			$ampproject_returned_array = $this->my_get_html($ampproject_url);
			if (200 === $ampproject_returned_array['code'])
			{
				$ampproject_article_html = $ampproject_returned_array['html'];
				$this->addArticleAmp($ampproject_url, $ampproject_article_html);
			}
			else
			{
				$amp_url = $this->getAmpLink($canonical_url);
				$amp_returned_array = $this->my_get_html($amp_url);
				if (200 === $amp_returned_array['code'])
				{
					$amp_article_html = $amp_returned_array['html'];
					$this->addArticleAmp($amp_url, $amp_article_html);
				}
				else
				{
					$canonical_returned_array = $this->my_get_html($canonical_url);
					if (200 === $canonical_returned_array['code'])
					{
						$canonical_article_html = $canonical_returned_array['html'];
						$this->addArticleAmp($canonical_url, $canonical_article_html);
					}
				}
			}
		}
		if (TRUE === $GLOBALS['my_debug'])
			echo "<br>Wszystkie {$GLOBALS['all_articles_counter']} artykulow zajelo {$GLOBALS['all_articles_time']}, <br>średnio ".$GLOBALS['all_articles_time']/$GLOBALS['all_articles_counter'] ."<br>";
		//https://opinie-wp-pl.cdn.ampproject.org/v/s/opinie.wp.pl/kataryna-chcialabym-umiec-sie-tym-bawic-gdyby-to-nie-bylo-takie-grozne-6150491372804225a?amp=1&_js_v=0.1
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

	private function addArticleAmp($url_article, $article_html)
	{
		$article = $article_html->find('main#content', 0);
		$article_data = $article_html->find('SCRIPT[type="application/ld+json"]', 0)->innertext;
		$article_data_parsed = parse_article_data(json_decode($article_data));
		$date = trim($article_data_parsed["datePublished"]);
		$title = trim($article_data_parsed["headline"]);
		$author = $article->find('P[data-st-area="Autor"] SPAN.uppercase', 0)->plaintext;
		$author = str_replace(',', '', $author);
		$tags = return_tags_array($article, 'P.text-grey.tags SPAN A[href*="/tag/"]');
		//Zduplikowane zdjęcie pod "Spotkania z kumplami": https://opinie.wp.pl/wielka-zmiana-mezczyzn-wirus-ja-tylko-przyspieszyl-6604913984391297a?amp=1&_js_v=0.1
		foreach_delete_element($article, 'AMP-IMG[src] IMG');
		foreach_delete_element($article, 'DIV.ad');
		foreach_delete_element($article, 'P.tags');
		foreach_delete_element($article, 'comment');
		foreach_delete_element($article, 'AMP-SOCIAL-SHARE');
		foreach_delete_element($article, 'SECTION.recommendations');
		foreach_delete_element($article, 'DIV.seolinks');
		foreach_delete_element($article, 'A.comment-button');
		foreach_delete_element($article, 'FOOTER#footer');
		foreach_delete_element($article, 'amp-video-iframe');
		//https://opinie.wp.pl/zakazac-demonstracji-onr-kataryna-problemy-z-demokracja-6119008400492673a?amp=1&_js_v=0.1
		//https://opinie.wp.pl/apel-o-zawieszenie-stosunkow-dyplomatycznych-z-polska-kataryna-jestem-wsciekla-6222729167030401a?amp=1&_js_v=0.1
		//https://opinie.wp.pl/kataryna-kaczynski-stawia-na-dude-6213466100516481a?amp=1&_js_v=0.1
		foreach_delete_element_containing_text_from_array($article, 'P, H2', array( 'Masz newsa, zdjęcie lub filmik? Prześlij nam przez', 'dla WP Opinie', 'Zobacz też: ', 'Zobacz też - ', 'Źródło: opinie.wp.pl', 'Czytaj także:', 'Zobacz także:'));
		$this->removeVideoTitles($article);

//		$article = str_get_html($article->save());
		convert_amp_photos($article);
		$article = str_get_html($article->save());

		convert_amp_frames_to_links($article);

		format_article_photos($article, 'DIV.header-image-container', TRUE, 'src', 'DIV.header-author');
		format_article_photos($article, 'DIV.photo.from.amp', FALSE, 'src', 'FIGCAPTION');

		$article = str_get_html($article->save());
		//https://opinie.wp.pl/kataryna-zyjemy-w-okrutnym-swiecie-ale-aborcja-embriopatologiczna-musi-pozostac-opinia-6567085945505921a?amp=1&_js_v=0.1
		add_style($article, 'FIGURE.photoWrapper', getStylePhotoParent());
		add_style($article, 'FIGURE.photoWrapper IMG', getStylePhotoImg());
		add_style($article, 'FIGCAPTION', getStylePhotoCaption());
		add_style($article, 'BLOCKQUOTE', getStyleQuote());
		$article = str_get_html($article->save());

//		$url_article = str_replace('https://opinie-wp-pl.cdn.ampproject.org/v/s/', 'https://', $url_article);
		$this->items[] = array(
			//Fix &amp z linku
			'uri' => htmlentities($url_article, ENT_QUOTES, 'UTF-8'),
			'title' => $title,
			'timestamp' => $date,
			'author' => $author,
			'categories' => $tags,
			'content' => $article
		);
	}

	private function getArticlesUrls()
	{
		$articles_urls = array();
		$url_articles_list = $this->getInput('url');
		while (count($articles_urls) < $GLOBALS['limit'] && "empty" != $url_articles_list)
		{
			$returned_array = $this->my_get_html($url_articles_list);
			if (200 !== $returned_array['code'])
			{
				break;
			}
			//DIV.teasersListing A[class][title][href][data-reactid]
			$html_articles_list = $returned_array['html'];
			if (200 !== $returned_array['code'] || 0 === count($found_hrefs = $html_articles_list->find('DIV.teasersListing A[class][title][href][data-reactid]')))
			{
				break;
			}
			else
			{
				$GLOBALS['author_name'] = get_text_plaintext($html_articles_list, 'H1.author--name', "");
				foreach($found_hrefs as $href_element)
				{
					if(isset($href_element->href))
						$articles_urls[] = 'https://opinie.wp.pl'.$href_element->href;
				}
			}
			$url_articles_list = $this->getNextPageUrl($html_articles_list);
		}
		return array_slice($articles_urls, 0, $GLOBALS['limit']);
	}

	private function getNextPageUrl($html_articles_list)
	{		
		$next_page_element = $html_articles_list->find('A[rel="next"][href^="/autor/"]', 0);
		if (FALSE === is_null($next_page_element) && $next_page_element->hasAttribute('href'))
		{
			return 'https://opinie.wp.pl'.$next_page_element->getAttribute('href');
		}
		else
			return "empty";
	}
	
	private function my_get_html($url)
	{
		$context = stream_context_create(array('http' => array('ignore_errors' => true)));

		if (TRUE === $GLOBALS['my_debug'])
		{
			$start_request = microtime(TRUE);
			$page_content = file_get_contents($url, false, $context);
			$end_request = microtime(TRUE);
			echo "<br>Article  took " . ($end_request - $start_request) . " seconds to complete - url: $url.";
			$GLOBALS['all_articles_counter']++;
			$GLOBALS['all_articles_time'] = $GLOBALS['all_articles_time'] + $end_request - $start_request;
		}
		else
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

	private function removeVideoTitles($article)
	{
		foreach($article->find('amp-video-iframe') as $amp_video_iframe)
		{
			$previous = $amp_video_iframe->prev_sibling();
			if ('div' === $previous->tag)
			{
				$attributes=$previous->getAllAttributes();
				if ('ad' === $attributes['class'])
				{
					$previous_second = $previous->prev_sibling();
					$previous->outertext='';
					if ('h2' === $previous_second->tag)
					{
						$previous_second->outertext='';
					}
				}
			}
			else
			{
				if ('h2' === $previous->tag)
				{
					$previous->outertext='';
				}
			}
		}
	}

	private function getAmpprojectLink($url)
	{
		$new_url = $url.'?amp=1&amp_js_v=0.1';
		$new_url = str_replace('https://', 'https://opinie-wp-pl.cdn.ampproject.org/v/s/', $new_url);
		return $new_url;
	}

	private function getAmpLink($url)
	{
//		$url = 'https://opinie.wp.pl'.$url;
		$new_url = $url.'?amp=1&amp_js_v=0.1';
		return $new_url;
	}
}