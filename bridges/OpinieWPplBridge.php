<?php
class OpinieWPplBridge extends BridgeAbstract {
	const NAME = 'Opinie WP.pl - strona autora';
	const URI = 'https://opinie.wp.pl/';
	const DESCRIPTION = 'No description provided';
	const MAINTAINER = 'No maintainer';
	const CACHE_TIMEOUT = 1; // Can be omitted!

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
				'type' => 'text',
				'required' => true
			)
		)
	);



	public function collectData()
	{
		include 'myFunctions.php';
		$author_page_url = $this->getInput('url');
		$GLOBALS['number_of_wanted_articles'] = $this->getInput('wanted_number_of_articles');
		$GLOBALS['my_debug'] = FALSE;
//		$GLOBALS['my_debug'] = TRUE;
		if (TRUE === $GLOBALS['my_debug'])
		{
			$GLOBALS['all_articles_time'] = 0;
			$GLOBALS['all_articles_counter'] = 0;
		}
		
		$urls = array();
		$url_articles_list = $author_page_url;
		$page_number = 1;
		while (count($this->items) < $GLOBALS['number_of_wanted_articles'])
		{
			$html_main_page = getSimpleHTMLDOM($url_articles_list);
			$articles_list_elements = $html_main_page->find('DIV[data-st-area="list-topic"]', 0)->first_child()->first_child();

			foreach($articles_list_elements->childNodes() as $articles_list_element)
			{
				if (count($this->items) < $GLOBALS['number_of_wanted_articles'])
				{
					if (FALSE === is_null($articles_list_element->find('A', 0)))
					{
						$href = $articles_list_element->find('A', 0)->getAttribute('href');
						$amp_url = $this->getCustomizedLink($href);
						$urls[] = $amp_url;
						$this->addArticle($amp_url);
					}
				}
			}
			$page_number++;
			$url_articles_list = $url_articles_list.'/'.$page_number;
		}
		if (TRUE === $GLOBALS['my_debug'])
			echo "<br>Wszystkie {$GLOBALS['all_articles_counter']} artykulow zajelo {$GLOBALS['all_articles_time']}, <br>średnio ".$GLOBALS['all_articles_time']/$GLOBALS['all_articles_counter'] ."<br>";
	}

	private function addArticle($url_article)
	{
		if (TRUE === $GLOBALS['my_debug'])
		{
			$start_request = microtime(TRUE);
			$article_html = getSimpleHTMLDOMCached($url_article, (864000/(count($this->items)+1)*$GLOBALS['number_of_wanted_articles']));
			$end_request = microtime(TRUE);
			echo "<br>Article  took " . ($end_request - $start_request) . " seconds to complete - url: $url_article.";
			$GLOBALS['all_articles_counter']++;
			$GLOBALS['all_articles_time'] = $GLOBALS['all_articles_time'] + $end_request - $start_request;
		}
		else
			$article_html = getSimpleHTMLDOMCached($url_article, (864000/(count($this->items)+1)*$GLOBALS['number_of_wanted_articles']));

		$article = $article_html->find('main#content', 0);
		$article_data = $article_html->find('SCRIPT[type="application/ld+json"]', 0)->innertext;
		$article_data_parsed = parse_article_data(json_decode($article_data));
		$date = trim($article_data_parsed["datePublished"]);
		$title = trim($article_data_parsed["headline"]);
		$author = $article->find('P[data-st-area="Autor"] SPAN.uppercase', 0)->plaintext;
		$author = str_replace(',', '', $author);
		$tags = array();
		foreach($article->find('P.tags', 0)->find('A[href*="/tag/"]') as $tag_link)
			$tags[] = trim($tag_link->plaintext);

		$article = fixAmpArticles($article);
		deleteAllDescendantsIfExist($article, 'DIV.ad');
		deleteAllDescendantsIfExist($article, 'P.tags');
		deleteAllDescendantsIfExist($article, 'comment');
		deleteAllDescendantsIfExist($article, 'SECTION.recommendations');
		deleteAllDescendantsIfExist($article, 'DIV.seolinks');
		deleteAllDescendantsIfExist($article, 'A.comment-button');
		deleteAllDescendantsIfExist($article, 'FOOTER#footer');
		deleteAllDescendantsIfExist($article, 'amp-video-iframe');
		deleteAncestorIfContainsTextForEach($article, 'P', array( 'Masz newsa, zdjęcie lub filmik? Prześlij nam przez', 'dla WP Opinie', 'Zobacz też: ', 'Źródło: opinie.wp.pl'));
		$this->removeVideoTitles($article);
		$photo_author_style = array(
			'transform-origin: right;',
			'transform: rotate(270deg);',
			'position: relative;',
			'z-index: 2;',
			'color: #fff;',
			'right: 15px;',
			'text-align: right;',
			'font-size: 14px;',
			'width: 100%;'
		);
		addStyle($article, 'DIV.header-image-container DIV.header-author', $photo_author_style);
		formatAmpLinks($article);



		$url_article = str_replace('https://opinie-wp-pl.cdn.ampproject.org/v/s/', 'https://', $url_article);
		$this->items[] = array(
			'uri' => $url_article,
			'title' => $title,
			'timestamp' => $date,
			'author' => $author,
			'categories' => $tags,
			'content' => $article
		);
	}

	private function removeVideoTitles($article)
	{
		foreach($article->find('amp-video-iframe') as $amp_video_iframe)
		{
			$previous = $amp_video_iframe->prev_sibling();
			if ('div' === $previous->tag)
			{
				$attributes=$previous->getAllAttributes();
				if ('ad' === $attributes['class'])
				{
					$previous_second = $previous->prev_sibling();
					$previous->outertext='';
					if ('h2' === $previous_second->tag)
					{
						$previous_second->outertext='';
					}
				}
			}
			else
			{
				if ('h2' === $previous->tag)
				{
					$previous->outertext='';
				}
			}
		}
	}

	private function getCustomizedLink($url)
	{
		$url = 'https://opinie.wp.pl'.$url;
		$new_url = $url.'?amp=1&amp_js_v=0.1';
		$new_url = str_replace('https://', 'https://opinie-wp-pl.cdn.ampproject.org/v/s/', $new_url);
		return $new_url;
	}
}