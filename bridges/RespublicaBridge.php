<?php
class RespublicaBridge extends FeedExpander {

	const MAINTAINER = 'No maintainer';
	const NAME = 'Res Publica Nowa';
	const URI = 'https://publica.pl/';
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
        $this->collectExpandableDatas('https://publica.pl/feed/');
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
		$article_page = getSimpleHTMLDOMCached($item['uri'], 86400 * 14);
		$article = $article_page->find('DIV.container.container-fix.moon_post_container[post_id]', 0);
		$this->fix_main_photo($article);
		foreach_delete_element($article, 'comment');
		foreach_delete_element($article, 'script');
		foreach_delete_element($article, 'NOSCRIPT');
		foreach_delete_element($article, 'DIV.main-column-social-icons');
		foreach_delete_element($article, 'DIV[role="main"] DIV.row');
		foreach_delete_element($article, 'DIV.social_bottom_container');
		foreach_delete_element($article, 'ARTICLE[id^="post-"] DIV[style="clear:both;"]');
		foreach_delete_element($article, 'DL.komentarze-w-akordeonie');
		//https://publica.pl/teksty/ue-zawiodla-w-sprawie-szczepionek-68348.html
		foreach_delete_element_containing_subelement($article, 'FIGURE.figure', 'A[href*="publica.pl/produkt"]');
		foreach_delete_element_containing_subelement($article, 'FIGURE.figure', 'IMG[title^="Zadeklaruj 1%"]');
		
		$tags = array();
		if (FALSE === is_null($tags_element = $article_page->find('META[name="keywords"][content]', 0)))
		{
			$tags_string = trim($tags_element->getAttribute('content'));
			$tags = explode(",", $tags_string);
			$tags = array_diff($tags, array('slider na stronie głównej', 'strona główna'));
		}

		$item['content'] = $article;
		$item['categories'] = $tags;
		return $item;
	}

	private function fix_main_photo($article)
	{
		foreach($article->find('DIV.main-image DIV.bencki[style^="background-image:url(\'http"]') as $article_element)
		{
			$style_string = $article_element->getAttribute('style');
			$style_string = str_replace('background-image:', '', $style_string);
			$style_string = trim($style_string);
			$style_string = remove_substring_if_exists_first($style_string, 'url(');
			$style_string = remove_substring_if_exists_last($style_string, ');');
			$style_string = trim($style_string);
			$img_src = $style_string;
			$img_src = str_replace('\'', '', $img_src);
			$new_outertext = '<figure class="photoWrapper mainPhoto"><img src="'.$img_src.'"></figure>';
			$article_element->parent->outertext = $new_outertext;
		}
	}

}
// Imaginary empty line!