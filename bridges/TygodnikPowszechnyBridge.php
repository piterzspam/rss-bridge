<?php
class TygodnikPowszechnyBridge extends FeedExpander {

	const MAINTAINER = 'No maintainer';
	const NAME = 'Tygodnik Powszechny';
	const URI = 'https://www.tygodnikpowszechny.pl/';
	const DESCRIPTION = 'No description provided';
	const CACHE_TIMEOUT = 86400;

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
			'include_not_downloaded' => array
			(
				'name' => 'Uwzględnij niepobrane',
				'type' => 'checkbox',
				'required' => false,
				'title' => 'Uwzględnij niepobrane'
			),
		)
	);

    public function collectData(){
		include 'myFunctions.php';
		$this->setGlobalArticlesParams();
		$this->collectExpandableDatas('https://www.tygodnikpowszechny.pl/rss.xml');
    }
	
	private function setGlobalArticlesParams()
	{
		$GLOBALS['my_debug'] = FALSE;
		if (TRUE === $this->getInput('include_not_downloaded'))
			$GLOBALS['include_not_downloaded'] = TRUE;
		else
			$GLOBALS['include_not_downloaded'] = FALSE;
	}

	protected function parseItem($newsItem)
	{
		$item = parent::parseItem($newsItem);
		if (count($this->items) >= intval($this->getInput('limit')))
		{
			if (TRUE === $GLOBALS['include_not_downloaded'])
			{
				return $item;
			}
			else
			{
				return;
			}
		}
		$returned_array = my_get_html($item['uri']);
		if (200 === $returned_array['code'])
		{
			$article_html = $returned_array['html'];
		}
		else
		{
			return $item;
		}
		$article_html = str_get_html(prepare_article($article_html, "https://www.tygodnikpowszechny.pl"));
		//https://www.tygodnikpowszechny.pl/muzeum-roznych-rzeczy-166924
/*		foreach ($article_html->find('IMG[src^="/"]') as $image_with_bad_source)
		{
			$img_src = $image_with_bad_source->getAttribute('src');
			$image_with_bad_source->setAttribute('src', 'https://www.tygodnikpowszechny.pl'.$img_src);
		}
		foreach ($article_html->find('A[href^="/"]') as $link_with_bad_href)
		{
			$href = $link_with_bad_href->getAttribute('href');
			$link_with_bad_href->setAttribute('href', 'https://www.tygodnikpowszechny.pl'.$href);
		}*/
		$tags = return_tags_array($article_html, 'DIV#breadcrumb SPAN[typeof="v:Breadcrumb"]');
		$tags = array_diff($tags, array('Strona główna', $item['title']));
		$date_published = get_text_from_attribute($article_html, 'META[property="article:published_time"][content]', 'content', "");
		$date_modified = get_text_from_attribute($article_html, 'META[property="article:modified_time"][content]', 'content', "");

		if (!is_null($premium_element = $article_html->find('DIV.view-id-ltd_warn', 0)))
		{
			$item['title'] = '[PREMIUM] '.$item['title'];
		}
		else if (!is_null($free_element = $article_html->find('DIV#fanipay-widget', 0)))
		{
			$item['title'] = '[FREE] '.$item['title'];
		}

		$article_html = insert_html($article_html, 'DIV.view-full-article', '<div class="article"><article>', '</article></div>');
		//print_element($article_html, "article_html");
		$article = $article_html->find('DIV.article', 0);
		$article = replace_tag_and_class($article, 'H1.field-content', 'single', 'H1', 'title');
		$article = replace_tag_and_class($article, 'DIV.views-field.views-field-field-summary', 'single', 'STRONG', 'lead');
		$article = replace_tag_and_class($article, 'DIV.field-content.views-field-name', 'single', 'DIV', 'authors');
		$article = replace_tag_and_class($article, 'DIV.authors IMG', 'multiple', 'IMG', 'author photo');
		$article = replace_tag_and_class($article, 'DIV.authors A', 'multiple', 'A', 'author name link');
		if (!is_null($author_description = $article->find('DIV.views-field.views-field-field-po-autorze', 0)))
		{
			$article = foreach_replace_innertext_with_plaintext($article, "DIV.views-field.views-field-field-po-autorze DIV");
			if (0 === strlen(trim($author_description->plaintext)))
			{
				$author_description->outertext = "";
				$article = str_get_html($article->save());
			}
			else
			{
				$article = replace_tag_and_class($article, 'DIV.views-field.views-field-field-po-autorze DIV', 'multiple', 'DIV', 'author description');
			}
		}
		$article = move_element($article, 'H1.title', 'ARTICLE', 'innertext', 'before');
		$article = insert_html($article, 'H1.title', '', get_date_outertext($date_published, $date_modified));
		$article = move_element($article, 'STRONG.lead', 'DIV.dates', 'outertext', 'after');
		$article = insert_html($article, 'STRONG.lead', '<div class="lead">', '</div>');
		$article = move_element($article, 'DIV.author', 'ARTICLE', 'innertext', 'after');
		$article = insert_html($article, 'DIV.authors IMG.author.photo', '<div class="author photo holder">', '</div>');
		$article = insert_html($article, 'DIV.authors', '', '', '<HR>');
		$article = move_element($article, 'DIV.author.description', 'DIV.authors', 'innertext', 'after');
		$article = move_element($article, 'DIV.authors', 'ARTICLE', 'innertext', 'after');

		$selectors_array = array();
		$selectors_array[] = 'DIV.views-field-body-1';
		$selectors_array[] = 'comment';
		$selectors_array[] = 'SPAN.field-content.initial_char.initial';
		$selectors_array[] = 'DIV.article-heading';
		$article = foreach_delete_element_array($article, $selectors_array);
		$article = combine_two_elements($article, 'DIV.views-field-field-zdjecia', 'DIV.views-field-field-zdjecia-2', 'DIV', 'super_photo');

		$article = format_article_photos($article, 'DIV.super_photo', TRUE, 'src', 'DIV.views-field-field-zdjecia-2 DIV.field-content');
		$article = format_article_photos($article, 'DIV.file-image', FALSE, 'src', 'DIV.field-item');
		$article = foreach_replace_outertext_with_innertext($article, 'DIV.field-content');
		$article = foreach_replace_outertext_with_innertext($article, 'DIV.views-field');
		$article = foreach_replace_outertext_with_innertext($article, 'DIV.views-row');
		$article = foreach_replace_outertext_with_innertext($article, 'DIV.view-content');
		$article = foreach_replace_outertext_with_innertext($article, 'DIV.view-full-article');
		$article = foreach_replace_outertext_with_innertext($article, 'FIGURE.photoWrapper A[href*="tygodnikpowszechny.pl/files/styles/"]');

		$recommended = array("CZYTAJ TAKŻE", "Czytaj także:", "CZYTAJ WIĘCEJ", "Polecamy:");
/*
		//Pętla, bo po usunięciu przesuwają się elementy
		$strong_counter = 0;
		foreach($article->find('ARTICLE P') as $paragraph)
		{
			if (!is_null($strong_element = $paragraph->find('STRONG', 0)))
			{
				if(check_string_contains_needle_from_array($strong_element->plaintext, $recommended))
				{
					$strong_counter++;
				}
			}
		}
*/
//		for ($i = 0; $i < $strong_counter; $i++)
//		{
			foreach($article->find('ARTICLE P') as $paragraph)
			{
				if (!is_null($strong_element = $paragraph->find('STRONG', 0)))
				{
					if(check_string_contains_needle_from_array($strong_element->plaintext, $recommended))
					{
						$previous_element = $paragraph->prev_sibling();
						$next_element = $paragraph->next_sibling();
						if (!is_null($previous_element) && !is_null($next_element) && "hr" === strtolower($previous_element->tag) && "hr" === strtolower($next_element->tag))
						{
							$paragraph->outertext = "";
							$previous_element->outertext = "";
							$next_element->outertext = "";
//							$article = str_get_html($article->save());
						}
						else if (!is_null($previous_element) && "hr" === strtolower($previous_element->tag))
						{
							$paragraph->outertext = "";
							$previous_element->outertext = "";
							$next_element->outertext = "";
						}
					}
				}
			}
//		}
		$article = str_get_html($article->save());

		$article = $article->find('ARTICLE', 0);

		$article = add_style($article, 'FIGURE.photoWrapper', getStylePhotoParent());
		$article = add_style($article, 'FIGURE.photoWrapper IMG', getStylePhotoImg());
		$article = add_style($article, 'FIGCAPTION', getStylePhotoCaption());
		$article = add_style($article, 'BLOCKQUOTE', getStyleQuote());
//		$article = replace_part_of_class($article, 'DIV[class*="views-field-field-"]', 'multiple', 'views-field-field-', '');
//		$article = replace_part_of_class($article, 'DIV[class*="views-field-"]', 'multiple', 'views-field-', '');

		$item['content'] = $article;
		$item['categories'] = $tags;
		
		return $item;
	}
}
// Imaginary empty line!