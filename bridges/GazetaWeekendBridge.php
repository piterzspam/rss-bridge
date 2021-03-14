<?php
class GazetaWeekendBridge extends BridgeAbstract {
	const NAME = 'Weekend Gazeta.pl';
	const URI = 'https://weekend.gazeta.pl/weekend/0,0.html';
	const DESCRIPTION = 'No description provided';
	const MAINTAINER = 'No maintainer';
	const CACHE_TIMEOUT = 86400; // Can be omitted!

	const PARAMETERS = array
	(
		'Parametry' => array
		(
			'limit' => array
			(
				'name' => 'Liczba artykułów',
				'type' => 'number',
				'required' => true
			)
		)
	);
/*
	public function getIcon()
	{
		return 'https://c.disquscdn.com/uploads/forums/349/4323/favicon.png';
	}
*/
	public function collectData()
	{
		include 'myFunctions.php';
		$GLOBALS['limit'] = $this->getInput('limit');
		$GLOBALS['my_debug'] = FALSE;
//		$GLOBALS['my_debug'] = TRUE;
		if (TRUE === $GLOBALS['my_debug'])
		{
			$GLOBALS['all_articles_time'] = 0;
			$GLOBALS['all_articles_counter'] = 0;
		}
		
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
		$url_articles_list = 'https://weekend.gazeta.pl/weekend/0,0.html';
		while (count($articles_urls) < $GLOBALS['limit'] && "empty" != $url_articles_list)
		{
			$html_articles_list = getSimpleHTMLDOM($url_articles_list);
			if (0 === count($found_hrefs = $html_articles_list->find('DIV.title A[href]')))
			{
				break;
			}
			else
			{
				foreach($found_hrefs as $href_element)
					if(isset($href_element->href)) $articles_urls[] = $href_element->href;
			}
			$url_articles_list = $this->getNextPageUrl($html_articles_list);
		}
		return array_slice($articles_urls, 0, $GLOBALS['limit']);
	}

	private function getNextPageUrl($html_articles_list)
	{		
		$next_page_element = $html_articles_list->find('A.next', 0);
		if (FALSE === is_null($next_page_element) && $next_page_element->hasAttribute('href'))
		{
			return 'https://weekend.gazeta.pl'.$next_page_element->getAttribute('href');
		}
		else
			return "empty";
	}

	private function addArticle($url)
	{
		$article_html = getSimpleHTMLDOMCached($url, 86400 * 14);
		$article = $article_html->find('DIV#gazeta_article', 0);

		if (FALSE === is_null($title_element = $article_html->find('DIV.gazeta_article_header H1', 0)))
			$title = $title_element->plaintext;
		else
			$title = "";

		if (FALSE === is_null($title_element = $article_html->find('DIV#gazeta_article_author SPAN[itemprop="author"]', 0)))
			$author = $title_element->plaintext;
		else
			$author = "";

		if (FALSE === is_null($date_element = $article_html->find('DIV#gazeta_article[data-pub]', 0)))
			$date = $date_element->getAttribute('data-pub');
		else
			$date = "";

		$tags = returnTagsArray($article_html, 'DIV.keyTag');

		addStyle($article, 'H6', getStyleQuote());

		addStyle($article, 'figure', getStylePhotoParent());
		addStyle($article, 'img', getStylePhotoImg());
		addStyle($article, 'figcaption', getStylePhotoCaption());
		//Fix podpisów pod zdjęciami
		deleteAllDescendantsIfExist($article, 'comment');
		deleteAllDescendantsIfExist($article, 'SCRIPT');
		deleteAllDescendantsIfExist($article, 'DIV#sitePath');
		deleteAllDescendantsIfExist($article, 'DIV#article_comments');
		deleteAllDescendantsIfExist($article, 'DIV.relatedHolder');
		deleteAllDescendantsIfExist($article, 'DIV.shortSocialBar');
		deleteAllDescendantsIfExist($article, 'DIV#adUnit-007-CONTENTBOARD');
		deleteAllDescendantsIfExist($article, 'DIV.noApp');
		
		
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
