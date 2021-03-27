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

		$selectors_array[] = 'qqqqqqqqqqqqqqqqqqqq';
		$selectors_array[] = 'qqqqqqqqqqqqqqqqqqqq';
		$selectors_array[] = 'qqqqqqqqqqqqqqqqqqqq';
		$selectors_array[] = 'qqqqqqqqqqqqqqqqqqqq';
		$selectors_array[] = 'qqqqqqqqqqqqqqqqqqqq';
		foreach_delete_element_array($article, $selectors_array);
		foreach_delete_element_containing_subelement($article, 'DIV.block-content_breaker.block-content_breaker_ramka', 'A[href*="/temat/"]');
		//https://klubjagiellonski.pl/2021/03/12/egzotyczny-sojusz-przeciwko-500-najbardziej-krytyczni-zwolennicy-konfederacji-lewica-i-najbogatsi/
		foreach_delete_element_containing_subelement($article, 'DIV.block-content_breaker.block-content_breaker_ramka', 'IMG[src*="1-procent"]');

		$tags = return_tags_array($article, 'A.block-catbox SPAN.catboxfg');
		$author = return_authors_as_string($article, 'A.block-author_bio P.imienazwisko');

		foreach_replace_outertext_with_innertext($article, 'DIV.col.c2');
		foreach_replace_outertext_with_innertext($article, 'qqqqqqqqqqqqqq');
		$article = str_get_html($article->save());


		foreach($article->find('DIV.block-wyimki DIV.row') as $row)
		{
			$row->outertext = '<strong>'.'- '.$row->plaintext.'</strong><br><br>';
		}

		$article = str_get_html($article->save());
/*
		foreach($article->find('A.block-author_bio') as $block_author)
		{
			if (FALSE === is_null($bio = $block_author->find('DIV.bio', 0)))
			{
				$bio_text = $bio->plaintext;
				$bio->outertext = '';
				$block_author->outertext = $block_author->outertext.'<div class="bio">'.$bio_text.'</div>';
			}
		}

		foreach($article->find('DIV.block-wyimki DIV.row') as $row)
		{
			$row->outertext = '<strong>'.'- '.$row->plaintext.'</strong><br><br>';
		}
		foreach($article->find('DIV.pix') as $pix)
		{
			if (FALSE === is_null($cat = $pix->find('DIV.cat', 0)))
				$cat->outertext = '';
			if (FALSE === is_null($pixbox_desktop = $pix->find('DIV.pixbox_desktop.mobile-hide[style^="background-image: "]', 0)))
				$pixbox_desktop->outertext = '';
		}
		foreach($article->find('A.block-author_bio') as $author_bio)
		{
			$author_bio->outertext = '<br>'.$author_bio->outertext;
		}
		*/
		add_style($article, 'DIV.pix', getStylePhotoParent());
		add_style($article, 'IMG.desktop-hide', getStylePhotoImg());
		$caption_style = getStylePhotoCaption();
		$caption_style[] = 'position: absolute';
		add_style($article, 'SPAN.pix_source', $caption_style);
		
		$item['categories'] = $tags;
		$item['author'] = $author;
		$item['content'] = $article;
		return $item;
	}

}
// Imaginary empty line!