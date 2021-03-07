<?php
class OutridersBridge extends FeedExpander {

	const MAINTAINER = 'No maintainer';
	const NAME = 'Outriders Brief';
	const URI = '';
	const DESCRIPTION = 'No description provided';
	const CACHE_TIMEOUT = 86400;

	const PARAMETERS = array
	(
		'Parametry' => array
		(
			'wanted_number_of_briefs' => array
			(
				'name' => 'Limit',
				'type' => 'number',
				'required' => true,
				'title' => 'Liczba briefów',
			)
		)
	);

	public function collectData()
	{
		include 'myFunctions.php';
		$GLOBALS['wanted_number_of_briefs'] = $this->getInput('wanted_number_of_briefs');
		$GLOBALS['my_debug'] = FALSE;
//		$GLOBALS['my_debug'] = TRUE;
		if (TRUE === $GLOBALS['my_debug'])
		{
			$GLOBALS['all_articles_time'] = 0;
			$GLOBALS['all_articles_counter'] = 0;
		}
		
		$found_urls = $this->getArticlesUrlsIssues();
		
		foreach($found_urls as $url_array)
		{
			$this->addArticle($url_array['url'], $url_array['date']);
		}
	}

	private function getArticlesUrlsIssues()
	{
		$articles_urls = array();
		$returned_array = $this->my_get_html('https://brief.outride.rs/pl');
		$html_issues_list = $returned_array['html'];
		if (200 !== $returned_array['code'] || 0 === count($found_issues_hrefs = $html_issues_list->find('DIV.issues A.issue-box[href]')))
		{
			return $articles_urls;
		}
		else
		{
			$issues_urls = array();
			foreach($found_issues_hrefs as $href_element)
			{
				$issues_urls[] = 'https://brief.outride.rs'.$href_element->href;
			}
		}
		$issues_urls = array_slice($issues_urls, 0, $GLOBALS['wanted_number_of_briefs']);

		foreach($issues_urls as $issue_url)
		{
			$returned_array = $this->my_get_html($issue_url);
			$html_articles_list = $returned_array['html'];
//			if (200 === $returned_array['code'] || 0 !== count($found_articles_hrefs = $html_articles_list->find('H3.title-spistresci A[href]')))
			if (200 === $returned_array['code'] && 0 !== count($found_articles_hrefs = $html_articles_list->find('UL.issue-nav LI.issue-nav__item A.issue-nav__link[href]')))
			{
				$date = '';
				if (FALSE === is_null($data_script = $html_articles_list->find('DIV[id$="nuxt"] + SCRIPT', 0)))
				{
					preg_match('/[0-9]{4}-[0-9]{2}-[0-9]{2}T[0-9]{2}:[0-9]{2}:[0-9]{2}\+[0-9]{2}:[0-9]{2}/', $data_script->outertext, $output_array);
					$date = $output_array[0];
				}
				foreach($found_articles_hrefs as $href_element)
				{
					if(isset($href_element->href))
					{
						$articles_urls[] = array
						(
							'url' => 'https://brief.outride.rs'.$href_element->href,
							'date' => $date
						);
					}
				}
			}
		}
//		var_dump_print($articles_urls);
		return $articles_urls;
	}

	private function addArticle($url, $date)
	{
		$returned_array = $this->my_get_html($url);
		if (200 !== $returned_array['code'])
		{
			return;
		}
		$article_html = $returned_array['html'];
		$article = $article_html->find('ARTICLE[id].post', 0);
		//tytuł
		$title = "";
		if (FALSE === is_null($title_element = $article->find('H1.post__title', 0)))
		{
			$title = trim($title_element->plaintext);
		}
		
		preg_match('/brief-[0-9]*/', $url, $output_array);
		$brief_number = ucwords($output_array[0]);
		//tagi
		$tags = returnTagsArray($article, 'DIV.categories A[href]');
		$tags = array_merge(array($brief_number), $tags);
		deleteAllDescendantsIfExist($article, 'DIV.categories');
		$this->items[] = array(
			'uri' => $url,
			'title' => $brief_number.': '.$title,
			'timestamp' => $date,
			'author' => 'Outriders',
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
// Imaginary empty line!