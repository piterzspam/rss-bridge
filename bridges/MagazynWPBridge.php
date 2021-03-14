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
			'limit' => array
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
		$GLOBALS['limit'] = $this->getInput('limit');
		$GLOBALS['my_debug'] = FALSE;
//		$GLOBALS['my_debug'] = TRUE;
		if (TRUE === $GLOBALS['my_debug'])
		{
			$GLOBALS['all_articles_time'] = 0;
			$GLOBALS['all_articles_counter'] = 0;
		}
		
		$found_urls = $this->getArticlesUrls();
//		var_dump_print($found_urls);
//		$found_urls[] = 'https://wiadomosci.wp.pl/poprosil-o-ekstradycje-do-polski-dostal-70-dni-w-karcerze-polak-z-rosyjskiego-lagru-potrzebuje-pomocy-6604992688413312a';
//		$found_urls[] = 'https://opinie.wp.pl/kataryna-zyjemy-w-okrutnym-swiecie-ale-aborcja-embriopatologiczna-musi-pozostac-opinia-6567085945505921a';
		
		foreach($found_urls as $url)
		{
			$this->addArticle($url);
		}
	}

	private function getArticlesUrls()
	{
		$articles_urls = array();
		$url_articles_list = 'https://magazyn.wp.pl/';
		while (count($articles_urls) < $GLOBALS['limit'] && "empty" != $url_articles_list)
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
		return array_slice($articles_urls, 0, $GLOBALS['limit']);
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
		$title = "";
		$date = "";
		$author = "";
		$tags = "";
		$article = "";

//		echo "url:<br> $url<br>";
//		echo "article_html:<br> $article_html<br><br><br><br>";
		if (FALSE === is_null($type_news_article = $article_html->find('ARTICLE.article.premium', 0)))
		{
//			$found_urls[] = 'https://wiadomosci.wp.pl/poprosil-o-ekstradycje-do-polski-dostal-70-dni-w-karcerze-polak-z-rosyjskiego-lagru-potrzebuje-pomocy-6604992688413312a';
//			$found_urls[] = 'https://opinie.wp.pl/kataryna-zyjemy-w-okrutnym-swiecie-ale-aborcja-embriopatologiczna-musi-pozostac-opinia-6567085945505921a';
//			$article_html = $article_html->find('//ARTICLE[@class]', 0);
			$article_html = $article_html->find('ARTICLE.article', 0);
			$article_html = $article_html->parentNode();

			//kategorie - tagi
			$tags = returnTagsArray($article_html, 'A[class^="subjectsList"][href^="/tag/"]');

			//usunięcie elementu z kategoriami
			if (FALSE === is_null($header_element = $article_html->find('DIV[data-st-area="article-header"]', 0)))
			{
				$header_element->previousSibling()->outertext = '';
				foreach($header_element->find('A, DIV') as $useless_element)
					$useless_element->outertext = '';
			}

			//usunięcie elementu z lajkami
			//usunięcie elementu autora: https://opinie.wp.pl/kataryna-zyjemy-w-okrutnym-swiecie-ale-aborcja-embriopatologiczna-musi-pozostac-opinia-6567085945505921a
			if (FALSE === is_null($lead_element = $article_html->find('DIV.article--lead', 0)) && FALSE === is_null($first_text = $article_html->find('DIV.article--text', 0)))
			{
				if ($lead_element->nextSibling() === $first_text->previousSibling())
					$lead_element->nextSibling()->outertext = '';
				if ($lead_element->nextSibling() === $first_text->previousSibling()->previousSibling())
				{
					$temp_element = $lead_element->nextSibling();
					if (FALSE === is_null($icon_element = $temp_element->find('svg[xmlns]', 0)))
					{
						$temp_element->outertext = '';
					}
					$temp_element = $lead_element->nextSibling()->nextSibling();
					if (FALSE === is_null($autor_link_element = $temp_element->find('SECTION A[class][href^="/autor/"]', 0)))
					{
						$temp_element->outertext = '';
					}
				}
			}

			//usunięcie elementu z lajkami
			if (FALSE === is_null($lead_element = $article_html->find('DIV.article--lead', 0)) && FALSE === is_null($first_text = $article_html->find('DIV.article--text', 0)))
			{
				if ($lead_element->nextSibling() === $first_text->previousSibling())
					$lead_element->nextSibling()->outertext = '';
			}

			deleteAllDescendantsIfExist($article_html, 'comment');
			//usuniecie elementu z reklamami w tresci
			deleteAllDescendantsIfExist($article_html, '//div/div/div[count(*)=3][img[@class][@src]][*[count(*)=1]/*[count(*)=1]/*[count(*)=1]/*[count(*)=1]/*[count(*)=0]]');
			//usuniecie pustego elementu
			deleteAllDescendantsIfExist($article_html, 'DIV[data-st-area="article-header"]');
			//usuniecie niepotrzebnego elementu w leadzaie
			deleteAllDescendantsIfExist($article_html, 'DIV.premium--full FIGURE SPAN DIV');

			//usunięcie ostatniego elementu z linkiem do dziejesie.wp.pl
			foreach($article_html->find('DIV.article--text') as $article_text_element)
			{
				if (FALSE === is_null($last_text_element = $article_text_element->find('P STRONG A[href="https://dziejesie.wp.pl/"][target="_blank"]', 0)))
				{
					if (FALSE === is_null($next_element = $article_text_element->nextSibling()))
					{
						if (FALSE === is_null($span_element = $next_element->find('SPAN', 0)))
						{
							$next_element->outertext = '';
						}
					}
					$article_text_element->outertext = '';
				}
			}

			//usunięcie klas dla porządku w kodzie
			foreach($article_html->find('[data-reactid]') as $data_reactid)
			{
				$data_reactid->setAttribute('data-reactid', NULL);
			}

			//usunięcie parametrów odpowiedzialnych za rozmiar
			foreach($article_html->find('[style*="width:"]') as $style_width)
			{
				$style_width->setAttribute('style', NULL);
			}

			//usunięcie parametrów odpowiedzialnych za rozmiar
			foreach($article_html->find('[width], [height]') as $size_element)
			{
				if($size_element->hasAttribute('width')) $size_element->setAttribute('width', NULL);
				if($size_element->hasAttribute('height')) $size_element->setAttribute('height', NULL);
			}

			//ustawienia źródła zdjęcia
			foreach($article_html->find('IMG[class][src^="data:image/"][data-src]') as $photo_element)
			{
				if($photo_element->hasAttribute('data-src')) $photo_url = $photo_element->getAttribute('data-src');
				$photo_element->setAttribute('data-src', NULL);
				$photo_element->setAttribute('src', $photo_url);
				if($photo_element->hasAttribute('data-sizes')) $photo_element->setAttribute('data-sizes', NULL);
			}

			//ustawienia łatwiejszych klas dla elementów ze zdjęciami
			foreach($article_html->find('DIV.premium--wide') as $photo_element)
			{
				if (FALSE === is_null($is_image = $photo_element->find('IMG.lazyload', 0)))
				{
					$photo_element->setAttribute('class', 'premium--wide photo');
					$photo_element->find('DIV', 0)->setAttribute('class', 'photo-holder');
				}
			}

			//styl leadu
			$lead_style = array(
				'font-weight: bold;'
			);
			addStyle($article_html, 'DIV.article--lead', $lead_style);
			//styl cytatu
			addStyle($article_html, 'blockquote', getStyleQuote());
			//styl holdera zdjęcia w treści i leadzie
			addStyle($article_html, 'DIV.premium--wide.photo, DIV.premium--full FIGURE', getStylePhotoParent());
			//styl zdjecia a treści i leadzie
			addStyle($article_html, 'DIV.photo-holder, DIV.premium--full FIGURE SPAN[class]', getStylePhotoImg());

			//podpis zdjęcia w jednym elemencie
			foreach($article_html->find('SMALL.article--mainPhotoSource') as $small)
			{
				$span_array = array();
				foreach($small->find('SPAN') as $span)
				{
					$span_array[] = trim($span->plaintext);
					$span->outertext = '';
				}
				$span_text = trim(implode('; ', $span_array));
				$span_text = removeSubstringIfExistsFirst($span_text, '; ');
				$small->innertext = '<span>'.$span_text.'</span>';
			}
			addStyle($article_html, 'SMALL.article--mainPhotoSource', getStylePhotoCaption());

			//tytul
			if (FALSE === is_null($title_element = $article_html->find('H1.article--title', 0)))
				$title = trim($title_element->plaintext);
			//data
			if (FALSE === is_null($date_element = $article_html->find('META[itemprop="datePublished"][content]', 0)))
				$date = $date_element->getAttribute('content');
			//autor
			$author = returnAuthorsAsString($article_html, 'SPAN.signature--author');

//DIV.article--lead + DIV[class] + ASIDE[class] + DIV.article--text
			
//			echo "url:<br> $url<br>";
//			echo "typ_artykul_article_html:<br> $article_html<br><br><br><br>";
			$article = $article_html;
//			return;
		}
		else if (FALSE === is_null($type_magazine_article = $article_html->find('A.logo--magazyn', 0)))
		{
//			echo "url:<br> $url<br>";
//			echo "typ_magazyn_article_html:<br> $article_html<br><br><br><br>";
//			return;
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
			if($header_element->hasAttribute('data-bg')) $photo_url = $header_element->getAttribute('data-bg');
			$teaser_element = $header_element->find('DIV.teaser--row', 0);
			$teaser_element->outertext = $teaser_element->outertext.'<img src="'.$photo_url.'">';

			//Fix leadu
			$lead_element = $article->find('DIV.article--lead.fb-quote', 0);
			$first_letter = $lead_element->find('SPAN.first-letter', 0);
			$first_letter->outertext = trim($first_letter->plaintext);
//			$lead_element->innertext = trim($lead_element->plaintext);
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
//			deleteAllDescendantsIfExist($article, '//div/div/div[count(*)=3][img[@class][@src]][*[count(*)=1]/*[count(*)=1]/*[count(*)=1]/*[count(*)=1]/*[count(*)=0]]');

//			:xpath(//DIV[@class="opbox-listing"]//DIV//SECTION//H2[text()="Oferty promowane"]/following-sibling::ARTICLE | //DIV[@class="opbox-listing"]//DIV//SECTION//H2[text()="Oferty"]/preceding-sibling::ARTICLE)
		}
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
