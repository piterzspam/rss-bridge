<?php
class NewsweekBridge extends BridgeAbstract {
	const NAME = 'Newsweek Autor';
	const URI = 'https://www.newsweek.pl/autorzy/';
	const DESCRIPTION = 'No description provided';
	const MAINTAINER = 'No maintainer';
	const CACHE_TIMEOUT = 3600; // Can be omitted!

	const PARAMETERS = array
	(
		'Tekst pogrubiony' => array
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
			),
		)
	);
	public function getIcon()
	{
		return 'https://play-lh.googleusercontent.com/cLc9eiCOd37r4ikOCFMSyJQm0NYWU5DOCaWuF1FUJwrq-Gfuud3sqqJ5GkvV8zLFKGH1';
	}

	public function collectData()
	{
		$url = $this->getInput('url');
		$author = preg_replace('/.*\/autorzy\/([a-z]+)-([a-z]+).*/', '$1 $2', $url);
		$url = preg_replace('/(.*\/autorzy\/[a-z]+-[a-z]+).*/', '$1', $url);
		$author = ucwords($author);
		$wanted_number_of_articles = $this->getInput('wanted_number_of_articles');
		
		$urls = array();
		$html = getSimpleHTMLDOM($url);
		foreach($html->find('DIV.authorArticles DIV.smallItem A') as $A)
		{
			if (count($urls) < $wanted_number_of_articles)
				$urls[] = $A->getAttribute('href');
		}

		$page_number = 0;
		while (count($urls) < $wanted_number_of_articles)
		{
			$current_url = $url.'?ajax=1&page='.$page_number;
			$html = getSimpleHTMLDOM($current_url);
//			echo '<br><br>html:<br>';
//			echo $html;

			if (0 !== ($url_counter = count($found_urls = $html->find("DIV.smallItem A"))))
			{
				foreach($found_urls as $article__link)
				{
					$link = $article__link->getAttribute('href');
					if (count($urls) < $wanted_number_of_articles && FALSE === in_array($link, $urls))
						$urls[] = $link;
				}
				$page_number++;
			}
			else
			{
				break;
			}
		}
//		echo 'urls<br>';
//		echo '<pre>'.var_export($urls, true).'</pre>';

		foreach($urls as $url)
		{

			$html = file_get_html($url);
			if (FALSE === is_null($html->find('ARTICLE', 0)))
			{
				$article = $html->find('ARTICLE', 0);
			}
			else
			{
				break;
			}

			//zamiana ramek na linki
			foreach($article->find('DIV.embeddedApp DIV[data-run-module]') as $data_run_module)
			{
				$data_params = $data_run_module->getAttribute('data-params');
				$hrml_decoded = html_entity_decode($data_params);
				$params_decoded=json_decode($hrml_decoded, true);
				$link = $this->redirectUrl($params_decoded['parameters']['url']);
				$link_element = str_get_html('<a href="'.$link.'">'.$link.'</a>');
				$data_run_module->outertext = $link_element->outertext;
			}
			foreach($article->find('DIV.embeddedApp') as $embeddedApp)
			{
				$previous = $embeddedApp->previousSibling();
				if ($previous->tag === 'script')
				{
					$previous->outertext = '';
				}
			}
			foreach($article->find('SPAN') as $span)
			{
				while (FALSE === is_null($previous = $span->prev_sibling()))
				{
					if($previous->tag === 'span')
					{
						$next_text=trim($span->innertext);
						$previous_text=trim($previous->innertext);
						$new_text=trim($previous_text.' '.$next_text);
						$previous->innertext = $new_text;
						$span->outertext='';
						$span = $previous;
					}
					else
						break;
				}
				$span->innertext = trim($span->innertext);
				$span->setAttribute('style', 'display: block;');

			}
			//paragrafy, czytaj inne artykuly
			foreach($article->find('P') as $paragraph)
			{
				$paragraph->innertext = trim($paragraph->innertext);
				$this->deleteAncestorIfContainsText($paragraph, 'Czytaj też: ');
				$this->deleteAncestorIfContainsText($paragraph, 'Czytaj także: ');
				$this->deleteAncestorIfContainsText($paragraph, 'Zobacz też: ');
			}
			//Przenoszenie tresci premium poziom wyzej
			if (FALSE === is_null($offerView = $article->find('DIV.offerView', 0)) && FALSE === is_null($article->find('DIV.contentPremium', 0)))
			{
				$offerView = $article->find('DIV.offerView', 0);
				foreach($article->find('DIV.contentPremium', 0)->childNodes() as $element)
				{
					$offerView->outertext = $offerView->outertext.$element->outertext;
				}
				$this->deleteDescendantIfExists($article, 'DIV.contentPremium');
			}
			//tags
			$tags = array();
			foreach($article->find('DIV.tags', 0)->find('A') as $tag_element)
			{
				$tags[] = trim($tag_element->plaintext);
				$tag_element->outertext = '';
			}
			//date
			$date = $article->find('SPAN.articleDates', 0)->innertext;
			$date = str_replace('Data publikacji: ', '', $date);
			//title
			$title = trim($article->find('H1.detailTitle', 0)->innertext);
			if (FALSE === is_null($html->find('H1.detailTitle', 0)->find('SPAN.label', 0)))
			{
				$title = '[OPINIA] '.$title;
			}

			$this->deleteAllDescendantsIfExist($article, 'comment');
			$this->deleteAllDescendantsIfExist($article, 'script');
			$this->deleteAllDescendantsIfExist($article, 'DIV.articleSocials');
			$this->deleteAllDescendantsIfExist($article, 'DIV.detailFeed');
			$this->deleteAllDescendantsIfExist($article, 'DIV.bottomArtticleAds');
			$this->deleteAllDescendantsIfExist($article, 'DIV.onet-ad');
			$this->deleteAllDescendantsIfExist($article, 'DIV#fbComments');
			$this->deleteAllDescendantsIfExist($article, 'UL.breadCrumb');
			$this->deleteAllDescendantsIfExist($article, 'DIV.tags');


			$this->items[] = array(
				'uri' => $url,
				'title' => $title,
				'timestamp' => $date,
				'author' => $author,
				'content' => $article,
				'categories' => $tags
			);

//			echo '<br><br><br>article<br>';
//			echo $article;
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

	private function deleteAllDescendantsIfExist($ancestor, $descendant_string)
	{
		foreach($ancestor->find($descendant_string) as $descendant)
			$descendant->outertext = '';
	}

	private function redirectUrl($url)
	{
		$twitter_proxy = 'nitter.net';
		$instagram_proxy = 'bibliogram.art';
		$facebook_proxy = 'mbasic.facebook.com';
		$url = preg_replace('/.*[\.\/]twitter\.com(.*)/', 'https://'.$twitter_proxy.'${1}', $url);
		$url = preg_replace('/.*[\.\/]instagram\.com(.*)/', 'https://'.$instagram_proxy.'${1}', $url);
		$url = preg_replace('/.*[\.\/]facebook\.com(.*)/', 'https://'.$facebook_proxy.'${1}', $url);
		return $url;
	}
}