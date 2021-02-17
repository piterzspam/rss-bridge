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
			'wanted_number_of_articles' => array
			(
				'name' => 'Liczba artykułów',
				'type' => 'number',
				'required' => true
			)
		)
	);



	public function collectData()
	{
		include 'myFunctions.php';
		$author_page_url = $this->getInput('url');
		$GLOBALS['number_of_wanted_articles'] = $this->getInput('wanted_number_of_articles');
		$GLOBALS['my_debug'] = FALSE;
//		$GLOBALS['my_debug'] = TRUE;
		if (TRUE === $GLOBALS['my_debug'])
		{
			$GLOBALS['all_articles_time'] = 0;
			$GLOBALS['all_articles_counter'] = 0;
		}

		$found_urls = $this->getArticlesUrls();
//		var_dump_print($found_urls);

//		$found_urls[] = 'https://opinie.wp.pl/kataryna-chcialabym-umiec-sie-tym-bawic-gdyby-to-nie-bylo-takie-grozne-6150491372804225a';
//		$found_urls[] = 'https://opinie.wp.pl/zakazac-demonstracji-onr-kataryna-problemy-z-demokracja-6119008400492673a';
//		$found_urls[] = 'https://opinie.wp.pl/apel-o-zawieszenie-stosunkow-dyplomatycznych-z-polska-kataryna-jestem-wsciekla-6222729167030401a';
		foreach($found_urls as $url)
		{
			$ampproject_url = $this->getAmpprojectLink($url);
			$returned_array = $this->my_get_html($ampproject_url);

			if (200 !== $returned_array['code'])
			{
				$amp_url = $this->getAmpLink($url);
				$returned_array_caoncical = $this->my_get_html($amp_url);
				if (200 === $returned_array_caoncical['code'])
				{
					$article_html = $returned_array_caoncical['html'];
					$this->addArticle($amp_url, $article_html);
				}
			}
			else
			{
				$article_html = $returned_array['html'];
				$this->addArticle($ampproject_url, $article_html);
			}
		}
		if (TRUE === $GLOBALS['my_debug'])
			echo "<br>Wszystkie {$GLOBALS['all_articles_counter']} artykulow zajelo {$GLOBALS['all_articles_time']}, <br>średnio ".$GLOBALS['all_articles_time']/$GLOBALS['all_articles_counter'] ."<br>";
		//https://opinie-wp-pl.cdn.ampproject.org/v/s/opinie.wp.pl/kataryna-chcialabym-umiec-sie-tym-bawic-gdyby-to-nie-bylo-takie-grozne-6150491372804225a?amp=1&_js_v=0.1

	}

	private function addArticle($url_article, $article_html)
	{
//		echo "url_article: $url_article<br>";
		$article = $article_html->find('main#content', 0);
		$article_data = $article_html->find('SCRIPT[type="application/ld+json"]', 0)->innertext;
		$article_data_parsed = parse_article_data(json_decode($article_data));
//		var_dump_print($article_data_parsed);
		$date = trim($article_data_parsed["datePublished"]);
		$title = trim($article_data_parsed["headline"]);
		$author = $article->find('P[data-st-area="Autor"] SPAN.uppercase', 0)->plaintext;
		$author = str_replace(',', '', $author);
		$tags = returnTagsArray($article, 'P.text-grey.tags SPAN A[href*="/tag/"]');

		fixAmpArticles($article);
		formatAmpLinks($article);
		deleteAllDescendantsIfExist($article, 'DIV.ad');
		deleteAllDescendantsIfExist($article, 'P.tags');
		deleteAllDescendantsIfExist($article, 'comment');
		deleteAllDescendantsIfExist($article, 'AMP-SOCIAL-SHARE');
		deleteAllDescendantsIfExist($article, 'SECTION.recommendations');
		deleteAllDescendantsIfExist($article, 'DIV.seolinks');
		deleteAllDescendantsIfExist($article, 'A.comment-button');
		deleteAllDescendantsIfExist($article, 'FOOTER#footer');
		deleteAllDescendantsIfExist($article, 'amp-video-iframe');
		//https://opinie.wp.pl/zakazac-demonstracji-onr-kataryna-problemy-z-demokracja-6119008400492673a?amp=1&_js_v=0.1
		//https://opinie.wp.pl/apel-o-zawieszenie-stosunkow-dyplomatycznych-z-polska-kataryna-jestem-wsciekla-6222729167030401a?amp=1&_js_v=0.1
		//https://opinie.wp.pl/kataryna-kaczynski-stawia-na-dude-6213466100516481a?amp=1&_js_v=0.1
		deleteAncestorIfContainsTextForEach($article, 'P, H2', array( 'Masz newsa, zdjęcie lub filmik? Prześlij nam przez', 'dla WP Opinie', 'Zobacz też: ', 'Zobacz też - ', 'Źródło: opinie.wp.pl', 'Czytaj także:', 'Zobacz także:'));
		$this->removeVideoTitles($article);
		
		foreach($article->find('DIV.header-image-container') as $photo_holder)
		{
			if (FALSE === is_null($photo_image = $photo_holder->find('IMG.header-image', 0)) && FALSE === is_null($photo_author = $photo_holder->find('DIV.header-author', 0)))
				$photo_holder->innertext = $photo_image->outertext.$photo_author->outertext;
		}

		$str = $article->save();
		$article = str_get_html($str);
		//https://opinie.wp.pl/kataryna-zyjemy-w-okrutnym-swiecie-ale-aborcja-embriopatologiczna-musi-pozostac-opinia-6567085945505921a?amp=1&_js_v=0.1
		addStyle($article, 'blockquote', getStyleQuote());
		addStyle($article, 'DIV.header-image-container', getStylePhotoParent());
		addStyle($article, 'IMG.header-image', getStylePhotoImg());
		addStyle($article, 'DIV.header-author', getStylePhotoCaption());

		$url_article = str_replace('https://opinie-wp-pl.cdn.ampproject.org/v/s/', 'https://', $url_article);
		$this->items[] = array(
			'uri' => $url_article,
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
		while (count($articles_urls) < $GLOBALS['number_of_wanted_articles'] && "empty" != $url_articles_list)
		{
			$returned_array = $this->my_get_html($url_articles_list);
			if (200 !== $returned_array['code'])
			{
				break;
			}
			$html_articles_list = $returned_array['html'];
			$articles_list_elements = $html_articles_list->find('DIV[data-st-area="list-topic"]', 0)->first_child()->first_child();
			$found_hrefs_for_current_page = array();
			foreach($articles_list_elements->childNodes() as $articles_list_element)
			{
				if (FALSE === is_null($articles_list_element->find('A', 0)))
				{
					$href = $articles_list_element->find('A', 0)->getAttribute('href');
					$found_hrefs_for_current_page[] = 'https://opinie.wp.pl'.$href;
				}
			}
			if (0 === count($found_hrefs_for_current_page))
			{
				break;
			}
			else
			{
				$articles_urls = array_merge($articles_urls, $found_hrefs_for_current_page);
			}
			$url_articles_list = $this->getNextPageUrl($html_articles_list);
		}
		return array_slice($articles_urls, 0, $GLOBALS['number_of_wanted_articles']);
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
//		$url = 'https://opinie.wp.pl'.$url;
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