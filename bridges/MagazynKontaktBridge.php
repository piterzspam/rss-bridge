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
			
			$selectors_array[] = 'DIV.js-pullable_hard_dest_left';
			$selectors_array[] = 'DIV.js-pullable_hard_dest_right';
			$selectors_array[] = 'SECTION.block-sharer';
			$selectors_array[] = 'DIV[data-source="ramka-zbiorka"]';
			$selectors_array[] = 'DIV[data-source="ramka-newsletter"]';
			$selectors_array[] = 'DIV[data-source="block-magazynpromo"]';
			$selectors_array[] = 'DIV[data-source="ramka-polecane-1"]';
			$selectors_array[] = 'DIV.block-content_breaker_ramka';
			$article = foreach_delete_element_array($article, $selectors_array);


			$tags = return_tags_array($article, 'DIV.catbox A.block-catbox');
			$author = return_authors_as_string($article, 'DIV.autor');
			//https://magazynkontakt.pl/kosciol-ludu-jest-kosciolem-politycznym/
			$lead_style = array(
				'font-weight: bold;'
			);
			$article = add_style($article, 'DIV.lead', $lead_style);

			$article = add_style($article, 'DIV.pixwrap', getStylePhotoParent());
			$article = add_style($article, 'IMG[src][alt]', getStylePhotoImg());
			$article = add_style($article, 'DIV.pix_source', getStylePhotoCaption());
	
			$item['content'] = $article;
			$item['author'] = $author;
			$item['categories'] = $tags;

			return $item;
		}
	}

}
// Imaginary empty line!