<?php
class GazetaplBridge extends BridgeAbstract {
	const NAME = 'Gazeta.pl - strona autora';
	const URI = 'https://wiadomosci.gazeta.pl/wiadomosci/0,114871.html';
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
//		Warning: https://wiadomosci.gazeta.pl/wiadomosci/7,114884,26406112,dr-hab-wigura-najwiekszym-problemem-nowej-solidarnosci-jest.html
//		Twitter frame: https://wiadomosci.gazeta.pl/wiadomosci/7,114884,25947207,trzaskowski-za-kidawe-blonska-kiedys-bylo-tusku-musisz.html
//		error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
		error_reporting(E_ALL & ~E_WARNING);
		$url = $this->getInput('url');
		$wanted_number_of_articles = $this->getInput('wanted_number_of_articles');

		$urls = array();
		while (count($urls) < $wanted_number_of_articles)
		{
			$html = getSimpleHTMLDOM($url);

			foreach($html->find('LI.entry') as $entry)
			{
				if (count($urls) < $wanted_number_of_articles)
					$urls[] = $entry->find('A', 0)->getAttribute('href');
			}

			if (FALSE === is_null($html->find('A.next', 0)))
				$url = $html->find('A.next', 0)->getAttribute('href');
			else
				break;
		}

		foreach($urls as $url)
		{
			$article_wrapper = getSimpleHTMLDOM($url)->find('SECTION#article_wrapper', 0);
			
			foreach($article_wrapper->find('div.art_embed') as $art_embed)
			{
				$this->deleteAncestorIfDescendantExists($art_embed, 'SCRIPT[src*="video.onnetwork.tv"]');
				
				if (FALSE === is_null($art_embed->find('A[href*="twitter.com/user/status/"]', 0)))
				{
					$twitter_url = $art_embed->find('a', 0)->getAttribute('href');
					$twitter_element = $this->getTwitterElement($twitter_url);
					$art_embed->outertext = $twitter_element->outertext;
				}
			}
			foreach($article_wrapper->find('P.art_paragraph A[href*="?tag="]') as $paragraph)
			{
				$paragraph->parent->innertext = $paragraph->parent->plaintext;
			}

			$tags = array();
			foreach($article_wrapper->find('LI.tags_item') as $tags_item)
			{
				$tags[] = trim($tags_item->plaintext);
			}

			$this->deleteAllDescendantsIfExist($article_wrapper, 'comment');
			$this->deleteAllDescendantsIfExist($article_wrapper, 'SCRIPT');
			$this->deleteAllDescendantsIfExist($article_wrapper, 'DIV[id^="banC"]');

			$this->deleteDescendantIfExists($article_wrapper, 'DIV#sitePath');
			$this->deleteDescendantIfExists($article_wrapper, 'DIV.left_aside');
			$this->deleteDescendantIfExists($article_wrapper, 'DIV.ban000_wrapper');
			$this->deleteDescendantIfExists($article_wrapper, 'DIV.ban001_wrapper');
			$this->deleteDescendantIfExists($article_wrapper, 'DIV.right_aside');
			$this->deleteDescendantIfExists($article_wrapper, 'DIV.top_section_bg');
			$this->deleteDescendantIfExists($article_wrapper, 'DIV.bottom_section_bg');
			$this->deleteDescendantIfExists($article_wrapper, 'DIV#adUnit-007-CONTENTBOARD');
			$this->deleteDescendantIfExists($article_wrapper, 'DIV.related_image_number_of_photo');
			$this->deleteDescendantIfExists($article_wrapper, 'DIV.related_image_open');
			$this->deleteDescendantIfExists($article_wrapper, 'SECTION.tags');
			

			$this->items[] = array(
				'uri' => $url,
				'title' => trim($article_wrapper->find('H1#article_title', 0)->plaintext),
				'timestamp' => $article_wrapper->find('TIME', 0)->getAttribute('datetime'),
				'author' => $article_wrapper->find('A[rel="author"]', 0)->plaintext,
				'content' => $article_wrapper,
				'categories' => $tags
			);
		}

	}

	private function getTwitterElement($twitter_url)
	{
		$twitter_proxy = 'nitter.net';
		$twitter_url = str_replace('twitter.com', $twitter_proxy, $twitter_url);
		$html_twitter = getSimpleHTMLDOM($twitter_url);
		$main_tweet = $html_twitter->find('DIV#m.main-tweet', 0);
		foreach($main_tweet->find('a') as $element)
		{
			$element_url = $element->getAttribute('href');
			if(strpos($element_url, '/') === 0)
			{
				$element_url = "https://".$twitter_proxy.$element_url;
				$element->setAttribute('href', $element_url);
			}
		}
		$date_text = $main_tweet->find('p.tweet-published', 0)->plaintext;
		$main_tweet->find('p.tweet-published', 0)->outertext = '<a href="'.$twitter_url.'" title="'.$date_text.'">'.$date_text.'</a>';
		$main_tweet->find('SPAN.tweet-date', 0)->outertext = '';
		$main_tweet->find('DIV.tweet-stats', 0)->outertext = '';
		$main_tweet->find('A.fullname', 0)->outertext = '';
		return $main_tweet;
	}

	private function deleteDescendantIfExists($ancestor, $descendant_string)
	{
		if (FALSE === is_null($descendant = $ancestor->find($descendant_string, 0)))
			$descendant->outertext = '';
	}
	
	private function deleteAllDescendantsIfExist($ancestor, $descendant_string)
	{
		foreach($ancestor->find($descendant_string) as $descendant)
			$descendant->outertext = '';
	}

	private function deleteAncestorIfDescendantExists($ancestor, $descendant_string)
	{
		if (FALSE === is_null($descendant = $ancestor->find($descendant_string, 0)))
			$ancestor->outertext = '';
	}
}