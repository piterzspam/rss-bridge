<?php
class GazetaWeekendBridge extends BridgeAbstract {
	const NAME = 'Weekend Gazeta.pl';
	const URI = 'https://weekend.gazeta.pl/weekend/0,0.html';
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
		foreach($found_urls as $url)
		{
			$this->addArticle($url);
		}
	}

	private function getArticlesUrls()
	{
		$articles_urls = array();
		$url_articles_list = 'https://weekend.gazeta.pl/weekend/0,0.html';
//		$url_articles_list = 'https://weekend.gazeta.pl/weekend/0,177332.html';
		
		while (count($articles_urls) < $GLOBALS['limit'] && "empty" != $url_articles_list)
		{
			$returned_array = my_get_html($url_articles_list);
			$html_articles_list = $returned_array['html'];
			if (200 !== $returned_array['code'] || 0 === count($found_hrefs = $html_articles_list->find('LI.indexPremium__element HEADER.indexPremium__title A.indexPremium__info--link[href][title], DIV.midIndex.lazy_load A.midIndex__titleLink[href]')))
			{
				break;
			}
			else
			{
				foreach($found_hrefs as $href_element)
					if(isset($href_element->href)) $articles_urls[] = $href_element->href;
			}
			$url_articles_list = $this->getNextPageUrl($html_articles_list);
		}
		return array_slice($articles_urls, 0, $GLOBALS['limit']);
	}

	private function getNextPageUrl($html_articles_list)
	{		
		if (FALSE === is_null($next_page_element = $html_articles_list->find('FOOTER A.more_link[href]', 0)))
		{
			return 'https://weekend.gazeta.pl/'.$next_page_element->getAttribute('href');
		}
		else if (FALSE === is_null($next_page_element = $html_articles_list->find('FOOTER A.next[href]', 0)))
		{
			return 'https://weekend.gazeta.pl/'.$next_page_element->getAttribute('href');
		}
		else
			return "empty";
	}

	private function addArticle($url)
	{
		$returned_array = my_get_html($url);
		$url = $returned_array['url'];
		if (200 === $returned_array['code'])
		{
			$article_html = $returned_array['html'];
//			print_element($article_html, 'article_html przed');
			replace_attribute($article_html, 'IMG[data-src][!src], IMG[src*="image_placeholder_small"]', 'src', 'data-src');
//			replace_attribute($article_html, 'IMG[data-src][!src]', 'src', 'data-src');
			
//			print_element($article_html, 'article_html po');
//			$article_html = str_get_html($article_html->save());
			$article_html = str_get_html(prepare_article($article_html));
			if (FALSE === is_null($article = $article_html->find('DIV#premiumArticle__mainArticle', 0)))
			{
				$this->addArticle1($url, $article_html);
			}
			else if (FALSE === is_null($article = $article_html->find('DIV#gazeta_article', 0)))
			{
				//<div id="page" class="layout_uniwersalny n_hat2014">
				$this->addArticle2($url, $article_html);
			}
			else
				echo "Brak elementu dla url: $url<br>";
		}
	}

	private function addArticle1($url_article, $article_html)
	{
		$article = $article_html->find('DIV#premiumArticle__mainArticle', 0);
		$title = get_text_plaintext($article, 'H1.article__title', $url_article);
		$author = return_authors_as_string($article, 'DIV.article__author_date SPAN.article_author');
		$tags = return_tags_array($article_html, 'DIV.article__type-wrapper DIV.article__type.article__type--section, DIV.article__type-wrapper SPAN.article__type-title');
		//tagi
		foreach($tags as $key => $tag)
		{
			$tags[$key] = ucwords(strtolower($tag));
		}
/*
		$date = "";
		if (FALSE === is_null($article_data = $article_html->find('DIV#gazeta_article_body SCRIPT[type="application/ld+json"]', 0)))
		{
			$json = $article_data->innertext;
			$json = mb_convert_encoding($json, "UTF-8", "ISO-8859-2");
			$article_data_parsed = parse_article_data(json_decode($json));
			$date = $article_data_parsed["datePublished"];
		}
*/
		$date = get_json_value($article_html, 'SCRIPT[type="application/ld+json"]', 'datePublished');
//		print_var_dump($temp_date, 'temp_date');
		insert_html($article, 'SPAN.article_data', '<br>', '', '', '');
/*
		if (FALSE === is_null($article_data_element = $article->find('SPAN.article_data', 0)))
		{
			$article_data_element->outertext = '<br>'.$article_data_element->outertext;
		}
*/
		foreach_delete_element($article, 'comment');
		foreach_delete_element($article, 'DIV.article__bottomTextFrame');
//		foreach_delete_element($article, 'SCRIPT');
		foreach_delete_element($article, 'DIV.article__socialbar');
		foreach_delete_element($article, 'DIV#adUnit-007-CONTENTBOARD');
		foreach_delete_element($article, 'DIV[id^="banC"]');
		foreach_delete_element($article, 'DIV#bottom_wrapper');
		foreach_delete_element($article, 'DIV#top_wrapper');
		foreach_delete_element($article, 'DIV.socialBar');
		foreach_delete_element($article, 'DIV.article__slot');
		foreach_delete_element($article, 'DIV.article__sidebar_extraContent');
		foreach_delete_element($article, 'DIV.article__type-wrapper');
//		replace_attribute($article, 'IMG[data-src][!src]', 'src', 'data-src');
		replace_attribute($article, 'BLOCKQUOTE[class="art_blockquote"]', 'class', NULL);
		combine_two_elements($article, 'IMG.article__image', 'DIV.article__image_signature', 'DIV', 'super_photo');

		foreach_replace_outertext_with_innertext($article, 'SECTION.art_content');
		//https://weekend.gazeta.pl/weekend/7,177333,26878416,mam-33-lata-i-troje-dzieci-to-nie-pora-by-umierac-malgorzata.html
		foreach_replace_outertext_with_subelement_outertext($article, 'P.art_paragraph', 'SPAN.imageUOM');

		$article = str_get_html($article->save());
		format_article_photos($article, 'DIV.super_photo', TRUE, 'src', 'DIV.article__image_signature');
		format_article_photos($article, 'DIV.art_embed', FALSE, 'src', 'DIV.article__galleryDescription');
		format_article_photos($article, 'SPAN.imageUOM', FALSE, 'src', 'SPAN.photoAuthor');

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
		foreach_delete_element($article, 'SCRIPT');
		$article = str_get_html($article->save());
		//https://weekend.gazeta.pl/weekend/7,177333,26878416,mam-33-lata-i-troje-dzieci-to-nie-pora-by-umierac-malgorzata.html
		
		replace_tag_and_class($article, 'H4', 'multiple', 'H3');
//		add_style($article, 'H4.art_interview_question, DIV.article__lead', array('font-weight: bold;'));
		add_style($article, 'DIV.article__lead', array('font-weight: bold;'));
		add_style($article, 'FIGURE.photoWrapper', getStylePhotoParent());
		add_style($article, 'FIGURE.photoWrapper IMG', getStylePhotoImg());
		add_style($article, 'FIGCAPTION', getStylePhotoCaption());
		add_style($article, 'BLOCKQUOTE', getStyleQuote());
		
//		$article = str_get_html($article->save());
		$this->items[] = array(
			'uri' => $url_article,
			'title' => $title,
			'timestamp' => $date,
			'author' => $author,
			'categories' => $tags,
			'content' => $article
		);
	}

	private function addArticle2($url_article, $article_html)
	{
//		echo "addArticle2, url: $url_article<br>";
		$article = $article_html->find('DIV#gazeta_article', 0);
		//tytul
		$title = get_text_plaintext($article, 'DIV.gazeta_article_header H1', $url_article);
		//autor
		$author = get_text_plaintext($article, 'SPAN[itemprop="author"]');
		$author = str_replace('Tekst: ', '', $author);
		$author = str_replace('Przygotowanie wideo: ', '', $author);
		$author = str_replace('Zdjęcia: ', '', $author);
		$author = str_replace('Montaż: ', '', $author);
		$author = str_replace(';', ',', $author);
		//tagi
		$tags = return_tags_array($article_html, 'DIV.gazeta_article_header DIV.keyTag');
		foreach($tags as $key => $tag)
		{
			$tags[$key] = ucwords(strtolower($tag));
		}
		//data
		$date = get_text_from_attribute($article_html, '[data-pub]', 'data-pub', "");

		foreach ($article->find('SCRIPT[src*="video.onnetwork.tv/embed.php?"]') as $script)
		{
			$attribute_url = $script->getAttribute('src');
			$returned_array = my_get_html($attribute_url);
			if (200 === $returned_array['code'])
			{
				$str_html = $returned_array['html']->save();
				preg_match('/https?:\/\/video\.onnetwork\.tv\/frame[0-9]*\.php\?mid=[0-9a-zA-Z]*/', $str_html, $output_array);
				if (isset($output_array[0]))
				{
					$frame_url = $output_array[0];
					$script->parent->outertext = 
						'<strong><br>'
						.'<a href='.$frame_url.'>'
						."Ramka - ".$frame_url.'<br>'
						.'</a>'
						.'<br></strong>';
				}
			}
		}
		$article = str_get_html($article->save());
		foreach_delete_element($article, 'DIV.keyTag');
		foreach_delete_element($article, 'DIV#gazeta_article_author');
		foreach_delete_element($article, 'DIV.shortSocialBar');
		foreach_replace_outertext_with_subelement_innertext($article, 'DIV#article', 'DIV.cmsArtykulElem');
		foreach_delete_element($article, 'DIV#sitePath');
		foreach_delete_element($article, 'DIV#article_comments');
		foreach_delete_element($article, 'DIV.relatedHolder');
		foreach_delete_element($article, 'DIV#adUnit-007-CONTENTBOARD');
		foreach_delete_element($article, 'comment');
		foreach_delete_element($article, 'SCRIPT');
		replace_attribute($article, '[data-pub]', 'data-pub', NULL);
		replace_attribute($article, '[data-adv]', 'data-adv', NULL);
		$article = str_get_html($article->save());
		$main_photo = $article->find('DIV#gazeta_article_image', 0);
		$title_element = $article->find('DIV.gazeta_article_header', 0);
		if (FALSE === is_null($main_photo) && FALSE === is_null($title_element))
		{
			$main_photo->outertext = $title_element->outertext.$main_photo->outertext;
			$title_element->outertext = '';
		}
		$article = str_get_html($article->save());
		foreach ($article->find('H6') as $h6)
		{
			$h6->outertext = '<BLOCKQUOTE>'.$h6->innertext.'</BLOCKQUOTE>';
		}
		$article = str_get_html($article->save());
		format_article_photos($article, 'DIV#gazeta_article_image', TRUE, 'src', 'P.desc');
		$article = str_get_html($article->save());
		add_style($article, 'FIGURE.photoWrapper', getStylePhotoParent());
		add_style($article, 'FIGURE.photoWrapper IMG', getStylePhotoImg());
		add_style($article, 'FIGCAPTION', getStylePhotoCaption());
		add_style($article, 'BLOCKQUOTE', getStyleQuote());
		$article = str_get_html($article->save());


		$this->items[] = array(
			'uri' => $url_article,
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
