<?php
class GazetaplBridge extends BridgeAbstract {
	const NAME = 'Gazeta.pl - strona autora';
	const URI = 'https://wiadomosci.gazeta.pl/wiadomosci/0,114871.html';
	const DESCRIPTION = 'No description provided';
	const MAINTAINER = 'No maintainer';
	const CACHE_TIMEOUT = 86400; // Can be omitted!

	const PARAMETERS = array
	(
		'Parametry' => array
		(
			'url' => array
			(
				'name' => 'URL',
				'type' => 'text',
				'required' => true
			),
			'wanted_number_of_articles' => array
			(
				'name' => 'Liczba artykułów',
				'type' => 'number',
				'required' => true
			),
		)
	);

	public function collectData()
	{
		include 'myFunctions.php';
//		Warning: https://wiadomosci.gazeta.pl/wiadomosci/7,114884,26406112,dr-hab-wigura-najwiekszym-problemem-nowej-solidarnosci-jest.html
//		Twitter frame: https://wiadomosci.gazeta.pl/wiadomosci/7,114884,25947207,trzaskowski-za-kidawe-blonska-kiedys-bylo-tusku-musisz.html
//		error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
//		error_reporting(E_ALL & ~E_WARNING);
		$url_articles_list = $this->getInput('url');
		$GLOBALS['number_of_wanted_articles'] = $this->getInput('wanted_number_of_articles');
		while (count($this->items) < $GLOBALS['number_of_wanted_articles'])
		{
			$html_articles_list = getSimpleHTMLDOM($url_articles_list);
			if (0 !== count($found_urls = $html_articles_list->find('LI.entry')))
			{
				foreach($found_urls as $article_link)
				{
					if (count($this->items) < $GLOBALS['number_of_wanted_articles'])
					{
						$href = $article_link->find('A', 0)->getAttribute('href');
						$this->addArticle($href);
					}
				}
			}
			else
			{
				break;
			}

			if (FALSE === is_null($html_articles_list->find('A.next', 0)))
				$url_articles_list = $html_articles_list->find('A.next', 0)->getAttribute('href');
			else
				break;
		}
	}

	private function addArticle($url_article)
	{
//		$article_html = getSimpleHTMLDOMCached($url_article, (86400/(count($this->items)+1)*$GLOBALS['number_of_wanted_articles']));
		$article_html = getSimpleHTMLDOMCached($url_article, 86400 * 14);
		if (is_bool($article_html))
		{
			$this->items[] = array(
				'uri' => $url_article,
				'title' => "file_get_html($url_article) jest boolem $article_html",
				'timestamp' => '',
				'author' => '',
				'content' => '',
				'categories' => ''
			);
			return;
		}
		
		$article = $article_html->find('SECTION#article_wrapper', 0);

		$title = trim($article->find('H1#article_title', 0)->plaintext);
		$timestamp = trim($article->find('TIME', 0)->getAttribute('datetime'));
		$author = trim($article->find('A[rel="author"]', 0)->plaintext);
		$tags = returnTagsArray($article, 'LI.tags_item');

		deleteAllDescendantsIfExist($article, 'comment');
		deleteAllDescendantsIfExist($article, 'SCRIPT');
		deleteAllDescendantsIfExist($article, 'DIV[id^="banC"]');

		deleteDescendantIfExists($article, 'DIV#sitePath');
		deleteDescendantIfExists($article, 'DIV.left_aside');
		deleteDescendantIfExists($article, 'DIV.ban000_wrapper');
		deleteDescendantIfExists($article, 'DIV.ban001_wrapper');
		deleteDescendantIfExists($article, 'DIV.right_aside');
		deleteDescendantIfExists($article, 'DIV.top_section_bg');
		deleteDescendantIfExists($article, 'DIV.bottom_section_bg');
		deleteDescendantIfExists($article, 'DIV#adUnit-007-CONTENTBOARD');
		deleteDescendantIfExists($article, 'DIV.related_image_number_of_photo');
		deleteDescendantIfExists($article, 'DIV.related_image_open');
		deleteDescendantIfExists($article, 'SECTION.tags');
		clearParagraphsFromTaglinks($article, 'P.art_paragraph', array('/\?tag=/'));

		$interview_question_style = array(
			'font-weight: bold;'
		);
		addStyle($article, 'H4.art_interview_question', $interview_question_style);


		foreach($article->find('div.art_embed') as $art_embed)
		{
			deleteAncestorIfDescendantExists($art_embed, 'SCRIPT[src*="video.onnetwork.tv"]');
			
			if (FALSE === is_null($art_embed->find('A[href*="twitter.com/user/status/"]', 0)))
			{
				$twitter_url = $art_embed->find('a', 0)->getAttribute('href');
				$twitter_proxy_url = redirectUrl($twitter_url);
				$art_embed->outertext = 
					'<strong><br>'
					.'<a href='.$twitter_url.'>'
					."Ramka - ".$twitter_url.'<br>'
					.'</a>'
					.'<a href='.$twitter_proxy_url.'>'
					."Ramka - ".$twitter_proxy_url.'<br>'
					.'</a>'
					.'<br></strong>';
			}
		}
		

		$this->items[] = array(
			'uri' => $url_article,
			'title' => $title,
			'timestamp' => $timestamp,
			'author' => $author,
			'content' => $article,
			'categories' => $tags
		);
	}


}