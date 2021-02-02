<?php
class TygodnikPolsatNewsBridge extends BridgeAbstract {
	const NAME = 'Tygodnik Polsat News';
	const URI = 'https://tygodnik.polsatnews.pl/';
	const DESCRIPTION = 'No description provided';
	const MAINTAINER = 'No maintainer';
	const CACHE_TIMEOUT = 86400;

	const PARAMETERS = array
	(
		'Parametry' => array
		(
			'wanted_number_of_articles' => array
			(
				'name' => 'Liczba artykułów',
				'type' => 'number',
				'required' => true,
				'title' => 'Liczba artykułów'
			),
			'filter' => array
			(
				'name' => 'filtruj według autora',
				'type' => 'checkbox',
				'required' => true,
				'title' => 'filtruj według autora'
			),
			'author' => array
			(
				'name' => 'Autor',
				'type' => 'text',
				'required' => false,
				'title' => 'Autor'
			),
		)
	);
	public function getIcon()
	{
		return 'https://tygodnik.polsatnews.pl/favicon-16x16.png';
	}

	public function collectData()
	{
		include 'myFunctions.php';
		$GLOBALS['number_of_wanted_articles'] = $this->getInput('wanted_number_of_articles');
//		Ramka FB: https://tygodnik.polsatnews.pl/news/2020-05-08/jan-komasa-bohater-jest-zly-bo-z-biednej-rodziny-wywiad/
//		Ramka YT: https://tygodnik.polsatnews.pl/news/2020-05-08/jan-komasa-bohater-jest-zly-bo-z-biednej-rodziny-wywiad/
//		Brak autora: https://tygodnik.polsatnews.pl/piotr-witwicki-wywiady-bezunikow/
//		Brak div.article__comments i div.article__related: https://tygodnik.polsatnews.pl/news/2020-07-10/zdalna-dehumanizacja-zaremba-o-przyszlosci-uczelni/
		
		$this->setGlobalArticlesParams();
		$found_urls = $this->getArticlesUrls();
//		var_dump_print($found_urls);
		foreach($found_urls as $url)
		{
			$this->addArticle($url);
		}
	}
	private function setGlobalArticlesParams()
	{
		$GLOBALS['author'] = $this->getInput('author');
		if (TRUE === is_null($GLOBALS['author']))
			$GLOBALS['author'] = "";
		
		$GLOBALS['filter'] = $this->getInput('filter');
		if (TRUE === is_null($GLOBALS['filter']))
			$GLOBALS['filter'] = FALSE;
	}

	private function getArticlesUrls()
	{
		$articles_urls = array();
		$url_articles_list = 'https://tygodnik.polsatnews.pl/';
		while (count($articles_urls) < $GLOBALS['number_of_wanted_articles'] && "empty" != $url_articles_list)
		{
			$html_articles_list = getSimpleHTMLDOM($url_articles_list);
			if (0 === count($found_hrefs = $html_articles_list->find('A.article__link[href^="https://tygodnik.polsatnews.pl/news/"]')))
			{
				break;
			}
			else
			{
				if (TRUE === $GLOBALS['filter'])
				{
					foreach($found_hrefs as $href_element)
					{
						$author_element = $href_element->find('DIV.article__author', 0);
						if (FALSE === is_null($author_element))
						{
							$author_name = $author_element->plaintext;
							$author_name = strtolower(trim($author_name));
							$wanted_author = strtolower($GLOBALS['author']);
							if (FALSE !== strpos($author_name, $wanted_author))
							{
								if(isset($href_element->href))
									$articles_urls[] = $href_element->href;
							}
						}
					}
				}
				else
				{
					foreach($found_hrefs as $href_element)
					{
						if(isset($href_element->href))
							$articles_urls[] = $href_element->href;
					}
				}
			}
			$url_articles_list = $this->getNextPageUrl($html_articles_list);
		}
		return array_slice($articles_urls, 0, $GLOBALS['number_of_wanted_articles']);
	}

	private function getNextPageUrl($html_articles_list)
	{
		$next_page_element = $html_articles_list->find('BUTTON.pager__button[data-url]', 0);
		if (FALSE === is_null($next_page_element) && $next_page_element->hasAttribute('data-url'))
		{
			return $next_page_element->getAttribute('data-url');
		}
		else
			return "empty";
	}

	private function addArticle($url_article)
	{
		$article_html = getSimpleHTMLDOMCached($url_article, 86400 * 14);
		$article = $article_html->find('article.article--target', 0);

		//title
		if (FALSE === is_null($title_element = $article->find('H1.article__title', 0)))
		{
			$title = $title_element->plaintext;
		}
		//timestamp
		if (FALSE === is_null($time_element = $article->find('TIME.article__time[datetime]', 0)))
		{
			$timestamp = $time_element->getAttribute('datetime');
		}
		//author
		if (FALSE === is_null($author_element = $article->find('DIV.article__author', 0)))
		{
			$author = $author_element->plaintext;
		}

		foreach($article->find('p') as $paragraph)
		{
			deleteAncestorIfDescendantExists($paragraph, 'SPAN.article__more');
			deleteAncestorIfContainsText($paragraph, 'ZOBACZ: ');
		}

		deleteAllDescendantsIfExist($article, 'SCRIPT');
		deleteAllDescendantsIfExist($article, 'UL.menu__list');
		deleteAllDescendantsIfExist($article, 'DIV.article__comments');
		deleteAllDescendantsIfExist($article, 'DIV.article__related');
		deleteAllDescendantsIfExist($article, 'DIV.article__share');
		deleteAllDescendantsIfExist($article, 'DIV#fb-root');
		deleteAncestorIfChildMatches($article, array('A', 'STRONG', 'SPAN.article__more'));

		foreach($article->find('amp-img, img') as $photo_element)
		{
			if(isset($photo_element->width)) $photo_element->width = NULL;
			if(isset($photo_element->height)) $photo_element->height = NULL;
			if(isset($photo_element->srcset)) $photo_element->srcset = NULL;
			if(isset($photo_element->sizes)) $photo_element->sizes = NULL;
		}
/*
		foreach($article->find('FIGURE.article-image') as $photo_element)
		{
			$photo_src = $photo_element->find('SPAN.article-image-src', 0);
			$photo_caption = $photo_element->find('FIGCAPTION.article-image-capition', 0);
			if (FALSE === is_null($photo_src) && FALSE === is_null($photo_caption))
			{
				echo "<br>photo_caption->plaintext=$photo_caption->plaintext<br>";
				echo "photo_src->plaintext=$photo_src->plaintext<br>";
				$new_text = $photo_caption->plaintext.' /'.$photo_src->plaintext;
				$photo_src->outertext = '';
				$old_outertext = $photo_caption->outertext;
				preg_match('/>[^<]*</', $old_outertext, $output_array);
				$new_outertext = str_replace($output_array[0], '>"testttttttttttttttttttttttt"<', $old_outertext);
				$photo_caption->outertext = $new_outertext;
				echo "old_outertext=$old_outertext<br>";
				echo "new_outertext=$new_outertext<br>";
				echo "output_array[0]=$output_array[0]<br>";
				echo "Po zmienie photo_caption->plaintext=$photo_caption->plaintext<br>";
			}
			else
			{
				echo "Coś jest nullem<br>";
			}
//			$my_text = $photo_caption->plaintext;
//			$photo_element->find('FIGCAPTION.article-image-capition', 0)->plaintext = 'testttttttttttttttttttttttt';
//			echo "Po zmienie photo_element=$photo_element<br>";
		}
*/
		$lead_style = array(
			'font-weight: bold;'
		);
		addStyle($article, 'DIV.article__preview', $lead_style);
		//https://tygodnik.polsatnews.pl/news/2021-01-23/kiedys-bano-sie-zbydlecenia-dzis-mikroczipow/
		addStyle($article, 'FIGURE.article__figure, FIGURE.article-image', getStylePhotoParent());
		addStyle($article, 'IMG.article__img, DIV.article-image-wrap', getStylePhotoImg());
		//https://tygodnik.polsatnews.pl/news/2021-01-30/sladami-maurow-na-polwyspie-iberyjskim/
		addStyle($article, 'DIV.article__source, FIGCAPTION.article-image-capition, SPAN.article-image-src', getStylePhotoCaption());
		//https://tygodnik.polsatnews.pl/news/2021-01-30/sladami-maurow-na-polwyspie-iberyjskim/
		addStyle($article, 'BLOCKQUOTE', getStyleQuote());

		$this->items[] = array(
			'uri' => $url_article,
			'title' => $title,
			'timestamp' => $timestamp,
			'author' => $author,
			'content' => $article
		);
	}
}