<?php
class MagazynWPBridge extends BridgeAbstract {
	const NAME = 'Magazyn WP.pl';
	const URI = 'https://magazyn.wp.pl/';
	const DESCRIPTION = 'No description provided';
	const MAINTAINER = 'No maintainer';
	const CACHE_TIMEOUT = 86400; // Can be omitted!

	const PARAMETERS = array
	(
		'Parametry' => array
		(
			'wanted_number_of_articles' => array
			(
				'name' => 'Liczba artykułów',
				'type' => 'number',
				'required' => true
			)
		)
	);
/*
	public function getIcon()
	{
		return 'https://c.disquscdn.com/uploads/forums/349/4323/favicon.png';
	}
*/
	public function collectData()
	{
		include 'myFunctions.php';
		$GLOBALS['number_of_wanted_articles'] = $this->getInput('wanted_number_of_articles');
		$GLOBALS['my_debug'] = FALSE;
//		$GLOBALS['my_debug'] = TRUE;
		if (TRUE === $GLOBALS['my_debug'])
		{
			$GLOBALS['all_articles_time'] = 0;
			$GLOBALS['all_articles_counter'] = 0;
		}
		
		$found_urls = $this->getArticlesUrls();
//		var_dump_print($found_urls);
		
		foreach($found_urls as $url)
		{
			$this->addArticle($url);
		}
	}

	private function getArticlesUrls()
	{
		$articles_urls = array();
		$url_articles_list = 'https://magazyn.wp.pl/';
		while (count($articles_urls) < $GLOBALS['number_of_wanted_articles'] && "empty" != $url_articles_list)
		{
			$html_articles_list = getSimpleHTMLDOM($url_articles_list);
			if (0 === count($found_hrefs = $html_articles_list->find('FIGURE.teaser A[href]')))
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
		return array_slice($articles_urls, 0, $GLOBALS['number_of_wanted_articles']);
	}

	private function getNextPageUrl($html_articles_list)
	{		
		$next_page_element = $html_articles_list->find('DIV.moreTeasers', 0);
		if (FALSE === is_null($next_page_element) && $next_page_element->hasAttribute('data-url'))
		{
			return $next_page_element->getAttribute('data-url');
		}
		else
			return "empty";
	}

	private function addArticle($url)
	{
		$article_html = getSimpleHTMLDOMCached($url, 86400 * 14);
		$article = $article_html->find('ARTICLE', 0);

		foreach($article_html->find('SCRIPT') as $script_element)
		{
			if (FALSE !== strpos($script_element, 'wp_dot_addparams'))
			{
				$variables = explode(";", $script_element->innertext);
				foreach($variables as $var)
				{
					if (FALSE !== strpos($var, 'wp_dot_addparams'))
					{
						$encoded_array = str_replace("var wp_dot_addparams = ", "", $var);
						$array_params = parse_article_data(json_decode($encoded_array));

						$author = $array_params["cauthor"];
						$date = $array_params["cdate"];
						$tags_string = $array_params["ctags"];
						$tags_string = str_replace(",magazynwp:sg", "", $tags_string);
						$tags_string = str_replace("magazynwp:sg,", "", $tags_string);
						$tags = explode(",", $tags_string);
					}
				}
			}
		}

		$title_element = $article_html->find('TITLE', 0);
		if (FALSE === is_null($title_element = $article_html->find('TITLE', 0)))
		{
			$title = $title_element->plaintext;
			$title = str_replace(" – Magazyn WP", "", $title);
		}
		else
		{
			$title = "";
		}

		//Fix zdjęcia głównego
		$header_element = $article->find('HEADER.fullPage--teaser', 0);
		$photo_url = $header_element->getAttribute('data-bg');
		$teaser_element = $header_element->find('DIV.teaser--row', 0);
		$teaser_element->outertext = $teaser_element->outertext.'<img src="'.$photo_url.'">';
		
		//Fix leadu
		$lead_element = $article->find('DIV.article--lead.fb-quote', 0);
		$lead_element->innertext = $lead_element->plaintext;
		$lead_style = array(
			'font-weight: bold;'
		);
		addStyle($article, 'DIV.article--lead.fb-quote', $lead_style);

		//Fix zdjęć
		foreach($article->find('IMG') as $photo_element)
		{
			if(isset($photo_element->style)) $photo_element->style = NULL;
		}

		//Fix podpisów pod zdjęciami
		foreach($article->find('FIGCAPTION') as $caption)
		{
			$caption_text = "";
			foreach($caption->children as $caption_element)
			{
				$caption_text = $caption_text."; ".$caption_element->plaintext;
			}
			while (0 === strpos($caption_text, '; '))
			{
				$caption_text = substr_replace($caption_text, '', 0, strlen('; '));
			}
			$caption->innertext = $caption_text;
		}
		addStyle($article, 'figure', getStylePhotoParent());
		addStyle($article, 'img', getStylePhotoImg());
		addStyle($article, 'figcaption, DIV.foto-desc', getStylePhotoCaption());
		//https://magazyn.wp.pl/ksiazki/artykul/zapomniana-epidemia
		addStyle($article, 'blockquote', getStyleQuote());

		deleteAllDescendantsIfExist($article, 'FIGURE.a--instream');
		deleteAllDescendantsIfExist($article, 'SCRIPT');
		deleteAllDescendantsIfExist($article, 'A[href="#"]');
		deleteAllDescendantsIfExist($article, 'DIV.article--footer');
		deleteAllDescendantsIfExist($article, 'DIV.socials');
		deleteAllDescendantsIfExist($article, 'FIGURE.a--instream');
		deleteAllDescendantsIfExist($article, 'SCRIPT');

		$this->items[] = array(
			'uri' => $url,
			'title' => $title,
			'timestamp' => $date,
			'author' => $author,
			'categories' => $tags,
			'content' => $article
		);
	}
}
