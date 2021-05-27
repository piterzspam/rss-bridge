<?php
class LiberteBridge extends BridgeAbstract {
	const NAME = 'Liberte';
	const URI = 'https://liberte.pl/';
	const DESCRIPTION = 'No description provided';
	const MAINTAINER = 'No maintainer';
	const CACHE_TIMEOUT = 86400; // Can be omitted!

	const PARAMETERS = array
	(
		'Kategoria' => array
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
					'Polityka' => 'https://liberte.pl/category/polityka-2/',
					'Społeczeństwo otwarte' => 'https://liberte.pl/category/spoleczenstwo-otwarte/',
					'Kultura' => 'https://liberte.pl/category/kultura/',
					'Wolny rynek' => 'https://liberte.pl/category/wolny-rynek/',
					'Siła w nas' => 'https://liberte.pl/category/sila-w-nas/',
				 ),
				'title' => 'Kategoria',
				'defaultValue' => 'https://liberte.pl/category/polityka-2/',
			),
		),
		'Wydania' => array
		(
			'limit' => array
			(
				'name' => 'Liczba wydań',
				'type' => 'number',
				'required' => true
			),
		),
		'Najnowsze' => array
		(
			'limit' => array
			(
				'name' => 'Liczba artykułów',
				'type' => 'number',
				'required' => true
			),
		)
	);

	public function collectData()
	{
		include 'myFunctions.php';
		$GLOBALS['my_debug'] = FALSE;
//		$GLOBALS['my_debug'] = TRUE;
		switch($this->queriedContext)
		{
			case 'Kategoria':
				$this->collectCategoryArticles();
				break;
			case 'Wydania':
				$this->collectIssues();
				break;
			case 'Najnowsze':
				$this->collectNewest();
				break;
		}
	}

	public function collectCategoryArticles()
	{
		$GLOBALS['limit'] = $this->getInput('limit');
		$GLOBALS['chosen_category_url'] = $this->getInput('category');
		$found_urls = $this->getArticlesUrlsCategoryArticles();
		foreach($found_urls as $url)
			$this->addArticle($url);
	}
	private function getArticlesUrlsCategoryArticles()
	{
		$articles_urls = array();
		$url_articles_list = $GLOBALS['chosen_category_url'];
		while (count($articles_urls) < $GLOBALS['limit'] && "empty" != $url_articles_list)
		{
			$returned_array = my_get_html($url_articles_list);
			$html_articles_list = $returned_array['html'];
			if (200 !== $returned_array['code'] || 0 === count($found_hrefs = $html_articles_list->find('DIV#wrapper DIV.row H3 A[href]')))
			{
				break;
			}
			else
			{
				foreach($found_hrefs as $href_element)
				{
					if(isset($href_element->href))
						$articles_urls[] = $href_element->href;
				}
			}
			$url_articles_list = $this->getNextPageUrl($html_articles_list);
		}
		return array_slice($articles_urls, 0, $GLOBALS['limit']);
	}
	private function getNextPageUrl($html_articles_list)
	{		
		$next_page_element = $html_articles_list->find('A.nextpostslink[href]', 0);
		if (FALSE === is_null($next_page_element) && $next_page_element->hasAttribute('href'))
		{
			$url = $next_page_element->getAttribute('href');
			return $url;
		}
		else
			return "empty";
	}

	public function collectNewest()
	{
		$GLOBALS['limit'] = $this->getInput('limit');
		$found_urls = $this->getArticlesUrlsNewest();
		foreach($found_urls as $url)
			$this->addArticle($url);
	}
	private function getArticlesUrlsNewest()
	{
		$articles_urls = array();
		$GLOBALS['limit'] = $this->getInput('limit');

		$returned_array = my_get_html('https://liberte.pl/');
		$html_articles_list = $returned_array['html'];
		if (200 !== $returned_array['code'] || 0 === count($found_hrefs = $html_articles_list->find('SECTION[!class] DIV#anchor-link-2 ARTICLE.entry.box-white H2 A[href], SECTION[!class] DIV#anchor-link-2 ARTICLE.entry.box-white H3 A[href]')))
		{
			return $articles_urls;
		}
		else
		{
			foreach($found_hrefs as $href_element)
			{
				if(isset($href_element->href))
					$articles_urls[] = $href_element->href;
			}
		}
		return array_slice($articles_urls, 0, $GLOBALS['limit']);
	}

	public function collectIssues()
	{
		$GLOBALS['limit'] = $this->getInput('limit');
		$found_urls = $this->getArticlesUrlsIssues();
//		print_var_dump($found_urls);
		foreach($found_urls as $url)
			$this->addArticle($url);
	}

	private function getArticlesUrlsIssues()
	{
		$articles_urls = array();
		$returned_array = my_get_html('https://liberte.pl/miesiecznik/');
		$html_issues_list = $returned_array['html'];
		if (200 !== $returned_array['code'] || 0 === count($found_issues_hrefs = $html_issues_list->find('DIV#wrapper SECTION DIV.row ARTICLE.entry.box-white H2 A[href]')))
		{
			return $articles_urls;
		}
		else
		{
			$issues_urls = array();
			foreach($found_issues_hrefs as $href_element)
			{
				$issues_urls[] = $href_element->href;
			}
		}
		$issues_urls = array_slice($issues_urls, 0, $GLOBALS['limit']);

		foreach($issues_urls as $issue_url)
		{
			$returned_array = my_get_html($issue_url);
			$html_articles_list = $returned_array['html'];
//			if (200 === $returned_array['code'] || 0 !== count($found_articles_hrefs = $html_articles_list->find('H3.title-spistresci A[href]')))
			if (200 === $returned_array['code'] && 0 !== count($found_articles_hrefs = $html_articles_list->find('H3.title-spistresci A[href]')))
			{
				foreach($found_articles_hrefs as $href_element)
				{
					if(isset($href_element->href))
						$articles_urls[] = $href_element->href;
				}
			}
		}
//		print_var_dump($articles_urls);
		return $articles_urls;
	}



	private function addArticle($url)
	{
		$returned_array = my_get_html($url);
		if (200 !== $returned_array['code'])
		{
			return;
		}
		$article_html = $returned_array['html'];
		$article_html = str_get_html(prepare_article($article_html));


		$article = $article_html->find('DIV#wrapper', 0);
		//tytuł
		$title = get_text_plaintext($article, 'SECTION.single-top DIV.row.align-center DIV.large-8.medium-12.small-12.columns.text-center H1', $url);
		//autor
		$author = return_authors_as_string($article, 'DIV.entry-autor H5 A.big.black.bold[href*="/author/"]');
		//tagi
		$tags1 = array();;
		$single_tag = get_text_from_attribute($article_html, 'META[property="article:section"][content]', 'content', NULL);
		$tags1[] = $single_tag;
		$tags2 = return_tags_array($article, 'DIV.entry-autor A.light[href*="/tag/"]');
		$tags = array_unique(array_merge($tags1, $tags2));

		//data
		$date = get_text_from_attribute($article_html, 'META[property="article:published_time"][content]', 'content', '');

		$article = move_element($article, 'DIV.row.entry-autor', 'DIV#anchor-link', 'outertext', 'before');
		$article = foreach_replace_outertext_with_innertext($article, 'P[class^="s"] SPAN[class^="s"]');
		$article = replace_tag_and_class($article, 'P', 'single', 'P', 'lead');
		//https://liberte.pl/labedzi-spiew-alfy/
		$article = replace_tag_and_class($article, 'H4', 'multiple', 'H3');
		$article = foreach_delete_element_containing_subelement($article, 'SECTION.single-top DIV.large-8.medium-12.small-12.columns', 'A.share-icon');
		$article = replace_part_of_class($article, 'SECTION.single-top DIV.row, DIV.margin-bottom30 DIV.row', 'multiple', 'row', 'row_v2');

		$selectors_array = array();
		$selectors_array[] = 'DIV.single-bottom.border-top.padding-top30';
		$selectors_array[] = 'DIV[data-sticky-container]';
		$selectors_array[] = 'FOOTER#footer';
		$selectors_array[] = 'DIV.row';
		$selectors_array[] = 'comment';
		$article = foreach_delete_element_array($article, $selectors_array);
		$article = foreach_replace_outertext_with_subelement_outertext($article, 'SECTION.single-top', 'H1');
		$article = foreach_delete_element_containing_subelement($article, 'DIV#anchor-link DIV.row_v2', 'DIV#disqus_thread');
		$article = format_article_photos($article, 'DIV.large-12.medium-12.small-12.columns', TRUE);
		$article = format_article_photos($article, 'FIGURE[id^="attachment_"]', FALSE, 'src', 'FIGCAPTION');
		//https://liberte.pl/co-w-ttrawie-piszczy-13/
		$article = format_article_photos($article, 'P', FALSE);
		$article = replace_part_of_class($article, 'DIV.row_v2.entry-autor', 'single', 'row_v2', '');
		$article = foreach_replace_outertext_with_innertext($article, 'DIV.row_v2');
		$article = foreach_replace_outertext_with_innertext($article, 'DIV.flexible');
		$article = foreach_replace_outertext_with_innertext($article, 'DIV.text-content');
		$article = foreach_replace_outertext_with_innertext($article, 'DIV#anchor-link');
		$article = foreach_replace_outertext_with_innertext($article, 'DIV.margin-bottom30');
		$article = foreach_replace_outertext_with_innertext($article, 'ARTICLE.blog-single.margin-bottom30');
		
		



/*		
		foreach($article->find('DIV.important-text') as $quote_element)
		{
			$new_outertext = '<blockquote>'.$quote_element->innertext.'</blockquote>';
			$quote_element->outertext = $new_outertext;
		}
*/

		//https://liberte.pl/rozmowy-z-bogami-z-katarzyna-gorewicz-rozmawia-alicja-mysliwiec/
		$article = add_style($article, 'P.lead', array('font-weight: bold;'));
		$article = add_style($article, 'FIGURE.photoWrapper', getStylePhotoParent());
		$article = add_style($article, 'FIGURE.photoWrapper IMG', getStylePhotoImg());
		$article = add_style($article, 'FIGCAPTION', getStylePhotoCaption());
		$article = add_style($article, 'BLOCKQUOTE', getStyleQuote());

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
