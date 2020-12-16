<?php
class SekurakBridge extends FeedExpander {

	const MAINTAINER = 'No maintainer';
	const NAME = 'Sekurak';
	const URI = '';
	const DESCRIPTION = 'No description provided';
	const PARAMETERS = array();
	const CACHE_TIMEOUT = 3600;

    public function collectData(){
//		include 'myFunctions.php';
        $this->collectExpandableDatas('http://feeds.feedburner.com/Sekurak');
    }

	protected function parseItem($newsItem)
	{
		$item = parent::parseItem($newsItem);
		$articlePage = getSimpleHTMLDOMCached($item['uri'], 60*60*24*7*2);
		$article = $articlePage->find('ARTICLE#articleContent', 0);

		$tags = array();
		foreach($article->find('DIV.meta', 1)->find('A[href^="https://sekurak.pl/tag/"][rel="tag"]') as $tag)
		{
			$tags[] = trim($tag->plaintext);
		}
		
		$item['content'] = $article;
		$item['categories'] = $tags;
		
		if (FALSE === in_array("szkolenie", $item['categories']))
		{
			return $item;
		}
	}

}
// Imaginary empty line!