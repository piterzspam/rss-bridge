<?php
class KonkretTVN24Bridge extends BridgeAbstract {
	const NAME = 'Konkret TVN24';
	const URI = 'https://konkret24.tvn24.pl/';
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
			),
			'category' => array(
				'name' => 'Kategoria',
				'type' => 'list',
				'values' => array(
					'Najnowsze' => 'https://konkret24.tvn24.pl/najnowsze,118.html',
					'Polska' => 'https://konkret24.tvn24.pl/polska,108.html',
					'Świat' => 'https://konkret24.tvn24.pl/swiat,109.html',
					'Polityka' => 'https://konkret24.tvn24.pl/polityka,112.html',
					'Nauka' => 'https://konkret24.tvn24.pl/nauka,111.html',
					'Zdrowie' => 'https://konkret24.tvn24.pl/zdrowie,110.html',
					'Rozrywka' => 'https://konkret24.tvn24.pl/rozrywka,113.html',
					'Tech' => 'https://konkret24.tvn24.pl/tech,116.html',
					'Mity' => 'https://konkret24.tvn24.pl/mity,114.html',
				 ),
				'title' => 'Kategoria',
				'defaultValue' => 'https://konkret24.tvn24.pl/najnowsze,118.html',
			),
		)
	);

	public function collectData()
	{
		include 'myFunctions.php';
		$GLOBALS['limit'] = $this->getInput('limit');
		$GLOBALS['chosen_category_url'] = $this->getInput('category');
		
		$found_urls = $this->getArticlesUrls();
//		$found_urls[] = 'https://konkret24.tvn24.pl/polska,108/mieszkancy-orzysza-pobili-sie-z-zolnierzami-nato-policja-i-zandarmeria-to-fake-news,1054169.html';
//		print_var_dump($found_urls);
		
		foreach($found_urls as $url)
		{
			$this->addArticle($url);
		}
	}

	private function getArticlesUrls()
	{
		$articles_urls = array();
		$url_articles_list = $GLOBALS['chosen_category_url'];
		while (count($articles_urls) < $GLOBALS['limit'] && "empty" != $url_articles_list)
		{
			$html_articles_list = getSimpleHTMLDOM($url_articles_list);
			if (0 === count($found_hrefs = $html_articles_list->find('ARTICLE.news-teaser A.news-teaser__link[href]')))
			{
				break;
			}
			else
			{
				foreach($found_hrefs as $href_element)
					if(isset($href_element->href)) $articles_urls[] = 'https://konkret24.tvn24.pl'.$href_element->href;
			}
			$url_articles_list = $this->getNextPageUrl($html_articles_list);
		}
		return array_slice($articles_urls, 0, $GLOBALS['limit']);
	}

	private function getNextPageUrl($html_articles_list)
	{		
		$next_page_element = $html_articles_list->find('A.pagination__link.pagination__link--next', 0);
		if (FALSE === is_null($next_page_element) && $next_page_element->hasAttribute('href'))
		{
			$url = 'https://konkret24.tvn24.pl'.$next_page_element->getAttribute('href');
			$url = strtolower($url);
			return $url;
		}
		else
			return "empty";
	}

	private function addArticle($url)
	{
		$article_html = getSimpleHTMLDOMCached($url, 86400 * 14);
		$article_html = str_get_html(prepare_article($article_html));
		$article_html_str = str_replace("\xc2\xa0", '', $article_html->save());
		$article_html = str_get_html($article_html_str);

		$date = get_json_value($article_html, 'SCRIPT', 'date');
		
		move_element($article_html, 'HEADER.article-main-photo', 'DIV.article-content__metadata', 'outertext', 'after');

		$article = $article_html->find('DIV.page-article DIV.article-content', 0);
		$title = get_text_plaintext($article, 'H2.article-content__title', $url);
		$author = get_text_plaintext($article, 'DIV.article-content__sources__type--author', '');
		$author = trim(str_replace('Autor:', '', $author));
		$tags = array();
		//https://konkret24.tvn24.pl/mity,114/orzel-w-trumnie-na-dwustuzlotowym-banknocie-uwaga-na-powracajacy-fejk,1049136.html
		if (FALSE !== strpos($url, 'tvn24.pl/mity')) $tags[] = 'Mity';
		else if (FALSE !== strpos($url, 'tvn24.pl/najnowsze')) $tags[] = 'Najnowsze';
		else if (FALSE !== strpos($url, 'tvn24.pl/nauka')) $tags[] = 'Nauka';
		else if (FALSE !== strpos($url, 'tvn24.pl/polityka')) $tags[] = 'Polityka';
		else if (FALSE !== strpos($url, 'tvn24.pl/polska')) $tags[] = 'Polska';
		else if (FALSE !== strpos($url, 'tvn24.pl/rozrywka')) $tags[] = 'Rozrywka';
		else if (FALSE !== strpos($url, 'tvn24.pl/swiat')) $tags[] = 'Świat';
		else if (FALSE !== strpos($url, 'tvn24.pl/tech')) $tags[] = 'Tech';
		else if (FALSE !== strpos($url, 'tvn24.pl/zdrowie')) $tags[] = 'Zdrowie';

		format_article_photos($article, 'HEADER.article-main-photo', TRUE);
		//https://konkret24.tvn24.pl/polska,108/dlaczego-prezes-orlenu-nie-sklada-oswiadczenia-majatkowego-a-prezes-uzdrowiska-w-rabce-musi-wyjasniamy,1053560.html
		//konkret24.tvn24.pl##DIV.article-content__inner-texts.article-content__inner-texts--photo
		//konkret24.tvn24.pl##DIV.article-content__inner-texts.article-content__inner-texts--video.article-content__inner-texts--video--false
		format_article_photos($article, 'DIV.article-content__inner-texts--video', FALSE, 'src', 'DIV.article-content__inner-texts--video__metadata');
		format_article_photos($article, 'DIV.article-content__inner-texts--photo', FALSE, 'src', 'FIGCAPTION.photo-figure__caption');
		replace_tag_and_class($article, 'DIV.article-content__lead', 'single', 'STRONG', NULL);
		//konkret24.tvn24.pl##DIV.article-content__inner-texts.article-content__inner-texts--text
		foreach_replace_outertext_with_innertext($article, 'DIV.article-content__inner-texts');

		$selectors_array = array();
		$selectors_array[] = 'SCRIPT';
		$selectors_array[] = 'comment';
		$selectors_array[] = 'DIV.adoSlot';
		$selectors_array[] = 'DIV.share-container__position';
		$selectors_array[] = 'comment';
		foreach_delete_element_array($article, $selectors_array);
		$article = str_get_html($article->save());
		foreach_replace_outertext_with_innertext($article, 'DIV.article-content__inner-texts');
		$article = str_get_html($article->save());

		$next_data_array = get_json_variable_as_array($article_html, '__NEXT_DATA__', 'SCRIPT');
		$next_data_subarrays = get_subarrays_by_key($next_data_array, "detail", NULL);
		$detail_data_flattened = flatten_array($next_data_subarrays, "detail");
		if (isset($detail_data_flattened[0]["detail"]["isTruth"]))
		{
			if ("no" === $detail_data_flattened[0]["detail"]["isTruth"])
				$title = '[FAŁSZ] '.$title;
			if ("yes" === $detail_data_flattened[0]["detail"]["isTruth"])
				$title = '[PRAWDA] '.$title;
		}

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
			'content' => $article,
//			'content' => $article_html,
		);
	}

	function isValidJSON($string) 
	{
    	json_decode($string);
    	return (json_last_error() == JSON_ERROR_NONE);
	}
}
