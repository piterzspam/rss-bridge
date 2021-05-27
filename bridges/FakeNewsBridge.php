<?php
class FakeNewsBridge extends FeedExpander {

	const MAINTAINER = 'No maintainer';
	const NAME = 'Fakenews.pl';
	const URI = 'https://fakenews.pl/';
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
//		$GLOBALS['my_debug'] = TRUE;
		if (TRUE === $GLOBALS['my_debug'])
		{
			$GLOBALS['all_articles_time'] = 0;
			$GLOBALS['all_articles_counter'] = 0;
		}
		$this->setGlobalArticlesParams();
        $this->collectExpandableDatas('https://fakenews.pl/feed/');
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
		
		$returned_array = my_get_html($item['uri']);
		if (200 !== $returned_array['code'])
		{
			return;
		}
		$article_html = $returned_array['html'];
		$article_html = str_get_html(prepare_article($article_html));
		$article = $article_html->find('DIV#container DIV.single-post-box', 0);

		//tagi
		$tags = return_tags_array($article, 'DIV.post-tags-box A');

		$selectors_array = array();
		$selectors_array[] = 'DIV.share-post-box';
		$selectors_array[] = 'DIV.post-tags-box';
		$selectors_array[] = 'DIV.donate';
		$selectors_array[] = 'DIV.prev-next-posts';
		$selectors_array[] = 'DIV#more-autor';
		$selectors_array[] = 'DIV.related';
		$selectors_array[] = 'UL.nav.nav-tabs.clickable';
		$selectors_array[] = 'DIV.autor-box IMG.avatar.avatar-96.photo';
		$selectors_array[] = 'DIV.autor-box DIV.autor-title H1 A';
		$article = foreach_delete_element_array($article, $selectors_array);

		$article = format_article_photos($article, 'DIV.post-gallery', TRUE, 'src', 'SPAN.image-caption');
		$article = format_article_photos($article, 'FIGURE[id^="attachment_"]', FALSE, 'src', 'FIGCAPTION');
		$article = replace_tag_and_class($article, 'DIV.post-content P', 'single', 'STRONG', 'lead');
		$article = insert_html($article, 'DIV.about-more-autor', '<hr>', '', '', '');

		$rating = get_text_plaintext($article, 'DIV.verdict H2', NULL);
		if (isset($rating))
		{
			$prefix = '['.strtoupper($rating).'] ';
			$item['title'] = $prefix.$item['title'];
		}
		$item['title'] = getChangedTitle($item['title']);
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