<?php
class TVNPremiumBridge extends FeedExpander {

	const MAINTAINER = 'No maintainer';
	const NAME = 'TVN Premium';
	const URI = 'https://tvn24.pl/premium';
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
        $this->collectExpandableDatas('https://tvn24.pl/premium.xml');
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

		if (count($this->items) >= $this->getInput('limit'))
		{
			return;
		}
		else
		{
			$articlePage = getSimpleHTMLDOMCached($item['uri'], 86400 * 14);

			if (FALSE === is_null($article = $articlePage->find('DIV.article-story-content__elements-wrapper', 0)))
			{
				$article = $articlePage->find('DIV.article-story-content__elements-wrapper', 0);

				foreach($article->find('DIV.main-asset-detail-info__author-name') as $author_element)
				{
					if (FALSE === is_null($name_element = $author_element->find('DIV.main-asset-detail-info__author-first-name', 0)))
					{
						if (FALSE === is_null($surname_element = $author_element->find('DIV.main-asset-detail-info__author-surname', 0)))
						{
							$author_element->innertext = trim($name_element->plaintext).' '.trim($surname_element->plaintext);
						}
						else
						{
							$author_element->innertext = trim($name_element->plaintext);
						}
					}
				}
				$author = returnAuthorsAsString($article, 'DIV.main-asset-detail-info__author');
				deleteAllDescendantsIfExist($article, 'SCRIPT');
				deleteAllDescendantsIfExist($article, 'NOSCRIPT');
				deleteAllDescendantsIfExist($article, 'LINK');
				deleteAllDescendantsIfExist($article, 'ASIDE.article-share-socials');
				deleteAllDescendantsIfExist($article, 'DIV.image-component--author');
				deleteAllDescendantsIfExist($article, 'SPAN.main-asset-premium__label');
				deleteAllDescendantsIfExist($article, 'IMG.premium-lock__picture');
				deleteAllDescendantsIfExist($article, 'DIV.account-buttons--article');
				deleteAllDescendantsIfExist($article, 'FIGURE.media-content');

				$item['content'] = $article;
				$item['author'] = $author;
			}
			else
				echo "error url1: ".$item['uri'];
/*			else if (FALSE === is_null($article = $articlePage->find('ARTICLE.table-of-contents', 0)))
			{
				$article = $articlePage->find('ARTICLE.table-of-contents', 0);
				$item['content'] = $article;
			}*/
			return $item;
		}
		return $item;
	}

}
// Imaginary empty line!