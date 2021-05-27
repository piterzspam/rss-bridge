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
		$url_parts = explode("?utm_source=", $item['uri']);
		$item['uri'] = $url_parts[0];
		$article_html = getSimpleHTMLDOMCached($item['uri'], 86400 * 14);
		
		$article_html = str_get_html(prepare_article($article_html));


		$article = $article_html->find('MAIN#site-content', 0);
		$article = foreach_delete_element_containing_subelement($article, 'DIV.section-inner', 'LI.post-tags.meta-wrapper');

		
		$selectors_array[] = 'DIV.comments-wrapper';
		$selectors_array[] = 'HEADER DIV.entry-categories';
		$selectors_array[] = 'comment';
		$selectors_array[] = 'script';
		$selectors_array[] = 'NAV.pagination-single';
		$selectors_array[] = 'DIV.post-meta-single-top';
		$article = foreach_delete_element_array($article, $selectors_array);
		

		$tags = return_tags_array($article, 'LI.post-tags A[href*="informatykzakladowy.pl/tag/"][rel="tag"]');
		$article = format_article_photos($article, 'DIV.wp-block-image', FALSE, 'src', 'FIGCAPTION');


		$article = add_style($article, 'FIGURE.photoWrapper', getStylePhotoParent());
		$article = add_style($article, 'FIGURE.photoWrapper IMG', getStylePhotoImg());
		$article = add_style($article, 'FIGCAPTION', getStylePhotoCaption());
		$article = add_style($article, 'BLOCKQUOTE', getStyleQuote());

		$item['content'] = $article;
		$item['categories'] = $tags;
		
				
		return $item;
	}

}
// Imaginary empty line!