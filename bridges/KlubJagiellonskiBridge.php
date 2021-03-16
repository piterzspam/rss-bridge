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
		$article_page = getSimpleHTMLDOMCached($item['uri'], 86400 * 14);
		$article_post = $article_page->find('ARTICLE', 0);

		foreach_delete_element($article_post, 'SECTION.block-content_breaker_sharer');
		foreach_delete_element($article_post, 'DIV[data-source="ramka-newsletter"]');
		foreach_delete_element($article_post, 'DIV[data-source="ramka-zbiorka"]');
		foreach_delete_element($article_post, 'DIV[data-source="ramka-polecane"]');
		foreach_delete_element($article_post, 'DIV.meta_mobile.desktop-hide');
		foreach_delete_element_containing_subelement($article_post, 'DIV.block-content_breaker.block-content_breaker_ramka', 'A[href*="/temat/"]');

		$tags = return_tags_array($article_post, 'A.block-catbox SPAN.catboxfg');
		$author = return_authors_as_string($article_post, 'A.block-author_bio P.imienazwisko');

		foreach($article_post->find('A.block-author_bio') as $block_author)
		{
			if (FALSE === is_null($bio = $block_author->find('DIV.bio', 0)))
			{
				$bio_text = $bio->plaintext;
				$bio->outertext = '';
				$block_author->outertext = $block_author->outertext.'<div class="bio">'.$bio_text.'</div>';
			}
		}
		foreach($article_post->find('DIV.block-wyimki DIV.row') as $row)
		{
			$row->outertext = '<strong>'.'- '.$row->plaintext.'</strong><br><br>';
		}
		foreach($article_post->find('DIV.pix') as $pix)
		{
			if (FALSE === is_null($cat = $pix->find('DIV.cat', 0)))
				$cat->outertext = '';
			if (FALSE === is_null($pixbox_desktop = $pix->find('DIV.pixbox_desktop.mobile-hide[style^="background-image: "]', 0)))
				$pixbox_desktop->outertext = '';
		}
		foreach($article_post->find('A.block-author_bio') as $author_bio)
		{
			$author_bio->outertext = '<br>'.$author_bio->outertext;
		}
		add_style($article_post, 'DIV.pix', getStylePhotoParent());
		add_style($article_post, 'IMG.desktop-hide', getStylePhotoImg());
		$caption_style = getStylePhotoCaption();
		$caption_style[] = 'position: absolute';
		add_style($article_post, 'SPAN.pix_source', $caption_style);
		
		$item['categories'] = $tags;
		$item['author'] = $author;
		$item['content'] = $article_post;
		return $item;
	}

}
// Imaginary empty line!