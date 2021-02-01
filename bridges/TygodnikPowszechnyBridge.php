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
				'type' => 'number',
				'required' => true
			),
			'include_not_downloaded' => array
			(
				'name' => 'Uwzględnij niepobrane',
				'type' => 'checkbox',
				'required' => true,
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
		$GLOBALS['include_not_downloaded'] = $this->getInput('include_not_downloaded');
		if (TRUE === is_null($GLOBALS['include_not_downloaded']))
			$GLOBALS['include_not_downloaded'] = FALSE;
	}

	protected function parseItem($newsItem)
	{
		$item = parent::parseItem($newsItem);
		if (count($this->items) >= intval($this->getInput('wanted_number_of_articles')))
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
		$article_post = $article_page->find('DIV.view-full-article', 0);
		$lead_style = array(
			'font-weight: bold;'
		);
		addStyle($article_post, 'DIV.views-field-field-summary', $lead_style);
		
		$item['content'] = $article_post;
		return $item;
	}

}
// Imaginary empty line!