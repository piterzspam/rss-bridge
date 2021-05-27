<?php
class MagazynSWPlusBridge extends FeedExpander {

	const MAINTAINER = 'No maintainer';
	const NAME = 'Magazyn SW+';
	const URI = 'https://spidersweb.pl/plus/';
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
		$GLOBALS['my_debug'] = FALSE;
		$GLOBALS['my_debug'] = TRUE;
		if (TRUE === $GLOBALS['my_debug'])
		{
			$GLOBALS['all_articles_time'] = 0;
			$GLOBALS['all_articles_counter'] = 0;
		}

		$this->setGlobalArticlesParams();
        $this->collectExpandableDatas('https://spidersweb.pl/api/plus/feed/feed-gn');
    }

	private function setGlobalArticlesParams()
	{
		$GLOBALS['limit'] = intval($this->getInput('limit'));
		if (TRUE === $this->getInput('include_not_downloaded'))
			$GLOBALS['include_not_downloaded'] = TRUE;
		else
			$GLOBALS['include_not_downloaded'] = FALSE;
	}

	protected function parseItem($newsItem)
	{
		if (count($this->items) >= intval($this->getInput('limit')))
		{
			if (TRUE === $GLOBALS['include_not_downloaded'])
			{
				return $newsItem;
			}
			else
			{
				return;
			}
		}
		$item = parent::parseItem($newsItem);
		preg_match('/\(([^\)]*)/', $item['author'], $output_array);
		$item['author'] = $output_array[1];
		$article = str_get_html($item['content']);
		$article = convert_iframes_to_links($article);
		$article = fix_all_photos_attributes($article);
		$primary_photo = $article->find('P IMG[class$="primaryImage"]', 0);
		if (FALSE === is_null($primary_photo))
		{
			$primary_photo->parent->outertext = $primary_photo->outertext;
		}
		$article = str_get_html($article->save());
		$article = format_article_photos($article, 'IMG[class$="primaryImage"]', TRUE);
		$article = format_article_photos($article, 'FIGURE.wp-block-image', FALSE, 'src', 'FIGCAPTION');
		if (FALSE === is_null($last_link = $article->find('A[href*="spidersweb.pl/plus/"]', -1)))
			$last_link->outertext = '';
		$article = str_get_html($article->save());
		if (FALSE === is_null($br = $article->find('BR', -1)))
			$br->outertext = '';
		$article = str_get_html($article->save());
		if (FALSE === is_null($br = $article->find('BR', -1)))
			$br->outertext = '';
		$article = str_get_html($article->save());
		$article = add_style($article, 'FIGURE.photoWrapper', getStylePhotoParent());
		$article = add_style($article, 'FIGURE.photoWrapper IMG', getStylePhotoImg());
		$article = add_style($article, 'FIGCAPTION', getStylePhotoCaption());
		$article = add_style($article, 'BLOCKQUOTE', getStyleQuote());
		$item['content'] = $article;
		return $item;
	}
}
// Imaginary empty line!