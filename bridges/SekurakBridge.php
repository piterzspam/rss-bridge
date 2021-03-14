<?php
class SekurakBridge extends FeedExpander {

	const MAINTAINER = 'No maintainer';
	const NAME = 'Sekurak';
	const URI = 'https://sekurak.pl/';
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
//		include 'myFunctions.php';
		$this->setGlobalArticlesParams();
        $this->collectExpandableDatas('http://feeds.feedburner.com/Sekurak');
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
		$article = $articlePage->find('ARTICLE#articleContent', 0);

		$tags = array();
		foreach($article->find('DIV.meta', 1)->find('A[href^="https://sekurak.pl/tag/"][rel="tag"]') as $tag)
		{
			$tags[] = trim($tag->plaintext);
		}
		$page_link = $articlePage->find('LINK[rel="canonical"][href^="https://sekurak.pl/"]', 0);
		$href = $page_link->href;
		
		$item['uri'] = $href;
		$item['content'] = $article;
		$item['categories'] = $tags;
		
		if (FALSE === in_array("szkolenie", $item['categories']))
		{
			return $item;
		}
	}

}
// Imaginary empty line!