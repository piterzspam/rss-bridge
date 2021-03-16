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
		$articlePage = getSimpleHTMLDOMCached($item['uri'], 86400 * 14);
		$article = $articlePage->find('MAIN#site-content', 0);
		foreach_delete_element($article, 'DIV.comments-wrapper');
		foreach_delete_element($article, 'comment');
		foreach_delete_element($article, 'script');

		foreach($article->find('img') as $photo_element)
		{
			if(isset($photo_element->width)) $photo_element->width = NULL;
			if(isset($photo_element->height)) $photo_element->height = NULL;
		}

		$tags = array();
		foreach($article->find('LI.post-tags A[href*="informatykzakladowy.pl/tag/"][rel="tag"]') as $tag)
		{
			$tags[] = trim($tag->plaintext);
		}

		$item['content'] = $article;
		$item['categories'] = $tags;
		
//		if (FALSE === in_array("szkolenie", $item['categories'])) return $item;
				
		return $item;
	}

}
// Imaginary empty line!