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
//		print_var_dump($found_urls);
		
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
		$next_data_array = get_json_variable_as_array($article_html, 'afp_blog_theme', 'SCRIPT');
		$next_data_subarrays = get_subarrays_by_key($next_data_array, "output", NULL);
		$detail_data_flattened = flatten_array($next_data_subarrays, "output");
		$main_image_code = str_replace('<img', '<img class="photoWrapper mainPhoto" ', $detail_data_flattened[0]["output"]);
		insert_html($article_html, 'H1.content-title', '', $main_image_code, '', '');

		$article_html = str_get_html(prepare_article($article_html, 'https://sprawdzam.afp.com'));
		$article = $article_html->find('ARTICLE[role="article"]', 0);

		//title
		$title = get_text_from_attribute($article_html, 'META[property="og:title"][content]', 'content', $url);
		//date
		$date = get_text_from_attribute($article_html, 'META[property="article:published_time"][content]', 'content', "");
		//authors
		$author = return_authors_as_string($article, 'SPAN.meta-author A[href][target="_blank"]');
		$author = str_replace(', AFP Polska', '', $author);
		//tags
		$tags = return_tags_array($article_html, 'DIV.tags A[href]');
		foreach($tags as $key => $tag)
		{
			$tags[$key] = ucwords(strtolower($tag));
		}

		$article = str_get_html($article->save());
		$selectors_array[] = 'comment';
		$selectors_array[] = 'script';
		$selectors_array[] = 'SPAN.meta-share.addtoany';
		$selectors_array[] = 'SPAN.meta-separator';
		foreach_delete_element_array($article, $selectors_array);
//		move_element($article, 'DIV#container HEADER.entry-header.clearfix', 'DIV#content', 'innertext', 'before');
		format_article_photos($article, 'IMG.photoWrapper.mainPhoto', TRUE);
		format_article_photos($article, 'DIV.ww-item.image', FALSE, 'src', 'SPAN.legend');
		$article = str_get_html($article->save());
		foreach($article->find('DIV.content-meta SPAN[class^="meta-"]') as $separator)
		{
			$separator->outertext = $separator->outertext.'<br>';
		}
		$article = str_get_html($article->save());
		add_style($article, 'FIGURE.photoWrapper', getStylePhotoParent());
		add_style($article, 'FIGURE.photoWrapper IMG', getStylePhotoImg());
		add_style($article, 'FIGCAPTION', getStylePhotoCaption());
		add_style($article, 'BLOCKQUOTE', getStyleQuote());	
		$this->items[] = array(
			'uri' => $url,
			'title' => $title,
			'timestamp' => $date,
			'author' => $author,
			'categories' => $tags,
			'content' => $article
		);
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
