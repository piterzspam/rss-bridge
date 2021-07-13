<?php
class SprawdzamAFPBridge extends BridgeAbstract {
	const NAME = 'Sprawdzam AFP';
	const URI = 'https://sprawdzam.afp.com/list';
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
		$GLOBALS['limit'] = intval($this->getInput('limit'));
		$GLOBALS['my_debug'] = FALSE;
//		$GLOBALS['my_debug'] = TRUE;
		if (TRUE === $GLOBALS['my_debug'])
		{
			$GLOBALS['all_articles_time'] = 0;
			$GLOBALS['all_articles_counter'] = 0;
		}
		$found_urls = $this->getArticlesUrls();
//		print_var_dump($found_urls);
		
		foreach($found_urls as $url)
			$this->addArticle($url);
	}

	private function getArticlesUrls()
	{
		$articles_urls = array();
		$url_articles_list = 'https://sprawdzam.afp.com/list';
		while (count($articles_urls) < $GLOBALS['limit'] && "empty" != $url_articles_list)
		{
			$returned_array = my_get_html($url_articles_list);
			$html_articles_list = $returned_array['html'];
			if (200 !== $returned_array['code'] || 0 === count($found_hrefs = $html_articles_list->find('MAIN DIV.card A[href]')))
			{
				break;
			}
			else
			{
				foreach($found_hrefs as $href_element)
				{
					if(isset($href_element->href))
						$articles_urls[] = 'https://sprawdzam.afp.com'.$href_element->href;
				}
			}
			$url_articles_list = $this->getNextPageUrl($html_articles_list);
		}
		return array_slice($articles_urls, 0, $GLOBALS['limit']);
	}

	private function getNextPageUrl($html_articles_list)
	{		
		$next_page_element = $html_articles_list->find('UL.justify-content-end A[class^="page-link page-link-"][href]', 0);
		if (FALSE === is_null($next_page_element) && $next_page_element->hasAttribute('href'))
		{
			return 'https://sprawdzam.afp.com'.$next_page_element->getAttribute('href');
		}
		else
			return "empty";
	}

	private function addArticle($url)
	{
		$returned_array = my_get_html($url);
		if (200 !== $returned_array['code'])
		{
			return;
		}
		$article_html = $returned_array['html'];
		$next_data_array = get_json_variable_as_array($article_html, 'afp_blog_theme', 'SCRIPT');
		$next_data_subarrays = get_subarrays_by_key($next_data_array, "output", NULL);
		$detail_data_flattened = flatten_array($next_data_subarrays, "output");
		$main_image_code = str_replace('<img', '<figure class="photoWrapper mainPhoto"> <img  ', $detail_data_flattened[0]["output"]);
		$main_image_code = $main_image_code."</figure>";
		$article_html = insert_html($article_html, 'H1.content-title', '', $main_image_code, '', '');

		$article_html = str_get_html(prepare_article($article_html, 'https://sprawdzam.afp.com'));
		$article_html_str = $article_html->save();
		foreach($article_html->find('IMG[src^=" /"]') as $image)
		{
			$old_src = $image->src;
			$new_src = "https://sprawdzam.afp.com".trim($old_src);
			$article_html_str = str_replace($old_src, $new_src, $article_html_str);
		}
		$article_html = str_get_html($article_html_str);
		$article = $article_html->find('ARTICLE[role="article"]', 0);

		//title
		$title = get_text_from_attribute($article_html, 'META[property="og:title"][content]', 'content', $url);
		//date
		$date = get_text_from_attribute($article_html, 'META[property="article:published_time"][content]', 'content', "");
		$date_updated = get_text_from_attribute($article_html, 'META[property="article:modified_time"][content]', 'content', "");

		//authors
		$author = return_authors_as_string($article, 'SPAN.meta-author A[href][target="_blank"]');
		$author = str_replace(', AFP Polska', '', $author);
		//tags
		$tags = return_tags_array($article_html, 'DIV.tags A[href]');
		foreach($tags as $key => $tag)
		{
			$tags[$key] = ucwords(strtolower($tag));
		}

		$selectors_array = array();
		$selectors_array[] = 'comment';
		$selectors_array[] = 'script';
		$selectors_array[] = 'SPAN.meta-share.addtoany';
		$selectors_array[] = 'SPAN.meta-separator';
		$selectors_array[] = 'DIV.disclaimer';
		$article = foreach_delete_element_array($article, $selectors_array);

		$attributes_array = array();
		$attributes_array[] = "dir";
		$attributes_array[] = "about";
		$attributes_array[] = "typeof";
		$attributes_array[] = "role";
		$attributes_array[] = "data-article-type";
		$attributes_array[] = "container-fid";
		$article = remove_multiple_attributes($article, $attributes_array);

//		$article = move_element($article, 'DIV#container HEADER.entry-header.clearfix', 'DIV#content', 'innertext', 'before');
		$article = format_article_photos($article, 'FIGURE.photoWrapper.mainPhoto', TRUE);
		$article = format_article_photos($article, 'DIV.ww-item.image', FALSE, 'src', 'SPAN.legend');
/*		foreach($article->find('DIV.content-meta SPAN[class^="meta-"]') as $separator)
		{
			$separator->outertext = $separator->outertext.'<br>';
		}*/
		$article = str_get_html($article->save());
		

		$article = replace_date($article, 'SPAN.meta-date', $date, $date_updated);
		if (FALSE === is_null($lead = $article->find('DIV.article-entry H3 B[id^="docs-internal-guid-"]', 0)))
		{
			//https://sprawdzam.afp.com/nie-placebo-podawane-jest-wylacznie-wolontariuszom-uczestniczacym-w-badaniach-klinicznych
			$article = replace_tag_and_class($article, 'DIV.article-entry H3 B[id^="docs-internal-guid-"]', 'single', 'STRONG', 'lead');
			$article = foreach_replace_outertext_with_subelement_outertext($article, 'H3', 'STRONG.lead');
		}
		else
		{
			//gdyby nie było elementu B
			//https://sprawdzam.afp.com/netanjahu-powiedzial-ze-kraj-mierzy-sie-z-najgorszym-scenariuszem-poszczepionkowym-manipulacja
			$article = replace_tag_and_class($article, 'DIV.article-entry H3', 'single', 'STRONG', 'lead');
		}
		$article = replace_tag_and_class($article, 'H1.content-title', 'single', 'H1', 'title');
		$article = replace_tag_and_class($article, 'DIV.content-meta', 'single', 'DIV', 'authors');
		$article = move_element($article, 'DIV.dates', 'H1.title', 'outertext', 'after');
		$article = move_element($article, 'STRONG.lead', 'DIV.dates', 'outertext', 'after');
			$article = insert_html($article, 'STRONG.lead', '<div class="lead">', '</div>');
		$article = move_element($article, 'DIV.authors', 'DIV.article-entry', 'outertext', 'after');
		$article = foreach_replace_outertext_with_innertext($article, 'SPAN.meta-author');
		$article = foreach_replace_outertext_with_innertext($article, 'DIV.content-header-single');
		$article = foreach_replace_outertext_with_innertext($article, 'DIV.article-entry');
//		$article = foreach_replace_outertext_with_innertext($article, 'DIV.content-meta');
		$article = insert_html($article, 'DIV.authors', '<hr>');
		
		$attributes_array = array();
		$attributes_array[] = "id";
		$article = remove_multiple_attributes($article, $attributes_array);
		$article = replace_attribute($article, "ARTICLE", "class", NULL);
		
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
