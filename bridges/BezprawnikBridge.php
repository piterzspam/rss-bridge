<?php
class BezprawnikBridge extends BridgeAbstract {
	const NAME = 'Bezprawnik';
	const URI = 'https://bezprawnik.pl/';
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


	public function getIcon()
	{
		return 'https://ocs-pl.oktawave.com/v1/AUTH_2887234e-384a-4873-8bc5-405211db13a2/spidersweb/bp/fav/favicon-32x32.png';
//		return 'https://c.disquscdn.com/uploads/forums/349/4323/favicon.png';
//		return 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAACAAAAAgCAMAAABEpIrGAAAABGdBTUEAALGPC/xhBQAAAAFzUkdCAK7OHOkAAABdUExURRuX80Cn9fX8//r+//3+/wuR8kiq9f///x2Y8xGU9BSV8yGa9Mno/TWi9XzA9uHx/lmx9bPc/JTL9xIPE6TY/TuJwu34/w+B1W279xZVhDSm/TNefzZojkKb3gw2VRTmK5wAAAENSURBVDjLlZPZcsMgDEUxEj4Y8L5k7/9/Zh8yyaQYd9rzqjuSrhZj/ojfkQlCkxEyQVvl/CzgOkQ/ECaXFelR3ih9DFmKAUXsE5Szi5kLN4Kt2+v12vaQmtyFbypRltPpdOpQ6rwDY6KbUB7zvG6w5AWMMT7EDdLla4GtDX4/y+g6hOGB0hUSGGNMSJASpKN1uAFVlMH5I8WICONR3PjQWrDFDl+CDbRuDgVuQaQwxXe8fjZ5PnDpXzb7cjy6M0JdIUzlFLGHsWkWtGgkugmV9XJZLaVR+NBaZZrnee6QwjCfB7Pe7vfb2kMKpT0gVqwVa5H9QkP69Wi96z7joLuzr+qMKj8Wl//ef5/3kG/lHBAqw+chFAAAAFd6VFh0UmF3IHByb2ZpbGUgdHlwZSBpcHRjAAB4nOPyDAhxVigoyk/LzEnlUgADIwsuYwsTIxNLkxQDEyBEgDTDZAMjs1Qgy9jUyMTMxBzEB8uASKBKLgDqFxF08kI1lQAAAABJRU5ErkJggg==';
	}

	public function collectData()
	{
		include 'myFunctions.php';
		$GLOBALS['my_debug'] = FALSE;
//		$GLOBALS['my_debug'] = TRUE;
		if (TRUE === $GLOBALS['my_debug'])
		{
			$GLOBALS['all_articles_time'] = 0;
			$GLOBALS['all_articles_counter'] = 0;
		}
		$GLOBALS['limit'] = intval($this->getInput('limit'));
		$found_urls = $this->getArticlesUrls();
		foreach($found_urls as $url)
		{
			$amp_url = $this->getCustomizedLink($url);
			$this->addArticle($amp_url);
		}
	}


	private function getArticlesUrls()
	{
		$articles_urls = array();
		$url_articles_list = $this->getInput('url');
		while (count($articles_urls) < $GLOBALS['limit'] && "empty" != $url_articles_list)
		{
			$returned_array = $this->my_get_html($url_articles_list);
			$html_articles_list = $returned_array['html'];
			if (200 !== $returned_array['code'] || 0 === count($found_hrefs = $html_articles_list->find('A.linkbg[href]')))
			{
				break;
			}
			else
			{
				$GLOBALS['author_name'] = get_text_plaintext($html_articles_list, 'SECTION.autor-header H2', "");
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
		$next_page_element = $html_articles_list->find('DIV.wp-pagenavi A.nextpostslink[href]', 0);
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
		$article = $article_html->find('article', 0);
		$article_data = $article_html->find('SCRIPT[type="application/ld+json"]', 0)->innertext;
		$article_data_parsed = parse_article_data(json_decode($article_data));
		$title = $article_data_parsed["@graph"][2]["name"];
		$date = $article_data_parsed["@graph"][2]["datePublished"];
		$author = $article_data_parsed["@graph"][3]["name"];
//		$title = $article_html->find('META[property="og:title"]', 0)->content;
//		$date = $article_html->find('META[property="article:published_time]', 0)->content;
		$tags = return_tags_array($article, 'DIV.amp-wp-tax-tag [rel="tag"]');
		convert_amp_photos($article);
		convert_amp_frames_to_links($article);
//https://bezprawnik.pl/korwin-mikke-wyrzucony-z-facebooka/amp/
//https://bezprawnik.pl/rzad-zmienil-ustroj-polski/amp/
		foreach_delete_element_containing_elements_hierarchy($article, array('ul', 'li', 'h3', 'a'));
		foreach_delete_element($article, 'comment');
		//może pomoże na drugie zdjęcie pod zdjęciem głównynm w czytniku
		foreach_delete_element($article, 'script');
		//może pomoże na drugie zdjęcie pod zdjęciem głównynm w czytniku - 2
		foreach_delete_element($article, 'NOSCRIPT');
		foreach_delete_element($article, 'DIV.amp-autor');
		foreach_delete_element($article, 'FOOTER');
		clear_paragraphs_from_taglinks($article, 'P', array('/bezprawnik.pl\/tag\//'));

		//zdjęcie autora
		$author_photo = $article->find('FIGURE[id^="attachment_"][class^="wp-caption alignright amp-wp-"]', 0);
		if (FALSE === is_null($author_photo))
		{
			$author_photo = $author_photo->outertext = '';
		}
		
		format_article_photos($article, 'FIGURE.amp-wp-article-featured-image.wp-caption', TRUE);
		
/*
		foreach($article->find('amp-img, img') as $photo_element)
		{
			if(isset($photo_element->layout)) $photo_element->layout = NULL;
			if(isset($photo_element->srcset)) $photo_element->srcset = NULL;
		}
*/
		

		$this->items[] = array(
			'uri' => $url_article,
			'title' => $title,
			'timestamp' => $date,
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

	private function getCustomizedLink($url)
	{
		$new_url = $url."amp/";
		$new_url = str_replace('https://', 'https://bezprawnik-pl.cdn.ampproject.org/c/s/', $new_url);
		return $new_url;
	}
}
