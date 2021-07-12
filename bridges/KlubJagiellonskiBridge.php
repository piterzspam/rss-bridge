<?php
class KlubJagiellonskiBridge extends FeedExpander {

	const MAINTAINER = 'No maintainer';
	const NAME = 'Klub Jagielloński';
	const URI = 'https://klubjagiellonski.pl/';
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
        $this->collectExpandableDatas('https://klubjagiellonski.pl/feed/');
    }

	private function setGlobalArticlesParams()
	{
		$GLOBALS['my_debug'] = FALSE;
		//$GLOBALS['my_debug'] = TRUE;
		if ($this->getInput('include_not_downloaded'))
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
		//$item['uri'] = 'https://klubjagiellonski.pl/2021/03/19/polacy-nie-chca-wegla-a-co-trzeci-jest-gotow-placic-wiecej-za-transformacje-energetyczna/';
		$returned_array = my_get_html($item['uri']);
		if (200 === $returned_array['code'])
		{
			$article_html = $returned_array['html'];
		}
		else
		{
			return $item;
		}
		$article_html = str_get_html(prepare_article($article_html));
		//$article_html = getSimpleHTMLDOMCached($item['uri'], 86400 * 14);
/*
		foreach($article_html->find('DIV.pix') as $photo_element)
		{
			$background_element = $photo_element->find('[style^="background-image:"]', 0);
			$img_element = $photo_element->find('IMG', 0);
			if (!is_null($background_element) && !is_null($img_element))
			{
				$background_element->outertext = '';
			}
		}
*/
		$article_html = str_get_html(prepare_article($article_html));
		$article = $article_html->find('ARTICLE', 0);
		$article = str_get_html('<div class="article">'.$article->save().'</div>');


		$selectors_array[] = 'comment';
		$selectors_array[] = 'SCRIPT';
		$selectors_array[] = 'DIV.cat';
		$selectors_array[] = 'SECTION.block-content_breaker_sharer';
		$selectors_array[] = 'DIV[data-source="ramka-newsletter"]';
		$selectors_array[] = 'DIV[data-source="ramka-zbiorka"]';
		$selectors_array[] = 'DIV[data-source="ramka-polecane"]';
		$selectors_array[] = 'DIV.meta_mobile.desktop-hide';
		$selectors_array[] = 'DIV.block-wyimki DIV.col.c1';
		$selectors_array[] = 'DIV.inject_placeholder.shortcode[data-source="ramka-publikacje"]';
		$selectors_array[] = 'DIV.meta2 DIV.meta_desktop.mobile-hide SPAN.block-jag_meta SPAN.data';
		$article = foreach_delete_element_array($article, $selectors_array);
		$article = foreach_delete_element_containing_subelement($article, 'DIV.block-content_breaker.block-content_breaker_ramka', 'A[href*="/temat/"]');
		//https://klubjagiellonski.pl/2021/03/12/egzotyczny-sojusz-przeciwko-500-najbardziej-krytyczni-zwolennicy-konfederacji-lewica-i-najbogatsi/
		$article = foreach_delete_element_containing_subelement($article, 'DIV.block-content_breaker.block-content_breaker_ramka', 'IMG[src*="1-procent"]');
		$article = foreach_delete_element_containing_subelement($article, 'SPAN.colored', 'I.fa.fa-clock-o');
 

		$tags = return_tags_array($article, 'A.block-catbox SPAN.catboxfg');
		$author = return_authors_as_string($article, 'A.block-author_bio P.imienazwisko');

		//Wyimki - streszczenie
		$article = foreach_replace_outertext_with_innertext($article, 'DIV.col.c2');
		foreach($article->find('DIV.block-wyimki DIV.row') as $row)
		{
			$row->outertext = '<strong>'.'- '.$row->plaintext.'</strong><br><br>';
		}
		$article = str_get_html($article->save());

		$article = foreach_replace_outertext_with_single_child_outertext($article, 'P', 'IMG');
		$article = format_article_photos($article, 'IMG[class*="wp-image-"]', FALSE);
		$article = format_article_photos($article, 'DIV.pix', TRUE, 'src', 'SPAN.pix_source');
		//Zdjęcia w treści
		//https://klubjagiellonski.pl/2021/03/19/polacy-nie-chca-wegla-a-co-trzeci-jest-gotow-placic-wiecej-za-transformacje-energetyczna/

		$article = replace_tag_and_class($article, 'DIV.meta2 DIV.meta_desktop.mobile-hide SPAN.block-jag_meta SPAN.autor A.imienazwisko', 'single', 'STRONG', 'lead');
		$article = replace_attribute($article, "STRONG.lead", "href", NULL);
		$article = move_element($article, 'STRONG.lead', 'H1.title', 'outertext', 'after');
		$article = replace_tag_and_class($article, 'DIV.meta2', 'single', 'DIV', 'reading_time');
		$article = foreach_replace_innertext_with_plaintext($article, "DIV.reading_time");
		
		$article = replace_tag_and_class($article, 'DIV.content.stdtxt', 'single', 'DIV', 'article body');
		$article = replace_tag_and_class($article, 'DIV.content_col.r', 'single', 'DIV', 'authors');

		


		$date = date_format(date_timestamp_set(new DateTime(), $item['timestamp'])->setTimezone(new DateTimeZone('Europe/Warsaw')), 'c');
		$article = insert_html($article, 'H1.title', '', get_date_outertext($date));
		$article = move_element($article, 'DIV.dates', 'H1.title', 'outertext', 'after');
		$article = move_element($article, 'STRONG.lead', 'DIV.dates', 'outertext', 'after');
		$article = move_element($article, 'DIV.reading_time', 'STRONG.lead', 'outertext', 'after');
		$article = move_element($article, 'DIV.article.body', 'HEADER', 'outertext', 'after');
		$article = move_element($article, 'DIV.authors', 'DIV.article.body', 'outertext', 'after');
		$article = replace_attribute($article, "ARTICLE", "class", NULL);
		$article = replace_attribute($article, "HEADER", "class", NULL);

		$selectors_array = array();
		$selectors_array[] = 'DIV.content_col.m';
		$selectors_array[] = 'DIV.content_colset';
		$article = foreach_delete_element_array($article, $selectors_array);		
		$article = insert_html($article, 'DIV.authors', '', '', '<HR>');
		
		$article = $article->find('ARTICLE', 0);

		$article = add_style($article, 'FIGURE.photoWrapper', getStylePhotoParent());
		$article = add_style($article, 'FIGURE.photoWrapper IMG', getStylePhotoImg());
		$article = add_style($article, 'FIGCAPTION', getStylePhotoCaption());
		$article = add_style($article, 'BLOCKQUOTE', getStyleQuote());
		
		$item['categories'] = $tags;
		$item['author'] = $author;
		$item['content'] = $article;
		return $item;
	}

}
// Imaginary empty line!