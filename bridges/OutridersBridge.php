<?php
	
class OutridersBridge extends FeedExpander {

	const MAINTAINER = 'No maintainer';
	const NAME = 'Outriders Brief';
	const URI = 'https://outride.rs/';
	const DESCRIPTION = 'No description provided';
	const CACHE_TIMEOUT = 86400;

	const PARAMETERS = array
	(
		'Brief' => array
		(
			'limit' => array
			(
				'name' => 'Liczba briefów',
				'type' => 'number',
				'required' => true,
				'title' => 'Liczba briefów',
				'defaultValue' => 3,
			)
		),
		'Rss' => array
		(
			'limit' => array
			(
				'name' => 'Liczba artykułów',
				'type' => 'number',
				'required' => true,
				'title' => 'Liczba artykułów',
				'defaultValue' => 3,
			),
			'include_not_downloaded' => array
			(
				'name' => 'Uwzględnij niepobrane',
				'type' => 'checkbox',
				'required' => false,
				'title' => 'Uwzględnij niepobrane'
			),
		)
	);

	public function getName()
	{
		switch($this->queriedContext)
		{
			case 'Brief':
				return "Outriders - Briefy";
				break;
			case 'Rss':
				return "Outriders - Artykuły";
				break;
			default: return parent::getName();
		}
	}

	public function getIcon()
	{
		return 'https://outride.rs/wp-content/themes/outriders2/assets/images/favicons/favicon-196x196.png';
	}

	public function collectData()
	{
		$GLOBALS['name'] = "test1";
		include 'myFunctions.php';
		$GLOBALS['my_debug'] = FALSE;
		//$GLOBALS['my_debug'] = TRUE;
		if (TRUE === $GLOBALS['my_debug'])
		{
			$GLOBALS['all_articles_time'] = 0;
			$GLOBALS['all_articles_counter'] = 0;
		}
		switch($this->queriedContext)
		{
			case 'Brief':
				$this->getArticlesBriefs();
				break;
			case 'Rss':
				$this->getArticlesRss();
				break;
		}		
		if (TRUE === $GLOBALS['my_debug'])
			echo "<br>Wszystkie {$GLOBALS['all_articles_counter']} artykulow zajelo {$GLOBALS['all_articles_time']}, <br>średnio ".$GLOBALS['all_articles_time']/$GLOBALS['all_articles_counter'] ."<br>";

	}


	private function getArticlesBriefs()
	{
		$GLOBALS['limit'] = $this->getInput('limit');
		$articles_urls = array();

		$returned_array = my_get_html('https://brief.outride.rs/pl');
		if (200 === $returned_array['code'])
		{
			$html_issues_list = $returned_array['html'];
		}
		else if (200 !== $returned_array['code'])
		{
			$this->items[] = $returned_array['html'];
			return;
		}

		if (0 === count($found_issues_hrefs = $html_issues_list->find('DIV.issues A.issue-box[href]')))
		{
			return $articles_urls;
		}
		else
		{
			$issues_urls = array();
			foreach($found_issues_hrefs as $href_element)
			{
				$issues_urls[] = 'https://brief.outride.rs'.$href_element->href;
			}
		}
		$issues_urls = array_slice($issues_urls, 0, $GLOBALS['limit']);

		foreach($issues_urls as $issue_url)
		{
			$returned_array = my_get_html($issue_url);
			if (200 !== $returned_array['code'])
			{
				$this->items[] = $returned_array['html'];
				return;
			}
			$html_articles_list = $returned_array['html'];
			if (0 !== count($found_articles_hrefs = $html_articles_list->find('UL.issue-nav LI.issue-nav__item A.issue-nav__link[href]')))
			{
				$date = "";
				if (FALSE === is_null($data_script = $html_articles_list->find('DIV[id$="nuxt"] + SCRIPT', 0)))
				{
					preg_match('/[0-9]{4}-[0-9]{2}-[0-9]{2}T[0-9]{2}:[0-9]{2}:[0-9]{2}\+[0-9]{2}:[0-9]{2}/', $data_script->outertext, $output_array);
					$date = $output_array[0];
				}
				$cover_outertext = "";
				if (FALSE === is_null($cover_element = $html_articles_list->find('DIV.intro__cover', 0)))
				{
					$cover_outertext = $cover_element->outertext;
				}
				foreach($found_articles_hrefs as $href_element)
				{
					if(isset($href_element->href))
					{
						$articles_urls[] = array
						(
							'url' => 'https://brief.outride.rs'.$href_element->href,
							'date' => $date,
							'cover' => $cover_outertext,
						);
					}
				}
			}
		}
		foreach($articles_urls as $url_array)
		{
			$this->addArticleBrief($url_array['url'], $url_array['date'], $url_array['cover']);
		}
	}

	private function addArticleBrief($url, $date, $cover_code)
	{
		$returned_array = my_get_html($url);
		if (200 !== $returned_array['code'])
		{
			$this->items[] = $returned_array['html'];
			return;
		}

		$article_html = $returned_array['html'];
		$article = $article_html->find('ARTICLE[id].post', 0);
		insert_html($article, "HEADER", NULL, $cover_code);
		$article = str_get_html($article->save());

		//tytuł
		$title = get_text_plaintext($article, 'H1.post__title', $url);
		preg_match('/brief-[0-9]*/', $url, $output_array);
		$brief_number = ucwords($output_array[0]);
		$title = $brief_number.': '.$title;

		//tagi
		$tags = return_tags_array($article, 'DIV.categories A[href]');
		$tags = array_merge(array($brief_number), $tags);
		foreach_delete_element($article, 'DIV.categories');

		$this->items[] = array(
			'uri' => $url,
			'title' => $title,
			'timestamp' => $date,
			'author' => 'Outriders',
			'categories' => $tags,
			'content' => $article
		);
	}

	private function getArticlesRss()
	{
		if (TRUE === $this->getInput('include_not_downloaded'))
			$GLOBALS['include_not_downloaded'] = TRUE;
		else
			$GLOBALS['include_not_downloaded'] = FALSE;
        $this->collectExpandableDatas('https://outride.rs/feed');
	}

	protected function parseItem($newsItem)
	{
		$item = parent::parseItem($newsItem);
		if (count($this->items) >= intval($this->getInput('limit')))
		{
			if (TRUE === $GLOBALS['include_not_downloaded'])
			{
				return $item;
			}
			else
			{
				return;
			}
		}
		$returned_array = my_get_html($item['uri']);
		if (200 !== $returned_array['code'])
		{
			$this->items[] = $returned_array['html'];
			return;
		}
		$article_html = $returned_array['html'];
		$article_html = str_get_html(prepare_article($article_html));
		//outride.rs##BODY.post-template > ARTICLE.gallery
		//https://outride.rs/pl/minsk-decentralizacja-protestu/
		//outride.rs##BODY > DIV#\5f _nuxt
		//https://outride.rs/pl/wspolnota-odpowiedz-na-wylesianie/
		//outride.rs##BODY.post-template > ARTICLE.article
		//https://outride.rs/pl/rezim-nie-lubi-tego-co-robimy/
		//$js_element = $article_html->find('DIV[id$="_nuxt"][data-server-rendered="true"], BODY.post-template-interactives', 0);
		$static_article = $article_html->find('BODY.post-template.post-template-single-new', 0);
		if (is_null($static_article))
		{
		//	echo "Artykul z js: ".$item['uri']."<br>";
			return $item;
		}
		else
		{
		//	echo "Artykul bez js: ".$item['uri']."<br>";
		}
		$article = $article_html->find('BODY.single-post ARTICLE', 0);
		move_element($article, 'H1.article__title', 'DIV.article__thumbnail', 'outertext', 'before');
		$article = str_get_html($article->save());
		move_element($article, 'DIV.article-date', 'DIV.article__thumbnail', 'outertext', 'after');
		$article = str_get_html($article->save());
		move_element($article, 'DIV.article-author', 'DIV.article__text', 'outertext', 'after');
		$article = str_get_html($article->save());
		insert_html($article, "DIV.article-author", "<HR>");
		$article = str_get_html($article->save());
		

		$selectors_array[] = 'SCRIPT';
		$selectors_array[] = 'NOSCRIPT';
		$selectors_array[] = 'LINK';
		$selectors_array[] = 'DIV.context-modal';
		$selectors_array[] = 'AUDIO.or-player';
		$selectors_array[] = 'qqqqqqqq';
		$selectors_array[] = 'qqqqqqqq';
		$selectors_array[] = 'qqqqqqqq';
		foreach_delete_element_array($article, $selectors_array);
		//FIGCAPTION
		format_article_photos($article, 'DIV.article__thumbnail', TRUE);
		format_article_photos($article, 'FIGURE.wp-block-image', FALSE, 'src', 'FIGCAPTION');
		//https://outride.rs/pl/konflikt-w-gorskim-karabachu/
		format_article_photos($article, 'DIV.gallery-photo', FALSE, 'src', 'DIV.gallery-photo__text.text-under');

		$article = str_get_html($article->save());
		add_style($article, 'FIGURE.photoWrapper', getStylePhotoParent());
		add_style($article, 'FIGURE.photoWrapper IMG', getStylePhotoImg());
		add_style($article, 'FIGCAPTION', getStylePhotoCaption());
		add_style($article, 'BLOCKQUOTE', getStyleQuote());
		$article = str_get_html($article->save());

		$item['content'] = $article;
		return $item;
	}
}
// Imaginary empty line!