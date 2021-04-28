<?php
class WPplBridge extends BridgeAbstract {
	const NAME = 'WP.pl';
	const URI = 'https://www.wp.pl/';
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
		$this->setGlobalArticlesParams();
		$found_urls = $this->getArticlesUrls();
//		print_var_dump($found_urls, "found_urls");

//		$found_urls[] = 'https://opinie.wp.pl/kataryna-chcialabym-umiec-sie-tym-bawic-gdyby-to-nie-bylo-takie-grozne-6150491372804225a';
//		$found_urls[] = 'https://opinie.wp.pl/zakazac-demonstracji-onr-kataryna-problemy-z-demokracja-6119008400492673a';
//		$found_urls[] = 'https://opinie.wp.pl/apel-o-zawieszenie-stosunkow-dyplomatycznych-z-polska-kataryna-jestem-wsciekla-6222729167030401a';
		foreach($found_urls as $canonical_url)
		{
			$getAmp = FALSE;
			$getCanonical = FALSE;
//			$ampproject_returned_array = $this->my_get_html($ampproject_url);
			$ampproject_url = $this->getAmpprojectLink($canonical_url);
			$ampproject_returned_array = my_get_html($ampproject_url);
			if (200 === $ampproject_returned_array['code'])
			{
				$ampproject_article_html = $ampproject_returned_array['html'];
				if (!is_null($redirect_element = $ampproject_article_html->find('BODY[onload^="location.replace("]', 0)))
				{
					$getAmp = TRUE;
				}
				else
				{
					$this->addArticleAmp($ampproject_url, $ampproject_article_html);
				}
			}
			else
			{
				$getAmp = TRUE;
			}

			if ($getAmp)
			{
				$amp_url = $this->getAmpLink($canonical_url);
				$amp_returned_array = my_get_html($amp_url);
				if (200 === $amp_returned_array['code'])
				{
					$amp_article_html = $amp_returned_array['html'];
					if (!is_null($redirect_element = $amp_article_html->find('BODY[onload^="location.replace("]', 0)))
					{
						$getCanonical = TRUE;
					}
					else
					{
						$this->addArticleAmp($amp_url, $amp_article_html);
					}
				}
				else
				{
					$getCanonical = TRUE;
				}
			}

			if ($getCanonical)
			{
				$canonical_returned_array = my_get_html($canonical_url);
				if (200 === $canonical_returned_array['code'])
				{
/*					$canonical_article_html = $canonical_returned_array['html'];
					$date = new DateTime("now", new DateTimeZone('Europe/Warsaw'));
					$date_string = date_format($date, 'Y-m-d H:i:s');
					$page_html = array(
						'uri' => $canonical_url,
						'title' => $canonical_url,
						'timestamp' => $date_string,
						'content' => $canonical_article_html
					);*/
					$this->items[] = $canonical_article_html;
				}
				else
				{
					$this->items[] = $canonical_returned_array['html'];
				}
			}
		}
		if (TRUE === $GLOBALS['my_debug'])
			echo "<br>Wszystkie {$GLOBALS['all_articles_counter']} artykulow zajelo {$GLOBALS['all_articles_time']}, <br>średnio ".$GLOBALS['all_articles_time']/$GLOBALS['all_articles_counter'] ."<br>";
		//https://opinie-wp-pl.cdn.ampproject.org/v/s/opinie.wp.pl/kataryna-chcialabym-umiec-sie-tym-bawic-gdyby-to-nie-bylo-takie-grozne-6150491372804225a?amp=1&_js_v=0.1
	}

	private function setGlobalArticlesParams()
	{
		//https://opinie-wp-pl.cdn.ampproject.org/c/s/opinie.wp.pl/kataryna-hackowanie-systemu-rzadowych-obostrzen-zabawa-w-kotka-i-myszke-opinia-6628299841584000a?amp=1
		$GLOBALS['limit'] = intval($this->getInput('limit'));
//		$GLOBALS['my_debug'] = TRUE;
		$GLOBALS['my_debug'] = FALSE;
		$GLOBALS['url_articles_list'] = $this->getInput('url');
		if (TRUE === $GLOBALS['my_debug'])
		{
			$GLOBALS['all_articles_time'] = 0;
			$GLOBALS['all_articles_counter'] = 0;
		}
		$url_array = parse_url($this->getInput('url'));
		$GLOBALS['prefix'] = $url_array["scheme"].'://'.$url_array["host"];
		$GLOBALS['host'] = ucfirst($url_array["host"]);
		$GLOBALS['amp_project_prefix'] = str_replace('.', '-', $GLOBALS['prefix']);
		$GLOBALS['amp_project_prefix'] = $GLOBALS['amp_project_prefix'].".cdn.ampproject.org/c/s/";
	}
	
	public function getName()
	{
/*
		echo 'GLOBALS[\'host\']<br>';
		var_dump($GLOBALS['host']);
		echo '<br><br>GLOBALS[\'author_name\']<br>';
		var_dump($GLOBALS['author_name']);
		echo '<br><br>isset($GLOBALS[\'host\']<br>';
		var_dump(isset($GLOBALS['host']));
		echo '<br><br>isset($GLOBALS[\'author_name\']<br>';
		var_dump(isset($GLOBALS['author_name']));
		echo '<br><br>strlen($GLOBALS[\'host\']<br>';
		var_dump(strlen($GLOBALS['host']));
		echo '<br><br>strlen($GLOBALS[\'author_name\']<br>';
		var_dump(strlen($GLOBALS['author_name']));
		echo "<br><br><br><br>";
*/
		switch($this->queriedContext)
		{
			case 'Parametry':
				if(1 < strlen($GLOBALS['host']) && 1 < strlen($GLOBALS['author_name']))
				{
					return $GLOBALS['host']." - ".ucfirst($GLOBALS['author_name']);
				}
				else if (1 < strlen($GLOBALS['host']))
				{
					return $GLOBALS['host'];
				}
			default:
				return parent::getName();
		}
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
//		echo "addArticleAmp: <br>$url_article<br><br>";
		$article_html = str_get_html(prepare_article($article_html));
		$article = $article_html->find('main#content', 0);
		//https://sportowefakty-wp-pl.cdn.ampproject.org/c/s/sportowefakty.wp.pl/amp/kolarstwo/926408/43-lata-temu-polscy-kolarze-zgineli-w-katastrofie-lotniczej-po-otwarciu-trumny-o
		if (!is_null($article_test = $article->find('ARTICLE[id].article', 0)))
		{
			$article = $article_test;
		}		
		$date = get_json_value($article_html, 'SCRIPT[type="application/ld+json"]', 'datePublished');
		$title = get_json_value($article_html, 'SCRIPT[type="application/ld+json"]', 'headline');
		//nazwisko autora ma na końcu przecinek
		$author = "";
		$author_selector = 'P[data-st-area="Autor"] SPAN.uppercase, DIV.indicator__authorname SPAN.indicator__authorname--name';
		foreach ($article->find($author_selector) as $author_element)
		{
			$new_author = trim($author_element->plaintext);
			$new_author = remove_substring_if_exists_last($new_author, ",");
			$author = $author.", ".$new_author;
		}
		//$author = return_authors_as_string($article, 'P[data-st-area="Autor"] SPAN.uppercase, DIV.indicator__authorname SPAN.indicator__authorname--name', "");
		$author = remove_substring_if_exists_first($author, ", ");
		//https://www-money-pl.cdn.ampproject.org/c/s/www.money.pl/gospodarka/polski-manhattan-bezdomnych-ogrzewana-nora-za-prace-w-zsypach-6629704500415008a.html?amp=1		
		$tags = return_tags_array($article, 'P A.tag-link, P.text-grey.tags SPAN A[href]');
		//Zduplikowane zdjęcie pod "Spotkania z kumplami": https://opinie.wp.pl/wielka-zmiana-mezczyzn-wirus-ja-tylko-przyspieszyl-6604913984391297a?amp=1&_js_v=0.1
		$selectors_array[] = 'DIV.ad';
		$selectors_array[] = 'P.tags';
		$selectors_array[] = 'comment';
		$selectors_array[] = 'AMP-SOCIAL-SHARE';
		$selectors_array[] = 'SECTION.recommendations';
		$selectors_array[] = 'DIV.seolinks';
		$selectors_array[] = 'A.comment-button';
		$selectors_array[] = 'FOOTER#footer';
		//https://sportowefakty-wp-pl.cdn.ampproject.org/c/s/sportowefakty.wp.pl/amp/kolarstwo/926408/43-lata-temu-polscy-kolarze-zgineli-w-katastrofie-lotniczej-po-otwarciu-trumny-o
		$selectors_array[] = 'DIV.wpsocial-shareBox';
		$selectors_array[] = 'UL.teasers';
		$selectors_array[] = 'DIV.center-content';
		foreach_delete_element_array($article, $selectors_array);
		$article = str_get_html($article->save());
		//https://opinie.wp.pl/zakazac-demonstracji-onr-kataryna-problemy-z-demokracja-6119008400492673a?amp=1&_js_v=0.1
		//https://opinie.wp.pl/apel-o-zawieszenie-stosunkow-dyplomatycznych-z-polska-kataryna-jestem-wsciekla-6222729167030401a?amp=1&_js_v=0.1
		//https://opinie.wp.pl/kataryna-kaczynski-stawia-na-dude-6213466100516481a?amp=1&_js_v=0.1
		$article = str_get_html($article->save());
		foreach ($article->find("P") as $paragraph)
		{
			if (check_string_contains_needle_from_array($paragraph->plaintext, array("ZOBACZ WIDEO:")))
			{
				if (!is_null($paragraph->find("AMP-VIDEO-IFRAME", 0)))
				{
					$paragraph->outertext = "";
				}
			}
			else if (check_string_contains_needle_from_array($paragraph->plaintext, array("Masz newsa, zdjęcie lub filmik?")))
			{
				$paragraph->outertext = "";
			}
		}
		$article = str_get_html($article->save());
		//https://sportowefakty-wp-pl.cdn.ampproject.org/c/s/sportowefakty.wp.pl/amp/kolarstwo/926408/43-lata-temu-polscy-kolarze-zgineli-w-katastrofie-lotniczej-po-otwarciu-trumny-o
		format_article_photos($article, 'DIV.image.top-image', TRUE, 'src', 'SMALL');
		format_article_photos($article, 'DIV.image', FALSE, 'src', 'SMALL');
		format_article_photos($article, 'DIV.header-image-container', TRUE, 'src', 'DIV.header-author');
		format_article_photos($article, 'DIV.photo.from.amp', FALSE, 'src', 'FIGCAPTION');
		$article = str_get_html($article->save());
		//https://opinie.wp.pl/kataryna-zyjemy-w-okrutnym-swiecie-ale-aborcja-embriopatologiczna-musi-pozostac-opinia-6567085945505921a?amp=1&_js_v=0.1
		add_style($article, 'FIGURE.photoWrapper', getStylePhotoParent());
		add_style($article, 'FIGURE.photoWrapper IMG', getStylePhotoImg());
		add_style($article, 'FIGCAPTION', getStylePhotoCaption());
		add_style($article, 'BLOCKQUOTE', getStyleQuote());
		$article = str_get_html($article->save());
		$this->items[] = array(
			//Fix &amp z linku
			'uri' => htmlentities($url_article, ENT_QUOTES, 'UTF-8'),
			'title' => getChangedTitle($title),
			'timestamp' => $date,
			'author' => $author,
			'categories' => $tags,
			'content' => $article
		);
	}

	private function getArticlesUrls()
	{
		$GLOBALS['author_name'] = "";
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
				$GLOBALS['author_name'] = get_text_plaintext($html_articles_list, 'H1.author--name, H1.sectionPage--title', $GLOBALS['author_name']);
				foreach($found_hrefs as $href_element)
				{
					if(isset($href_element->href))
					{
						$new_url = $GLOBALS['prefix'].$href_element->href;
						if (!in_array($new_url, $articles_urls))
						{
							$articles_urls[] = $new_url;
						}
					}
				}
			}
			$url_articles_list = $this->getNextPageUrl($html_articles_list);
		}
		return array_slice($articles_urls, 0, $GLOBALS['limit']);
	}

	private function getNextPageUrl($html_articles_list)
	{		
		$next_page_element = $html_articles_list->find('A[rel="next"][href]', 0);
		if (FALSE === is_null($next_page_element) && $next_page_element->hasAttribute('href'))
		{
			return $GLOBALS['prefix'].$next_page_element->getAttribute('href');
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
			if ('h2' === $previous->tag)
			{
				$previous->outertext='';
				$amp_video_iframe->outertext='';
			}
		}
	}

	private function getAmpprojectLink($url)
	{
		$new_url = $url.'?amp=1';
		$new_url = str_replace('https://', $GLOBALS['amp_project_prefix'], $new_url);
		return $new_url;
	}

	private function getAmpLink($url)
	{
		$new_url = $url.'?amp=1';
		return $new_url;
	}
}