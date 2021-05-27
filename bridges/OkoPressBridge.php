<?php
class OkoPressBridge extends FeedExpander {

	const MAINTAINER = 'No maintainer';
	const NAME = 'OKO.press';
	const URI = 'https://oko.press/';
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
		$GLOBALS['my_debug'] = FALSE;
//		$GLOBALS['my_debug'] = TRUE;
		if (TRUE === $GLOBALS['my_debug'])
		{
			$GLOBALS['all_articles_time'] = 0;
			$GLOBALS['all_articles_counter'] = 0;
		}
		$this->setGlobalArticlesParams();
        $this->collectExpandableDatas('https://oko.press/feed/');
		if (TRUE === $GLOBALS['my_debug'])
			echo "<br>Wszystkie {$GLOBALS['all_articles_counter']} artykulow zajelo {$GLOBALS['all_articles_time']}, <br>średnio ".$GLOBALS['all_articles_time']/$GLOBALS['all_articles_counter'] ."<br>";

    }

	private function setGlobalArticlesParams()
	{
		$GLOBALS['limit'] = intval($this->getInput('limit'));
		if (TRUE === $this->getInput('include_not_downloaded'))
			$GLOBALS['include_not_downloaded'] = TRUE;
		else
			$GLOBALS['include_not_downloaded'] = FALSE;
	}

	protected function parseItem($newsItem)
	{
		$item = parent::parseItem($newsItem);
//		print_var_dump($item, 'item');
		if (count($this->items) >= $GLOBALS['limit'])
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
		$returned_array = my_get_html($item['uri']);
		if (200 === $returned_array['code'])
		{
			$article_html = $returned_array['html'];
		}
		else
		{
			return $item;
		}
		$author = return_authors_as_string($article_html, 'SPAN.meta-section__autor A[href*="/autor/"]');
		$tags = array();
		if (FALSE === is_null($script_element = $article_html->find('HEAD SCRIPT[type="application/ld+json"]', 1)))
		{
			$script_text = $script_element->innertext;
			$article_data_parsed = parse_article_data(json_decode($script_text));
			foreach($article_data_parsed["itemListElement"] as $element)
			{
				$item_element = $element["item"];
				if (FALSE !== strpos($item_element["@id"], 'oko.press/kategoria/'))
				{
					$tags[] = $item_element["name"];
				}
			}
		}
		$tags[] = get_json_value($article_html, 'SCRIPT', 'pageCategory');
		$tags_links = return_tags_array($article_html, 'DIV.entry-content DIV.tags A[rel="tag"]');
		$tags = array_unique(array_merge($tags, $tags_links));
//		set_biggest_photo_size_from_attribute($article_html, 'IMG.lazy[data-srcset]', 'data-srcset');
//		$article_html = str_get_html($article_html->save());
//		set_biggest_photo_size_from_attribute($article_html, 'IMG.lazy[data-src]', 'data-src');
//		$article_html = str_get_html($article_html->save());
		$header_photo = $article_html->find('DIV#main-image IMG.lazy[src]', 0);
		if (FALSE === is_null($header_photo))
		{
			$src_header_image = $header_photo->getAttribute('src');
		}
		$article_html = str_get_html(prepare_article($article_html));
		$article = $article_html->find('ARTICLE[id^="post-"] DIV.large-9', 0);
		$selectors_array[] = 'DIV.cr-paragraph-additions[data-cookie="HasLogged"]';
		$selectors_array[] = 'DIV.cr-login-block.oko-widget-frame';
		$selectors_array[] = 'comment';
		$selectors_array[] = 'SCRIPT';
		$article = foreach_replace_outertext_with_innertext($article, 'DIV.socialwall');
		$article = str_get_html($article->save());
		$selectors_array[] = 'DIV.related_image_open';
		$selectors_array[] = 'DIV.row.js-display-random-element';
		$selectors_array[] = 'DIV.tags';
		$selectors_array[] = 'DIV.row.large-collapse';
		$selectors_array[] = 'DIV#banner-after-excerpt';
		$selectors_array[] = 'DIV#intertext-banners';
		$selectors_array[] = 'DIV.powiazany-artykul-shortcode';
		$article = foreach_delete_element_array($article, $selectors_array);
		$article->find('hr',-1)->outertext = '';
		if (isset($src_header_image))
		{
			$article = str_get_html('<h1 class="title">'.$item['title'].'</h1>'.'<figure class="photoWrapper mainPhoto"><img src="'.$src_header_image.'"></figure>'.$article->save());
		}
		else
		{
			$article = str_get_html('<h1 class="title">'.$item['title'].'</h1>'.$article->save());
		}
		$article = str_get_html($article->save());
		
		//https://oko.press/astra-zeneca-ema/
		$article = convert_iframes_to_links($article);
		//https://oko.press/stalo-sie-przemyslaw-radzik-symbol-dobrej-zmiany-w-sadach-dostal-awans-od-prezydenta/
//		$this->format_article_photos_sources($article);
		$article = add_style($article, 'DIV.excerpt', array('font-weight: bold;'));
		$article = fix_all_photos_attributes($article);
		foreach($article->find('P') as $paragraph)
		{
			$paragraph_photo = $paragraph->find('IMG[src]', 0);
			if (FALSE === is_null($paragraph_photo))
			{
				$next_sibling = $paragraph->next_sibling();
				if ('p' == strtolower($next_sibling->tag))
				{
					$paragraph_description = $next_sibling->find('EM', 0);
					$paragraph->outertext = '<div class="photo">'.$paragraph->innertext.$next_sibling->innertext.'</div>';
					$next_sibling->outertext='';
				}
			}
		}
		$article = str_get_html($article->save());
		$article = format_article_photos($article, 'FIGURE.photoWrapper.mainPhoto', TRUE, 'src');
		$article = format_article_photos($article, 'DIV.photo', FALSE, 'src', 'EM');
		$article = format_article_photos($article, 'FIGURE[id^="attachment_"]', FALSE, 'src', 'FIGCAPTION');
		//https://opinie.wp.pl/kataryna-zyjemy-w-okrutnym-swiecie-ale-aborcja-embriopatologiczna-musi-pozostac-opinia-6567085945505921a?amp=1&_js_v=0.1
		$article = add_style($article, 'FIGURE.photoWrapper', getStylePhotoParent());
		$article = add_style($article, 'FIGURE.photoWrapper IMG', getStylePhotoImg());
		$article = add_style($article, 'FIGCAPTION', getStylePhotoCaption());
		$article = add_style($article, 'BLOCKQUOTE', getStyleQuote());
		
		
//		$item['title'] = $title;
		$item['content'] = $article;
		$item['author'] = $author;
		$item['categories'] = $tags;

		return $item;
	}
}
// Imaginary empty line!