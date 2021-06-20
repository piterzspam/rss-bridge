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
//		$found_urls[] = "https://www.o2.pl/artykul/jaroslaw-walesa-zza-kulis-o-ojcu-zonie-i-walce-o-zycie-wywiad-6423688712931457a?fbclid=IwAR0Z2BrE6mII1P8z_Qm-u7edzaC3FbkyCmTn7vfleIv5w2hL6Y04clNeF0M";
//		$found_urls[] = "https://wiadomosci.wp.pl/zycie-ze-spadkiem-po-koronawirusie-czyli-wojna-za-wojna-6639264505064320a";
//		$found_urls[] = "qqqqqqqqqqqq";
//		$found_urls[] = "qqqqqqqqqqqq";
/*
$found_urls[] = "https://sportowefakty.wp.pl/alpinizm/903081/anna-solska-mackiewicz-jestesmy-z-niego-dumni-energia-tomka-wciaz-zyje";
$found_urls[] = "https://sportowefakty.wp.pl/euro-2020-2021/premium/943856/aleksander-kwasniewski-mam-klopot-z-wiara-w-reprezentacje";
$found_urls[] = "https://sportowefakty.wp.pl/kajakarstwo/841641/marta-walczykiewicz-potrafie-oddac-serce-wywiad";
$found_urls[] = "https://sportowefakty.wp.pl/kolarstwo/896405/wakacje-2020-wystarczy-jeden-krok-aby-stracic-zycie";
$found_urls[] = "https://sportowefakty.wp.pl/kolarstwo/926408/43-lata-temu-polscy-kolarze-zgineli-w-katastrofie-lotniczej-po-otwarciu-trumny-o";
$found_urls[] = "https://sportowefakty.wp.pl/koszykowka/844371/adam-waczynski-ciary-po-plecach-wywiad";
$found_urls[] = "https://sportowefakty.wp.pl/la/844179/lekkoatletyka-ms-2019-doha-marcin-lewandowski-czuwa-nade-mna-aniol-stroz";
$found_urls[] = "https://sportowefakty.wp.pl/la/premium/937591/na-ulicy-medali-juz-nie-wieszaja";
$found_urls[] = "https://sportowefakty.wp.pl/la/premium/938325/charakter-rodzi-sie-w-glowie";
$found_urls[] = "https://sportowefakty.wp.pl/la/premium/938588/mozesz-byc-smutna-mamo-ale-musisz-zyc";
$found_urls[] = "https://sportowefakty.wp.pl/mma/premium/937021/droga-do-marzen";
*/
/*
$found_urls[] = "https://magazyn.wp.pl/moto/artykul/zanim-przyjedzie-karetka";
$found_urls[] = "https://magazyn.wp.pl/moto/artykul/zmyslowski-wodor-to-przyszlosc-ale-diesel-zostanie-z-nami-jeszcze-dlugo";
$found_urls[] = "https://magazyn.wp.pl/moto/artykul/zycie-z-deadlinem";
$found_urls[] = "https://magazyn.wp.pl/tech/artykul/50-lat-temu-w-katowicach-wyladowalo-ufo";
$found_urls[] = "https://magazyn.wp.pl/tech/artykul/ach-coz-to-byl-za-slub";
$found_urls[] = "https://magazyn.wp.pl/tech/artykul/aleksander-doba-ostatni-romantyk-wielkich-podrozy";
$found_urls[] = "https://magazyn.wp.pl/tech/artykul/alfredl-redl-podwojny-agent-ktory-zachwial-cesarstwem";
$found_urls[] = "https://magazyn.wp.pl/tech/artykul/bursztynowy-szpieg-stary-nazista-i-tajemnica-bursztynowej-komnaty";
$found_urls[] = "https://magazyn.wp.pl/tech/artykul/cud-znad-wisly-polska-perla-popkultury";
$found_urls[] = "https://magazyn.wp.pl/tech/artykul/dwoje-bezdzietnych-boomerow-rozmawia-o-dzieciach";
*/
$found_urls[] = "https://wiadomosci.wp.pl/ujawniamy-oto-majatek-mateusza-i-iwony-morawieckich-6643252365990816a";

$found_urls[] = "https://film.wp.pl/brad-pitt-dla-wp-ostatnio-troche-myslalem-o-smierci-6428961770256001a";
$found_urls[] = "https://finanse.wp.pl/emerytalne-perpetuum-mobile-system-stanal-na-glowie-6641692256443328a";
$found_urls[] = "https://gwiazdy.wp.pl/kazik-staszewski-wrocilem-do-swoich-zasad-6646710523185952a";
$found_urls[] = "https://kobieta.wp.pl/polska-bieda-nie-zniknela-przyczaila-sie-tylko-i-wyglada-inaczej-6427938873690241a";
$found_urls[] = "https://ksiazki.wp.pl/istnieja-tylko-zycia-ludzi-czyli-czym-zachwyca-proza-tokarczuk-6433949495383681a";

$found_urls[] = "https://ksiazki.wp.pl/w-podrozy-z-adolfem-hitlerem-6428692796860545a";
$found_urls[] = "https://opinie.wp.pl/ten-niedobry-komunista-joe-felieton-6649523316730784a";
$found_urls[] = "https://tech.wp.pl/amerykanski-sen-ryszarda-bez-wizy-ale-z-oddechem-smierci-na-plecach-6433923530978945a";
$found_urls[] = "https://teleshow.wp.pl/kim-jest-dorota-kania-to-ona-zdecyduje-o-przekazie-do-17-mln-czytelnikow-mediow-orlenu-6626181291801216a";
$found_urls[] = "https://turystyka.wp.pl/wyspy-zielonego-przyladka-sal-tropem-atrakcji-6466181276333697a";
//$found_urls[] = "https://video.wp.pl/zbrodnia-bez-kary-kiepska-pamiec-erny-wallisch-strazniczki-z-majdanka-6431508306228865v";
$found_urls[] = "https://wiadomosci.wp.pl/adam-bodnar-czekam-na-lepsze-czasy-6642150627334784a";
$found_urls[] = "https://wiadomosci.wp.pl/beata-zegnaj-beata-wroc-szydlo-juz-wie-ze-dobrze-atakowac-z-cienia-6642074199394944a";
//$found_urls[] = "https://wideo.wp.pl/zbrodnia-bez-kary-co-horst-pilarzik-robil-w-plaszowie-christiane-szuka-prawdy-6440983341185153v";


		//print_var_dump($found_urls, "found_urls");
		$GLOBALS["amp_urls_data"] = array();
		$amp_urls_data = array();
		foreach($found_urls as $url)
		{
			$amp_urls_data[] = $this->getAmpData($url);
		}
//		print_var_dump($amp_urls_data, "amp_urls_data");
		foreach($amp_urls_data as $amp_url_data)
		{
			$this->addArticle2($amp_url_data);
		}
	}
	private function addArticle2($amp_url_data)
	{
		if ("sportowefakty_wp_pl" === $amp_url_data["type"])
		{
			$returned_array = my_get_html($amp_url_data["ampproject_url"]);
			if (200 !== $returned_array['code'])
			{
				$this->items[] = $returned_array['html'];
				return;
			}
			else
			{
				$article_html = str_get_html(prepare_article($returned_array['html']));
			}
			$url_article = $amp_url_data["ampproject_url"];
			$article = $article_html->find('ARTICLE', 0);
			$title = get_text_from_attribute($article_html, 'META[property="og:title"][content]', 'content', NULL);
			$title = remove_substring_if_exists_last($title, " - WP SportoweFakty");
			$datePublished = get_json_value($article_html, 'SCRIPT[type="application/ld+json"]', 'datePublished');
			$dateModified = get_json_value($article_html, 'SCRIPT[type="application/ld+json"]', 'dateModified');
			$author = get_text_plaintext($article, 'SPAN.indicator__authorname--name', NULL);
			$returned_array = my_get_html($amp_url_data["canonical_url"]);
			if (200 !== $returned_array['code'])
			{
				$tags = array();
			}
			else
			{
				$article_html = $returned_array['html'];
				$tags = return_tags_array($article_html, 'ADDRESS.articletags A[href]');
			}
			$selectors_array = array();
			$selectors_array[] = 'comment';
			$selectors_array[] = 'script';
			$selectors_array[] = 'DIV.wpsocial-shareBox';
			$selectors_array[] = 'UL.teasers';
			$selectors_array[] = 'DIV.center-content';
			$article = foreach_delete_element_array($article, $selectors_array);
			$attributes_array = array();
			$attributes_array[] = "id";
			$attributes_array[] = "data-vars-stat-tag";
			$attributes_array[] = "data-vars-target-image";
			$attributes_array[] = "data-vars-target-title";
			$attributes_array[] = "data-vars-target-url";
			$article = remove_multiple_attributes($article, $attributes_array);
			$article = replace_attribute($article, 'IMG[class]', 'class', NULL);
			$article = foreach_replace_outertext_with_plaintext($article, 'A[data-st-area="Artykul-link-tag-mob"]');

			//Wideo powiązane w treści
			$article_str = $article->save();
			foreach ($article->find("P") as $paragraph)
			{
				foreach ($paragraph->find("STRONG") as $strong)
				{
					if (check_string_contains_needle_from_array($strong->plaintext, array("ZOBACZ WIDEO")))
					{
						$current_child = $strong;
						$outertext_to_remove = $strong->outertext;
						//https://sportowefakty-wp-pl.cdn.ampproject.org/v/s/sportowefakty.wp.pl/amp/kolarstwo/926408/43-lata-temu-polscy-kolarze-zgineli-w-katastrofie-lotniczej-po-otwarciu-trumny-o?amp_js_v=0.1
						while (FALSE === is_null($next_child = $current_child->next_sibling()))
						{
							if ("br" === strtolower($next_child->tag))
							{
								$outertext_to_remove = $outertext_to_remove.$next_child->outertext;
								$current_child = $current_child->next_sibling();
							}
							else if ("amp-video-iframe" === strtolower($next_child->tag))
							{
								if (FALSE === is_null($possible_next_child = $next_child->next_sibling()))
								{//nie jest to ostatni element w paragrafie, wiec usuwane tylko te, co były do tej pory
									$outertext_to_remove = $outertext_to_remove.$next_child->outertext;
									$article_str = str_replace($outertext_to_remove, "", $article_str);
								}
								else
								{
									$article_str = str_replace($paragraph, "", $article_str);
								}
								break;
							}
							else
							{
								break;
							}
						}
					}
				}
			}
			$article = str_get_html($article_str);

			$article = replace_tag_and_class($article, 'ARTICLE SPAN.h5', 'single', 'STRONG', 'lead');
			$article = replace_tag_and_class($article, 'ARTICLE ADDRESS.indicator', 'single', 'DIV', 'author');

			$article = replace_date($article, 'TIME.indicator__time', $datePublished, $dateModified);

			$article = format_article_photos($article, 'DIV.image.top-image', TRUE, 'src', 'SMALL');
			$article = format_article_photos($article, 'DIV.image', FALSE, 'src', 'SMALL');

			$article = move_element($article, 'FIGURE.photoWrapper.mainPhoto', 'ARTICLE', 'innertext', 'before');
			$article = move_element($article, 'STRONG.lead', 'ARTICLE', 'innertext', 'before');
			$article = move_element($article, 'DIV.dates', 'ARTICLE', 'innertext', 'before');
			$article = move_element($article, 'H1.title', 'ARTICLE', 'innertext', 'before');
			$article = move_element($article, 'DIV.author', 'ARTICLE', 'innertext', 'after');
			$article = insert_html($article, 'DIV.author', '', '', '<HR>');
		}
		else if ("magazyn_wp_pl" === $amp_url_data["type"])
		{
			$returned_array = my_get_html($amp_url_data["canonical_url"]);
			if (200 !== $returned_array['code'])
			{
				$this->items[] = $returned_array['html'];
				return;
			}
			else
			{
				$article_html = str_get_html(prepare_article($returned_array['html']));
			}
			$url_article = $amp_url_data["canonical_url"];
			$article = $article_html->find('ARTICLE.article--center', 0);
			$author = return_authors_as_string($article, 'DIV.teaser--row SPAN.author');
			$datePublished = get_json_value($article_html, 'HEAD SCRIPT', 'cdate');
			$tags_string = get_json_value($article_html, 'HEAD SCRIPT', 'ctags');
			//https://magazyn.wp.pl/informacje/artykul/wstrzasajace-jak-wybiorczo-pis-traktuje-fakty-w-sprawie-lotow-opinia
			$tags_string = json_decode("\"$tags_string\"");
			$tags_string = str_replace(",magazynwp:sg", "", $tags_string);
			$tags_string = str_replace("magazynwp:sg,", "", $tags_string);
			$tags = explode(",", $tags_string);
			$title = get_text_plaintext($article, 'HEADER.fullPage--teaser H1', NULL);
			//Zdjęcie główne
			$article = move_element($article, 'FIGURE.teaser', 'HEADER', 'outertext', 'after');
			if (!is_null($header_element = $article->find('HEADER[data-bg]', 0)))
			{
				$photo_url = $header_element->getAttribute('data-bg');
				if (!is_null($caption = $header_element->find('DIV.foto-desc', 0)))
				{
					$main_photo_html = '<figure class="photoWrapper mainPhoto"><img src="'.$photo_url.'"><figcaption>'.$caption->innertext.'</figcaption></figure>';
				}
				else
				{
					$main_photo_html = '<figure class="photoWrapper mainPhoto"><img src="'.$photo_url.'"></figure>';
				}
				$header_element->outertext = $main_photo_html;
			}
			$article = format_article_photos($article, 'FIGURE.photoWrapper.mainPhoto', TRUE, 'src', 'FIGCAPTION');
			$article = format_article_photos($article, 'FIGURE[!class]', FALSE, 'src', 'FIGCAPTION');

			foreach($article->find('FIGURE FIGCAPTION') as $caption)
			{
				$new_caption_text = "";
				foreach($caption->children as $paragraph)
				{
					if (0 < strlen($current_text = trim($paragraph->plaintext)))
					{
						if ("" === $new_caption_text)
						{
							$new_caption_text = $current_text;
						}
						else
						{
							$new_caption_text = $new_caption_text."; ".$current_text;
						}
					}
				}
				if ("" === $new_caption_text)
				{
					$caption->outertext = "";
				}
				else
				{
					$caption->innertext = $new_caption_text;
				}
			}
			$article = str_get_html($article->save());
			$article = foreach_replace_outertext_with_innertext($article, 'DIV.article--body');
			$article = foreach_replace_outertext_with_innertext($article, 'DIV.wrapper');
			$article = foreach_replace_outertext_with_innertext($article, 'DIV.fullPage--rest-of-art');	
			//cytat https://magazyn.wp.pl/ksiazki/artykul/zapomniana-epidemia
			$article = replace_date($article, 'SPAN.time', $datePublished);
			//lead
			$article = replace_tag_and_class($article, 'DIV.article--lead.fb-quote', 'single', 'STRONG', 'lead');
			$article = replace_tag_and_class($article, 'DIV.article--author-wrapper-new', 'single', 'DIV', 'author');
			$article = replace_tag_and_class($article, 'A[href="#"] H1', 'single', 'H1', 'title');
			$article = move_element($article, 'FIGURE.photoWrapper.mainPhoto', 'ARTICLE', 'innertext', 'before');
			$article = move_element($article, 'STRONG.lead', 'ARTICLE', 'innertext', 'before');
			$article = move_element($article, 'DIV.dates', 'ARTICLE', 'innertext', 'before');
			$article = move_element($article, 'H1.title', 'ARTICLE', 'innertext', 'before');
			$article = move_element($article, 'DIV.author', 'ARTICLE', 'innertext', 'after');
			$article = insert_html($article, 'DIV.author', '', '', '<HR>');
			$article = move_element($article, 'DIV.author SPAN.article--author-name', 'DIV.author DIV.article--avatar', 'outertext', 'before');
			$selectors_array = array();
			$selectors_array[] = 'DIV.socials';
			$selectors_array[] = 'DIV.article--footer';
			$selectors_array[] = 'comment';
			$selectors_array[] = 'SCRIPT';
			$selectors_array[] = 'FIGURE.a--instream';
			$selectors_array[] = 'FIGURE.teaser';
			$article = foreach_delete_element_array($article, $selectors_array);
		}
		else if ("wp_pl" === $amp_url_data["type"])
		{
			$returned_array = my_get_html($amp_url_data["ampproject_url"]);
			if (200 !== $returned_array['code'])
			{
				$this->items[] = $returned_array['html'];
				return;
			}
			else
			{
				$article_html = str_get_html(prepare_article($returned_array['html']));
			}
			$url_article = $amp_url_data["ampproject_url"];
			$article = $article_html->find('MAIN', 0);
			$title = get_text_plaintext($article, 'H1', NULL);
			$datePublished = get_json_value($article_html, 'SCRIPT[type="application/ld+json"]', 'datePublished');
			$dateModified = get_json_value($article_html, 'SCRIPT[type="application/ld+json"]', 'dateModified');
			$tags = return_tags_array($article_html, 'P.tags A[href]');
			$author_from_element = get_text_plaintext($article, 'P[data-st-area="Autor"] SPAN.uppercase', NULL);
			$author_from_element = trim($author_from_element);
			$author_from_element = remove_substring_if_exists_last($author_from_element, ",");
			$author = $author_from_element;
			$selectors_array = array();
			$selectors_array[] = 'comment';
			$selectors_array[] = 'SCRIPT';
			$selectors_array[] = 'DIV.seolinks';
			$selectors_array[] = 'DIV.ad';
			$selectors_array[] = 'FOOTER';
			$selectors_array[] = 'SECTION.recommendations';
			$selectors_array[] = 'A.comment-button';
			$selectors_array[] = 'AMP-SOCIAL-SHARE';
			$selectors_array[] = 'P.tags';
			$article = foreach_delete_element_array($article, $selectors_array);
			$attributes_array = array();
			$attributes_array[] = "id";
			$article = remove_multiple_attributes($article, $attributes_array);
			
			//Wideo powiązane w treści
			//https://gwiazdy-wp-pl.cdn.ampproject.org/v/s/gwiazdy.wp.pl/kazik-staszewski-wrocilem-do-swoich-zasad-6646710523185952a?amp=1&amp_js_v=0.1
			//https://opinie-wp-pl.cdn.ampproject.org/v/s/opinie.wp.pl/ten-niedobry-komunista-joe-felieton-6649523316730784a?amp=1&amp_js_v=0.1

			foreach ($article->find("MAIN AMP-VIDEO-IFRAME") as $amp_video)
			{
				if (FALSE === is_null($previous_element = $amp_video->prev_sibling()))
				{
					if ("h2" === strtolower($previous_element->tag))
					{
						$previous_element->outertext = "";
						$amp_video->outertext = "";
					}
					else
					{
						$amp_video->outertext = "";
					}
				}
				else
				{
					$amp_video->outertext = "";
				}
			}
			$article = str_get_html($article->save());
			
			$article = foreach_delete_element_containing_elements_hierarchy($article, array('P', 'STRONG', 'A[href="https://dziejesie.wp.pl/"]'));
			$article = format_article_photos($article, 'DIV.header-image-container', TRUE, 'src', 'DIV.header-author');
			$article = format_article_photos($article, 'DIV.photo.from.amp', FALSE, 'src', 'FIGCAPTION');
			$article = foreach_replace_outertext_with_innertext($article, 'ARTICLE');
			$article = replace_tag_and_class($article, 'MAIN', 'single', 'ARTICLE', NULL);
			$article = replace_date($article, 'P[data-st-area="Autor"]', $datePublished, $dateModified);
			$article = insert_html($article, 'ARTICLE', '', '', '<DIV class="author">'.$author.'</DIV>');
			$article = replace_tag_and_class($article, 'H1', 'single', 'H1', 'title');
			$article = replace_tag_and_class($article, 'STRONG', 'single', 'STRONG', 'lead');

			$article = move_element($article, 'FIGURE.photoWrapper.mainPhoto', 'ARTICLE', 'innertext', 'before');
			$article = move_element($article, 'STRONG.lead', 'ARTICLE', 'innertext', 'before');
			$article = move_element($article, 'DIV.dates', 'ARTICLE', 'innertext', 'before');
			$article = move_element($article, 'H1.title', 'ARTICLE', 'innertext', 'before');
			$article = move_element($article, 'DIV.author', 'ARTICLE', 'innertext', 'after');
			$article = insert_html($article, 'DIV.author', '', '', '<HR>');
		}

		$article = add_style($article, 'FIGURE.photoWrapper', getStylePhotoParent());
		$article = add_style($article, 'FIGURE.photoWrapper IMG', getStylePhotoImg());
		$article = add_style($article, 'FIGCAPTION', getStylePhotoCaption());
		$article = add_style($article, 'BLOCKQUOTE', getStyleQuote());

		$this->items[] = array(
			'uri' => $url_article,
			'title' => getChangedTitle($title),
			'timestamp' => $datePublished,
			'author' => $author,
			'categories' => $tags,
			'content' => $article
		);
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
		$article = $article_html->find('MAIN', 0);
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
		$article = foreach_delete_element_array($article, $selectors_array);
		//https://opinie.wp.pl/zakazac-demonstracji-onr-kataryna-problemy-z-demokracja-6119008400492673a?amp=1&_js_v=0.1
		//https://opinie.wp.pl/apel-o-zawieszenie-stosunkow-dyplomatycznych-z-polska-kataryna-jestem-wsciekla-6222729167030401a?amp=1&_js_v=0.1
		//https://opinie.wp.pl/kataryna-kaczynski-stawia-na-dude-6213466100516481a?amp=1&_js_v=0.1
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
		$article = format_article_photos($article, 'DIV.image.top-image', TRUE, 'src', 'SMALL');
		$article = format_article_photos($article, 'DIV.image', FALSE, 'src', 'SMALL');
		$article = format_article_photos($article, 'DIV.header-image-container', TRUE, 'src', 'DIV.header-author');
		$article = format_article_photos($article, 'DIV.photo.from.amp', FALSE, 'src', 'FIGCAPTION');
		//https://opinie.wp.pl/kataryna-zyjemy-w-okrutnym-swiecie-ale-aborcja-embriopatologiczna-musi-pozostac-opinia-6567085945505921a?amp=1&_js_v=0.1
		$article = add_style($article, 'FIGURE.photoWrapper', getStylePhotoParent());
		$article = add_style($article, 'FIGURE.photoWrapper IMG', getStylePhotoImg());
		$article = add_style($article, 'FIGCAPTION', getStylePhotoCaption());
		$article = add_style($article, 'BLOCKQUOTE', getStyleQuote());
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


	private function getAmpData($url)
	{
		$url_array = parse_url($url);
		$edited_host = str_replace(".", "-", $url_array["host"]);
		$prefix = $url_array["scheme"].'://';
		$ampproject_domain = ".cdn.ampproject.org/v/s/";
		//print_var_dump($url_array, "url_array");
		if (FALSE !== strpos($url_array["host"], "wp.pl"))
		{
			if ("sportowefakty.wp.pl" === $url_array["host"])
			{
				$new_path = "/amp".$url_array["path"];
		//		print_var_dump($new_path, "new_path");
				return array(
					"type" => "sportowefakty_wp_pl",
					"ampproject_url" => $prefix.$edited_host.$ampproject_domain.$url_array["host"].$new_path."?amp_js_v=0.1",
					"canonical_url" => $prefix.$url_array["host"].$url_array["path"],
				);
			}
			else if ("magazyn.wp.pl" === $url_array["host"])
			{
				return array(
					"type" => "magazyn_wp_pl",
					"url" => $url,
					"canonical_url" => $prefix.$url_array["host"].$url_array["path"],
				);
			}
			else
			{
				return array(
					"type" => "wp_pl",
					"ampproject_url" => htmlentities($prefix.$edited_host.$ampproject_domain.$url_array["host"].$url_array["path"].'?amp=1&amp_js_v=0.1', ENT_QUOTES, 'UTF-8'),
					"amp_url" => $prefix.$url_array["host"].$url_array["path"].'?amp=1',
				);
			}
		}
		else
		{
			return NULL;
		}
	}

	private function getArticlesUrls()
	{
		$articles_urls = array();
		$url_articles_list = 'https://magazyn.wp.pl/';
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
			$GLOBALS["amp_urls_data"][] = $this->getAmpProjectLink($amp_link);
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
		$selectors_array[] = 'comment';
		//usuniecie elementu z reklamami w tresci
		$selectors_array[] = '//div/div/div[count(*)=3][img[@class][@src]][*[count(*)=1]/*[count(*)=1]/*[count(*)=1]/*[count(*)=1]/*[count(*)=0]]';
		//usuniecie pustego elementu
		$selectors_array[] = 'DIV[data-st-area="article-header"]';
		//usuniecie niepotrzebnego elementu w leadzaie
		$selectors_array[] = 'DIV.premium--full FIGURE SPAN DIV';
		$article_html = foreach_delete_element_array($article_html, $selectors_array);
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
		$article_html = add_style($article_html, 'DIV.article--lead', $lead_style);
		//styl cytatu
		$article_html = add_style($article_html, 'blockquote', getStyleQuote());
		//styl holdera zdjęcia w treści i leadzie
		$article_html = add_style($article_html, 'DIV.premium--wide.photo, DIV.premium--full FIGURE', getStylePhotoParent());
		//styl zdjecia a treści i leadzie
		$article_html = add_style($article_html, 'DIV.photo-holder, DIV.premium--full FIGURE SPAN[class]', getStylePhotoImg());
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
		$article_html = add_style($article_html, 'SMALL.article--mainPhotoSource', getStylePhotoCaption());
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
		$article = foreach_delete_element_array($article, $selectors_array);
		//Zamiana elementu z perwszą literą na tekst
		$article = foreach_replace_outertext_with_plaintext($article, "SPAN.first-letter");
		//lead
		$article = replace_tag_and_class($article, 'DIV.article--lead.fb-quote', 'single', 'STRONG', 'lead');
		$article = move_element($article, 'HEADER.fullPage--teaser DIV.teaser--row', 'HEADER.fullPage--teaser', 'outertext', 'after');
		//zdjęcia w treści
		$article = format_article_photos($article, 'FIGURE', FALSE, 'src', 'FIGCAPTION');
		//Zdjęcie główne
		if (!is_null($header_element = $article->find('HEADER[data-bg]', 0)) && !is_null($center_element = $article->find('HEADER[data-bg] DIV.center', 0)))
		{
			$photo_url = $header_element->getAttribute('data-bg');
			$center_element->outertext = $center_element->outertext.'<img src="'.$photo_url.'">';
			$article = str_get_html($article->save());
			$article = format_article_photos($article, 'HEADER.fullPage--teaser', TRUE, 'src', 'DIV.foto-desc');
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
		//https://magazyn.wp.pl/ksiazki/artykul/zapomniana-epidemia
		$article = add_style($article, 'FIGURE.photoWrapper', getStylePhotoParent());
		$article = add_style($article, 'FIGURE.photoWrapper IMG', getStylePhotoImg());
		$article = add_style($article, 'FIGCAPTION', getStylePhotoCaption());
		//https://magazyn.wp.pl/ksiazki/artykul/zapomniana-epidemia
		$article = add_style($article, 'BLOCKQUOTE', getStyleQuote());
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
