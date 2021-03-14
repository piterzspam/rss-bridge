<?php
class TygodnikPowszechnyBridge extends FeedExpander {

	const MAINTAINER = 'No maintainer';
	const NAME = 'Tygodnik Powszechny';
	const URI = 'https://www.tygodnikpowszechny.pl/';
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
        $this->collectExpandableDatas('https://www.tygodnikpowszechny.pl/rss.xml');
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
		$article_post = $article_page->find('DIV.view-full-article', 0);
		deleteAllDescendantsIfExist($article_post, 'DIV.views-field.views-field-body-1');

		$article_post = str_get_html($article_post->save());

		$this->fix_main_photo($article_post);
		$this->fix_article_photos($article_post);

		$lead_style = array(
			'font-weight: bold;'
		);
		addStyle($article_post, 'DIV.views-field-field-summary', $lead_style);
		
		$item['content'] = $article_post;
		return $item;
	}



	private function fix_main_photo($article)
	{
		if (FALSE === is_null($main_image = $article->find('DIV.views-field.views-field-field-zdjecia DIV.field-content IMG[src^="http"]', 0)))
		{
			if (FALSE === is_null($image_caption = $article->find('DIV.views-field.views-field-field-zdjecia-2 DIV.field-content', 0)))
			{
				$caption_text = trim($image_caption->plaintext);
				$image_caption->parent->outertext = '';
			}
			$img_src = "";
			if($main_image->hasAttribute('src'))
				$img_src = $main_image->getAttribute('src');

			if (0 === strlen($caption_text))
				$new_element = str_get_html('<figure class="photoWrapper mainPhoto"><img src="'.$img_src.'"></figure>');
			else
				$new_element = str_get_html('<figure class="photoWrapper mainPhoto"><img src="'.$img_src.'"><figcaption>'.$caption_text.'</figcaption></figure>');

			$new_element_img = $new_element->find('IMG', 0);
			$img_alt = "";
			if($main_image->hasAttribute('alt'))
				$img_alt = trim($main_image->getAttribute('alt'));
			$img_title = "";
			if($main_image->hasAttribute('title'))
				$img_title = trim($main_image->getAttribute('title'));
			if (0 === strlen($img_alt))
				$new_element_img->setAttribute('alt', $img_alt);
			if (0 === strlen($img_title))
				$new_element_img->setAttribute('title', $img_title);
			
			$main_image->parent->parent->outertext = $new_element;
			
//			element_print($main_image, "main_image", "<br>");
//			element_print($new_element, "new_element", "<br>");
//			element_print($date, "date_element", "<br>");
//			var_dump_print($date);
		}
	}

	private function fix_article_photos($article_post)
	{
		foreach($article_post->find('DIV[id^="attachment_"]') as $article_element)
		{
			if (FALSE === is_null($photo_element = $article_element->find('IMG[src^="http"]', 0)))
			{
				if (FALSE === is_null($caption_element = $article_element->find('P.wp-caption-text', 0)))
				{
					$caption_text = trim($caption_element->plaintext);
				}
				$img_src = "";
				$img_src = $photo_element->getAttribute('src');

				$img_alt = "";
				if($photo_element->hasAttribute('alt'))
					$img_alt = trim($photo_element->getAttribute('alt'));

				if (0 === strlen($img_alt) && 0 === strlen($caption_text))
					$new_outertext = '<figure class="photoWrapper photo"><img src="'.$img_src.'"></figure>';
				else if (0 === strlen($img_alt) && 0 !== strlen($caption_text))
					$new_outertext = '<figure class="photoWrapper photo"><img src="'.$img_src.'"><figcaption>'.$caption_text.'</figcaption></figure>';
				else if (0 !== strlen($img_alt) && 0 === strlen($caption_text))
					$new_outertext = '<figure class="photoWrapper photo"><img src="'.$img_src.'" alt="'.$img_alt.'"></figure>';
				else if (0 !== strlen($img_alt) && 0 !== strlen($caption_text))
					$new_outertext = '<figure class="photoWrapper photo"><img src="'.$img_src.'" alt="'.$img_alt.'"><figcaption>'.$caption_text.'</figcaption></figure>';
				$article_element->outertext = $new_outertext;
			}
			if (FALSE === is_null($href_element = $article_element->find('A[href]', 0)))
			{
				$href = $href_element->getAttribute('href');
				if (0 === strlen($img_alt) && 0 === strlen($caption_text))
					$new_outertext = '<figure class="photoWrapper photo"><a href="'.$href.'"><img src="'.$img_src.'"></a></figure>';
				else if (0 === strlen($img_alt) && 0 !== strlen($caption_text))
					$new_outertext = '<figure class="photoWrapper photo"><a href="'.$href.'"><img src="'.$img_src.'"></a><figcaption>'.$caption_text.'</figcaption></figure>';
				else if (0 !== strlen($img_alt) && 0 === strlen($caption_text))
					$new_outertext = '<figure class="photoWrapper photo"><a href="'.$href.'"><img src="'.$img_src.'" alt="'.$img_alt.'"></a></figure>';
				else if (0 !== strlen($img_alt) && 0 !== strlen($caption_text))
					$new_outertext = '<figure class="photoWrapper photo"><a href="'.$href.'"><img src="'.$img_src.'" alt="'.$img_alt.'"></a><figcaption>'.$caption_text.'</figcaption></figure>';
				$article_element->outertext = $new_outertext;
			}
		}
	}

}
// Imaginary empty line!