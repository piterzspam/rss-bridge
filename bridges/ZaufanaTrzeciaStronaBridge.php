<?php
class ZaufanaTrzeciaStronaBridge extends FeedExpander {

	const MAINTAINER = 'No maintainer';
	const NAME = 'Zaufana Trzecia Strona';
	const URI = 'https://zaufanatrzeciastrona.pl/';
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
        $this->collectExpandableDatas('https://zaufanatrzeciastrona.pl/feed/');
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
		$article_html = getSimpleHTMLDOMCached($newsItem->link, 86400 * 14);
		$article_html = str_get_html(prepare_article($article_html));
		$article = $article_html->find('DIV#main DIV.postcontent', 0);
		$tags = return_tags_array($article, 'DIV.dolna-ramka A[href*="/tag/"][rel="tag"]');
		foreach ($article->find('IMG[src^="/"]') as $image_with_bad_source)
		{
			$img_src = $image_with_bad_source->getAttribute('src');
			$image_with_bad_source->setAttribute('src', 'https://zaufanatrzeciastrona.pl'.$img_src);
		}
		
		$selectors_array[] = 'comment';
		$selectors_array[] = 'script';
		$selectors_array[] = 'DIV.thumbnail-wrap P';
		$article = foreach_delete_element_array($article, $selectors_array);

		$article = format_article_photos($article, '.wp-block-image', FALSE, 'src', 'FIGCAPTION');
		$article = replace_tag_and_class($article, 'P', 'single', 'STRONG', 'lead');

/*
		foreach($article->find('DIV.wp-block-embed__wrapper') as $embed)
		{
			$embed_innertext = $embed->innertext;
			preg_match('/title="([^"]*)"/', $embed_innertext, $output_array);
			$title = $output_array[1];
			preg_match('/src="([^"]*)"/', $embed_innertext, $output_array);
			$src = $output_array[1];
			$embed->outertext = 
				'<strong><br>'
				.'<a href='.$src.'>'
				."Ramka - ".$title.'<br>'
				.'</a>'
				.'<br></strong>';
		}
*/	
		$article = add_style($article, 'DIV.thumbnail-wrap', array('float: left;', 'margin-right: 15px;'));
		$article = add_style($article, 'FIGURE.photoWrapper', getStylePhotoParent());
		$article = add_style($article, 'FIGURE.photoWrapper IMG', getStylePhotoImg());
		$article = add_style($article, 'FIGCAPTION', getStylePhotoCaption());
		$article = add_style($article, 'BLOCKQUOTE', getStyleQuote());
		$item['content'] = $article;
		$item['categories'] = $tags;
		if (FALSE === in_array("konferencja", $item['categories']))
		{
			return $item;
		}
	}

}
// Imaginary empty line!