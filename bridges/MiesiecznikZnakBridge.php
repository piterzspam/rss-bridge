<?php
class MiesiecznikZnakBridge extends FeedExpander {

	const MAINTAINER = 'No maintainer';
	const NAME = 'Miesięcznik Znak';
	const URI = 'https://www.miesiecznik.znak.com.pl/';
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

	public function getIcon()
	{
		return 'https://www.miesiecznik.znak.com.pl/wp-content/themes/znak/img/favicon.jpg';
	}

    public function collectData(){
		include 'myFunctions.php';
		$this->setGlobalArticlesParams();
        $this->collectExpandableDatas('https://www.miesiecznik.znak.com.pl/feed/');
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
		
		if (FALSE === is_null($article_first_part = $articlePage->find('SECTION.article', 0)))
		{
			//https://www.miesiecznik.znak.com.pl/co-dalej-z-prawem-aborcyjnym-w-polsce/
			$article = $articlePage->find('BODY', 0);

			foreach_delete_element($article, 'SECTION.article.w-gradient');
			foreach_delete_element($article, 'SCRIPT');
			foreach_delete_element($article, 'SECTION.nav');
			foreach_delete_element($article, 'SECTION#buy-issue');
			foreach_delete_element($article, 'SECTION.related-tabs');
			foreach_delete_element($article, 'FOOTER');
			foreach_delete_element($article, 'DIV[id^="newsletter-popup"]');
			foreach_delete_element($article, 'DIV#people-modal');
			foreach_delete_element($article, 'DIV#mediaModal');
			foreach_delete_element($article, 'DIV#cookie-notice');
			foreach_delete_element($article, 'DIV.read-others');
			foreach_delete_element($article, 'DIV.read-others-content');
			foreach_delete_element($article, 'DIV.issue-sidebar');
			foreach_delete_element($article, 'DIV.share-panel');
			foreach_delete_element($article, 'DIV.article-col-33');
			foreach_delete_element($article, 'DIV.read-others-content');
	
			//Fix szerokości artykulu
			if(isset($article->width))
			{
				$article->width = NULL;
			}

			//Fix zdjęć
			foreach($article->find('DIV[id^="attachment_"][style]') as $photo_element)
			{
				$photo_element->style = NULL;
			}

			foreach($article->find('IMG[src^="data:image"][data-src^="http"]') as $photo_element)
			{
				$src = $photo_element->getAttribute('data-src');
				if(isset($photo_element->src)) $photo_element->src = NULL;
				$photo_element->setAttribute('src', $src);
				if(isset($photo_element->width)) $photo_element->width = NULL;
				if(isset($photo_element->height)) $photo_element->height = NULL;
				if($photo_element->hasAttribute('data-sizes')) $photo_element->setAttribute('data-sizes', NULL);
				if($photo_element->hasAttribute('data-src')) $photo_element->setAttribute('data-src', NULL);
				if($photo_element->hasAttribute('data-srcset')) $photo_element->setAttribute('data-srcset', NULL);
			}


			//https://www.miesiecznik.znak.com.pl/oswiadczenie-ws-tygodnika-powszechnego-na-ul-wislnej/
			$author = return_authors_as_string($article, 'SPAN.author');
			add_style($article, 'BLOCKQUOTE', getStyleQuote());
			add_style($article, 'DIV[id^="attachment_"]', getStylePhotoParent());
			add_style($article, 'IMG[class*="wp-image-"]', getStylePhotoImg());
			add_style($article, 'P.wp-caption-text', getStylePhotoCaption());
			//lead
			$lead_style = array(
				'font-weight: bold;'
			);
			add_style($article, 'DIV.lead[itemprop="description"]', $lead_style);
			$item['author'] = $author;
			$item['content'] = $article;
		}
		else if (FALSE === is_null($article = $articlePage->find('ARTICLE.table-of-contents', 0)))
		{
			$article = $articlePage->find('ARTICLE.table-of-contents', 0);
			$item['content'] = $article;
		}
		return $item;
	}

}
// Imaginary empty line!