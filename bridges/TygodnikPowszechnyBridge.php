<?php
class TygodnikPowszechnyBridge extends FeedExpander {

	const MAINTAINER = 'No maintainer';
	const NAME = 'Tygodnik Powszechny';
	const URI = 'https://www.tygodnikpowszechny.pl/';
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
		$this->collectExpandableDatas('https://www.tygodnikpowszechny.pl/rss.xml');
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
		//https://www.tygodnikpowszechny.pl/muzeum-roznych-rzeczy-166924
		foreach ($article_html->find('IMG[src^="/"]') as $image_with_bad_source)
		{
			$img_src = $image_with_bad_source->getAttribute('src');
			$image_with_bad_source->setAttribute('src', 'https://www.tygodnikpowszechny.pl'.$img_src);
		}
		foreach ($article_html->find('A[href^="/"]') as $link_with_bad_href)
		{
			$href = $link_with_bad_href->getAttribute('href');
			$link_with_bad_href->setAttribute('href', 'https://www.tygodnikpowszechny.pl'.$href);
		}
		$tags = return_tags_array($article_html, 'DIV#breadcrumb SPAN[typeof="v:Breadcrumb"]');
		$tags = array_diff($tags, array('Strona główna', $item['title']));

		if (!is_null($premium_element = $article_html->find('DIV.view-id-ltd_warn', 0)))
		{
			$item['title'] = '[PREMIUM] '.$item['title'];
		}
		else if (!is_null($free_element = $article_html->find('DIV#fanipay-widget', 0)))
		{
			$item['title'] = '[FREE] '.$item['title'];
		}

		$article = $article_html->find('DIV.view-full-article', 0);
		$article = replace_part_of_class($article, 'DIV.views-field', 'multiple', 'views-field ', '');
		$article = foreach_replace_outertext_with_innertext($article, 'DIV.views-field-title');
		$article = foreach_replace_outertext_with_innertext($article, 'DIV.media-element-container');
		$article = replace_tag_and_class($article, 'DIV.views-field-field-summary DIV.field-content', 'single', 'STRONG', NULL);

		$selectors_array = array();
		$selectors_array[] = 'DIV.views-field-body-1';
		$selectors_array[] = 'comment';
		$article = foreach_delete_element_array($article, $selectors_array);
		$article = combine_two_elements($article, 'DIV.views-field-field-zdjecia', 'DIV.views-field-field-zdjecia-2', 'DIV', 'super_photo');

		$article = foreach_replace_outertext_with_innertext($article, 'qqqqqqqqqqqqqqqqq');
		$article = format_article_photos($article, 'DIV.super_photo', TRUE, 'src', 'DIV.views-field-field-zdjecia-2 DIV.field-content');
		$article = format_article_photos($article, 'DIV.file-image', FALSE, 'src', 'DIV.field-item');
		$article = add_style($article, 'FIGURE.photoWrapper', getStylePhotoParent());
		$article = add_style($article, 'FIGURE.photoWrapper IMG', getStylePhotoImg());
		$article = add_style($article, 'FIGCAPTION', getStylePhotoCaption());
		$article = add_style($article, 'BLOCKQUOTE', getStyleQuote());
		$article = replace_part_of_class($article, 'DIV[class*="views-field-field-"]', 'multiple', 'views-field-field-', '');
		$article = replace_part_of_class($article, 'DIV[class*="views-field-"]', 'multiple', 'views-field-', '');

		$item['content'] = $article;
		$item['categories'] = $tags;
		
		return $item;
	}
}
// Imaginary empty line!