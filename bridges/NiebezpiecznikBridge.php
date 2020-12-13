<?php
class NiebezpiecznikBridge extends FeedExpander {

	const MAINTAINER = 'No maintainer';
	const NAME = 'Niebezpiecznik';
	const URI = '';
	const DESCRIPTION = 'No description provided';
	const PARAMETERS = array();
	const CACHE_TIMEOUT = 3600;

    public function collectData(){
//		include 'myFunctions.php';
        $this->collectExpandableDatas('http://feeds.feedburner.com/Niebezpiecznik');
    }

	protected function parseItem($newsItem)
	{
		$item = parent::parseItem($newsItem);
		$articlePage = getSimpleHTMLDOMCached(($item['uri']), 60*60*24*7*2);
		$article = $articlePage->find('DIV#main DIV.post', 0);
		$ad = $article->find('DIV.entry DIV[style="margin-top:-25px;"]', 0);
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
		if (FALSE === in_array("konferencje i wykłady", $item['categories']))
		{
			return $item;
		}
	}

}
// Imaginary empty line!