<?php
class KulturaLiberalnaBridge extends FeedExpander {

	const MAINTAINER = 'No maintainer';
	const NAME = 'Kultura Liberalna';
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
        $this->collectExpandableDatas('https://kulturaliberalna.pl/feed/');
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
		$article_page = getSimpleHTMLDOMCached($item['uri'], 86400 * 14);
		$article_post = $article_page->find('ARTICLE[id^="post-"]', 0);
		
		deleteAllDescendantsIfExist($article_post, 'script');
		deleteAllDescendantsIfExist($article_post, 'DIV.kl-10lat-box');
		deleteAllDescendantsIfExist($article_post, 'DIV.go-to-comments');
		deleteAllDescendantsIfExist($article_post, 'DIV.nr-info');
		deleteAllDescendantsIfExist($article_post, 'DIV.more-in-number-container');
		deleteAllDescendantsIfExist($article_post, 'DIV.fb-comm');
		deleteAllDescendantsIfExist($article_post, 'P.section-name.mobile-section-name');
		//https://kulturaliberalna.pl/2021/01/12/cena-osobnosci-nie-jest-wysoka-na-razie/
		deleteAllDescendantsIfExist($article_post, 'DIV.promobox');
		$tags = returnTagsArray($article_post, 'DIV.post-tags A');
		$author = returnAuthorsAsString($article_post, 'DIV.article-footer H2');

		foreach($article_post->find('DIV[id^="attachment_"]') as $attachment_element)
		{
			if(isset($attachment_element->style)) $attachment_element->style = "";
		}

		foreach($article_post->find('IMG') as $photo_element)
		{
			if(isset($photo_element->width)) $photo_element->width = NULL;
			if(isset($photo_element->height)) $photo_element->height = NULL;
			if(isset($photo_element->srcset)) $photo_element->srcset = NULL;
			if(isset($photo_element->sizes)) $photo_element->sizes = NULL;
		}
//https://kulturaliberalna.pl/2021/01/26/dlaczego-maly-sklepik-jest-otwarty-a-duza-restauracja-zamknieta-latwiej-zarazic-sie-w-sklepiku/
		addStyle($article_post, 'blockquote', getStyleQuote());
		addStyle($article_post, 'DIV[id^="attachment_"]', getStylePhotoParent());
		addStyle($article_post, 'IMG[class^="wp-image-"]', getStylePhotoImg());
		addStyle($article_post, 'P.wp-caption-text', getStylePhotoCaption());

		$item['categories'] = $tags;
		$item['author'] = $author;
		$item['content'] = $article_post;
		return $item;
	}

}
// Imaginary empty line!