<?php
class ToTylkoTeoriaBridge extends BridgeAbstract {
	const NAME = 'To tylko teoria';
	const URI = 'https://www.totylkoteoria.pl/';
	const DESCRIPTION = 'No description provided';
	const MAINTAINER = 'No maintainer';
	const CACHE_TIMEOUT = 86400; // Can be omitted!

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
		)
	);

	public function getIcon()
	{
		return 'https://c.disquscdn.com/uploads/forums/349/4323/favicon.png';
	}

	public function collectData()
	{

		$GLOBALS['limit'] = $this->getInput('limit');
		$main_page_url = 'https://www.totylkoteoria.pl/';
		$html_main_page = getSimpleHTMLDOM($main_page_url);

		$urls = array();
		##ARTICLE.hentry
		foreach($html_main_page->find('ARTICLE.hentry') as $hentry)
		{
			$a_element=$hentry->find('A[href]', 0);
			if (count($urls) < $GLOBALS['limit'])
			{
				$href = $a_element->getAttribute('href');
				$this->addArticle($href);
			}
		}

	}

	private function addArticle($url_article)
	{
//		$article_html = file_get_html($url_article);
		$article_html = getSimpleHTMLDOMCached($url_article, 86400 * 14);
		if (FALSE === is_null($article_html->find('ARTICLE', 0)))
		{
			$article = $article_html->find('ARTICLE', 0);
		}
		else
		{
			$this->items[] = array(
				'uri' => $url_article,
				'title' => "Brak elementu ARTICLE",
				'timestamp' => '',
				'author' => '',
				'content' => '',
				'categories' => ''
			);
			return;
		}

		$this->foreach_delete_element($article, 'SPAN.label-info');
		$this->foreach_delete_element($article, 'SPAN.item-control.blog-admin');
		$this->foreach_delete_element($article, 'A[href="https://patronite.pl/totylkoteoria"][style="margin-left: 1em; margin-right: 1em;"]');
		$this->foreach_delete_element($article, 'DIV#share-post');
		$this->foreach_delete_element($article, 'DIV#related-posts');
		$this->foreach_delete_element($article, 'A[href="https://www.totylkoteoria.pl/2015/06/kim-jestem.html"]');
		$this->foreach_delete_element($article, 'DIV.author-avatar');
		//date
		$date = $article->find('ABBR.published', 0)->getAttribute('title');
		//title
		$title = $article->find('H1.post-title', 0)->plaintext;
		//author
		$author = trim($article->find('DIV.author-description', 0)->plaintext);
		$author = str_replace('Czytaj więcej', '', $author);
		$author = str_replace('. ', '', $author);
		$author = trim($author);
		//tags
		$tags = array();
		foreach($article->find('SPAN.label-info', 0)->find('A[rel="tag"]') as $tag_element)
		{
			$tags[] = trim($tag_element->plaintext);
		}

		foreach($article->find('P') as $paragraph)
		{
			$this->single_delete_element_containing_text($paragraph, 'Przeczytaj także: ');
		}
		foreach($article->find('DIV[style="text-align: justify;"]') as $paragraph)
		{
			$this->single_delete_element_containing_text($paragraph, 'Przeczytaj także: ');
		}
	
		$this->items[] = array(
			'uri' => $url_article,
			'title' => $title,
			'timestamp' => $date,
			'author' => $author,
			'content' => $article,
			'categories' => $tags
		);
//		echo 'article:'; echo $article;
	}

	private function single_delete_element_containing_subelement($ancestor, $descendant_string)
	{
		if (FALSE === is_null($descendant = $ancestor->find($descendant_string, 0)))
			$descendant->outertext = '';
	}

	private function single_delete_subelement($ancestor, $descendant_string)
	{
		if (FALSE === is_null($descendant = $ancestor->find($descendant_string, 0)))
			$ancestor->outertext = '';
	}

	private function single_delete_element_containing_text($ancestor, $descendant_string)
	{
		if (FALSE === is_null($ancestor))
			if (FALSE !== strpos($ancestor->plaintext, $descendant_string))
				$ancestor->outertext = '';
	}

	private function foreach_delete_element($ancestor, $descendant_string)
	{
		foreach($ancestor->find($descendant_string) as $descendant)
			$descendant->outertext = '';
	}

	private function foreach_delete_element_containing_elements_hierarchy($element, $hierarchy)
	{
		$last = count($hierarchy)-1;
		$counter = 0;
		foreach($element->find($hierarchy[$last]) as $found)
		{
			$counter++;
			$iterator = $last-1;
			while ($iterator >= 0 && $found->parent->tag === $hierarchy[$iterator])
			{
				$found = $found->parent;
				$iterator--;
			}
			if ($iterator === -1)
			{
				$found->outertext = '';
			}
		}
	}

	private function get_proxy_url($social_url)
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