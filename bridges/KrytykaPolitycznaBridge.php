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
			'limit' => array
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
		$article = $article_html->find('DIV#content', 0);
		//tagi
		$tags = returnTagsArray($article, 'DIV.single-post-tags A[rel="tag"]');
		deleteAllDescendantsIfExist($article, 'DIV.single-post-tags');

		deleteAllDescendantsIfExist($article, 'SCRIPT');
		deleteAllDescendantsIfExist($article, 'NOSCRIPT');
		deleteAllDescendantsIfExist($article, 'LINK');
		deleteAllDescendantsIfExist($article, 'DIV.entry-meta-footer');
		deleteAllDescendantsIfExist($article, 'DIV.read-also');
		deleteAllDescendantsIfExist($article, 'ASIDE.book-item.site-commerc');
		deleteAllDescendantsIfExist($article, 'DIV.addthis_tool');
		deleteAllDescendantsIfExist($article, 'DIV.article-donate-bottom');
		deleteAllDescendantsIfExist($article, 'DIV[id^="kppromo"]');
		deleteAllDescendantsIfExist($article, 'DIV.hidden-meta');
		//https://krytykapolityczna.pl/nauka/psychologia/witkowski-bujany-fotel-z-wachlarzem-skad-wiemy-czy-psychoterapia-w-ogole-dziala/
		deleteAllDescendantsIfExist($article, 'DIV.article-top-advertisement');
		deleteAncestorIfChildMatches($article, array('BLOCKQUOTE', 'P', 'A[href^="https://krytykapolityczna.pl/"]'));
		deleteAncestorIfChildMatches($article, array('DIV', 'A[href][rel="author"]'));
		
		if (FALSE === is_null($date_created_element = $article->find('TIME.published', 0)))
		{
			$date_created_element->innertext = 'Publikacja: '.$date_created_element->innertext;
		}
		if (FALSE === is_null($date_updated_element = $article->find('TIME.updated', 0)))
		{
			$date_updated_element->innertext = 'Aktualizacja: '.$date_updated_element->innertext;
			$date_updated_element->outertext = '<br>'.$date_updated_element->outertext.'<br><br>';
		}

		$content = $article->find('DIV.entry-content', 0);
		$content->outertext = $content->innertext;
		$article = str_get_html($article->save());
		$this->fix_main_photo($article);
		//https://krytykapolityczna.pl/swiat/jagpda-grondecka-afganistan-talibowie-chca-znow-rzadzic/
		$this->fix_article_photos($article);
		replaceAllBiggerOutertextWithSmallerOutertext($article, 'ASIDE.single-post-sidebar', 'SPAN.meta-date');
		replaceAllBiggerOutertextWithSmallerOutertext($article, 'DIV.single-post-content-holder', 'DIV.single-post-content');
		$article = str_get_html($article->save());
		
		//Fix zdjęcia autora
		foreach($article->find('IMG.avatar[data-cfsrc^="http"]') as $avatar_element)
		{
			if(isset($avatar_element->style)) $avatar_element->style = NULL;
			$src = $avatar_element->getAttribute('data-cfsrc');
			$avatar_element->setAttribute('src', $src);
			$avatar_element->setAttribute('data-cfsrc', NULL);
		}

		//Fix reszty zdjęć
		//https://krytykapolityczna.pl/kraj/dawid-krawczyk-cyrk-polski-reportaz/
		foreach($article->find('IMG[data-cfsrc][!src]') as $photo_element)
		{
			$src = $photo_element->getAttribute('data-cfsrc');
			$photo_element->setAttribute('src', $src);
			$photo_element->setAttribute('data-cfsrc', NULL);
			$photo_element->setAttribute('style', NULL);
		}
		//lead - https://krytykapolityczna.pl/kraj/galopujacy-major-aborcja-opozycjo-musisz-dac-kobietom-nadzieje/

		addStyle($article, 'P.post-lead', array('font-weight: bold;'));
		addStyle($article, 'FIGURE.photoWrapper', getStylePhotoParent());
		addStyle($article, 'FIGURE.photoWrapper IMG', getStylePhotoImg());
		addStyle($article, 'FIGCAPTION', getStylePhotoCaption());
		$item['content'] = $article;
		$item['categories'] = $tags;
		return $item;
	}

	private function fix_main_photo($article)
	{
		if (FALSE === is_null($main_image = $article->find('ARTICLE[id^="post-"] DIV.post-preview IMG.attachment-full[data-cfsrc^="http"]', 0)))
		{
			if (FALSE === is_null($image_caption = $article->find('DIV.mnky-featured-image-caption', 0)))
			{
				$caption_text = trim($image_caption->plaintext);
				$image_caption->outertext = '';
			}
			$img_src = "";
			$img_src = $main_image->getAttribute('data-cfsrc');

			$img_alt = "";
			if($main_image->hasAttribute('alt'))
				$img_alt = trim($main_image->getAttribute('alt'));

			if (0 === strlen($img_alt) && 0 === strlen($caption_text))
				$new_outertext = '<figure class="photoWrapper mainPhoto"><img src="'.$img_src.'"></figure>';
			else if (0 === strlen($img_alt) && 0 !== strlen($caption_text))
				$new_outertext = '<figure class="photoWrapper mainPhoto"><img src="'.$img_src.'"><figcaption>'.$caption_text.'</figcaption></figure>';
			else if (0 !== strlen($img_alt) && 0 === strlen($caption_text))
				$new_outertext = '<figure class="photoWrapper mainPhoto"><img src="'.$img_src.'" alt="'.$img_alt.'"></figure>';
			else if (0 !== strlen($img_alt) && 0 !== strlen($caption_text))
				$new_outertext = '<figure class="photoWrapper mainPhoto"><img src="'.$img_src.'" alt="'.$img_alt.'"><figcaption>'.$caption_text.'</figcaption></figure>';
			$main_image->parent->outertext = $new_outertext;
		}
	}

	private function fix_article_photos($article)
	{
		foreach($article->find('DIV.content-image FIGURE[id^="attachment_"] IMG[data-cfsrc^="http"]') as $article_element)
		{
			if (FALSE === is_null($image_caption = $article_element->parent->find('FIGCAPTION', 0)))
			{
				$caption_text = trim($image_caption->plaintext);
				$image_caption->outertext = '';
			}
			$img_src = "";
			$img_src = $article_element->getAttribute('data-cfsrc');

			$img_alt = "";
			if($article_element->hasAttribute('alt'))
				$img_alt = trim($article_element->getAttribute('alt'));

			if (0 === strlen($img_alt) && 0 === strlen($caption_text))
				$new_outertext = '<figure class="photoWrapper photo"><img src="'.$img_src.'"></figure>';
			else if (0 === strlen($img_alt) && 0 !== strlen($caption_text))
				$new_outertext = '<figure class="photoWrapper photo"><img src="'.$img_src.'"><figcaption>'.$caption_text.'</figcaption></figure>';
			else if (0 !== strlen($img_alt) && 0 === strlen($caption_text))
				$new_outertext = '<figure class="photoWrapper photo"><img src="'.$img_src.'" alt="'.$img_alt.'"></figure>';
			else if (0 !== strlen($img_alt) && 0 !== strlen($caption_text))
				$new_outertext = '<figure class="photoWrapper photo"><img src="'.$img_src.'" alt="'.$img_alt.'"><figcaption>'.$caption_text.'</figcaption></figure>';
			$article_element->parent->parent->outertext = $new_outertext;
		}
	}

}
// Imaginary empty line!