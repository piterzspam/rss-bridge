<?php
class WiezBridge extends FeedExpander {

	const MAINTAINER = 'No maintainer';
	const NAME = 'Więź';
	const URI = 'https://wiez.pl/';
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
        $this->collectExpandableDatas('https://wiez.pl/feed/');
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
		$articlePage = getSimpleHTMLDOMCached($item['uri'], 86400 * 14);
		$article = $articlePage->find('DIV.single__post', 0);
		$tags = return_tags_array($article, 'LI.post-categories__item A.post-categories__item__link');


		foreach_delete_element($article, 'SCRIPT');
		foreach_delete_element($article, 'NOSCRIPT');
		foreach_delete_element($article, 'LINK');
		foreach_delete_element($article, 'DIV.single-post-sidebar-wrapper');
		foreach_delete_element($article, 'DIV.latest-articles-section');
		foreach_delete_element($article, 'DIV.single__post__socials-top');
		foreach_delete_element($article, 'DIV.single-post-sidebar-wrapper');
		foreach_delete_element($article, 'DIV.post__socials-box');
		foreach_delete_element($article, 'A.link-more');
		foreach_delete_element($article, 'DIV[id^="advads-"]');
		foreach_delete_element($article, 'ASIDE.book__box');
		foreach_delete_element($article, 'DIV.quote-socials');
		foreach_delete_element($article, 'SPAN[id^="more-"]');
		foreach_delete_element($article, 'IMG.quote-image');
		foreach_delete_element($article, 'IMG.post__author__image');
		foreach_delete_element($article, 'DIV.single__post__category');
		foreach_delete_element($article, 'DIV.post__tags');

		//https://wiez.pl/2021/02/04/od-gomulki-do-jana-pawla-ii-warsztaty-kanonu-wolnych-polakow-w-krosnie/
		add_style($article, 'FIGURE', getStylePhotoParent());
		add_style($article, 'IMG.single__post__img-thumbnail, IMG[class^="wp-image-"]', getStylePhotoImg());
		add_style($article, 'FIGCAPTION', getStylePhotoCaption());
		add_style($article, 'BLOCKQUOTE', getStyleQuote());

		$item['content'] = $article;
		$item['categories'] = $tags;
		return $item;
	}

}
// Imaginary empty line!