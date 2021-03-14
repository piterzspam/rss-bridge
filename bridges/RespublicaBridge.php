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
		deleteAllDescendantsIfExist($article, 'comment');
		deleteAllDescendantsIfExist($article, 'script');
		deleteAllDescendantsIfExist($article, 'NOSCRIPT');
		deleteAllDescendantsIfExist($article, 'DIV.main-column-social-icons');
		deleteAllDescendantsIfExist($article, 'DIV[role="main"] DIV.row');
		deleteAllDescendantsIfExist($article, 'DIV.social_bottom_container');
		deleteAllDescendantsIfExist($article, 'ARTICLE[id^="post-"] DIV[style="clear:both;"]');
		deleteAllDescendantsIfExist($article, 'DL.komentarze-w-akordeonie');
		//https://publica.pl/teksty/ue-zawiodla-w-sprawie-szczepionek-68348.html
		deleteAllAncestorsIfDescendantExists($article, 'FIGURE.figure', 'A[href*="publica.pl/produkt"]');
		deleteAllAncestorsIfDescendantExists($article, 'FIGURE.figure', 'IMG[title^="Zadeklaruj 1%"]');
		
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
			$style_string = removeSubstringIfExistsFirst($style_string, 'url(');
			$style_string = removeSubstringIfExistsLast($style_string, ');');
			$style_string = trim($style_string);
			$img_src = $style_string;
			$img_src = str_replace('\'', '', $img_src);
			$new_outertext = '<figure class="photoWrapper mainPhoto"><img src="'.$img_src.'"></figure>';
			$article_element->parent->outertext = $new_outertext;
		}
	}

}
// Imaginary empty line!