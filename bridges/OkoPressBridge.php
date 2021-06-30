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
		//$returned_array = my_get_html("https://oko.press/afera-mailowa-kolejny-wyciek-morawiecki-nie-ma-pieniedzy-na-dzietnosc-w-koncu-zadaje-kluczowe-pytanie/");
		
		if (200 === $returned_array['code'])
		{
			$article_html = $returned_array['html'];
		}
		else
		{
			return $item;
		}
		$article_html = str_get_html(prepare_article($article_html));
		//author
		$author = return_authors_as_string($article_html, 'SPAN.meta-section__autor A[href*="/autor/"]');
		//tags
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
		//date
		$date_published = get_json_value($article_html, 'SCRIPT[type="application/ld+json"]', 'datePublished');
		$date_modified = get_json_value($article_html, 'SCRIPT[type="application/ld+json"]', 'dateModified');
		//Podcast
		if (FALSE === is_null($podcast_player = $article_html->find("DIV.podcast-player", 0)))
		{
			$item['title'] = "[PODCAST] ".$item['title'];
		}

		$header_element = $article_html->find('DIV#main-image', 0);
		$header_element = format_article_photos($header_element->parent, 'DIV#main-image', TRUE, 'src', "DIV.caption");
		$header_element = foreach_replace_outertext_with_innertext($header_element, 'A');
		$header_element = $header_element->find('FIGURE', 0);
		$article = $article_html->find('ARTICLE', 0);
		foreach($article->children as $child)
		{
			if ("div" === strtolower($child->tag) && "row" === strtolower($child->class))
			{
				$child->outertext = "";
			}
		}
		$article = str_get_html($article->save());
		
		$article = insert_html($article, 'ARTICLE', '', '', get_date_outertext($date_published, $date_modified));
		$article = insert_html($article, 'ARTICLE', '', '', $header_element->outertext);
		$article = insert_html($article, 'ARTICLE', '', '', '<h1 class="title">'.$item['title'].'</h1>');
		$article = replace_attribute($article, 'ARTICLE', 'id', NULL);
		$article = replace_attribute($article, 'ARTICLE', 'class', NULL);
		$selectors_array[] = 'comment';
		$selectors_array[] = 'SCRIPT';
		//https://oko.press/chow-klatkowy-bedzie-zakazany-w-calej-ue-od-2027-roku/
		$selectors_array[] = 'DIV#banner-after-excerpt';
		$selectors_array[] = 'DIV#intertext-banners';
		$selectors_array[] = 'DIV.powiazany-artykul-shortcode';
		$selectors_array[] = 'DIV.tags';
		//DIV.row spoko: https://oko.press/odznaczenie-morawieckiego-kapitula-panstwowe-spolki/
		

		$article = foreach_delete_element_array($article, $selectors_array);
		//premium
		if (FALSE === is_null($login_element = $article->find("DIV.socialwall", 0)))
		{
			$item['title'] = "[PREMIUM] ".$item['title'];
		}
		//https://oko.press/afera-mailowa-kolejny-wyciek-morawiecki-nie-ma-pieniedzy-na-dzietnosc-w-koncu-zadaje-kluczowe-pytanie/
		$article = foreach_replace_outertext_with_subelement_innertext($article, 'BLOCKQUOTE', 'DIV.socialwall');
		
		//$article = foreach_replace_outertext_with_subelement_outertext($article, 'DIV.row.large-collapse', 'DIV#scena-autora DIV.autor-wrapper');
		$article = foreach_replace_outertext_with_subelement_outertext($article, 'DIV.row.large-collapse', 'DIV[id][class="autor"]');
		$article = foreach_replace_outertext_with_innertext($article, 'DIV.excerpt P');
		$article = foreach_replace_outertext_with_innertext($article, 'DIV.entry-content');
		$article = foreach_replace_outertext_with_innertext($article, 'DIV.large-9.medium-12.small-12.large-centered.columns.special-fix-padding-on-mobile-columns');
		$article = foreach_delete_element_containing_subelement($article, 'DIV.row.js-display-random-element', 'DIV.article-cf-banner.js-random');
		$article = foreach_delete_element_containing_subelement($article, 'DIV.row.large-collapse', 'DIV.large-12.medium-12.small-12.columns DIV.social-share');

		//https://oko.press/ojciec-rydzyk-proces-ksiegowa-kochanowicz-mank/
		if (FALSE === is_null($author_element = $article->find('DIV[id][class="autor"]', 0)))
		{
			$maybe_hr_element = $author_element->next_sibling();
			if (FALSE === is_null($maybe_hr_element))
			{
				if ("hr" === strtolower($maybe_hr_element->tag))
				{
					$maybe_hr_element->outertext = "";
					$article = str_get_html($article->save());
				}
			}
			
		}
		$article = replace_tag_and_class($article, 'DIV.excerpt', 'single', 'STRONG', 'lead');
		$article = replace_tag_and_class($article, 'DIV[id][class="autor"]', 'single', 'DIV', 'authors info');
		$article = replace_attribute($article, 'DIV.authors.info', 'id', NULL);
		//https://oko.press/centrum-przesuwa-sie-blizej-lewicy-niz-prawicy-postepuje-izolacja-wyborcow-pis/
		$article = replace_tag_and_class($article, 'DIV.authors.info DIV.autor-wrapper.row.collapse', 'multiple', 'DIV', 'author wrapper');
		$article = replace_tag_and_class($article, 'DIV.authors.info DIV.author.wrapper H5.autor__name', 'multiple', 'H5', 'author name');
		$article = replace_tag_and_class($article, 'DIV.authors.info DIV.author.wrapper DIV.large-2.medium-2.small-12.column.autor__author-photo', 'multiple', 'DIV', 'author photo');
		$article = replace_tag_and_class($article, 'DIV.authors.info DIV.author.wrapper DIV.large-10.medium-10.small-12.column.end.autor__author-description', 'multiple', 'DIV', 'author description');
		
		//https://oko.press/stalo-sie-przemyslaw-radzik-symbol-dobrej-zmiany-w-sadach-dostal-awans-od-prezydenta/
		//https://oko.press/po-wyroku-etpcz-sedzia-z-gorzowa-wzywa-do-dymisji/
		//https://oko.press/starzy-sedziowie-sn-nie-chca-wybierac-prezesa-izby-cywilnej-z-nowymi-sedziami/
		foreach($article->find('P') as $current_paragraph)
		{
			if (FALSE === is_null($photo_from_paragraph = $current_paragraph->find('IMG[src]', 0)))
			{
				$next_sibling = $current_paragraph->next_sibling();
				if ('p' == strtolower($next_sibling->tag) && FALSE === is_null($paragraph_description = $next_sibling->find('EM', 0)))
				{
					$current_paragraph->outertext = '<div class="combined_photo photo">'.$current_paragraph->innertext.'<FIGCAPTION class="combined_photo caption">'.$next_sibling->innertext.'</FIGCAPTION></div>';
					$next_sibling->outertext='';
				}
				else
				{
					$current_paragraph->outertext = '<div class="combined_photo photo">'.$current_paragraph->innertext.'</div>';
				}
			}
		}
		$article = str_get_html($article->save());
		$article = format_article_photos($article, 'FIGURE.photoWrapper.mainPhoto', TRUE, 'src', 'FIGCAPTION');
		$article = format_article_photos($article, 'DIV.combined_photo.photo', FALSE, 'src', 'FIGCAPTION.combined_photo.caption');
		$article = format_article_photos($article, 'FIGURE[id^="attachment_"]', FALSE, 'src', 'FIGCAPTION');
		
		$article = move_element($article, 'DIV.dates', 'H1.title', 'outertext', 'after');
		$article = move_element($article, 'STRONG.lead', 'DIV.dates', 'outertext', 'after');
		$article = move_element($article, 'FIGURE.photoWrapper.mainPhoto', 'STRONG.lead', 'outertext', 'after');
		$article = move_element($article, 'DIV.authors', 'ARTICLE', 'innertext', 'after');
		$article = insert_html($article, 'DIV.authors.info', '', '', '<HR>');

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