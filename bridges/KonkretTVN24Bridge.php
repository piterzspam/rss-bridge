<?php
class KonkretTVN24Bridge extends BridgeAbstract {
	const NAME = 'Konkret TVN24';
	const URI = 'https://konkret24.tvn24.pl/';
	const DESCRIPTION = 'No description provided';
	const MAINTAINER = 'No maintainer';
	const CACHE_TIMEOUT = 86400; // Can be omitted!

	const PARAMETERS = array
	(
		'Parametry' => array
		(
			'wanted_number_of_articles' => array
			(
				'name' => 'Liczba artykułów',
				'type' => 'number',
				'required' => true
			),
			'category' => array(
				'name' => 'Kategoria',
				'type' => 'list',
				'values' => array(
					'Najnowsze' => 'https://konkret24.tvn24.pl/najnowsze,118.html',
					'Polska' => 'https://konkret24.tvn24.pl/polska,108.html',
					'Świat' => 'https://konkret24.tvn24.pl/swiat,109.html',
					'Polityka' => 'https://konkret24.tvn24.pl/polityka,112.html',
					'Nauka' => 'https://konkret24.tvn24.pl/nauka,111.html',
					'Zdrowie' => 'https://konkret24.tvn24.pl/zdrowie,110.html',
					'Rozrywka' => 'https://konkret24.tvn24.pl/rozrywka,113.html',
					'Tech' => 'https://konkret24.tvn24.pl/tech,116.html',
					'Mity' => 'https://konkret24.tvn24.pl/mity,114.html',
				 ),
				'title' => 'Kategoria',
				'defaultValue' => 'https://konkret24.tvn24.pl/najnowsze,118.html',
			),
		)
	);

	public function collectData()
	{
		include 'myFunctions.php';
		$GLOBALS['number_of_wanted_articles'] = $this->getInput('wanted_number_of_articles');
		$GLOBALS['chosen_category_url'] = $this->getInput('category');
		
		$found_urls = $this->getArticlesUrls();
//		var_dump_print($found_urls);
		
		foreach($found_urls as $url)
		{
			$this->addArticle($url);
		}
	}

	private function getArticlesUrls()
	{
		$articles_urls = array();
		$url_articles_list = $GLOBALS['chosen_category_url'];
		while (count($articles_urls) < $GLOBALS['number_of_wanted_articles'] && "empty" != $url_articles_list)
		{
			$html_articles_list = getSimpleHTMLDOM($url_articles_list);
			if (0 === count($found_hrefs = $html_articles_list->find('ARTICLE.news-teaser A.news-teaser__link[href]')))
			{
				break;
			}
			else
			{
				foreach($found_hrefs as $href_element)
					if(isset($href_element->href)) $articles_urls[] = 'https://konkret24.tvn24.pl'.$href_element->href;
			}
			$url_articles_list = $this->getNextPageUrl($html_articles_list);
		}
		return array_slice($articles_urls, 0, $GLOBALS['number_of_wanted_articles']);
	}

	private function getNextPageUrl($html_articles_list)
	{		
		$next_page_element = $html_articles_list->find('A.pagination__link.pagination__link--next', 0);
		if (FALSE === is_null($next_page_element) && $next_page_element->hasAttribute('href'))
		{
			$url = 'https://konkret24.tvn24.pl'.$next_page_element->getAttribute('href');
			$url = strtolower($url);
			return $url;
		}
		else
			return "empty";
	}

	private function addArticle($url)
	{
		$article_html = getSimpleHTMLDOMCached($url, 86400 * 14);
		$article = $article_html->find('DIV.page-article DIV.article-content', 0);
		foreach($article_html->find('SCRIPT') as $script_element)
		{
			if (FALSE !== strpos($script_element, 'props'))
			{
				preg_match_all('/"date":"([^"]+)/', $script_element, $output_array);
				$date = $output_array[0][0];
				$date = str_replace('"date":"', '', $date);
			}
		}

		if (FALSE === is_null($title_element = $article_html->find('H2.article-content__title', 0)))
			$title = $title_element->plaintext;
		else
			$title = "";

		if (FALSE === is_null($title_element = $article_html->find('DIV.article-content__sources__type--author', 0)))
		{
			$author = $title_element->plaintext;
			$author = str_replace('Autor:', '', $author);
			$author = trim($author);
		}
		else
			$author = "";

		$tags = array();
		$tag = $GLOBALS['chosen_category_url'];
		$tag = str_replace('https://konkret24.tvn24.pl/', '', $tag);
		preg_match('/[a-z]*/', $tag, $output_array);
		$tag = $output_array[0];
		$tag = ucwords($tag);
		$tags[] = $tag;

		addStyle($article, 'DIV.article-content__inner-texts--quote', getStyleQuote());

		addStyle($article, 'DIV.article-content__inner-texts--video', getStylePhotoParent());
		addStyle($article, 'DIV.article-content__inner-texts--video__wrapper', getStylePhotoImg());
		addStyle($article, 'DIV.article-content__inner-texts--video__metadata', getStylePhotoCaption());

		addStyle($article, 'FIGURE.photo-figure', getStylePhotoParent());
		addStyle($article, 'IMG.photo-figure__image', getStylePhotoImg());
		addStyle($article, 'FIGCAPTION.photo-figure__caption', getStylePhotoCaption());

		deleteAllDescendantsIfExist($article, 'SCRIPT');
		deleteAllDescendantsIfExist($article, 'comment');
		deleteAllDescendantsIfExist($article, 'DIV.adoSlot');
		deleteAllDescendantsIfExist($article, 'DIV.share-container__position');
		
	
		$this->items[] = array(
			'uri' => $url,
			'title' => $title,
			'timestamp' => $date,
			'author' => $author,
			'categories' => $tags,
			'content' => $article
		);

	}
}
