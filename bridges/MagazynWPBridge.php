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
				'required' => true,
				'defaultValue' => 3,
			)
		)
	);


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
		$GLOBALS["amp_urls"] = array();
//		$found_urls[] = 'https://wiadomosci.wp.pl/poprosil-o-ekstradycje-do-polski-dostal-70-dni-w-karcerze-polak-z-rosyjskiego-lagru-potrzebuje-pomocy-6604992688413312a';
//		$found_urls[] = 'https://opinie.wp.pl/kataryna-zyjemy-w-okrutnym-swiecie-ale-aborcja-embriopatologiczna-musi-pozostac-opinia-6567085945505921a';
		
		foreach($found_urls as $url)
		{
			$amp_url = $this->getAmpLink($url);
			if ($url === $amp_url)
			{
				$this->addArticle($url);
			}
			else
			{
				$GLOBALS["amp_urls"][] = $this->getAmpProjectLink($amp_url);
			}
		}
		foreach($GLOBALS["amp_urls"] as $amp_url)
		{
			$this->addArticleAmp($amp_url);
		}
	}

	private function getArticlesUrls()
	{
		$articles_urls = array();
		$url_articles_list = 'https://magazyn.wp.pl/';
/*
		$articles_urls[] = 'https://magazyn.wp.pl/ksiazki/artykul/zapomniana-epidemia';
		$articles_urls[] = 'https://wiadomosci.wp.pl/poprosil-o-ekstradycje-do-polski-dostal-70-dni-w-karcerze-polak-z-rosyjskiego-lagru-potrzebuje-pomocy-6604992688413312a';
		$articles_urls[] = 'https://opinie.wp.pl/kataryna-zyjemy-w-okrutnym-swiecie-ale-aborcja-embriopatologiczna-musi-pozostac-opinia-6567085945505921a';
		$articles_urls[] = 'https://www.money.pl/gospodarka/polski-manhattan-bezdomnych-ogrzewana-nora-za-prace-w-zsypach-6629704500415008a.html';
		$articles_urls[] = 'https://sportowefakty.wp.pl/kolarstwo/926408/43-lata-temu-polscy-kolarze-zgineli-w-katastrofie-lotniczej-po-otwarciu-trumny-o';
*/
		while (count($articles_urls) < $GLOBALS['limit'] && "empty" != $url_articles_list)
		{
			$returned_array = my_get_html($url_articles_list, TRUE);
			if (200 === $returned_array['code'])
			{
				$html_articles_list = $returned_array['html'];
			}
			else
			{
				$this->items[] = $returned_array['html'];
				break;
			}
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

	private function addArticleAmp($url_article)
	{
		//echo "addArticleAmp: <br>$url_article<br><br>";
		$returned_array = my_get_html($url_article);
		if (200 === $returned_array['code'])
		{
			$article_html = $returned_array['html'];
		}
		else
		{
			$this->items[] = $returned_array['html'];
			return;
		}
		$article_html = str_get_html(prepare_article($article_html));
		$article = $article_html->find('main#content', 0);
		//https://sportowefakty-wp-pl.cdn.ampproject.org/c/s/sportowefakty.wp.pl/amp/kolarstwo/926408/43-lata-temu-polscy-kolarze-zgineli-w-katastrofie-lotniczej-po-otwarciu-trumny-o
		if (!is_null($article_test = $article->find('ARTICLE[id].article', 0)))
		{
			$article = $article_test;
		}		
		$date = get_json_value($article_html, 'SCRIPT[type="application/ld+json"]', 'datePublished');
		$title = get_json_value($article_html, 'SCRIPT[type="application/ld+json"]', 'headline');
		//nazwisko autora ma na końcu przecinek
		$author = "";
		$author_selector = 'P[data-st-area="Autor"] SPAN.uppercase, DIV.indicator__authorname SPAN.indicator__authorname--name';
		foreach ($article->find($author_selector) as $author_element)
		{
			$new_author = trim($author_element->plaintext);
			$new_author = remove_substring_if_exists_last($new_author, ",");
			$author = $author.", ".$new_author;
		}
		//$author = return_authors_as_string($article, 'P[data-st-area="Autor"] SPAN.uppercase, DIV.indicator__authorname SPAN.indicator__authorname--name', "");
		$author = remove_substring_if_exists_first($author, ", ");
		//https://www-money-pl.cdn.ampproject.org/c/s/www.money.pl/gospodarka/polski-manhattan-bezdomnych-ogrzewana-nora-za-prace-w-zsypach-6629704500415008a.html?amp=1		
		$tags = return_tags_array($article, 'P A.tag-link, P.text-grey.tags SPAN A[href]');
		//Zduplikowane zdjęcie pod "Spotkania z kumplami": https://opinie.wp.pl/wielka-zmiana-mezczyzn-wirus-ja-tylko-przyspieszyl-6604913984391297a?amp=1&_js_v=0.1
		$selectors_array[] = 'DIV.ad';
		$selectors_array[] = 'P.tags';
		$selectors_array[] = 'comment';
		$selectors_array[] = 'AMP-SOCIAL-SHARE';
		$selectors_array[] = 'SECTION.recommendations';
		$selectors_array[] = 'DIV.seolinks';
		$selectors_array[] = 'A.comment-button';
		$selectors_array[] = 'FOOTER#footer';
		//https://sportowefakty-wp-pl.cdn.ampproject.org/c/s/sportowefakty.wp.pl/amp/kolarstwo/926408/43-lata-temu-polscy-kolarze-zgineli-w-katastrofie-lotniczej-po-otwarciu-trumny-o
		$selectors_array[] = 'DIV.wpsocial-shareBox';
		$selectors_array[] = 'UL.teasers';
		$selectors_array[] = 'DIV.center-content';
		foreach_delete_element_array($article, $selectors_array);
		$article = str_get_html($article->save());
		//https://opinie.wp.pl/zakazac-demonstracji-onr-kataryna-problemy-z-demokracja-6119008400492673a?amp=1&_js_v=0.1
		//https://opinie.wp.pl/apel-o-zawieszenie-stosunkow-dyplomatycznych-z-polska-kataryna-jestem-wsciekla-6222729167030401a?amp=1&_js_v=0.1
		//https://opinie.wp.pl/kataryna-kaczynski-stawia-na-dude-6213466100516481a?amp=1&_js_v=0.1
		$article = str_get_html($article->save());
		foreach ($article->find("P") as $paragraph)
		{
			if (check_string_contains_needle_from_array($paragraph->plaintext, array("ZOBACZ WIDEO:")))
			{
				if (!is_null($paragraph->find("AMP-VIDEO-IFRAME", 0)))
				{
					$paragraph->outertext = "";
				}
			}
			else if (check_string_contains_needle_from_array($paragraph->plaintext, array("Masz newsa, zdjęcie lub filmik?")))
			{
				$paragraph->outertext = "";
			}
		}
		$article = str_get_html($article->save());
		//https://sportowefakty-wp-pl.cdn.ampproject.org/c/s/sportowefakty.wp.pl/amp/kolarstwo/926408/43-lata-temu-polscy-kolarze-zgineli-w-katastrofie-lotniczej-po-otwarciu-trumny-o
		format_article_photos($article, 'DIV.image.top-image', TRUE, 'src', 'SMALL');
		format_article_photos($article, 'DIV.image', FALSE, 'src', 'SMALL');
		format_article_photos($article, 'DIV.header-image-container', TRUE, 'src', 'DIV.header-author');
		format_article_photos($article, 'DIV.photo.from.amp', FALSE, 'src', 'FIGCAPTION');
		$article = str_get_html($article->save());
		//https://opinie.wp.pl/kataryna-zyjemy-w-okrutnym-swiecie-ale-aborcja-embriopatologiczna-musi-pozostac-opinia-6567085945505921a?amp=1&_js_v=0.1
		add_style($article, 'FIGURE.photoWrapper', getStylePhotoParent());
		add_style($article, 'FIGURE.photoWrapper IMG', getStylePhotoImg());
		add_style($article, 'FIGCAPTION', getStylePhotoCaption());
		add_style($article, 'BLOCKQUOTE', getStyleQuote());
		$article = str_get_html($article->save());
		$this->items[] = array(
			//Fix &amp z linku
			'uri' => htmlentities($url_article, ENT_QUOTES, 'UTF-8'),
			'title' => getChangedTitle($title),
			'timestamp' => $date,
			'author' => $author,
			'categories' => $tags,
			'content' => $article
		);
	}


	private function getAmpLink($url)
	{
		if (FALSE !== strpos($url, 'opinie.wp.pl/'))
		{
			return $url."?amp=1";
		}
		else if (FALSE !== strpos($url, 'sportowefakty.wp.pl/'))
		{
			return str_replace("sportowefakty.wp.pl/", "sportowefakty.wp.pl/amp/", $url);
		}
		else if (FALSE !== strpos($url, 'wiadomosci.wp.pl/'))
		{
			return $url."?amp=1";
		}
		else if (FALSE !== strpos($url, 'money.pl/'))
		{
			return $url."?amp=1";
		}
		else return $url;
	}

	private function getAmpProjectLink($url)
	{
		$url_array = parse_url($url);
		$old_scheme = $url_array["scheme"].'://';
		$old_prefix = $old_scheme.$url_array["host"];
		$amp_link_edit = str_replace($old_scheme, "", $url);
		$prefix_edit = str_replace(".", "-", $old_prefix);
		$new_url = $prefix_edit.".cdn.ampproject.org/c/s/".$amp_link_edit;
		return $new_url;
	}

	private function addArticle($url)
	{
		$returned_array = my_get_html($url, TRUE);
		if (200 === $returned_array['code'])
		{
			$article_html = $returned_array['html'];
		}
		else
		{
			$this->items[] = $returned_array['html'];
			return;
		}
		$amp_link = get_text_from_attribute($article_html, 'LINK[rel="amphtml"][href]', 'href', "");
		if (1 < strlen($amp_link))
		{
			$GLOBALS["amp_urls"][] = $this->getAmpProjectLink($amp_link);
			return;
		}
		if (FALSE === is_null($type_news_article = $article_html->find('ARTICLE.article.premium', 0)))
		{
			$this->addArticleStandard($article_html, $url);
		}
		else if (FALSE === is_null($type_magazine_article = $article_html->find('A.logo--magazyn', 0)))
		{
			$this->addArticleMagazine($article_html, $url);
		}
		else
		{
			echo "typ: Inny typ, url: $url<br><br>";
		}
	}

	private function addArticleStandard($article_html, $url)
	{
//		echo "typ: ARTICLE.article.premium, url: $url<br><br>";
		$found_urls[] =	'https://opinie.wp.pl/kataryna-zyjemy-w-okrutnym-swiecie-ale-aborcja-embriopatologiczna-musi-pozostac-opinia-6567085945505921a';
//		$found_urls[] = 'https://wiadomosci.wp.pl/poprosil-o-ekstradycje-do-polski-dostal-70-dni-w-karcerze-polak-z-rosyjskiego-lagru-potrzebuje-pomocy-6604992688413312a';
//		$found_urls[] = 'https://opinie.wp.pl/kataryna-zyjemy-w-okrutnym-swiecie-ale-aborcja-embriopatologiczna-musi-pozostac-opinia-6567085945505921a';
//		$article_html = $article_html->find('//ARTICLE[@class]', 0);
		$article_html = $article_html->find('ARTICLE.article', 0);
		$article_html = $article_html->parentNode();
		//kategorie - tagi
		$tags = return_tags_array($article_html, 'A[class^="subjectsList"][href^="/tag/"]');
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
		foreach_delete_element($article_html, 'comment');
		//usuniecie elementu z reklamami w tresci
		foreach_delete_element($article_html, '//div/div/div[count(*)=3][img[@class][@src]][*[count(*)=1]/*[count(*)=1]/*[count(*)=1]/*[count(*)=1]/*[count(*)=0]]');
		//usuniecie pustego elementu
		foreach_delete_element($article_html, 'DIV[data-st-area="article-header"]');
		//usuniecie niepotrzebnego elementu w leadzaie
		foreach_delete_element($article_html, 'DIV.premium--full FIGURE SPAN DIV');
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
		add_style($article_html, 'DIV.article--lead', $lead_style);
		//styl cytatu
		add_style($article_html, 'blockquote', getStyleQuote());
		//styl holdera zdjęcia w treści i leadzie
		add_style($article_html, 'DIV.premium--wide.photo, DIV.premium--full FIGURE', getStylePhotoParent());
		//styl zdjecia a treści i leadzie
		add_style($article_html, 'DIV.photo-holder, DIV.premium--full FIGURE SPAN[class]', getStylePhotoImg());
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
			$span_text = remove_substring_if_exists_first($span_text, '; ');
			$small->innertext = '<span>'.$span_text.'</span>';
		}
		add_style($article_html, 'SMALL.article--mainPhotoSource', getStylePhotoCaption());
		//tytul
		if (FALSE === is_null($title_element = $article_html->find('H1.article--title', 0)))
			$title = trim($title_element->plaintext);
		//data
		if (FALSE === is_null($date_element = $article_html->find('META[itemprop="datePublished"][content]', 0)))
			$date = $date_element->getAttribute('content');
		//autor
		$author = return_authors_as_string($article_html, 'SPAN.signature--author');
		$article = $article_html;
		$this->items[] = array(
			'uri' => $url,
			'title' => getChangedTitle($title),
			'timestamp' => $date,
			'author' => $author,
			'categories' => $tags,
			'content' => $article
		);
	}

	private function addArticleMagazine($article_html, $url)
	{
		$article_html = str_get_html(prepare_article($article_html));
		$article = $article_html->find('ARTICLE.article--center', 0);
		//autor
		$author = return_authors_as_string($article, 'DIV.teaser--row SPAN.author');
		//data
		$date = get_json_value($article_html, 'HEAD SCRIPT', 'cdate');
		//tags
		$tags_string = get_json_value($article_html, 'HEAD SCRIPT', 'ctags');
		//https://magazyn.wp.pl/informacje/artykul/wstrzasajace-jak-wybiorczo-pis-traktuje-fakty-w-sprawie-lotow-opinia
		$tags_string = json_decode("\"$tags_string\"");
		$tags_string = str_replace(",magazynwp:sg", "", $tags_string);
		$tags_string = str_replace("magazynwp:sg,", "", $tags_string);
		$tags = explode(",", $tags_string);
		//title
		$title = get_text_plaintext($article, 'HEADER.fullPage--teaser H1', $url);
		//usuwanie elementów
		$selectors_array[] = 'A[href="#"]';
		$selectors_array[] = 'DIV.socials';
		$selectors_array[] = 'DIV.article--footer';
		$selectors_array[] = 'SCRIPT';
		$selectors_array[] = 'FIGURE.a--instream';
		$selectors_array[] = 'qqqqqqqq';
		$selectors_array[] = 'qqqqqqqq';
		$selectors_array[] = 'qqqqqqqq';
		foreach_delete_element_array($article, $selectors_array);
		$article = str_get_html($article->save());
		//Zamiana elementu z perwszą literą na tekst
		foreach_replace_outertext_with_plaintext($article, "SPAN.first-letter");
		//lead
		replace_tag_and_class($article, 'DIV.article--lead.fb-quote', 'single', 'STRONG', 'lead');
		move_element($article, 'HEADER.fullPage--teaser DIV.teaser--row', 'HEADER.fullPage--teaser', 'outertext', 'after');
		$article = str_get_html($article->save());
		//zdjęcia w treści
		format_article_photos($article, 'FIGURE', FALSE, 'src', 'FIGCAPTION');
		$article = str_get_html($article->save());
		//Zdjęcie główne
		if (!is_null($header_element = $article->find('HEADER[data-bg]', 0)) && !is_null($center_element = $article->find('HEADER[data-bg] DIV.center', 0)))
		{
			$photo_url = $header_element->getAttribute('data-bg');
			$center_element->outertext = $center_element->outertext.'<img src="'.$photo_url.'">';
			$article = str_get_html($article->save());
			format_article_photos($article, 'HEADER.fullPage--teaser', TRUE, 'src', 'DIV.foto-desc');
			$article = str_get_html($article->save());
		}
		//niepotrzebna spacja
		if (!is_null($teaser_element = $article->find('DIV.teaser--row', 0)))
		{
			$new_innertext = "";
			foreach($teaser_element->children() as $child)
			{
				$new_innertext = $new_innertext.$child->outertext;
			}
			$teaser_element->innertext = $new_innertext;
		$article = str_get_html($article->save());
		}
		//Fix podpisów pod zdjęciami
		foreach($article->find('FIGCAPTION') as $caption)
		{
			$caption_text = "";
			foreach($caption->children as $caption_element)
			{
				$caption_text = $caption_text."; ".trim($caption_element->plaintext);
			}
			$caption_text = remove_substring_if_exists_first($caption_text, "; ");
			$caption->innertext = $caption_text;
		}
		$article = str_get_html($article->save());
		add_style($article, 'FIGURE.photoWrapper', getStylePhotoParent());
		add_style($article, 'FIGURE.photoWrapper IMG', getStylePhotoImg());
		add_style($article, 'FIGCAPTION', getStylePhotoCaption());
		//https://magazyn.wp.pl/ksiazki/artykul/zapomniana-epidemia
		add_style($article, 'BLOCKQUOTE', getStyleQuote());
		$article = str_get_html($article->save());
		$this->items[] = array(
			'uri' => $url,
			'title' => getChangedTitle($title),
			'timestamp' => $date,
			'author' => $author,
			'categories' => $tags,
			'content' => $article
		);
	}
}
