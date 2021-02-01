<?php
class TVNPremiumBridge extends FeedExpander {

	const MAINTAINER = 'No maintainer';
	const NAME = 'TVN Premium';
	const URI = '';
	const DESCRIPTION = 'No description provided';
	const CACHE_TIMEOUT = 86400;

	const PARAMETERS = array
	(
		'Parametry' => array
		(
			'wanted_number_of_articles' => array
			(
				'name' => 'Liczba artykułów',
				'type' => 'number',
				'required' => true
			)
		)
	);

    public function collectData(){
		include 'myFunctions.php';
        $this->collectExpandableDatas('https://tvn24.pl/premium.xml');
    }

	protected function parseItem($newsItem)
	{
		$item = parent::parseItem($newsItem);

		if (count($this->items) >= $this->getInput('wanted_number_of_articles'))
		{
			return;
		}
		else
		{
			$articlePage = getSimpleHTMLDOMCached($item['uri'], 86400 * 14);
			
			if (FALSE === is_null($article = $articlePage->find('DIV.article-story-content__elements-wrapper', 0)))
			{
				$article = $articlePage->find('DIV.article-story-content__elements-wrapper', 0);

				if (FALSE === is_null($author_elem = $article->find('DIV.main-asset-detail-info__author-name', 0)))
				{
					if (FALSE === is_null($first_name = $author_elem->find('DIV.main-asset-detail-info__author-first-name', 0)))
						$item['author'] = $first_name->plaintext;
					if (FALSE === is_null($second_elem = $author_elem->find('DIV.main-asset-detail-info__author-surname', 0)))
						$item['author'] = $item['author']." ".$second_elem->plaintext;

/*
					$autor_element = $article->find('DIV.main-asset-detail-info__author-name', 0);
					$autor = trim($autor_element->plaintext);
					$item['author'] = $autor;*/
				}
				deleteAllDescendantsIfExist($article, 'ASIDE.article-share-socials');
				deleteAllDescendantsIfExist($article, 'DIV.image-component--author');
				deleteAllDescendantsIfExist($article, 'SPAN.main-asset-premium__label');
				deleteAllDescendantsIfExist($article, 'IMG.premium-lock__picture');
				deleteAllDescendantsIfExist($article, 'DIV.account-buttons--article');
				deleteAllDescendantsIfExist($article, 'FIGURE.media-content');
				
				
				
				

/*				if (FALSE === is_null($temp = $article->find('DIV.main-asset-detail-info__author-name', 0)))
				{
					$authors = array();
					foreach($article->find('DIV.main-asset-detail-info__author-name') as $author)
					{
						$authors[] = trim($author->plaintext);
					}
					$item['author'] = $authors;
				}*/
/*				if(isset($article->width)) $article->width = NULL;

				foreach($article->find('IMG[src^="data:image"][data-src^="http"]') as $photo_element)
				{
					$photo_element->src = $photo_element->getAttribute('data-src');
					if(isset($photo_element->width)) $photo_element->width = NULL;
					if(isset($photo_element->height)) $photo_element->height = NULL;
				}

				deleteAllDescendantsIfExist($article, 'DIV.read-others-content');
				deleteAllDescendantsIfExist($article, 'DIV.issue-sidebar');
				deleteAllDescendantsIfExist($article, 'DIV.share-panel');
				deleteAllDescendantsIfExist($article, 'DIV.article-col-33');

				if (FALSE === is_null($temp = $article->find('SPAN.author', 0)))
				{
					$autor_element = $article->find('SPAN.author', 0);
					$autor = trim($autor_element->plaintext);
					$item['author'] = $autor;
				}*/
				$item['content'] = $article;
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