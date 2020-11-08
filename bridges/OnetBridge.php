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
		$url_articles_list = $this->getInput('url');
		$url_articles_list = preg_replace('/(.*\/autorzy\/[a-z]+-[a-z]+).*/', '$1', $url_articles_list);
		$GLOBALS['number_of_wanted_articles'] = $this->getInput('wanted_number_of_articles');

		$urls = array();
		$page_number = 0;
		while (count($urls) < $GLOBALS['number_of_wanted_articles'])
		{
			$current_url = $url_articles_list.'?ajax=1&page='.$page_number;
			$html_articles_list = getSimpleHTMLDOM($current_url);
			$this->deleteAllDescendantsIfExist($html_articles_list, 'DIV.breadcrumbs');

			if (0 !== ($url_counter = count($found_urls = $html_articles_list->find("DIV.listItem A[href][title]"))))
			{
				foreach($found_urls as $article__link)
				{
					if (count($urls) < $GLOBALS['number_of_wanted_articles'])
						$urls[] = $article__link->getAttribute('href');
				}
				$page_number++;
			}
			else
			{
				break;
			}
		}
		foreach($urls as $url_article_link)
		{
			$article_html = getSimpleHTMLDOMCached($url_article_link, (864000/(count($this->items)+1)*$GLOBALS['number_of_wanted_articles']));
			if (is_bool($article_html))
			{
				$this->items[] = array(
					'uri' => $url_article_link,
					'title' => "getSimpleHTMLDOM($url_article_link) jest booleml",
					'timestamp' => '',
					'author' => '',
					'content' => $article_html,
					'categories' => ''
				);
				continue;
			}
			if (FALSE === is_null($article_html->find('SECTION#doc ARTICLE', 0)))
			{
				//artykul
				$article = $article_html->find('SECTION#doc ARTICLE', 0);
			}
			else if (FALSE === is_null($article_html->find('DIV#doc DIV.videoAdultOverlay', 0)))
			{
				//wideo
				$article = $article_html->find('DIV#doc DIV.videoAdultOverlay', 0);
			}
			else if (FALSE === is_null($article_html->find('DIV#doc ARTICLE.articleDetail', 0)))
			{
				//podcast
				$article = $article_html->find('DIV#doc ARTICLE.articleDetail', 0);
			}
			//tags
			$tags = array();
			foreach($article->find('DIV#relatedTopics SPAN.relatedTopic') as $tag_element)
			{
				$tags[] = str_replace(', ', '', $tag_element->plaintext);
			}
			$this->deleteDescendantIfExists($article, 'DIV#relatedTopics');
			//title
			$title = trim($article->find('H1.mainTitle', 0)->plaintext);
			//timestamp
			$timestamp = $article->find('META[itemprop="datePublished"]', 0)->getAttribute('content');
			//author
			$author = $article->find('DIV.authDesc SPAN.name', 0)->plaintext;

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
				'uri' => $url_article_link,
				'title' => $title,
				'timestamp' => $timestamp,
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

	private function redirectUrl($social_url)
	{
		$twitter_proxy = 'nitter.net';
		$instagram_proxy = 'bibliogram.art';
		$facebook_proxy = 'mbasic.facebook.com';
		$social_url = preg_replace('/.*[\.\/]twitter\.com(.*)/', 'https://'.$twitter_proxy.'${1}', $social_url);
		$social_url = preg_replace('/.*[\.\/]instagram\.com(.*)/', 'https://'.$instagram_proxy.'${1}', $social_url);
		$social_url = preg_replace('/.*[\.\/]facebook\.com(.*)/', 'https://'.$facebook_proxy.'${1}', $social_url);
		return $social_url;
	}
}