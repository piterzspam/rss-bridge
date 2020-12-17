<?php
class InformatykZakladowyBridge extends FeedExpander {

	const MAINTAINER = 'No maintainer';
	const NAME = 'Informatyk Zakładowy';
	const URI = '';
	const DESCRIPTION = 'No description provided';
	const PARAMETERS = array();
	const CACHE_TIMEOUT = 3600;

    public function collectData(){
		include 'myFunctions.php';
        $this->collectExpandableDatas('https://informatykzakladowy.pl/feed/');
    }

	protected function parseItem($newsItem)
	{
		$item = parent::parseItem($newsItem);
		$articlePage = getSimpleHTMLDOMCached($item['uri'], 60*60*24*7*2);
		$article = $articlePage->find('MAIN#site-content', 0);
		deleteAllDescendantsIfExist($article, 'DIV.comments-wrapper');
		deleteAllDescendantsIfExist($article, 'comment');
		deleteAllDescendantsIfExist($article, 'script');

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