<?php
class GazetaplBridge extends BridgeAbstract {
	const NAME = 'Gazeta.pl';
	const URI = 'https://gazeta.pl/';
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
			),
		)
	);

    public function collectData(){
		include 'myFunctions.php';
		$GLOBALS['my_debug'] = FALSE;
//		$GLOBALS['my_debug'] = TRUE;
		if (TRUE === $GLOBALS['my_debug'])
		{
			$GLOBALS['all_articles_time'] = 0;
			$GLOBALS['all_articles_counter'] = 0;
		}
		$this->setGlobalArticlesParams();
		$found_urls = $this->getArticlesUrls();
		
		foreach($found_urls as $url)
		{
			$amp_urls_data[] = $this->getAmpData($url);
		}

		foreach($amp_urls_data as $amp_url_data)
		{
			$this->addArticle($amp_url_data);
		}
    }

	public function getName()
	{
		if (FALSE === isset($GLOBALS['author_name']))
			return self::NAME;
		else
			$author_name = $GLOBALS['author_name'];

		$url = $this->getInput('url');
		if (is_null($url))
			return self::NAME;
		else
		{
			$url_array = parse_url($this->getInput('url'));
			$host_name = $url_array["host"];
			$host_name = ucwords($host_name);
		}
		return $host_name." - ".$author_name;
	}
	
	public function getURI()
	{
		$url = $this->getInput('url');
		if (is_null($url))
			return self::URI;
		else
			return $this->getInput('url');
	}

	private function setGlobalArticlesParams()
	{
		$GLOBALS['limit'] = intval($this->getInput('limit'));
		$url_array = parse_url($this->getInput('url'));
		$host_name = $url_array["host"];
		$GLOBALS['host_name'] = $host_name;
		$amp_host_name = str_replace('.', '-', $host_name);
		$GLOBALS['amp_host_name'] = $amp_host_name;
	}

	private function getAmpData($url)
	{
		$url_array = parse_url($url);
		$edited_host = str_replace(".", "-", $url_array["host"]);
		$prefix = $url_array["scheme"].'://';
		$ampproject_domain = ".cdn.ampproject.org/c/s/";
		$new_path = str_replace(".html", ".amp", $url_array["path"]);
		return array(
			"canonical_url" => $prefix.$url_array["host"].$url_array["path"],
			"amp_url" => $prefix.$url_array["host"].$new_path,
			"ampproject_url" => $prefix.$edited_host.$ampproject_domain.$url_array["host"].$new_path,
		);
	}

	private function getArticlesUrls()
	{
		$articles_urls = array();
		$url_articles_list = $this->getInput('url');
		while (count($articles_urls) < $GLOBALS['limit'] && "empty" != $url_articles_list)
		{
			$returned_array = my_get_html($url_articles_list);
			$html_articles_list = $returned_array['html'];
			if (200 !== $returned_array['code'] || 0 === count($found_hrefs = $html_articles_list->find('DIV.index_body ARTICLE.news UL.list_tiles LI.entry ARTICLE.article H2 A[href]')))
			{
				break;
			}
			else
			{
				$GLOBALS['author_name'] = get_text_plaintext($html_articles_list, 'DIV.index_body H1', "");
				foreach($found_hrefs as $href_element)
				{
					if(isset($href_element->href))
					{
						$articles_urls[] = $href_element->href;
					}
				}
			}
			$url_articles_list = $this->getNextPageUrl($html_articles_list);
		}
		return array_slice($articles_urls, 0, $GLOBALS['limit']);
	}

	private function getNextPageUrl($html_articles_list)
	{		
		$next_page_element = $html_articles_list->find('FOOTER.footer DIV.pagination A.next[href]', 0);
		if (FALSE === is_null($next_page_element) && $next_page_element->hasAttribute('href'))
		{
			return 'https://'.$GLOBALS['host_name'].$next_page_element->getAttribute('href');
		}
		else
			return "empty";
	}

	private function addArticle($amp_url_data)
	{
		$url_article = $amp_url_data['ampproject_url'];
		$returned_array = my_get_html($url_article);
		if (200 !== $returned_array['code'])
		{
			return;
		}
		$article_html = $returned_array['html'];
		
		$article_html = str_get_html(prepare_article($article_html));
		$date_published = get_json_value($article_html, 'SCRIPT[type="application/ld+json"]', 'datePublished');
		$date_modified = get_json_value($article_html, 'SCRIPT[type="application/ld+json"]', 'dateModified');
		$article = $article_html->find('DIV#article', 0);
		$article = replace_tag_and_class($article, 'DIV.part', 'single', 'ARTICLE', "");
		$article = $article->find('ARTICLE', 0);


		$tags = return_tags_array($article_html, 'DIV.tags A[href]');
		
		$selectors_array = array();
		$selectors_array[] = 'comment';
		$selectors_array[] = 'SCRIPT';
		$selectors_array[] = 'DIV.breadcrumbs';
		$selectors_array[] = 'DIV.banner';
		$selectors_array[] = 'DIV.button';
		$article = foreach_delete_element_array($article, $selectors_array);
		
		$article = foreach_delete_element_containing_elements_hierarchy($article, array('P', 'amp-date-display'));
		
		foreach ($article->find('DIV.art_embed') as $embed)
		{
			if (FALSE === is_null($video_title = $embed->find("SPAN.video-head", 0)))
			{
				if(check_string_contains_needle_from_array($video_title->plaintext, array("Zobacz wideo")))
				{
					$embed->outertext = "";
				}
			}
		}
		$article = str_get_html($article->save());
		
		$article = str_get_html($article->save());
		$article = foreach_replace_outertext_with_innertext($article, 'DIV.gallery A.photo.from.amp');
		$article = replace_tag_and_class($article, 'DIV.author.hasPhoto', 'single', 'DIV', 'author');
		$article = replace_tag_and_class($article, 'DIV.author IMG', 'multiple', 'IMG', 'author photo');
		$article = replace_tag_and_class($article, 'DIV.author STRONG', 'multiple', 'STRONG', 'author name');
		$article = replace_tag_and_class($article, 'H1.font', 'single', 'H1', 'title');
		$article = replace_tag_and_class($article, 'H4.art_interview_question', 'multiple', 'H3', 'art_interview_question');

		$article = replace_tag_and_class($article, 'DIV.part.lead', 'single', 'STRONG', 'lead');
		$article = replace_tag_and_class($article, 'DIV.quote', 'multiple', 'BLOCKQUOTE', NULL);
		
		$selectors_array = array();
		$selectors_array[] = 'BLOCKQUOTE SPAN';
		$article = foreach_delete_element_array($article, $selectors_array);
		$article = foreach_replace_outertext_with_innertext($article, 'DIV.part');
		
		$title = get_text_plaintext($article, 'H1.title', NULL);
		$author = return_authors_as_string($article, "DIV.author STRONG.author.name");

		foreach ($article->find('P SPAN.imageUOM SPAN.photoAuthor SPAN') as $image_description)
		{
			$image_description->innertext = $image_description->innertext."; ";
		}
		$article = foreach_replace_outertext_with_subelement_outertext($article, 'P', 'SPAN.imageUOM');
		$article = format_article_photos($article, 'SPAN.imageUOM', FALSE, 'src', 'SPAN.photoAuthor');
		$article = format_article_photos($article, 'DIV.gallery', TRUE, 'src', 'DIV.gallery_copyright');

		$article = move_element($article, 'FIGURE.photoWrapper.mainPhoto', 'ARTICLE', 'innertext', 'before');
		$article = move_element($article, 'STRONG.lead', 'ARTICLE', 'innertext', 'before');
		$article = insert_html($article, 'ARTICLE', '', '', get_date_outertext($date_published, $date_modified));
		$article = move_element($article, 'DIV.dates', 'ARTICLE', 'innertext', 'before');
		$article = move_element($article, 'H1.title', 'ARTICLE', 'innertext', 'before');
		$article = move_element($article, 'DIV.author', 'ARTICLE', 'innertext', 'after');
		$article = insert_html($article, 'DIV.author', '', '', '<HR>');

		$article = add_style($article, 'FIGURE.photoWrapper', getStylePhotoParent());
		$article = add_style($article, 'FIGURE.photoWrapper IMG', getStylePhotoImg());
		$article = add_style($article, 'FIGCAPTION', getStylePhotoCaption());
		$article = add_style($article, 'BLOCKQUOTE', getStyleQuote());

		$this->items[] = array(
			'uri' => $url_article,
			'title' => getChangedTitle($title),
			'timestamp' => $date_published,
			'author' => $author,
			'categories' => $tags,
			'content' => $article,
		);
	}
}