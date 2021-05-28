<?php
class InteriaBridge extends BridgeAbstract {
	const NAME = 'Interia.pl';
	const URI = 'https://www.interia.pl/';
	const DESCRIPTION = 'No description provided';
	const MAINTAINER = 'No maintainer';
	const CACHE_TIMEOUT = 86400; // Can be omitted!

	const PARAMETERS = array
	(
		'Parametry' => array
		(
			'url' => array
			(
				'name' => 'URL',
				'type' => 'text',
				'required' => true,
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
			case 'Parametry':
				if(isset($GLOBALS['author_name']) && 1 < strlen($GLOBALS['author_name']))
				{
					return parent::getName()." - ".ucfirst($GLOBALS['author_name']);
				}
				else
				{
					return parent::getName();
				}
				break;
			default:
				return parent::getName();
		}
	}

	public function getURI()
	{
		switch($this->queriedContext)
		{
			case 'Parametry':
					return $this->getInput('url');
				break;
			default:
				return parent::getURI();
		}
	}

/*
	public function getIcon()
	{
		return 'https://c.disquscdn.com/uploads/forums/349/4323/favicon.png';
	}
*/


	public function collectData()
	{
		include 'myFunctions.php';
		$this->setGlobalArticlesParams();
		$articles_data = $this->getArticlesUrls();
//		print_var_dump($articles_data, "articles_data");
//		return;
		foreach ($articles_data as $url)
		{
			$this->addArticleCanonical($url);
//			$this->addArticleAmpProject($url);
		}
	}

	private function setGlobalArticlesParams()
	{
		$GLOBALS['limit'] = intval($this->getInput('limit'));
		$GLOBALS['my_debug'] = FALSE;
		$GLOBALS['url_articles_list'] = $this->getInput('url');
		if (TRUE === $GLOBALS['my_debug'])
		{
			$GLOBALS['all_articles_time'] = 0;
			$GLOBALS['all_articles_counter'] = 0;
		}
		$url_array = parse_url($this->getInput('url'));
		$GLOBALS['prefix'] = $url_array["scheme"].'://'.$url_array["host"];
//		print_var_dump($url_array, "url_array");
	}

	private function getArticlesUrls()
	{
		$GLOBALS['author_name'] = "";
		$articles_urls = array();
		$url_articles_list = $GLOBALS['url_articles_list'];
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
				foreach_delete_element($html_articles_list, 'LI.has-mixerAdTopRight');
				$html_articles_list = str_get_html($html_articles_list->save());
//				print_element($html_articles_list, "html_articles_list");
				if (0 === count($found_leads = $html_articles_list->find('DIV.tile-magazine A.tile-magazine-title-url[href]')))
				{
					break;
				}
				else
				{
					$GLOBALS['author_name'] = get_text_plaintext($html_articles_list, 'DIV.main-special HEADER H1.base-title, SECTION.stream HEADER.authorInfo H1, DIV.main-special DIV.author-header-container DIV.author-header-container-text', $GLOBALS['author_name']);
					foreach($found_leads as $lead)
					{
						$articles_urls[] = $GLOBALS['prefix'].$lead->href;
					}
				}
			}
			$url_articles_list = $this->getNextPageUrl($html_articles_list);
		}
		return array_slice($articles_urls, 0, $GLOBALS['limit']);
	}
	
	private function getNextPageUrl($html_articles_list)
	{		
		$next_page_element = $html_articles_list->find('UL.js-pagination LI A[href][rel="next"]', 0);
		if (FALSE === is_null($next_page_element) && $next_page_element->hasAttribute('href'))
		{
			return $GLOBALS['prefix'].$next_page_element->getAttribute('href');
		}
		else
			return "empty";
	}

	private function remove_useless_elements($main_element)
	{
		$selectors_array[] = 'comment';
		$selectors_array[] = 'SCRIPT';
		$selectors_array[] = 'NOSCRIPT';
		$selectors_array[] = 'ASIDE.article-social';
		$selectors_array[] = 'DIV.box.ad';
		$selectors_array[] = 'ASIDE.embed-video';
		$selectors_array[] = 'FOOTER';
		$selectors_array[] = 'A[id^="mobile_app_promo_url_"]';
		$selectors_array[] = 'DIV#adBox625';
		$selectors_array[] = 'DIV.crt-rectangle';
		$selectors_array[] = 'DIV#ad-view-rectangle';
		$selectors_array[] = 'comment';
		$selectors_array[] = 'comment';
		$selectors_array[] = 'comment';
		$selectors_array[] = 'comment';
		foreach_delete_element_array($main_element, $selectors_array);
		return $main_element->save();
	}

	private function addArticleAmpProject($url_article_link)
	{
		$url_article_link = $this->getAmpProjectLink($url_article_link);
		$returned_array = my_get_html($url_article_link);
		if (200 !== $returned_array['code'])
		{
			return;
		}
		$article_html = $returned_array['html'];
		$naxt_page_element_selector = 'DIV.article-pagination A[href][rel="next"]';
		$article_body_selector = "DIV.article-body";
		if (!is_null($naxt_page_element = $article_html->find($naxt_page_element_selector, 0)))
		{
			$current_page_html = $article_html;
			$combined_innertexts = "";
			while (true)
			{
				$combined_innertexts = $combined_innertexts.$current_page_html->find($article_body_selector, 0)->innertext;
				if (!is_null($naxt_page_element = $current_page_html->find($naxt_page_element_selector, 0)))
				{
					$next_page_url = $this->getAmpProjectLink($naxt_page_element->href);
//					$next_page_url = $GLOBALS["prefix"].$naxt_page_element->href;
					$next_page_array = my_get_html($next_page_url);
					if (200 !== $next_page_array['code'])
					{
						return;
					}
					else
					{
						$current_page_html = $next_page_array['html'];
					}
				}
				else
				{
					break;
				}
			}
			$article_html->find($article_body_selector, 0)->innertext = $combined_innertexts;
		}
		$article_html = str_get_html($article_html->save());
		$article = $article_html->find('ARTICLE[id^="article-single-"]', 0);
//title
		$title = get_text_plaintext($article, 'H1.article-title', $url_article_link);
//tags
		$tags = return_tags_array($article, 'DIV.article-info DIV.article-category');
//authors
		$author = return_authors_as_string($article, 'DIV.article-info A.article-author-name');
//date
		$date = get_text_from_attribute($article, 'META[itemprop="datePublished"][content]', 'content', "");

		$amp_project_url = $this->getCustomizedLink($article_html, $url_article_link);
		$this->items[] = array(
			'uri' => $amp_project_url,
			'title' => getChangedTitle($title),
			'timestamp' => $date,
			'author' => $author,
			'categories' => $tags,
			'content' => $article,
		);
	}

	private function addArticleCanonical($url_article_link)
	{
		$returned_array = my_get_html($url_article_link);
		if (200 !== $returned_array['code'])
		{
			return;
		}
		$article_html = $returned_array['html'];
		$naxt_page_element_selector = "FOOTER.article-footer DIV.article-pagination LI.next A[href]";
		$article_body_selector = "DIV.article-body";
		if (!is_null($naxt_page_element = $article_html->find($naxt_page_element_selector, 0)))
		{
			$current_page_html = $article_html;
			$combined_innertexts = "";
			while (true)
			{
				$combined_innertexts = $combined_innertexts.$current_page_html->find($article_body_selector, 0)->innertext;
				if (!is_null($naxt_page_element = $current_page_html->find($naxt_page_element_selector, 0)))
				{
					$next_page_url = $GLOBALS["prefix"].$naxt_page_element->href;
					$next_page_array = my_get_html($next_page_url);
					if (200 !== $next_page_array['code'])
					{
						return;
					}
					else
					{
						$current_page_html = $next_page_array['html'];
					}
				}
				else
				{
					break;
				}
			}
			$article_html->find($article_body_selector, 0)->innertext = $combined_innertexts;
		}
		$article_html = str_get_html($article_html->save());
		$article_html = foreach_replace_outertext_with_innertext($article_html, 'SPAN.article-avatar');
		$article_html = str_get_html(prepare_article($article_html, $GLOBALS["prefix"]));
		$article = $article_html->find('ARTICLE[id^="article-single-"]', 0);
		$article = str_get_html($this->remove_useless_elements($article));
		//title
		$title = get_text_plaintext($article, 'H1.article-title', $url_article_link);
//tags
		$tags = return_tags_array($article, 'DIV.article-info DIV.article-category');
		foreach_delete_element($article, 'DIV.article-category');
//authors
		$author = return_authors_as_string($article, 'DIV.article-info A.article-author-name');
//date
		$date = get_text_from_attribute($article, 'META[itemprop="datePublished"][content]', 'content', "");

//		print_element($article, "article przed");
//		$article = str_get_html($this->remove_empty_elements($article->save(), "DIV"));
//		print_element($article, "article po");
//		$article = str_get_html($this->remove_empty_elements($article->save(), "DIV"));
		$article = foreach_replace_outertext_with_innertext($article, 'DIV[id^="naglowek-"]');
		$article = format_article_photos($article, 'ASIDE.embed-photo', TRUE, 'src', 'DIV.embed-work-detail');

//		$article = move_element($article, 'DIV.article-date', 'ASIDE.embed-photo', 'outertext', 'before');
//		$article = move_element($article, 'DIV.article-info', 'DIV.article-body', 'outertext', 'after');
//		$article = insert_html($article, 'DIV.article-info', '<HR>');
		$article = move_element($article, 'DIV.article-date', 'H1.article-title', 'outertext', 'after');
		$article = move_element($article, 'P.article-lead', 'DIV.article-date', 'outertext', 'after');
		$article = move_element($article, 'DIV.article-info', 'DIV.article-body', 'outertext', 'after');
		$article = insert_html($article, 'A.article-author-name', '<BR>');
		
		$article = foreach_replace_outertext_with_subelement_outertext($article, "HEADER.article-header", "DIV.article-header-body");
		$article = foreach_replace_outertext_with_subelement_outertext($article, "DIV.article-info", "DIV.article-author");
		$article = foreach_replace_outertext_with_innertext($article, 'DIV.article-container');
		$article = insert_html($article, "DIV.article-author", '', '', '<HR>');
		$article = replace_tag_and_class($article, 'P.article-lead', 'single', 'STRONG', "lead");
		$article = replace_date($article, 'DIV.article-date', $date);


		$attributes_array[] = "itemscope";
		$attributes_array[] = "itemtype";
		$attributes_array[] = "itemprop";
//		$attributes_array[] = "style";
		$article = remove_multiple_attributes($article, $attributes_array);
		$article = replace_attribute($article, "ARTICLE", "class", NULL);
		$article = replace_attribute($article, "ARTICLE", "id", NULL);



//		$article = foreach_replace_innertext_with_plaintext($article, "DIV.article-date");
		$article = add_style($article, 'FIGURE.photoWrapper', getStylePhotoParent());
		$article = add_style($article, 'FIGURE.photoWrapper IMG', getStylePhotoImg());
		$article = add_style($article, 'FIGCAPTION', getStylePhotoCaption());
		$article = add_style($article, 'BLOCKQUOTE', getStyleQuote());

		$amp_project_url = $this->getCustomizedLink($article_html, $url_article_link);
		$this->items[] = array(
			'uri' => $amp_project_url,
			'title' => $title,
			'timestamp' => $date,
			'author' => $author,
			'categories' => $tags,
			'content' => $article,
		);
	}

	private function getAmpProjectLink($url)
	{
		$prefix_edit = str_replace(".", "-", $GLOBALS["prefix"]);
		$amp_link_edit = str_replace("/news-", "/newsamp-", $url);
		$amp_link_edit = str_replace("https://", "", $amp_link_edit);
		return $prefix_edit.".cdn.ampproject.org/c/s/".$amp_link_edit;
	}

	private function getCustomizedLink($article_html, $url)
	{
		if (!is_null($amp_link_element = $article_html->find('LINK[href][rel="amphtml"]', 0)))
		{
			$amp_link = $amp_link_element->href;
			$prefix_edit = str_replace(".", "-", $GLOBALS["prefix"]);
			$amp_link_edit = str_replace("https://", "", $amp_link);
			return $prefix_edit.".cdn.ampproject.org/c/s/".$amp_link_edit;
//https://wydarzenia.interia.pl/felietony/zaremba/news-koniec-zjednoczonej-prawicy,nId,5158633
//https://wydarzenia-interia-pl.cdn.ampproject.org/c/s/wydarzenia.interia.pl/felietony/zaremba/newsamp-koniec-zjednoczonej-prawicy,nId,5158633
//			preg_match('/https?:\/\/(([^\.]*)\..*)/', $amp_link, $output_array);
//			return ('https://'.$output_array[2].'-dziennik-pl.cdn.ampproject.org/v/s/'.$output_array[1].'.amp?amp_js_v=0.1');
		}
		else
		{
			return $url;
		}
	}

	private function getChangedTitle($title, $price_param)
	{
		preg_match_all('/\[[^\]]*\]/', $title, $title_categories);
		$title_prefix = "";
		foreach($title_categories[0] as $category)
		{
			$title = str_replace($category, '', $title);
			$title_prefix = $title_prefix.$category;
		}
		$new_title = '['.strtoupper($price_param).']'.$title_prefix.' '.trim($title);
		return $new_title;
	}
}
