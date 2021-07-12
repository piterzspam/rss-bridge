<?php
class NiebezpiecznikBridge extends FeedExpander {

	const MAINTAINER = 'No maintainer';
	const NAME = 'Niebezpiecznik';
	const URI = 'https://niebezpiecznik.pl/';
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
        $this->collectExpandableDatas('http://feeds.feedburner.com/Niebezpiecznik');
    }

	private function setGlobalArticlesParams()
	{
		$GLOBALS['my_debug'] = FALSE;
		if (TRUE === $this->getInput('include_not_downloaded'))
			$GLOBALS['include_not_downloaded'] = TRUE;
		else
			$GLOBALS['include_not_downloaded'] = FALSE;
	}

	protected function parseItem($newsItem)
	{
		$item = parent::parseItem($newsItem);
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
		$returned_array = my_get_html($item['uri']);
		if (200 === $returned_array['code'])
		{
			$article_html = $returned_array['html'];
		}
		else
		{
			return $item;
		}
		$article_html = str_get_html(prepare_article($article_html));
		$article = $article_html->find('DIV#main DIV.post', 0);
		
		$tags = return_tags_array($article, 'DIV.postmeta A[href*="niebezpiecznik.pl/tag/"][rel="tag"]');

		if (!is_null($similar_posts = $article->find('UL.similar-posts', 0)))
		{
			$previous_element = $similar_posts->prev_sibling();
			if (!is_null($previous_element) && "h4" === strtolower($previous_element->tag) && check_string_contains_needle_from_array($previous_element->plaintext, array("Przeczytaj także:")))
			{
				$similar_posts->outertext = "";
				$previous_element->outertext = "";
			}
			else
			{
				$similar_posts->outertext = "";
			}
			$article = str_get_html($article->save());
		}
		
		$new_url = get_text_from_attribute($article, 'DIV.title A[href]', 'href', NULL);
		if (isset($new_url))
		{
			$item['uri'] = $new_url;
		}

		$article = str_get_html('<div class="article"><ARTICLE>'.$article->save().'</ARTICLE></div>');
		
		$selectors_array[] = 'comment';
		$selectors_array[] = 'script';
		$selectors_array[] = 'DIV.entry DIV[style="margin-top:-25px;"]';
		$selectors_array[] = 'DIV.date';
		$selectors_array[] = 'DIV.title';
		$selectors_array[] = 'DIV.clear';
		$article = foreach_delete_element_array($article, $selectors_array);
		$article = foreach_replace_outertext_with_innertext($article, 'DIV.post');

		$article = replace_tag_and_class($article, 'DIV.entry', 'single', 'DIV', 'article body');
		$article = insert_html($article, 'ARTICLE', '', '', '<h1 class="title">'.$item['title'].'</h1>');
		$date = date_format(date_timestamp_set(new DateTime(), $item['timestamp'])->setTimezone(new DateTimeZone('Europe/Warsaw')), 'c');
		$article = insert_html($article, 'H1.title', '', get_date_outertext($date));
		$article = move_element($article, 'DIV.article.body', 'DIV.dates', 'outertext', 'after');
		$article = insert_html($article, 'DIV.article.body', '', '<div class="authors">'.$item['author'].'</div>');
		$article = insert_html($article, 'DIV.authors', '', '', '<HR>');

		$article = foreach_replace_outertext_with_subelement_outertext($article, 'A[href*="niebezpiecznik.pl/wp-content/uploads/"]', 'IMG[class*="wp-image-"]');
		//najpierw zamiana A na IMG, potem formatowanie, inaczej nie działało
		$article = format_article_photos($article, 'IMG[class*="wp-image-"]', FALSE, 'src', 'FIGCAPTION');
		//$article = foreach_replace_outertext_with_innertext($article, 'FIGURE.photoWrapper A[href*="niebezpiecznik.pl/wp-content/uploads/"]');
		//$article = foreach_replace_outertext_with_subelement_outertext($article, 'FIGURE.photoWrapper A[href]', 'IMG');



//		$article = foreach_replace_outertext_with_subelement_outertext($article, 'A[href*="niebezpiecznik.pl/wp-content/uploads/"]', 'IMG[class*="wp-image-"]');
		//$article = foreach_replace_outertext_with_innertext($article, 'A[href*="niebezpiecznik.pl/wp-content/uploads/"]');

		//$article = str_get_html($article->save());
		//$article = foreach_replace_outertext_with_innertext($article, 'FIGURE.photoWrapper A');
		//$article = foreach_replace_outertext_with_innertext($article, 'FIGURE');
		//$article = str_get_html($article->save());
		
		
/*
		$article_str = $article->save();
		$counter = 0;
		foreach($article->find('FIGURE.photoWrapper') as $element)
		{
			echo "FIGURE.photoWrapper element nr $counter<br>";
			print_element($element, "FIGURE.photoWrapper element nr $counter");
			print_html($element, "FIGURE.photoWrapper element nr $counter");
			$counter++;
			if (!is_null($link_with_image = $element->find('A[href*="niebezpiecznik.pl/wp-content/uploads/"]', 0)))
			{
				echo "Znaleziono link w numerze $counter<br>";
				echo "Przed zamianą<br> <br><pre>".htmlspecialchars($article_str)."</pre><br>";
				$article_str = str_replace($link_with_image->outertext, $link_with_image->innertext, $article_str);
				echo "Po zamianie<br> <br><pre>".htmlspecialchars($article_str)."</pre><br>";
			}
//			echo "Przed zamianą<br> <br><pre>".htmlspecialchars($main_element_str)."</pre><br>";
//			$counter++;
//			$main_element_str = str_replace();
//			echo "Po zamianie<br> <br><pre>".htmlspecialchars($main_element_str)."</pre><br>";
//			$element->outertext = $element->innertext;
		}
		$article = str_get_html($article_str);
*/
		$article = $article->find('ARTICLE', 0);

		$article = add_style($article, 'FIGURE.photoWrapper', getStylePhotoParent());
		$article = add_style($article, 'FIGURE.photoWrapper IMG', getStylePhotoImg());
		$article = add_style($article, 'FIGCAPTION', getStylePhotoCaption());
		$article = add_style($article, 'BLOCKQUOTE', getStyleQuote());

		$item['content'] = $article;
		$item['categories'] = $tags;
		if (FALSE === in_array("konferencje i wykłady", $item['categories']) && FALSE === in_array("szkolenia", $item['categories']) )
		{
			return $item;
		}
	}

}
// Imaginary empty line!