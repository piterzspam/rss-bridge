<?php
class ZaufanaTrzeciaStronaBridge extends FeedExpander {

	const MAINTAINER = 'No maintainer';
	const NAME = 'Zaufana Trzecia Strona';
	const URI = '';
	const DESCRIPTION = 'No description provided';
	const PARAMETERS = array();
	const CACHE_TIMEOUT = 86400;

    public function collectData(){
//		include 'myFunctions.php';
        $this->collectExpandableDatas('https://zaufanatrzeciastrona.pl/feed/');
    }

	protected function parseItem($newsItem)
	{
		$item = parent::parseItem($newsItem);
		$articlePage = getSimpleHTMLDOMCached($newsItem->link, 86400 * 14);
		$article = $articlePage->find('DIV#main DIV.postcontent', 0);
		foreach($article->find('IMG') as $img)
		{
			if ($img->hasAttribute('src'))
				if (0 === strpos($img->src, '/'))
					$img->src = 'https://zaufanatrzeciastrona.pl'.$img->src;
		}
		foreach($article->find('DIV.wp-block-embed__wrapper') as $embed)
		{
			$embed_innertext = $embed->innertext;
			preg_match('/title="([^"]*)"/', $embed_innertext, $output_array);
			$title = $output_array[1];
			preg_match('/src="([^"]*)"/', $embed_innertext, $output_array);
			$src = $output_array[1];
			$embed->outertext = 
				'<strong><br>'
				.'<a href='.$src.'>'
				."Ramka - ".$title.'<br>'
				.'</a>'
				.'<br></strong>';
		}
		
		$tags = array();
		foreach($article->find('DIV.dolna-ramka A[href*="/tag/"][rel="tag"]') as $tag)
		{
			$tags[] = trim($tag->plaintext);
		}
		
		$item['content'] = $article;
		$item['categories'] = $tags;
		if (FALSE === in_array("konferencja", $item['categories']))
		{
			return $item;
		}
	}

}
// Imaginary empty line!