<?php
class SprawdzamAFPBridge extends BridgeAbstract {
	const NAME = 'Sprawdzam AFP';
	const URI = 'https://sprawdzam.afp.com/list';
	const DESCRIPTION = 'No description provided';
	const MAINTAINER = 'No maintainer';
	const CACHE_TIMEOUT = 86400; // Can be omitted!

	const PARAMETERS = array
	(
		'Parametry' => array
		(
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
//		var_dump_print($found_urls);
		
		foreach($found_urls as $url)
			$this->addArticle($url);
	}

	private function getArticlesUrls()
	{
		$articles_urls = array();
		$url_articles_list = 'https://sprawdzam.afp.com/list';
		while (count($articles_urls) < $GLOBALS['limit'] && "empty" != $url_articles_list)
		{
			$returned_array = $this->my_get_html($url_articles_list);
			$html_articles_list = $returned_array['html'];
			if (200 !== $returned_array['code'] || 0 === count($found_hrefs = $html_articles_list->find('MAIN DIV.card A[href]')))
			{
				break;
			}
			else
			{
				foreach($found_hrefs as $href_element)
				{
					if(isset($href_element->href))
						$articles_urls[] = 'https://sprawdzam.afp.com'.$href_element->href;
				}
			}
			$url_articles_list = $this->getNextPageUrl($html_articles_list);
		}
		return array_slice($articles_urls, 0, $GLOBALS['limit']);
	}

	private function getNextPageUrl($html_articles_list)
	{		
		$next_page_element = $html_articles_list->find('UL.justify-content-end A[class^="page-link page-link-"][href]', 0);
		if (FALSE === is_null($next_page_element) && $next_page_element->hasAttribute('href'))
		{
			return 'https://sprawdzam.afp.com'.$next_page_element->getAttribute('href');
		}
		else
			return "empty";
	}

	private function addArticle($url)
	{
		$returned_array = $this->my_get_html($url);
		if (200 !== $returned_array['code'])
		{
			return;
		}
		$article_html = $returned_array['html'];
		$article = $article_html->find('ARTICLE[role="article"]', 0);
//		$article = str_get_html($article->save());


		//title
		$title = $url;
		if (FALSE === is_null($title_element = $article_html->find('META[property="og:title"][content]', 0)))
			$title = $title_element->getAttribute('content');
		//date
		$date = "";
		if (FALSE === is_null($date_element = $article_html->find('META[property="article:published_time"][content]', 0)))
			$date = $date_element->getAttribute('content');
		//authors
		$author = returnAuthorsAsString($article, 'SPAN.meta-author A[href][target="_blank"]');
		$author = str_replace(', AFP Polska', '', $author);
		//tags
		$tags = returnTagsArray($article_html, 'DIV.tags A[href]');
		foreach($tags as $tag)
		{
			$tags = ucwords($tag);
		}

		
		foreach($article_html->find('SCRIPT') as $script_element)
		{
			if (FALSE !== strpos($script_element, 'jQuery.extend(Drupal.settings'))
			{
				$script_element_text = str_replace('jQuery.extend(Drupal.settings, ', '', $script_element->innertext);
				$script_element_text = str_replace(');', '', $script_element_text);
				$article_data_parsed = parse_article_data(json_decode($script_element_text));
				$main_photo_code = $article_data_parsed["afp_blog_theme"]["image"]["output"];
				if (FALSE === is_null($header_element = $article_html->find('H1.content-title', 0)))
				{
					$new_code = $header_element->outertext.'<div class="mainPhoto">'.$main_photo_code.'</div>';
					//tutaj nie dało się zmienić elementu przez $header_element->outertext = i $article->outertext = str_replace
					$article->find('H1.content-title', 0)->outertext = $new_code;
				}
				break;
			}
		}
		$article = str_get_html($article->save());
		$this->fix_article_photos_sources($article);
		fix_article_photos($article, 'DIV.ww-item.image', FALSE, 'src', 'SPAN.legend');
		fix_article_photos($article, 'DIV.mainPhoto', TRUE);
		deleteAllDescendantsIfExist($article, 'comment');
		deleteAllDescendantsIfExist($article, 'script');
		deleteAllDescendantsIfExist($article, 'SPAN.meta-share.addtoany');
		deleteAllDescendantsIfExist($article, 'SPAN.meta-separator');
		$article = str_get_html($article->save());
		foreach($article->find('DIV.content-meta SPAN[class^="meta-"]') as $separator)
		{
			$separator->outertext = $separator->outertext.'<br>';
		}
		$article = str_get_html($article->save());
		addStyle($article, 'FIGURE.photoWrapper', getStylePhotoParent());
		addStyle($article, 'FIGURE.photoWrapper IMG', getStylePhotoImg());
		addStyle($article, 'FIGCAPTION', getStylePhotoCaption());
		addStyle($article, 'BLOCKQUOTE', getStyleQuote());	
		$this->items[] = array(
			'uri' => $url,
			'title' => $title,
			'timestamp' => $date,
			'author' => $author,
			'categories' => $tags,
			'content' => $article
		);
	}
	
	private function fix_article_photos_sources($article)
	{
		foreach($article->find('IMG[srcset]') as $photo_element)
		{
			$img_src = $photo_element->getAttribute('src');
			if($photo_element->hasAttribute('srcset'))
			{
				$img_srcset = $photo_element->getAttribute('srcset');
				$srcset_array  = explode(',', $img_srcset);
				$last = count($srcset_array) - 1;
				$last_url_string = trim($srcset_array[$last]);
				$last_url_array  = explode(' ', $last_url_string);
				$img_src = 'https://sprawdzam.afp.com'.$last_url_array[0];
			}
			$photo_element->setAttribute('srcset', '');
			$photo_element->setAttribute('src', $img_src);
		}
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
}
