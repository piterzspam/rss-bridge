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
			),
			'limit' => array
			(
				'name' => 'Liczba artykułów',
				'type' => 'number',
				'required' => true,
				'defaultValue' => 3,
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
//		print_var_dump($found_urls);
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
		$url_array = parse_url($this->getInput('url'));
		$host_name = $url_array["host"];
		$GLOBALS['host_name'] = $host_name;
		$amp_host_name = str_replace('.', '-', $host_name);
		$GLOBALS['amp_host_name'] = $amp_host_name;
	}

	private function getArticlesUrls()
	{
		$articles_urls = array();
		$url_articles_list = $this->getInput('url');
		while (count($articles_urls) < $GLOBALS['limit'] && "empty" != $url_articles_list)
		{
//			echo "url_articles_list :$url_articles_list<br>";
			$returned_array = $this->my_get_html($url_articles_list);
			$html_articles_list = $returned_array['html'];
			if (200 !== $returned_array['code'] || 0 === count($found_hrefs = $html_articles_list->find('UL.list_tiles LI.entry ARTICLE.article A[href]')))
			{
				break;
			}
			else
			{
				$GLOBALS['author_name'] = get_text_plaintext($html_articles_list, 'DIV.index_body H1', "");
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
			return 'https://'.$GLOBALS['host_name'].$next_page_element->getAttribute('href');
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
		replace_attribute($article_html, 'IMG[src$="/image_placeholder_small.svg"][data-src]', 'src', 'data-src');
		$article_html = str_get_html(prepare_article($article_html));
/*
		$article_html_str = $article_html->save();
		$article_html_str = mb_convert_encoding($article_html_str, "UTF-8", "ISO-8859-2");
		$article_html = str_get_html($article_html_str);
*/
		
		$article = $article_html->find('SECTION#article_wrapper', 0);
		$article->tag = 'DIV';


		$title = get_text_plaintext($article, 'H1#article_title', $url_article);
//		$title = trim($article->find('H1#article_title', 0)->plaintext);
		$timestamp = get_text_from_attribute($article, 'TIME', 'datetime', "");
//		$timestamp = trim($article->find('TIME', 0)->getAttribute('datetime'));
//		$author = trim($article->find('A[rel="author"]', 0)->plaintext);
		$author = return_authors_as_string($article, 'DIV.author_and_date SPAN.article_author');
		$tags = return_tags_array($article, 'LI.tags_item');
		$tags = mb_convert_encoding($tags, "ISO-8859-2", "UTF-8");

		$selectors_array[] = 'comment';
		$selectors_array[] = 'SCRIPT';
		$selectors_array[] = 'DIV[id^="banC"]';
		$selectors_array[] = 'DIV#sitePath';
		$selectors_array[] = 'DIV.left_aside';
		$selectors_array[] = 'DIV.ban000_wrapper';
		$selectors_array[] = 'DIV.ban001_wrapper';
		$selectors_array[] = 'DIV.right_aside';
		$selectors_array[] = 'DIV.top_section_bg';
		$selectors_array[] = 'DIV.bottom_section_bg';
		$selectors_array[] = 'DIV#adUnit-007-CONTENTBOARD';
		$selectors_array[] = 'DIV.related_image_number_of_photo';
		$selectors_array[] = 'DIV.related_image_open';
		$selectors_array[] = 'SECTION.tags';
		//https://next.gazeta.pl/next/7,151003,26558939,europa-nam-to-zapamieta-wsciekli-nie-beda-eurokraci-tylko.html
		$selectors_array[] = 'P.art_embed.relatedBox';
		foreach_delete_element_array($article, $selectors_array);

		clear_paragraphs_from_taglinks($article, 'P.art_paragraph', array('/\?tag=/'));
		foreach_delete_element_containing_text_from_array($article, 'div.art_embed', array('Zobacz wideo'));
		foreach_replace_outertext_with_subelement_innertext($article, 'DIV.bottom_section', 'SECTION.art_content');

		insert_html($article, 'SPAN.article_data', '<BR>');
		
		$article = str_get_html($article->save());
		foreach ($article->find('DIV.art_embed') as $embed)
		{
			$url = "";
			$attribute = "";
			if (FALSE === is_null($youtube_element = $embed->find('DIV.youtube-player[data-id]', 0)))
			{
				$attribute = $youtube_element->getAttribute('data-id');
				$url = 'https://www.youtube.com/watch?v='.$attribute;
			}
			else if (FALSE === is_null($generic_element = $embed->find('A[href]', 0)))
			{
				$attribute = $generic_element->getAttribute('href');
				$url = $attribute;
			}
			if ("" !== $url)
			{
				$proxy_url = get_proxy_url($url);
				if ($proxy_url !== $url)
				{
					$embed->outertext = 
						'<strong><br>'
						.'<a href='.$url.'>'
						."Ramka - ".$url.'<br>'
						.'</a>'
						.'<a href='.$proxy_url.'>'
						."Ramka - ".$proxy_url.'<br>'
						.'</a>'
						.'<br></strong>';
				}
				else
				{
					$embed->outertext = 
						'<strong><br>'
						.'<a href='.$url.'>'
						."Ramka - ".$url.'<br>'
						.'</a>'
						.'<br></strong>';
				}
			}
		}
		$article = str_get_html($article->save());
		format_article_photos($article, 'DIV.related_images', TRUE, 'src', 'P.desc');
		//https://wiadomosci.gazeta.pl/wiadomosci/7,114884,26873712,sondazowe-eldorado-polski-2050-i-szymona-holowni-trwa-to-oni.html
		format_article_photos($article, 'DIV.art_embed', FALSE, 'src', 'P.desc');

//		add_style($article, 'H4.art_interview_question, DIV#gazeta_article_lead', array('font-weight: bold;'));
		replace_tag_and_class($article, 'DIV#gazeta_article_lead', 'single', 'STRONG', 'lead');
		replace_tag_and_class($article, 'H4', 'multiple', 'H3');
		$article = str_get_html($article->save());
		add_style($article, 'FIGURE.photoWrapper', getStylePhotoParent());
		add_style($article, 'FIGURE.photoWrapper IMG', getStylePhotoImg());
		add_style($article, 'FIGCAPTION', getStylePhotoCaption());
		add_style($article, 'BLOCKQUOTE', getStyleQuote());
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