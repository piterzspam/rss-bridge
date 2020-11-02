<?php
class OnetBridge extends BridgeAbstract {
	const NAME = 'Onet Autor';
	const URI = 'https://wiadomosci.onet.pl/autorzy/';
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
	public function collectData()
	{
		$url = $this->getInput('url');
		$author = preg_replace('/.*\/autorzy\/([a-z]+)-([a-z]+).*/', '$1 $2', $url);
		$url = preg_replace('/(.*\/autorzy\/[a-z]+-[a-z]+).*/', '$1', $url);
		$author = ucwords($author);
		$wanted_number_of_articles = $this->getInput('wanted_number_of_articles');

		$page_number = 0;
		$urls = array();
		while (count($urls) < $wanted_number_of_articles)
		{
			$current_url = $url.'?ajax=1&page='.$page_number;
			$html = getSimpleHTMLDOM($current_url);
			$this->deleteAllDescendantsIfExist($html, 'DIV.breadcrumbs');

			if (0 !== ($url_counter = count($found_urls = $html->find("DIV.listItem A[href][title]"))))
			{
				foreach($found_urls as $article__link)
					if (count($urls) < $wanted_number_of_articles)
						$urls[] = $article__link->getAttribute('href');
				$page_number++;
			}
			else
			{
				break;
			}
		}
		foreach($urls as $url)
		{
			$html = getSimpleHTMLDOM($url);
			if (is_bool($html))
			{
				$this->items[] = array(
					'uri' => $url,
					'title' => "file_get_html($url) jest boolem $html",
					'timestamp' => '',
					'author' => '',
					'content' => '',
					'categories' => ''
				);
				continue;
			}
			if (FALSE === is_null($html->find('SECTION#doc ARTICLE', 0)))
			{
				//artykul
				$article = $html->find('SECTION#doc ARTICLE', 0);
			}
			else if (FALSE === is_null($html->find('DIV#doc DIV.videoAdultOverlay', 0)))
			{
				//wideo
				$article = $html->find('DIV#doc DIV.videoAdultOverlay', 0);
			}
			else if (FALSE === is_null($html->find('DIV#doc ARTICLE.articleDetail', 0)))
			{
				//podcast
				$article = $html->find('DIV#doc ARTICLE.articleDetail', 0);
			}

			$this->deleteAllDescendantsIfExist($article, 'comment');
			$this->deleteAllDescendantsIfExist($article, 'ASIDE');
			$this->deleteAllDescendantsIfExist($article, 'SCRIPT');
			$this->deleteAllDescendantsIfExist($article, 'DIV.onet-ad');
			$this->deleteDescendantIfExists($article, 'SECTION.streamWithRight');
			$this->deleteDescendantIfExists($article, 'DIV.social-container');
			$this->deleteDescendantIfExists($article, 'DIV.contentShareLeft');
			$this->deleteDescendantIfExists($article, 'DIV#googleAdsCont');
			$this->deleteDescendantIfExists($article, 'DIV#relatedItemsContainer');
			$this->deleteDescendantIfExists($article, 'SECTION.latestFromCategory');
			$this->deleteDescendantIfExists($article, 'DIV.ninSlot');
			$this->deleteDescendantIfExists($article, 'DIV.frameWrap');
			$this->deleteDescendantIfExists($article, 'DIV[data-scroll="zobacz-rowniez"]');
			$this->deleteDescendantIfExists($article, 'DIV[data-scroll="komentarze"]');
			$this->deleteDescendantIfExists($article, 'DIV.streamNews');
			$this->deleteDescendantIfExists($article, 'DIV#widgetStop');
			$this->deleteDescendantIfExists($article, 'DIV.videoRecommendedContainer');
			

			foreach($article->find('P') as $paragraph)
			{
				$this->deleteAncestorIfContainsText($paragraph, 'Cieszymy się, że jesteś z nami. Zapisz się na newsletter Onetu, aby otrzymywać od nas najbardziej wartościowe treści');
			}

			$tags = array();
			foreach($article->find('DIV#relatedTopics SPAN.relatedTopic') as $tag_element)
			{
				$tags[] = str_replace(', ', '', $tag_element->plaintext);
			}
			$this->deleteDescendantIfExists($article, 'DIV#relatedTopics');

			foreach($article->find('DIV.embeddedApp') as $embeddedApp)
			{
				$a = $embeddedApp->find('A', 0);
				$href = $this->redirectUrl($a->getAttribute('href'));
				if(strpos($href, 'htmlbox.pulsembed.eu') !== FALSE)
				{
					$embeddedApp->outertext = '';
				}
				else
				{
					$a->setAttribute('href', $href);
					$a->innertext = $a->innertext.' - '.$href;
					$embeddedApp->outertext = $a->outertext;
				}
			}
			foreach($article->find('DIV.authorProfile IMG.photo') as $photo)
			{
				$href = $photo->getAttribute('data-original');
				$href = 'https:'.$href;
				$photo->setAttribute('src', $href);
			}
//			echo '<br>article:<br><br><br>'; echo $article;

			$this->items[] = array(
				'uri' => $url,
				'title' => trim($article->find('H1.mainTitle', 0)->plaintext),
				'timestamp' => $article->find('META[itemprop="datePublished"]', 0)->getAttribute('content'),
//				'author' => $article->find('DIV.authDesc SPAN.name', 0)->plaintext,
				'author' => $author,
				'content' => $article,
				'categories' => $tags
			);
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