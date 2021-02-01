<?php
class KrytykaPolitycznaBridge extends FeedExpander {

	const MAINTAINER = 'No maintainer';
	const NAME = 'Krytyka polityczna';
	const URI = '';
	const DESCRIPTION = 'No description provided';
	const CACHE_TIMEOUT = 86400;

	const PARAMETERS = array
	(
		'Parametry' => array
		(
			'wanted_number_of_articles' => array
			(
				'name' => 'Liczba artykułów',
				'type' => 'number',
				'required' => true
			),
			'include_not_downloaded' => array
			(
				'name' => 'Uwzględnij niepobrane',
				'type' => 'checkbox',
				'required' => true,
				'title' => 'Uwzględnij niepobrane'
			),
		)
	);

    public function collectData(){
		include 'myFunctions.php';
		$this->setGlobalArticlesParams();
        $this->collectExpandableDatas('https://krytykapolityczna.pl/feed/');
    }

	private function setGlobalArticlesParams()
	{
		$GLOBALS['include_not_downloaded'] = $this->getInput('include_not_downloaded');
		if (TRUE === is_null($GLOBALS['include_not_downloaded']))
			$GLOBALS['include_not_downloaded'] = FALSE;
	}

	protected function parseItem($newsItem)
	{
		$item = parent::parseItem($newsItem);
		if (count($this->items) >= intval($this->getInput('wanted_number_of_articles')))
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
		$article = $article_html->find('DIV#content', 0);
		deleteAllDescendantsIfExist($article, 'script');
		deleteAllDescendantsIfExist($article, 'script');
		deleteAllDescendantsIfExist($article, 'DIV.read-also');
		deleteAllDescendantsIfExist($article, 'ASIDE.book-item.site-commerc');
		deleteAllDescendantsIfExist($article, 'DIV.addthis_tool');
		deleteAllDescendantsIfExist($article, 'DIV.article-donate-bottom');
		deleteAllDescendantsIfExist($article, 'DIV[id^="kppromo"]');
		deleteAllDescendantsIfExist($article, 'DIV.hidden-meta');
		deleteAllDescendantsIfExist($article, 'NOSCRIPT');
		//https://krytykapolityczna.pl/nauka/psychologia/witkowski-bujany-fotel-z-wachlarzem-skad-wiemy-czy-psychoterapia-w-ogole-dziala/
		deleteAllDescendantsIfExist($article, 'LINK');
		deleteAllDescendantsIfExist($article, 'DIV.article-top-advertisement');
		deleteAllDescendantsIfExist($article, 'script');
		deleteAncestorIfChildMatches($article, array('BLOCKQUOTE', 'P', 'A[href^="https://krytykapolityczna.pl/"]'));
		deleteAncestorIfChildMatches($article, array('DIV', 'A[href][rel="author"]'));

		foreach($article->find('IMG') as $photo_element)
		{
			if(isset($photo_element->style)) $photo_element->style = NULL;
			if(isset($photo_element->width)) $photo_element->width = NULL;
			if(isset($photo_element->height)) $photo_element->height = NULL;
			if(isset($photo_element->sizes)) $photo_element->sizes = NULL;
			if($photo_element->hasAttribute('srcset')) $photo_element->setAttribute('srcset', NULL);
		}
		//Fix zdjęcia autora
		foreach($article->find('IMG[data-cfsrc][!src]') as $photo_element)
		{
			$src = $photo_element->getAttribute('data-cfsrc');
			$photo_element->setAttribute('src', $src);
			$photo_element->setAttribute('data-cfsrc', NULL);
		}
		//https://krytykapolityczna.pl/swiat/jagpda-grondecka-afganistan-talibowie-chca-znow-rzadzic/
		foreach($article->find('FIGURE[id^="attachment_"][style]') as $photo_element)
		{
			$photo_element->setAttribute('style', NULL);
		}
		//lead - https://krytykapolityczna.pl/kraj/galopujacy-major-aborcja-opozycjo-musisz-dac-kobietom-nadzieje/
		$lead_style = array(
			'font-weight: bold;'
		);
		$tags = returnTagsArray($article, 'DIV.single-post-tags A[rel="tag"]');
		deleteAllDescendantsIfExist($article, 'DIV.single-post-tags');
		addStyle($article, 'P.post-lead', $lead_style);
		addStyle($article, 'FIGURE[id^="attachment_"]', getStylePhotoParent());
		addStyle($article, 'DIV.post-preview, IMG[class*="wp-image-"]', getStylePhotoImg());
		addStyle($article, 'DIV.mnky-featured-image-caption, FIGCAPTION[id^="caption-attachment-"]', getStylePhotoCaption());
		$item['content'] = $article;
		$item['categories'] = $tags;
		return $item;
	}

}
// Imaginary empty line!