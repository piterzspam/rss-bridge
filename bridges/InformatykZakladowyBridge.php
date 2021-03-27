<?php
class InformatykZakladowyBridge extends FeedExpander {

	const MAINTAINER = 'No maintainer';
	const NAME = 'Informatyk Zakładowy';
	const URI = 'https://informatykzakladowy.pl/';
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
        $this->collectExpandableDatas('https://informatykzakladowy.pl/feed/');
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
		
		$article_html = str_get_html(prepare_article($article_html));
		$article = $article_html->find('MAIN#site-content', 0);
		foreach_delete_element($article, 'DIV.comments-wrapper');
		foreach_delete_element($article, 'comment');
		foreach_delete_element($article, 'script');

		$tags = return_tags_array($article, 'LI.post-tags A[href*="informatykzakladowy.pl/tag/"][rel="tag"]');

		$item['content'] = $article;
		$item['categories'] = $tags;
		
//		if (FALSE === in_array("szkolenie", $item['categories'])) return $item;
		add_style($article, 'FIGURE.photoWrapper', getStylePhotoParent());
		add_style($article, 'FIGURE.photoWrapper IMG', getStylePhotoImg());
		add_style($article, 'FIGCAPTION', getStylePhotoCaption());
		add_style($article, 'BLOCKQUOTE', getStyleQuote());
				
		return $item;
	}

}
// Imaginary empty line!