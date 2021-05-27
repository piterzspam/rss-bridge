<?php
class MyEconomistBridge extends BridgeAbstract {
	const NAME = 'The Economist: Espresso';
	const URI = 'https://www.economist.com/espresso';
	const CACHE_TIMEOUT = 3600;

	public function getIcon() {
		return 'https://www.economist.com/sites/default/files/econfinal_favicon.ico';
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
		
//		$returned_array = my_get_html(self::URI);
		$returned_array = my_get_html(self::URI, TRUE);
//		$returned_array = my_get_html("https://server4.kproxy.com/servlet/redirect.srv/sruj/scctybbdsv/spqr/p2/espresso", TRUE);
		if (200 === $returned_array['code'])
		{
			$article_html = $returned_array['html'];
		}
		else
		{
			$this->items[] = $returned_array['html'];
			return;
		}

		$article_html = $returned_array['html'];
		$article_html = str_get_html(prepare_article($article_html));
		$article = $article_html->find('MAIN#content', 0);

			
//		$next_data_array = get_json_variable_as_array($article_html, 'props', 'SCRIPT[id="__NEXT_DATA__"][type="application/json"]');
//		$next_data_array = get_json_variable_as_array($article_html, NULL, 'SCRIPT[id="__NEXT_DATA__"][type="application/json"]');
//		print_var_dump($next_data_array, 'next_data_array');

//		get_values_from_json($main_element, $variable_with_data, $selector, $wanted_variable, $wanted_wariables = 0)
//		$values_from_json = get_values_from_json($article_html, NULL, 'SCRIPT[id="__NEXT_DATA__"][type="application/json"]', "headline", NULL);
		$values_headline = get_values_from_json($article_html, NULL, 'SCRIPT[id="__NEXT_DATA__"][type="application/json"]', "headline", TRUE);
		if (isset($values_headline[1]))
		{
			$title = $values_headline[1];
		}
		$values_date_modified = get_values_from_json($article_html, NULL, 'SCRIPT[id="__NEXT_DATA__"][type="application/json"]', "dateModified", TRUE);
		if (isset($values_date_modified[0]))
			$date = $values_date_modified[0];
		else
			$date = "";
		$url = self::URI.'#'.$date;

		print_element($article_html, "article_html");
		return;
//		print_var_dump($values_headline, 'values_headline');
//		print_var_dump($values_date_modified, 'values_date_modified');

		$selectors_array[] = 'STYLE';
		$selectors_array[] = 'DIV._newsletter-promo-container';
		$selectors_array[] = 'DIV._podcast-promo';
		$selectors_array[] = 'DIV.advert';
		$selectors_array[] = 'SCRIPT';
		foreach_delete_element_array($article, $selectors_array);

		if (!is_null($main_image = $article->find('SECTION DIV._image svg', 0)))
			$main_image->outertext = '<img src="https://www.economist.com/engassets/Espresso-OG-image.ff47695fd8.png">';
		
		if (!is_null($section0 = $article->find('SECTION', 0)))
			$section0->class = 'section_title';
		if (!is_null($section1 = $article->find('SECTION', 1)))
			$section1->class = 'section_world_in_brief';
		if (!is_null($section2 = $article->find('SECTION', 2)))
			$section2->class = 'section_todays_agenda';
		if (!is_null($section3 = $article->find('SECTION', 3)))
			$section3->class = 'section_quote';

		foreach_replace_outertext_with_innertext($article, 'DIV._gobbet DIV[class^="css-"]');
		foreach_replace_outertext_with_innertext($article, 'DIV._content DIV[class^="css-"]');
		foreach_replace_outertext_with_innertext($article, 'DIV._quote-container DIV[class^="css-"]');
		insert_html($article, 'DIV._gobbet, DIV._article', '<br><hr>');
		$article = str_get_html($article->save());
		move_element($article, 'SECTION.section_title DIV._image', 'SECTION.section_title H1._title', 'outertext', 'after');
		$article = str_get_html($article->save());
		format_article_photos($article, 'DIV._image', TRUE, 'src');
		format_article_photos($article, 'FIGURE._main-image, IMG', FALSE, 'src', 'FIGCAPTION');
		$article = str_get_html($article->save());
		insert_html($article, 'FIGURE.photoWrapper.photo', '', '<br>');
		$article = str_get_html($article->save());
		add_style($article, 'FIGURE.photoWrapper', getStylePhotoParent());
		add_style($article, 'FIGURE.photoWrapper IMG', getStylePhotoImg());
		add_style($article, 'FIGCAPTION', getStylePhotoCaption());
		add_style($article, 'BLOCKQUOTE', getStyleQuote());
		$article = str_get_html($article->save());

//		$title = self::NAME;
//		$date = "";

		$this->items[] = array(
			'uri' => $url,
			'title' => $title,
			'timestamp' => $date,
			'author' => 'The Economist',
			'content' => $article
		);
	}
}
