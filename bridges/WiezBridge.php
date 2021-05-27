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


		$selectors_array[] = 'SCRIPT';
		$selectors_array[] = 'NOSCRIPT';
		$selectors_array[] = 'LINK';
		$selectors_array[] = 'DIV.single-post-sidebar-wrapper';
		$selectors_array[] = 'DIV.latest-articles-section';
		$selectors_array[] = 'DIV.single__post__socials-top';
		$selectors_array[] = 'DIV.single-post-sidebar-wrapper';
		$selectors_array[] = 'DIV.post__socials-box';
		$selectors_array[] = 'A.link-more';
		$selectors_array[] = 'DIV[id^="advads-"]';
		$selectors_array[] = 'ASIDE.book__box';
		$selectors_array[] = 'DIV.quote-socials';
		$selectors_array[] = 'SPAN[id^="more-"]';
		$selectors_array[] = 'IMG.quote-image';
		$selectors_array[] = 'IMG.post__author__image';
		$selectors_array[] = 'DIV.single__post__category';
		$selectors_array[] = 'DIV.post__tags';
		$article = foreach_delete_element_array($article, $selectors_array);


		//https://wiez.pl/2021/02/04/od-gomulki-do-jana-pawla-ii-warsztaty-kanonu-wolnych-polakow-w-krosnie/
		$article = add_style($article, 'FIGURE', getStylePhotoParent());
		$article = add_style($article, 'IMG.single__post__img-thumbnail, IMG[class^="wp-image-"]', getStylePhotoImg());
		$article = add_style($article, 'FIGCAPTION', getStylePhotoCaption());
		$article = add_style($article, 'BLOCKQUOTE', getStyleQuote());

		$item['content'] = $article;
		$item['categories'] = $tags;
		return $item;
	}

}
// Imaginary empty line!