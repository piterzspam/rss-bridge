<?php
class MiesiecznikZnakBridge extends FeedExpander {

	const MAINTAINER = 'No maintainer';
	const NAME = 'Miesięcznik Znak';
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
				'type' => 'text',
				'required' => true
			)
		)
	);

    public function collectData(){
		include 'myFunctions.php';
        $this->collectExpandableDatas('https://www.miesiecznik.znak.com.pl/feed/');
    }

	protected function parseItem($newsItem)
	{
		$item = parent::parseItem($newsItem);
		if (count($this->items) >= $this->getInput('wanted_number_of_articles'))
		{
			return $item;
		}
		else
		{
			$articlePage = getSimpleHTMLDOMCached($item['uri'], 86400 * 14);
			
			if (FALSE === is_null($article = $articlePage->find('SECTION.article', 0)))
			{
				$article = $articlePage->find('SECTION.article', 0);

				if(isset($article->width)) $article->width = NULL;

				foreach($article->find('IMG[src^="data:image"][data-src^="http"]') as $photo_element)
				{
					$photo_element->src = $photo_element->getAttribute('data-src');
					if(isset($photo_element->width)) $photo_element->width = NULL;
					if(isset($photo_element->height)) $photo_element->height = NULL;
				}

				deleteAllDescendantsIfExist($article, 'DIV.read-others');
				deleteAllDescendantsIfExist($article, 'DIV.read-others-content');
				deleteAllDescendantsIfExist($article, 'DIV.issue-sidebar');
				deleteAllDescendantsIfExist($article, 'DIV.share-panel');
				deleteAllDescendantsIfExist($article, 'DIV.article-col-33');

				if (FALSE === is_null($temp = $article->find('SPAN.author', 0)))
				{
					$autor_element = $article->find('SPAN.author', 0);
					$autor = trim($autor_element->plaintext);
					$item['author'] = $autor;
				}
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

}
// Imaginary empty line!