<?php
class OSWBridge extends FeedExpander {

	const MAINTAINER = 'No maintainer';
	const NAME = 'Ośrodek Studiów Wschodnich';
	const URI = 'https://www.osw.waw.pl/';
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
        $this->collectExpandableDatas('https://www.osw.waw.pl/pl/rss.xml');
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
		$article = $article_page->find('ARTICLE.publikacje[role="article"]', 0);
		$this->fix_main_photo($article);
		foreach_delete_element($article, 'comment');
		foreach_delete_element($article, 'script');
		foreach_delete_element($article, 'NOSCRIPT');

		//https://www.osw.waw.pl/pl/publikacje/komentarze-osw/2021-03-02/przeciaganie-grenlandii-dania-usa-i-chiny-na-lodowej-wyspie
		$this->fix_article_photos_sources($article);
		fix_article_photos($article, 'IMG.inline-image', FALSE);
//		fix_article_photos($article, 'IMG.img-responsive', TRUE);
		fix_article_photos($article, 'IMG.img-responsive', FALSE);

		$tags = array();
		if (FALSE === is_null($tags_element = $article_page->find('DIV.field.field--name-taxonomy-vocabulary-9', 0)))
		{
			$tags_string = trim($tags_element->plaintext);
			$tags[] = $tags_string;
		}
		$date = "";
		if (FALSE === is_null($date_element = $article_page->find('META[property="article:published_time"][content]', 0)))
		{
//			print_element($date_element, "date_element", "<br>");
			$date = $date_element->getAttribute('content');
		}
		
		
		foreach($article->find('a') as $element)
		{
			$element_url = $element->getAttribute('href');
			if(strpos($element_url, '/') === 0)
			{
				$element_url = "https://www.osw.waw.pl".$element_url;
				$element->setAttribute('href', $element_url);
			}
		}


		$item['content'] = $article;
		$item['categories'] = $tags;
		if ("" !== $date)
		{
			$item['timestamp'] = $date;
		}
		return $item;
	}

	private function fix_main_photo($article)
	{
		foreach($article->find('DIV.main-image DIV.bencki[style^="background-image:url(\'http"]') as $article_element)
		{
			$style_string = $article_element->getAttribute('style');
			$style_string = str_replace('background-image:', '', $style_string);
			$style_string = trim($style_string);
			$style_string = remove_substring_if_exists_first($style_string, 'url(');
			$style_string = remove_substring_if_exists_last($style_string, ');');
			$style_string = trim($style_string);
			$img_src = $style_string;
			$img_src = str_replace('\'', '', $img_src);
			$new_outertext = '<figure class="photoWrapper mainPhoto"><img src="'.$img_src.'"></figure>';
			$article_element->parent->outertext = $new_outertext;
		}
	}

	private function fix_article_photos_sources($article)
	{
		foreach($article->find('IMG[srcset][src^="/"]') as $photo_element)
		{
			$img_src = $photo_element->getAttribute('src');
			$img_src = str_replace('-300x200', '', $img_src);
			if($photo_element->hasAttribute('srcset'))
			{
				$img_srcset = $photo_element->getAttribute('srcset');
				$srcset_array  = explode(',', $img_srcset);
				$last = count($srcset_array) - 1;
				$last_url_string = trim($srcset_array[$last]);
				$last_url_array  = explode(' ', $last_url_string);
				$img_src = 'https://www.osw.waw.pl'.$last_url_array[0];
			}
			$photo_element->setAttribute('src', $img_src);
		}
	}

}
// Imaginary empty line!