<?php
class MagazynKontaktBridge extends FeedExpander {

	const MAINTAINER = 'No maintainer';
	const NAME = 'Magazyn Kontakt';
	const URI = '';
	const DESCRIPTION = 'No description provided';
	const PARAMETERS = array();
	const CACHE_TIMEOUT = 100;

    public function collectData(){
		include 'myFunctions.php';
        $this->collectExpandableDatas('https://magazynkontakt.pl/feed/');
    }

	protected function parseItem($newsItem)
	{
		$item = parent::parseItem($newsItem);
		if (count($this->items) >= 2)
		{
			return;
		}
		if (FALSE === strpos($item['uri'], 'magazynkontakt.pl/profil/'))
		{
//			$articlePage = getSimpleHTMLDOMCached($item['uri'], 100);
			$articlePage = getSimpleHTMLDOM($item['uri'], 100);
			$article = $articlePage->find('ARTICLE.block-post', 0);
			foreach($article->find('DIV[id^="attachment_"]') as $photo_element)
			{
				if(isset($photo_element->style)) $photo_element->style = NULL;
			}
			foreach($article->find('IMG') as $photo_element)
			{
				if(isset($photo_element->width)) $photo_element->width = NULL;
				if(isset($photo_element->height)) $photo_element->height = NULL;
				if(isset($photo_element->srcset)) $photo_element->srcset = NULL;
				if(isset($photo_element->sizes)) $photo_element->sizes = NULL;
			}
			
			deleteAllDescendantsIfExist($article, 'DIV.js-pullable_hard_dest_left');
			deleteAllDescendantsIfExist($article, 'DIV.js-pullable_hard_dest_right');
			deleteAllDescendantsIfExist($article, 'SECTION.block-sharer');
	
			if (FALSE === is_null($temp = $article->find('DIV.catbox A.block-catbox', 0)))
			{
				$tags = array();
				foreach($article->find('DIV.catbox A.block-catbox') as $tag)
				{
					$tags[] = trim($tag->plaintext);
				}
				$item['categories'] = $tags;
			}

			if (FALSE === is_null($temp = $article->find('DIV.autor', 0)))
			{
				$autor_element = $article->find('DIV.autor', 0);
				$autor = trim($autor_element->plaintext);
				$item['author'] = $autor;
			}

			$item['content'] = $article;

			return $item;
		}
	}

}
// Imaginary empty line!