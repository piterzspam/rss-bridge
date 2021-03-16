<?php
class MagazynKontaktBridge extends FeedExpander {

	const MAINTAINER = 'No maintainer';
	const NAME = 'Magazyn Kontakt';
	const URI = 'https://magazynkontakt.pl/';
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
		include 'myFunctions.php';
		$this->setGlobalArticlesParams();
        $this->collectExpandableDatas('https://magazynkontakt.pl/feed/');
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
		if (FALSE === strpos($item['uri'], 'magazynkontakt.pl/profil/') && FALSE === strpos($item['uri'], 'magazynkontakt.pl/ramki/'))
		{
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
			
			foreach_delete_element($article, 'DIV.js-pullable_hard_dest_left');
			foreach_delete_element($article, 'DIV.js-pullable_hard_dest_right');
			foreach_delete_element($article, 'SECTION.block-sharer');
			foreach_delete_element($article, 'DIV[data-source="ramka-zbiorka"]');
			foreach_delete_element($article, 'DIV[data-source="ramka-newsletter"]');
			foreach_delete_element($article, 'DIV[data-source="block-magazynpromo"]');
			foreach_delete_element($article, 'DIV[data-source="ramka-polecane-1"]');
			foreach_delete_element($article, 'DIV.block-content_breaker_ramka');
			

			$tags = return_tags_array($article, 'DIV.catbox A.block-catbox');
			$author = return_authors_as_string($article, 'DIV.autor');
			//https://magazynkontakt.pl/kosciol-ludu-jest-kosciolem-politycznym/
			$lead_style = array(
				'font-weight: bold;'
			);
			add_style($article, 'DIV.lead', $lead_style);

			add_style($article, 'DIV.pixwrap', getStylePhotoParent());
			add_style($article, 'IMG[src][alt]', getStylePhotoImg());
			add_style($article, 'DIV.pix_source', getStylePhotoCaption());
	
			$item['content'] = $article;
			$item['author'] = $author;
			$item['categories'] = $tags;

			return $item;
		}
	}

}
// Imaginary empty line!