<?php
class TygodnikPolsatNewsBridge extends BridgeAbstract {
	const NAME = 'Tygodnik Polsat News';
	const URI = 'https://tygodnik.polsatnews.pl/';
	const DESCRIPTION = 'No description provided';
	const MAINTAINER = 'No maintainer';
	const CACHE_TIMEOUT = 3600;

	const PARAMETERS = array
	(
		'Parametry' => array
		(
			'author' => array
			(
				'name' => 'Autor',
				'type' => 'text',
				'required' => true
			),
			'wanted_number_of_articles' => array
			(
				'name' => 'Liczba artykułów',
				'type' => 'text',
				'required' => true
			),
		)
	);
	public function getIcon()
	{
		return 'https://tygodnik.polsatnews.pl/favicon-16x16.png';
	}

	public function collectData()
	{
//		Ramka FB: https://tygodnik.polsatnews.pl/news/2020-05-08/jan-komasa-bohater-jest-zly-bo-z-biednej-rodziny-wywiad/
//		Ramka YT: https://tygodnik.polsatnews.pl/news/2020-05-08/jan-komasa-bohater-jest-zly-bo-z-biednej-rodziny-wywiad/
//		Brak autora: https://tygodnik.polsatnews.pl/piotr-witwicki-wywiady-bezunikow/
//		Brak div.article__comments i div.article__related: https://tygodnik.polsatnews.pl/news/2020-07-10/zdalna-dehumanizacja-zaremba-o-przyszlosci-uczelni/
		$GLOBALS['author'] = $this->getInput('author');
		$GLOBALS['number_of_wanted_articles'] = $this->getInput('wanted_number_of_articles');

		$main_page_url = 'https://tygodnik.polsatnews.pl/';

//		ini_set('user_agent','Mozilla/4.0 (compatible; MSIE 6.0)');
		$html_main_page = getSimpleHTMLDOM($main_page_url);

		foreach($html_main_page->find('a.article__link') as $article__link)
		{
			if (FALSE === is_null($author_element = $article__link->find('DIV.article__author', 0)))
			{
				if (count($this->items) < $GLOBALS['number_of_wanted_articles'] && $author_element->plaintext === $GLOBALS['author'])
				{
					$href = $article__link->getAttribute('href');
					$this->addArticle($href);
				}
			}
		}

		$next_page_url = $html_main_page->find('BUTTON.pager__button', 0)->getAttribute('data-url');
		while (count($this->items) < $GLOBALS['number_of_wanted_articles'])
		{
			$html_next_page = getSimpleHTMLDOM($next_page_url);
			foreach($html_next_page->find('A.article__link') as $article__link)
			{
				if (count($this->items) < $GLOBALS['number_of_wanted_articles'] && $article__link->find('DIV.article__author', 0)->plaintext === $GLOBALS['author'])
				{
					$href = $article__link->getAttribute('href');
					$this->addArticle($href);
				}
			}
			if (FALSE === is_null($html_next_page->find('BUTTON.pager__button', 0)))
				$next_page_url = $html_next_page->find('BUTTON.pager__button', 0)->getAttribute('data-url');
			else
				break;
		}
	}

	private function addArticle($url_article)
	{
		$article_html = getSimpleHTMLDOMCached($url_article, 60*60*24*7*2);
		$article_target = $article_html->find('article.article--target', 0);

		//title
		$title = $article_target->find('H1.article__title', 0)->plaintext;
		//timestamp
		$timestamp = $article_target->find('TIME.article__time', 0)->getAttribute('datetime');
		//author
		$author = $article_target->find('DIV.article__author', 0)->plaintext;


		foreach($article_target->find('p') as $paragraph)
		{
			$this->deleteAncestorIfDescendantExists($paragraph, 'SPAN.article__more');
			$this->deleteAncestorIfContainsText($paragraph, 'ZOBACZ: ');
		}

		$this->deleteAllDescendantsIfExist($article_target, 'script');
		$this->deleteDescendantIfExists($article_target, 'UL.menu__list');
		$this->deleteDescendantIfExists($article_target, 'DIV.article__comments');
		$this->deleteDescendantIfExists($article_target, 'DIV.article__related');
		$this->deleteDescendantIfExists($article_target, 'DIV.article__share');
		$this->deleteDescendantIfExists($article_target, 'DIV#fb-root');
		foreach($article_target->find('amp-img, img') as $photo_element)
		{
			if(isset($photo_element->width)) $photo_element->width = NULL;
			if(isset($photo_element->height)) $photo_element->height = NULL;
			if(isset($photo_element->srcset)) $photo_element->srcset = NULL;
			if(isset($photo_element->sizes)) $photo_element->sizes = NULL;
		}

		$this->items[] = array(
			'uri' => $url_article,
			'title' => $title,
			'timestamp' => $timestamp,
			'author' => $author,
			'content' => $article_target
		);
	}

	private function deleteDescendantIfExists($ancestor, $descendant_string)
	{
		if (FALSE === is_null($descendant = $ancestor->find($descendant_string, 0)))
			$descendant->outertext = '';
	}

	private function deleteAncestorIfDescendantExists($ancestor, $descendant_string)
	{
		if (FALSE === is_null($descendant = $ancestor->find($descendant_string, 0)))
			$ancestor->outertext = '';
	}

	private function deleteAncestorIfContainsText($ancestor, $descendant_string)
	{
		if (FALSE === is_null($ancestor))
			if (FALSE !== strpos($ancestor->plaintext, $descendant_string))
				$ancestor->outertext = '';
	}
	private function deleteAllDescendantsIfExist($ancestor, $descendant_string)
	{
		foreach($ancestor->find($descendant_string) as $descendant)
			$descendant->outertext = '';
	}
}