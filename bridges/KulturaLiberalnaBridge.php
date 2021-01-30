<?php
class KulturaLiberalnaBridge extends FeedExpander {

	const MAINTAINER = 'No maintainer';
	const NAME = 'Kultura Liberalna';
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
        $this->collectExpandableDatas('https://kulturaliberalna.pl/feed/');
    }

	protected function parseItem($newsItem)
	{
		$item = parent::parseItem($newsItem);
		if (count($this->items) >= $this->getInput('wanted_number_of_articles'))
		{
			return $item;
		}
		$article_page = getSimpleHTMLDOMCached($item['uri'], 86400 * 14);
		$article_post = $article_page->find('ARTICLE[id^="post-"]', 0);
		
		deleteAllDescendantsIfExist($article_post, 'script');
		deleteAllDescendantsIfExist($article_post, 'DIV.kl-10lat-box');
		deleteAllDescendantsIfExist($article_post, 'DIV.go-to-comments');
		deleteAllDescendantsIfExist($article_post, 'DIV.nr-info');
		deleteAllDescendantsIfExist($article_post, 'DIV.more-in-number-container');
		deleteAllDescendantsIfExist($article_post, 'DIV.fb-comm');
		deleteAllDescendantsIfExist($article_post, 'P.section-name.mobile-section-name');
		//https://kulturaliberalna.pl/2021/01/12/cena-osobnosci-nie-jest-wysoka-na-razie/
		deleteAllDescendantsIfExist($article_post, 'DIV.promobox');
		$tags = returnTagsArray($article_post, 'DIV.post-tags A');
		$author = returnAuthorsAsString($article_post, 'DIV.article-footer H2');
		$interview_quote_style = array(
			'border: dashed;'
		);
		addStyle($article_post, 'blockquote', $interview_quote_style);
		foreach($article_post->find('IMG') as $photo_element)
		{
			if(isset($photo_element->width)) $photo_element->width = NULL;
			if(isset($photo_element->height)) $photo_element->height = NULL;
			if(isset($photo_element->srcset)) $photo_element->srcset = NULL;
			if(isset($photo_element->sizes)) $photo_element->sizes = NULL;
		}

		$item['categories'] = $tags;
		$item['author'] = $author;
		$item['content'] = $article_post;
		return $item;
	}

}
// Imaginary empty line!