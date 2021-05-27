<?php
class RespublicaBridge extends FeedExpander {

	const MAINTAINER = 'No maintainer';
	const NAME = 'Res Publica Nowa';
	const URI = 'https://publica.pl/';
	const DESCRIPTION = 'No description provided';
	const CACHE_TIMEOUT = 86400;

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
			),
			'include_not_downloaded' => array
			(
				'name' => 'Uwzględnij niepobrane',
				'type' => 'checkbox',
				'required' => false,
				'title' => 'Uwzględnij niepobrane'
			),
		)
	);

    public function collectData(){
		include 'myFunctions.php';
		$this->setGlobalArticlesParams();
//        $this->collectExpandableDatas('https://publica.pl/feed/');
		$articles_urls = $this->getArticlesUrls();
//		print_var_dump($articles_urls, "articles_urls");
		foreach ($articles_urls as $url)
		{
			$this->addArticle($url);
		}
    }
	
	private function getArticlesUrls()
	{
		$feed_url = "https://publica.pl/feed";
		$articles_urls = array();
		$page_content = file_get_contents($feed_url, false, stream_context_create(array('http' => array('ignore_errors' => true))));
//		$page_html = str_get_html($page_content);
		$code = getHttpCode($http_response_header);
//		print_var_dump($code, "code");
		if (200 !== $code)
		{
			$html_error = createErrorContent($http_response_header);
			$date = new DateTime("now", new DateTimeZone('Europe/Warsaw'));
			$date_string = date_format($date, 'Y-m-d H:i:s');
			$page_html = array(
				'uri' => $feed_url,
				'title' => "Error ".$code.": ".$feed_url,
				'timestamp' => $date_string,
				'content' => $html_error
			);
			$this->items[] = $page_html;
			return $articles_urls;
		}
//		preg_match_all('/<link>(.+)<\/link>/', $input_lines, $output_array);
//		preg_match_all('/<link>(.+)<\/link>/', $page_content, $output_array);
		preg_match_all('/<link>(https?:\/\/publica.pl\/.+)<\/link>/', $page_content, $output_array);

		foreach ($output_array[1] as $url)
		{
			$articles_urls[] = $url;
		}
//		print_html($page_content, "page_content");
//		print_var_dump($page_content, "page_content");
//		print_html($page_content, "page_content");
//		print_var_dump($output_array, "output_array");
		return array_slice($articles_urls, 0, $GLOBALS['limit']);
	}
	
	private function addArticle($url)
	{
		$returned_array = my_get_html($url);
		if (200 === $returned_array['code'])
		{
			$article_html = $returned_array['html'];
		}
		else
		{
			$this->items[] = $returned_array['html'];
			return;
		}
		if (!is_object($article_html))
		{
			return;
			//daje bool
			//https://publica.pl/teksty/viktor-orban-nie-bedzie-zyl-wiecznie-68571.html
			//print_var_dump($article_html, "article_html");
		}
		$article_html = str_get_html(prepare_article($article_html));
		$article = $article_html->find("DIV.posts_container DIV.container", 0);

		//title
		$title = get_text_plaintext($article, 'H1.entry-title', $url);
		$title = getChangedTitle($title);
		//tags
		$tags_string = get_text_from_attribute($article_html, 'META[name="keywords"][content]', 'content', "");
		$tags = explode(",", $tags_string);
		$tags = array_diff($tags, array('slider na stronie głównej', 'strona główna'));
		//authors
		$author = return_authors_as_string($article, 'HEADER A[rel="author"]');
		//date
		$date = get_text_from_attribute($article_html, 'TIME.updated[datetime]', 'datetime', "");

		$selectors_array[] = 'comment';
		$selectors_array[] = 'SCRIPT';
		$selectors_array[] = 'NOSCRIPT';
		$selectors_array[] = 'DIV.main-column-social-icons';
		$selectors_array[] = 'DIV.social_bottom_container';
		$selectors_array[] = 'DIV.main-column-social-icons';
		$selectors_array[] = 'DL.accordion';
		$selectors_array[] = 'SPAN.byline.author';
		$selectors_array[] = 'qqqqqqqqqq';
		$selectors_array[] = 'qqqqqqqqqq';
		$selectors_array[] = 'qqqqqqqqqq';
		$selectors_array[] = 'qqqqqqqqqq';
		$selectors_array[] = 'qqqqqqqqqq';
		$selectors_array[] = 'qqqqqqqqqq';
		foreach_delete_element_array($article, $selectors_array);
		$article = str_get_html(remove_empty_elements($article, "DIV, P"));
		replace_tag_and_class($article, 'P.lead', 'single', 'STRONG', NULL);
		$article = str_get_html($article->save());
		foreach_replace_outertext_with_innertext($article, 'DIV.small-12');
		foreach_replace_outertext_with_innertext($article, 'ARTICLE[id]');
		foreach_replace_outertext_with_innertext($article, 'DIV#content');
		$article = str_get_html($article->save());
		format_article_photos($article, 'DIV.main-image', TRUE);
		$article = str_get_html($article->save());
		add_style($article, 'FIGURE.photoWrapper', getStylePhotoParent());
		add_style($article, 'FIGURE.photoWrapper IMG', getStylePhotoImg());
		add_style($article, 'FIGCAPTION', getStylePhotoCaption());
		add_style($article, 'BLOCKQUOTE', getStyleQuote());
		$article = str_get_html($article->save());


		$this->items[] = array(
			'uri' => $url,
			'title' => $title,
			'timestamp' => $date,
			'author' => $author,
			'categories' => $tags,
			'content' => $article
			
		);
	}

	private function setGlobalArticlesParams()
	{
		if (TRUE === $this->getInput('include_not_downloaded'))
			$GLOBALS['include_not_downloaded'] = TRUE;
		else
			$GLOBALS['include_not_downloaded'] = FALSE;
		$GLOBALS['limit'] = intval($this->getInput('limit'));
		$GLOBALS['my_debug'] = FALSE;
//		$GLOBALS['my_debug'] = TRUE;
//		$GLOBALS['ignore_number'] = 10;
		if (TRUE === $GLOBALS['my_debug'])
		{
			$GLOBALS['all_articles_time'] = 0;
			$GLOBALS['all_articles_counter'] = 0;
		}
	}

	protected function parseItem($newsItem)
	{
//		echo "<br>Wszedłem<br>";
		$item = parent::parseItem($newsItem);
//		echo "Sparsowałem<br>";
		if (count($this->items) >= intval($this->getInput('limit')))
		{
			if (TRUE === $GLOBALS['include_not_downloaded'])
			{
				return $item;
			}
			else
			{
				return;
			}
		}
		$article_page = getSimpleHTMLDOMCached($item['uri'], 86400 * 14);
		$article = $article_page->find('DIV.container.container-fix.moon_post_container[post_id]', 0);
		$this->fix_main_photo($article);
		$selectors_array[] = 'comment';
		$selectors_array[] = 'script';
		$selectors_array[] = 'NOSCRIPT';
		$selectors_array[] = 'DIV.main-column-social-icons';
		$selectors_array[] = 'DIV[role="main"] DIV.row';
		$selectors_array[] = 'DIV.social_bottom_container';
		$selectors_array[] = 'ARTICLE[id^="post-"] DIV[style="clear:both;"]';
		$selectors_array[] = 'DL.komentarze-w-akordeonie';
		foreach_delete_element_array($article, $selectors_array);
		//https://publica.pl/teksty/ue-zawiodla-w-sprawie-szczepionek-68348.html
		foreach_delete_element_containing_subelement($article, 'FIGURE.figure', 'A[href*="publica.pl/produkt"]');
		foreach_delete_element_containing_subelement($article, 'FIGURE.figure', 'IMG[title^="Zadeklaruj 1%"]');
		
		$tags = array();
		if (FALSE === is_null($tags_element = $article_page->find('META[name="keywords"][content]', 0)))
		{
			$tags_string = trim($tags_element->getAttribute('content'));
			$tags = explode(",", $tags_string);
			$tags = array_diff($tags, array('slider na stronie głównej', 'strona główna'));
		}

		$item['content'] = $article;
		$item['categories'] = $tags;
		return $item;
	}

	private function fix_main_photo($article)
	{
		foreach($article->find('DIV.main-image DIV.bencki[style^="background-image:url(\'http"]') as $article_element)
		{
			$style_string = $article_element->getAttribute('style');
			$style_string = str_replace('background-image:', '', $style_string);
			$style_string = trim($style_string);
			$style_string = remove_substring_if_exists_first($style_string, 'url(');
			$style_string = remove_substring_if_exists_last($style_string, ');');
			$style_string = trim($style_string);
			$img_src = $style_string;
			$img_src = str_replace('\'', '', $img_src);
			$new_outertext = '<figure class="photoWrapper mainPhoto"><img src="'.$img_src.'"></figure>';
			$article_element->parent->outertext = $new_outertext;
		}
	}

}
// Imaginary empty line!