<?php
class WiezBridge extends FeedExpander {

	const MAINTAINER = 'No maintainer';
	const NAME = 'Więź';
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
        $this->collectExpandableDatas('https://wiez.pl/feed/');
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
		$articlePage = getSimpleHTMLDOMCached($item['uri'], 86400 * 14);
		$article = $articlePage->find('DIV.single__post', 0);
		$tags = returnTagsArray($article, 'LI.post-categories__item A.post-categories__item__link');


		deleteAllDescendantsIfExist($article, 'SCRIPT');
		deleteAllDescendantsIfExist($article, 'NOSCRIPT');
		deleteAllDescendantsIfExist($article, 'LINK');
		deleteAllDescendantsIfExist($article, 'DIV.single-post-sidebar-wrapper');
		deleteAllDescendantsIfExist($article, 'DIV.latest-articles-section');
		deleteAllDescendantsIfExist($article, 'DIV.single__post__socials-top');
		deleteAllDescendantsIfExist($article, 'DIV.single-post-sidebar-wrapper');
		deleteAllDescendantsIfExist($article, 'DIV.post__socials-box');
		deleteAllDescendantsIfExist($article, 'A.link-more');
		deleteAllDescendantsIfExist($article, 'DIV[id^="advads-"]');
		deleteAllDescendantsIfExist($article, 'ASIDE.book__box');
		deleteAllDescendantsIfExist($article, 'DIV.quote-socials');
		deleteAllDescendantsIfExist($article, 'SPAN[id^="more-"]');
		deleteAllDescendantsIfExist($article, 'IMG.quote-image');
		deleteAllDescendantsIfExist($article, 'IMG.post__author__image');
		deleteAllDescendantsIfExist($article, 'DIV.single__post__category');
		deleteAllDescendantsIfExist($article, 'DIV.post__tags');

		//https://wiez.pl/2021/02/04/od-gomulki-do-jana-pawla-ii-warsztaty-kanonu-wolnych-polakow-w-krosnie/
		addStyle($article, 'FIGURE', getStylePhotoParent());
		addStyle($article, 'IMG.single__post__img-thumbnail, IMG[class^="wp-image-"]', getStylePhotoImg());
		addStyle($article, 'FIGCAPTION', getStylePhotoCaption());
		addStyle($article, 'BLOCKQUOTE', getStyleQuote());

		$item['content'] = $article;
		$item['categories'] = $tags;
		return $item;
	}

}
// Imaginary empty line!