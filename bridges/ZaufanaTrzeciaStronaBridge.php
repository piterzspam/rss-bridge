<?php
class ZaufanaTrzeciaStronaBridge extends FeedExpander {

	const MAINTAINER = 'No maintainer';
	const NAME = 'Zaufana Trzecia Strona';
	const URI = '';
	const DESCRIPTION = 'No description provided';
	const PARAMETERS = array();
	const CACHE_TIMEOUT = 3600;

    public function collectData(){
//		include 'myFunctions.php';
        $this->collectExpandableDatas('https://zaufanatrzeciastrona.pl/feed/');
    }

	protected function parseItem($newsItem)
	{
		$item = parent::parseItem($newsItem);
		$articlePage = getSimpleHTMLDOM($newsItem->link);
		$article = $articlePage->find('DIV#main DIV.postcontent', 0);
		foreach($article->find('IMG') as $img)
		{
			if ($img->hasAttribute('src'))
				if (0 === strpos($img->src, '/'))
					$img->src = 'https://zaufanatrzeciastrona.pl'.$img->src;
		}
		foreach($article->find('DIV.wp-block-embed__wrapper') as $embed)
		{
			$embed->outertext = '<div class="wp-block-embed__wrapper">'.$embed->innertext.'</div>';
		}
		
		$item['content'] = $article;
		return $item;
	}

}
// Imaginary empty line!