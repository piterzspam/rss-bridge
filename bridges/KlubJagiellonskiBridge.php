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
		$article_html = getSimpleHTMLDOMCached($item['uri'], 86400 * 14);

		foreach($article_html->find('DIV.pix') as $photo_element)
		{
			$background_element = $photo_element->find('[style^="background-image:"]', 0);
			$img_element = $photo_element->find('IMG', 0);
			if (!is_null($background_element) && !is_null($img_element))
			{
				$background_element->outertext = '';
			}
		}
		$article_html = str_get_html(prepare_article($article_html));
		$article = $article_html->find('ARTICLE', 0);


		$selectors_array[] = 'DIV.cat';
		$selectors_array[] = 'SECTION.block-content_breaker_sharer';
		$selectors_array[] = 'DIV[data-source="ramka-newsletter"]';
		$selectors_array[] = 'DIV[data-source="ramka-zbiorka"]';
		$selectors_array[] = 'DIV[data-source="ramka-polecane"]';
		$selectors_array[] = 'DIV.meta_mobile.desktop-hide';
		$selectors_array[] = 'DIV.block-wyimki DIV.col.c1';
		$selectors_array[] = 'DIV.inject_placeholder.shortcode[data-source="ramka-publikacje"]';
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

		$article = format_article_photos($article, 'DIV.pix', TRUE, 'src', 'SPAN.pix_source');
		//Zdjęcia w treści
		//https://klubjagiellonski.pl/2021/03/19/polacy-nie-chca-wegla-a-co-trzeci-jest-gotow-placic-wiecej-za-transformacje-energetyczna/

		if (!is_null($meta_element = $article->find('DIV.meta2', 0)))
		{
			$date_string = get_text_plaintext($article, 'SPAN.data', '');
			$reading_time_string = get_text_plaintext($article, 'SPAN.colored.time', '');
			$lead_string = get_text_plaintext($article, 'SPAN.block-jag_meta', '');
			$lead_string = trim(str_replace($date_string, '', $lead_string));
			$lead_string = trim($lead_string);
			$new_metadata_outertext = '<DIV class="meta_data">';
			if (strlen($date_string) > 0)
			{
				$new_metadata_outertext = $new_metadata_outertext.'<DIV class="publication_date">'.$date_string.'</DIV>';
			}
			if (strlen($lead_string) > 0)
			{
				$new_metadata_outertext = $new_metadata_outertext.'<STRONG class="lead">'.$lead_string.'</STRONG>';
			}
			if (strlen($reading_time_string) > 0)
			{
				$new_metadata_outertext = $new_metadata_outertext.'<DIV class="reading_time">'.'Przeczytanie zajmie: '.$reading_time_string.'</DIV>';
			}
			$new_metadata_outertext = $new_metadata_outertext.'</DIV>';
			$meta_element->outertext = $new_metadata_outertext;
		}

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