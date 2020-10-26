<?php
class TygodnikPolsatNewsBridge extends BridgeAbstract {
	const NAME = 'Tygodnik Polsat News';
	const URI = 'https://tygodnik.polsatnews.pl/';
	const DESCRIPTION = 'No description provided';
	const MAINTAINER = 'No maintainer';
	const CACHE_TIMEOUT = 3600; // Can be omitted!

	const PARAMETERS = array
	(
		'Tekst pogrubiony' => array
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
	public function collectData()
	{
//		error_reporting(E_ERROR | E_WARNING | E_PARSE);
//		Ramka FB: https://tygodnik.polsatnews.pl/news/2020-05-08/jan-komasa-bohater-jest-zly-bo-z-biednej-rodziny-wywiad/
//		Ramka YT: https://tygodnik.polsatnews.pl/news/2020-05-08/jan-komasa-bohater-jest-zly-bo-z-biednej-rodziny-wywiad/
//		Brak autora: https://tygodnik.polsatnews.pl/piotr-witwicki-wywiady-bezunikow/
//		Brak div.article__comments i div.article__related: https://tygodnik.polsatnews.pl/news/2020-07-10/zdalna-dehumanizacja-zaremba-o-przyszlosci-uczelni/
//		error_reporting(E_ALL & ~E_NOTICE);
		$author = $this->getInput('author');
		$wanted_number_of_articles = $this->getInput('wanted_number_of_articles');

		$main_page_url = 'https://tygodnik.polsatnews.pl/';

//		ini_set('user_agent','Mozilla/4.0 (compatible; MSIE 6.0)');
		$html_main_page = getSimpleHTMLDOM($main_page_url);

		$urls = array();

		foreach($html_main_page->find('a.article__link') as $article__link)
		{
			if (count($urls) < $wanted_number_of_articles && $article__link->find('DIV.article__author', 0)->plaintext === $author)
				$urls[] = $article__link->getAttribute('href');
		}

		$url = $html_main_page->find('BUTTON.pager__button', 0)->getAttribute('data-url');
		while (count($urls) < $wanted_number_of_articles)
		{
			$html = getSimpleHTMLDOM($url);
			foreach($html->find('A.article__link') as $article__link)
			{
				if (count($urls) < $wanted_number_of_articles && $article__link->find('DIV.article__author', 0)->plaintext === $author)
					$urls[] = $article__link->getAttribute('href');
			}
			if (FALSE === is_null($html->find('BUTTON.pager__button', 0)))
				$url = $html->find('BUTTON.pager__button', 0)->getAttribute('data-url');
			else
				break;
		}

		foreach($urls as $url)
		{
			$html = file_get_html($url);
			$article_target = $html->find('article.article--target', 0);
			foreach($article_target->childNodes() as $elem)
			{
				if ($elem->tag === 'script')
					$elem->outertext = '';
			}
			

			foreach($article_target->find('p') as $paragraph)
			{
				$this->deleteAncestorIfDescendantExists($paragraph, 'SPAN.article__more');
				$this->deleteAncestorIfContainsText($paragraph, 'ZOBACZ: ');
			}
			
			$this->deleteDescendantIfExists($article_target, 'UL.menu__list');
			$this->deleteDescendantIfExists($article_target, 'DIV.article__comments');
			$this->deleteDescendantIfExists($article_target, 'DIV.article__related');
			$this->deleteDescendantIfExists($article_target, 'DIV.article__share');
			$this->deleteDescendantIfExists($article_target, 'DIV#fb-root');

			$this->items[] = array(
				'uri' => $url,
				'title' => $article_target->find('H1.article__title', 0)->plaintext,
				'timestamp' => $article_target->find('TIME.article__time', 0)->getAttribute('datetime'),
				'author' => $article_target->find('DIV.article__author', 0)->plaintext,
				'content' => $article_target
			);
		}
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
}