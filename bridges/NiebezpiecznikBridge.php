<?php
class NiebezpiecznikBridge extends FeedExpander {

	const MAINTAINER = 'No maintainer';
	const NAME = 'Niebezpiecznik';
	const URI = 'https://niebezpiecznik.pl/';
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
        $this->collectExpandableDatas('http://feeds.feedburner.com/Niebezpiecznik');
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
		$articlePage = getSimpleHTMLDOMCached(($item['uri']), 86400 * 14);
		$article = $articlePage->find('DIV#main DIV.post', 0);
		if (FALSE === is_null($ad = $article->find('DIV.entry DIV[style="margin-top:-25px;"]', 0)))
			$ad->outertext = '';
		$title_link = $article->find('DIV.title A[href]', 0);
		$href = $title_link->href;
		$tags = array();

		foreach($article->find('DIV.postmeta A[href*="niebezpiecznik.pl/tag/"][rel="tag"]') as $tag)
		{
			$tags[] = trim($tag->plaintext);
		}

		$item['uri'] = $href;
		$item['content'] = $article;
		$item['categories'] = $tags;
		if (FALSE === in_array("konferencje i wykłady", $item['categories']) && FALSE === in_array("szkolenia", $item['categories']) )
		{
			return $item;
		}
	}

}
// Imaginary empty line!