<?php
class KulturaLiberalnaBridge extends FeedExpander {

	const MAINTAINER = 'No maintainer';
	const NAME = 'Kultura Liberalna';
	const URI = 'https://kulturaliberalna.pl/';
	const DESCRIPTION = 'No description provided';
	const CACHE_TIMEOUT = 86400;

	const PARAMETERS = array
	(
		'Parametry' => array
		(
			'limit' => array
			(
				'name' => 'Liczba artykułów',
				'type' => 'number',
				'required' => true,
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

    public function collectData(){
		include 'myFunctions.php';
		$this->setGlobalArticlesParams();
        $this->collectExpandableDatas('https://kulturaliberalna.pl/feed/');
    }

	private function setGlobalArticlesParams()
	{
		if (TRUE === $this->getInput('include_not_downloaded'))
			$GLOBALS['include_not_downloaded'] = TRUE;
		else
			$GLOBALS['include_not_downloaded'] = FALSE;
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
		$article_page = getSimpleHTMLDOMCached($item['uri'], 86400 * 14);
		$article_post = $article_page->find('ARTICLE[id^="post-"]', 0);
		
		$selectors_array[] = 'script';
		$selectors_array[] = 'DIV.kl-10lat-box';
		$selectors_array[] = 'DIV.go-to-comments';
		$selectors_array[] = 'DIV.nr-info';
		$selectors_array[] = 'DIV.more-in-number-container';
		$selectors_array[] = 'DIV.fb-comm';
		$selectors_array[] = 'P.section-name.mobile-section-name';
		//https://kulturaliberalna.pl/2021/01/12/cena-osobnosci-nie-jest-wysoka-na-razie/
		$selectors_array[] = 'DIV.promobox';
		$article = foreach_delete_element_array($article_post, $selectors_array);
		$tags = return_tags_array($article_post, 'DIV.post-tags A');
		$author = return_authors_as_string($article_post, 'DIV.article-footer H2');

		foreach($article_post->find('DIV[id^="attachment_"]') as $attachment_element)
		{
			if(isset($attachment_element->style)) $attachment_element->style = "";
		}

		foreach($article_post->find('IMG') as $photo_element)
		{
			if(isset($photo_element->width)) $photo_element->width = NULL;
			if(isset($photo_element->height)) $photo_element->height = NULL;
			if(isset($photo_element->srcset)) $photo_element->srcset = NULL;
			if(isset($photo_element->sizes)) $photo_element->sizes = NULL;
		}
//https://kulturaliberalna.pl/2021/01/26/dlaczego-maly-sklepik-jest-otwarty-a-duza-restauracja-zamknieta-latwiej-zarazic-sie-w-sklepiku/
		$article_post = add_style($article_post, 'blockquote', getStyleQuote());
		$article_post = add_style($article_post, 'DIV[id^="attachment_"]', getStylePhotoParent());
		$article_post = add_style($article_post, 'IMG[class^="wp-image-"]', getStylePhotoImg());
		$article_post = add_style($article_post, 'P.wp-caption-text', getStylePhotoCaption());
		$str = $article_post->save();
		$article_post = str_get_html($str);

//		if (TRUE === $GLOBALS['my_debug'])
//			echo "<br>Wszystkie {$GLOBALS['all_articles_counter']} artykulow zajelo {$GLOBALS['all_articles_time']}, <br>średnio ".$GLOBALS['all_articles_time']/$GLOBALS['all_articles_counter'] ."<br>";
		$item['categories'] = $tags;
		$item['author'] = $author;
		$item['content'] = $article_post;
		return $item;
	}

}
// Imaginary empty line!