<?php
class GazetaplBridge extends BridgeAbstract {
	const NAME = 'Gazeta.pl';
	const URI = 'https://gazeta.pl/';
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
				'required' => true,
				'defaultValue' => 3,
			),
			'limit' => array
			(
				'name' => 'Liczba artykułów',
				'type' => 'number',
				'required' => true
			),
		)
	);

    public function collectData(){
		include 'myFunctions.php';
		$GLOBALS['my_debug'] = FALSE;
//		$GLOBALS['my_debug'] = TRUE;
		if (TRUE === $GLOBALS['my_debug'])
		{
			$GLOBALS['all_articles_time'] = 0;
			$GLOBALS['all_articles_counter'] = 0;
		}
		$this->setGlobalArticlesParams();
		$found_urls = $this->getArticlesUrls();
//		var_dump_print($found_urls);
		foreach($found_urls as $url)
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

	private function setGlobalArticlesParams()
	{
		$GLOBALS['limit'] = intval($this->getInput('limit'));
	}

	private function getArticlesUrls()
	{
		$articles_urls = array();
		$url_articles_list = $this->getInput('url');
		while (count($articles_urls) < $GLOBALS['limit'] && "empty" != $url_articles_list)
		{
			$returned_array = $this->my_get_html($url_articles_list);
			$html_articles_list = $returned_array['html'];
			if (200 !== $returned_array['code'] || 0 === count($found_hrefs = $html_articles_list->find('UL.list_tiles LI.entry ARTICLE.article A[href]')))
			{
				break;
			}
			else
			{
				$GLOBALS['author_name'] = getTextPlaintext($html_articles_list, 'DIV.index_body H1', "");
				foreach($found_hrefs as $href_element)
				{
					if(isset($href_element->href))
						$articles_urls[] = $href_element->href;
				}
			}
			$url_articles_list = $this->getNextPageUrl($html_articles_list);
		}
		return array_slice($articles_urls, 0, $GLOBALS['limit']);
	}

	private function getNextPageUrl($html_articles_list)
	{		
		$next_page_element = $html_articles_list->find('FOOTER.footer DIV.pagination A.next[href]', 0);
		if (FALSE === is_null($next_page_element) && $next_page_element->hasAttribute('href'))
		{
			return $next_page_element->getAttribute('href');
		}
		else
			return "empty";
	}

	private function addArticle($url_article)
	{
		$returned_array = $this->my_get_html($url_article);
		if (200 !== $returned_array['code'])
		{
			return;
		}
		$article_html = $returned_array['html'];
		
		$article = $article_html->find('SECTION#article_wrapper', 0);
		$article->tag = 'DIV';


		$title = getTextPlaintext($article, 'H1#article_title', $url_article);
//		$title = trim($article->find('H1#article_title', 0)->plaintext);
		$timestamp = getTextAttribute($article, 'TIME', 'datetime', "");
//		$timestamp = trim($article->find('TIME', 0)->getAttribute('datetime'));
		$author = returnAuthorsAsString($article, 'A[rel="author"]');
//		$author = trim($article->find('A[rel="author"]', 0)->plaintext);
		$tags = returnTagsArray($article, 'LI.tags_item');

		deleteAllDescendantsIfExist($article, 'comment');
		deleteAllDescendantsIfExist($article, 'SCRIPT');
		deleteAllDescendantsIfExist($article, 'DIV[id^="banC"]');
		deleteAllDescendantsIfExist($article, 'DIV#sitePath');
		deleteAllDescendantsIfExist($article, 'DIV.left_aside');
		deleteAllDescendantsIfExist($article, 'DIV.ban000_wrapper');
		deleteAllDescendantsIfExist($article, 'DIV.ban001_wrapper');
		deleteAllDescendantsIfExist($article, 'DIV.right_aside');
		deleteAllDescendantsIfExist($article, 'DIV.top_section_bg');
		deleteAllDescendantsIfExist($article, 'DIV.bottom_section_bg');
		deleteAllDescendantsIfExist($article, 'DIV#adUnit-007-CONTENTBOARD');
		deleteAllDescendantsIfExist($article, 'DIV.related_image_number_of_photo');
		deleteAllDescendantsIfExist($article, 'DIV.related_image_open');
		deleteAllDescendantsIfExist($article, 'SECTION.tags');
		clearParagraphsFromTaglinks($article, 'P.art_paragraph', array('/\?tag=/'));
		deleteAncestorIfContainsTextForEach($article, 'div.art_embed', array('Zobacz wideo'));
		//https://next.gazeta.pl/next/7,151003,26558939,europa-nam-to-zapamieta-wsciekli-nie-beda-eurokraci-tylko.html
		deleteAllDescendantsIfExist($article, 'P.art_embed.relatedBox');
//		$article = str_get_html($article->save());
		replaceAllBiggerOutertextWithSmallerInnertext($article, 'DIV.bottom_section', 'SECTION.art_content');
//		$article = str_get_html($article->save());

		if (FALSE === is_null($element = $article->find('SPAN.article_data', 0)))
		{
			$element->outertext = '<BR>'.$element->outertext;
		}


		foreach($article->find('div.art_embed') as $art_embed)
		{
			if (FALSE === is_null($art_embed->find('A[href*="twitter.com/user/status/"]', 0)))
			{
				$twitter_url = $art_embed->find('a', 0)->getAttribute('href');
				$twitter_proxy_url = redirectUrl($twitter_url);
				$art_embed->outertext = 
					'<strong><br>'
					.'<a href='.$twitter_url.'>'
					."Ramka - ".$twitter_url.'<br>'
					.'</a>'
					.'<a href='.$twitter_proxy_url.'>'
					."Ramka - ".$twitter_proxy_url.'<br>'
					.'</a>'
					.'<br></strong>';
			}
		}
		$article = str_get_html($article->save());
		fix_article_photos($article, 'DIV.related_images', TRUE, 'src', 'P.desc');
		//https://wiadomosci.gazeta.pl/wiadomosci/7,114884,26873712,sondazowe-eldorado-polski-2050-i-szymona-holowni-trwa-to-oni.html
		fix_article_photos($article, 'DIV.art_embed', FALSE, 'src', 'P.desc');

		$article = str_get_html($article->save());
		addStyle($article, 'H4.art_interview_question, DIV#gazeta_article_lead', array('font-weight: bold;'));
		addStyle($article, 'FIGURE.photoWrapper', getStylePhotoParent());
		addStyle($article, 'FIGURE.photoWrapper IMG', getStylePhotoImg());
		addStyle($article, 'FIGCAPTION', getStylePhotoCaption());
		addStyle($article, 'BLOCKQUOTE', getStyleQuote());
		$article = str_get_html($article->save());

		$this->items[] = array(
			'uri' => $url_article,
			'title' => $title,
			'timestamp' => $timestamp,
			'author' => $author,
			'content' => $article,
			'categories' => $tags
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