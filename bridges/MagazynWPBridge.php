<?php
class MagazynWPBridge extends BridgeAbstract {
	const NAME = 'Magazyn WP.pl';
	const URI = 'https://magazyn.wp.pl/';
	const DESCRIPTION = 'No description provided';
	const MAINTAINER = 'No maintainer';
	const CACHE_TIMEOUT = 86400; // Can be omitted!

	const PARAMETERS = array
	(
		'Magazyn WP' => array
		(
			'limit' => array
			(
				'name' => 'Liczba artykułów',
				'type' => 'number',
				'required' => true,
				'defaultValue' => 3,
			)
		),
		'WP' => array
		(
			'url' => array
			(
				'name' => 'URL',
				'type' => 'text',
				'required' => true
			),
			'limit' => array
			(
				'name' => 'Liczba artykułów',
				'type' => 'number',
				'required' => true,
				'defaultValue' => 3,
			)
		),
		'Money' => array
		(
			'url' => array
			(
				'name' => 'URL',
				'type' => 'text',
				'required' => true
			),
			'limit' => array
			(
				'name' => 'Liczba artykułów',
				'type' => 'number',
				'required' => true,
				'defaultValue' => 3,
			)
		)
	);

	public function getName()
	{
		switch($this->queriedContext)
		{
			case 'Magazyn WP':
				return "Magazyn WP";
				break;
			case 'WP':
				if(1 < strlen($GLOBALS['host']) && 1 < strlen($GLOBALS['author_name']))
					return $GLOBALS['host']." - ".ucfirst($GLOBALS['author_name']);
				else if (1 < strlen($GLOBALS['host']))
					return $GLOBALS['host'];
				break;
			case 'Money':
				if(1 < strlen($GLOBALS['host']) && 1 < strlen($GLOBALS['author_name']))
					return $GLOBALS['host']." - ".ucfirst($GLOBALS['author_name']);
				else if (1 < strlen($GLOBALS['host']))
					return $GLOBALS['host'];
				break;
			default:
				return parent::getName();
		}
	}
	
	public function getURI()
	{
		switch($this->queriedContext)
		{
			case 'Magazyn WP':
				return "https://magazyn.wp.pl/";
				break;
			case 'WP':
				return $this->getInput('url');
				break;
			case 'Money':
				return $this->getInput('url');
				break;
			default:
				return parent::getName();
		}
	}

	public function collectData()
	{
		include 'myFunctions.php';
		$this->setGlobalArticlesParams();
		switch($this->queriedContext)
		{
			case 'Magazyn WP':
				$found_urls = $this->getArticlesUrls_magazyn();
				break;
			case 'WP':
				$found_urls = $this->getArticlesUrls_wp();
				break;
			case 'Money':
				$found_urls = $this->getArticlesUrls_money();
				break;
		}

		$amp_urls_data = array();
		foreach($found_urls as $url)
		{
			$amp_urls_data[] = $this->getAmpData($url);
		}
		foreach($amp_urls_data as $amp_url_data)
		{
			//echo "<br>foreach url przed: ".$amp_url_data["ampproject_url"]."<br>";
			//hex_dump($amp_url_data["ampproject_url"]);
			$this->addArticle($amp_url_data);
		}
	}

	private function setGlobalArticlesParams()
	{
		//https://opinie-wp-pl.cdn.ampproject.org/c/s/opinie.wp.pl/kataryna-hackowanie-systemu-rzadowych-obostrzen-zabawa-w-kotka-i-myszke-opinia-6628299841584000a?amp=1
		$GLOBALS['limit'] = intval($this->getInput('limit'));
//		$GLOBALS['my_debug'] = TRUE;
		$GLOBALS['my_debug'] = FALSE;
		$GLOBALS['url_articles_list'] = $this->getInput('url');
		if (TRUE === $GLOBALS['my_debug'])
		{
			$GLOBALS['all_articles_time'] = 0;
			$GLOBALS['all_articles_counter'] = 0;
		}
		$url_array = parse_url($this->getInput('url'));
		$GLOBALS['prefix'] = $url_array["scheme"].'://'.$url_array["host"];
		$GLOBALS['host'] = str_replace('www.', "", $url_array["host"]);
		$GLOBALS['host'] = ucfirst($GLOBALS["host"]);

	}

	private function addArticle($amp_url_data)
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
			//echo "<br>addArticle url przed: ".$amp_url_data["ampproject_url"]."<br>";
			//hex_dump($amp_url_data["ampproject_url"]);
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
		else if ("wideo" === $amp_url_data["type"])
		{
			$returned_array = my_get_html($amp_url_data["amp_url"]);
			if (200 !== $returned_array['code'])
			{
				$this->items[] = $returned_array['html'];
				return;
			}
			else
			{
				$article_html = str_get_html(prepare_article($returned_array['html']));
			}
			$url_article = $amp_url_data["amp_url"];
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
			
			foreach ($article->find("MAIN AMP-VIDEO-IFRAME") as $amp_video)
			{
				$src = $amp_video->getAttribute("src");
				$frame_outertext = get_frame_outertext($src);
				$amp_video->outertext = $frame_outertext;
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
			'uri' => htmlspecialchars($url_article),
			'title' => getChangedTitle($title),
			'timestamp' => $datePublished,
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
					"canonical_url" => $prefix.$url_array["host"].$url_array["path"],
				);
			}
			else if(check_string_contains_needle_from_array($url_array["host"], array("wideo.wp.pl", "video.wp.pl")))
			{
				return array(
					"type" => "wideo",
					"canonical_url" => $prefix.$url_array["host"].$url_array["path"],
					"amp_url" => $prefix.$url_array["host"].$url_array["path"].'?amp=1',
					"ampproject_url" => $prefix.$edited_host.$ampproject_domain.$url_array["host"].$url_array["path"].'?amp=1&amp_js_v=0.1',
				);
			}
			else
			{
				return array(
					"type" => "wp_pl",
					"amp_url" => $prefix.$url_array["host"].$url_array["path"].'?amp=1',
					"ampproject_url" => $prefix.$edited_host.$ampproject_domain.$url_array["host"].$url_array["path"].'?amp=1&amp_js_v=0.1',
				);
			}
		}
		else if(check_string_contains_needle_from_array($url_array["host"], array("www.o2.pl", "www.money.pl")))
		{
			return array(
				"type" => "wp_pl",
				"canonical_url" => $prefix.$url_array["host"].$url_array["path"],
				"ampproject_url" => $prefix.$edited_host.$ampproject_domain.$url_array["host"].$url_array["path"].'?amp=1&amp_js_v=0.1',
			);
		}
		else if(check_string_contains_needle_from_array($url_array["host"], array("parenting.pl", "abczdrowie.pl")))
		{
			return array(
				"type" => "wp_pl",
				"canonical_url" => $prefix.$url_array["host"].$url_array["path"],
				"ampproject_url" => $prefix.$edited_host.$ampproject_domain.$url_array["host"].$url_array["path"].'?amp=1&amp_js_v=0.1',
			);
		}
		else
		{
			return NULL;
		}
	}
	
	private function getArticlesUrls_wp()
	{
		$GLOBALS['author_name'] = "";
		$articles_urls = array();
		$url_articles_list = $this->getInput('url');
		while (count($articles_urls) < $GLOBALS['limit'] && "empty" != $url_articles_list)
		{
			$returned_array = my_get_html($url_articles_list);
			if (200 !== $returned_array['code'])
			{
				break;
			}
			//DIV.teasersListing A[class][title][href][data-reactid]
			$html_articles_list = $returned_array['html'];
			if (200 !== $returned_array['code'] || 0 === count($found_hrefs = $html_articles_list->find('DIV.teasersListing A[class][title][href][data-reactid]')))
			{
				break;
			}
			else
			{
				$GLOBALS['author_name'] = get_text_plaintext($html_articles_list, 'H1.author--name, H1.sectionPage--title', $GLOBALS['author_name']);
				foreach($found_hrefs as $href_element)
				{
					if(isset($href_element->href))
					{
						$new_url = $GLOBALS['prefix'].$href_element->href;
						if (!in_array($new_url, $articles_urls))
						{
							$articles_urls[] = $new_url;
						}
					}
				}
			}
			$url_articles_list = $this->getNextPageUrl_wp($html_articles_list);
		}
		return array_slice($articles_urls, 0, $GLOBALS['limit']);
	}
	
	private function getNextPageUrl_wp($html_articles_list)
	{
		$next_page_element = $html_articles_list->find('A[rel="next"][href]', 0);
		if (FALSE === is_null($next_page_element) && $next_page_element->hasAttribute('href'))
		{
			return $GLOBALS['prefix'].$next_page_element->getAttribute('href');
		}
		else
			return "empty";
	}

	private function getArticlesUrls_money()
	{
		$GLOBALS['author_name'] = "";
		$articles_urls = array();
		$url_articles_list = $this->getInput('url');
		while (count($articles_urls) < $GLOBALS['limit'] && "empty" != $url_articles_list)
		{
			$returned_array = my_get_html($url_articles_list);
			if (200 !== $returned_array['code'])
			{
				break;
			}
			else
			{
				$html_articles_list = $returned_array['html'];
				$GLOBALS['author_name'] = get_text_plaintext($html_articles_list, 'ARTICLE H1', $GLOBALS['author_name']);
				$GLOBALS['author_name'] = str_replace("Archiwum artykułów autora ", "", $GLOBALS['author_name']);
				$content = $html_articles_list->find('DIV[data-st-area="st-page_content"]', 0);
				if (is_null($content))
				{
					break;
				}
				else
				{
					if (0 == count($found_leads = $content->children))
					{
						break;
					}
					else
					{
						foreach($found_leads as $lead_element)
						{
							$href_element = $lead_element->find('A[href]', 0);
							if(isset($href_element->href))
							{
								$new_url = $GLOBALS['prefix'].$href_element->href;
								if (!in_array($new_url, $articles_urls))
								{
									$articles_urls[] = $new_url;
								}
							}
						}
					}
				}
			}
			$url_articles_list = $this->getNextPageUrl_money($url_articles_list);
		}
		return array_slice($articles_urls, 0, $GLOBALS['limit']);
	}

	private function getNextPageUrl_money($url_articles_list)
	{
		if (FALSE !== strpos($url_articles_list, '?strona='))
		{
			preg_match('/(.*\.money\.pl\/.+\.html\?strona=)([0-9]+)/', $url_articles_list, $output_array);
			$url_without__page_number = $output_array[1];
			$page_number = $output_array[2];
			$page_number++;
			$url_articles_list = $url_without__page_number.$page_number;
		}
		else
		{
			preg_match('/.*\.money\.pl\/.+\.html/', $url_articles_list, $output_array);
			$url_articles_list = $output_array[0].'?strona=2';
		}
		return $url_articles_list;
	}


	private function getArticlesUrls_magazyn()
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
			$url_articles_list = $this->getNextPageUrl_magazyn($html_articles_list);
		}
		return array_slice($articles_urls, 0, $GLOBALS['limit']);
	}

	private function getNextPageUrl_magazyn($html_articles_list)
	{		
		$next_page_element = $html_articles_list->find('DIV.moreTeasers', 0);
		if (FALSE === is_null($next_page_element) && $next_page_element->hasAttribute('data-url'))
		{
			return $next_page_element->getAttribute('data-url');
		}
		else
			return "empty";
	}
}
