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
			$returned_array = my_get_html($url_articles_list);
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
		$returned_array = my_get_html($url_article);
		if (200 !== $returned_array['code'])
		{
			return;
		}
		$article_html = $returned_array['html'];
		$article_html = str_get_html(prepare_article($article_html));
	
		$title = get_text_from_attribute($article_html, 'META[property="og:title"][content]', 'content', $url_article);
		$date = get_text_from_attribute($article_html, 'META[property="article:published_time"][content]', 'content', '');
		$author = return_authors_as_string($article_html, 'DIV.amp-autor A[href*="/author/"]');
		
		$tags = array();
		foreach ($article_html->find('META[property="article:tag"][content]') as $tag_element)
		{
			$tags[] = trim($tag_element->content);
		}
//		$tags = return_authors_as_string($article_html, 'FOOTER A[rel="tag"]');
//		$tags = return_authors_as_string($article_html, 'A[rel="tag"]');
//		print_element($article_html, 'article_html');

		$article = $article_html->find('article', 0);
//https://bezprawnik.pl/korwin-mikke-wyrzucony-z-facebooka/amp/
//https://bezprawnik.pl/rzad-zmienil-ustroj-polski/amp/
		$article = foreach_delete_element_containing_elements_hierarchy($article, array('ul', 'li', 'h3', 'a'));

		$selectors_array[] = 'comment';
		//może pomoże na drugie zdjęcie pod zdjęciem głównynm w czytniku
		$selectors_array[] = 'script';
		//może pomoże na drugie zdjęcie pod zdjęciem głównynm w czytniku - 2
		$selectors_array[] = 'NOSCRIPT';
		$selectors_array[] = 'DIV.amp-autor';
		$selectors_array[] = 'FOOTER';
		$article = foreach_delete_element_array($article, $selectors_array);
		$article = clear_paragraphs_from_taglinks($article, 'P', array('/bezprawnik.pl\/tag\//'));

		//zdjęcie autora
		if (FALSE === is_null($author_photo = $article->find('FIGURE[id^="attachment_"][class^="wp-caption alignright amp-wp-"]', 0))) $author_photo = $author_photo->outertext = '';

		
		$article = format_article_photos($article, 'FIGURE.amp-wp-article-featured-image.wp-caption', TRUE);


		$article = add_style($article, 'FIGURE.photoWrapper', getStylePhotoParent());
		$article = add_style($article, 'FIGURE.photoWrapper IMG', getStylePhotoImg());
		$article = add_style($article, 'FIGCAPTION', getStylePhotoCaption());
		//https://bezprawnik.pl/z-kamera-wsrod-notariuszy/amp/
		//https://bezprawnik.pl/ludzie-umra-po-szczepionkach/amp/
		$article = add_style($article, 'BLOCKQUOTE', getStyleQuote());


		$this->items[] = array(
			'uri' => $url_article,
			'title' => $title,
			'timestamp' => $date,
			'author' => $author,
			'content' => $article,
			'categories' => $tags
		);
	}

	private function getCustomizedLink($url)
	{
		$new_url = $url."amp/";
//		$new_url = str_replace('https://', 'https://bezprawnik-pl.cdn.ampproject.org/c/s/', $new_url);
		return $new_url;
	}
}
