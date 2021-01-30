<?php
class TygodnikPowszechnyBridge extends FeedExpander {

	const MAINTAINER = 'No maintainer';
	const NAME = 'Tygodnik Powszechny';
	const URI = '';
	const DESCRIPTION = 'No description provided';
	const CACHE_TIMEOUT = 86400;

	const PARAMETERS = array
	(
		'Parametry' => array
		(
			'wanted_number_of_articles' => array
			(
				'name' => 'Liczba artykułów',
				'type' => 'text',
				'required' => true
			)
		)
	);

    public function collectData(){
		include 'myFunctions.php';
        $this->collectExpandableDatas('https://www.tygodnikpowszechny.pl/rss.xml');
    }

	protected function parseItem($newsItem)
	{
		$item = parent::parseItem($newsItem);
//		return $item;
		if (count($this->items) >= $this->getInput('wanted_number_of_articles'))
		{
			return $item;
		}
		$article_page = getSimpleHTMLDOMCached($item['uri'], 86400 * 14);
		$article_post = $article_page->find('DIV.view-full-article', 0);
		$item['content'] = $article_post;
		return $item;
	}

}
// Imaginary empty line!