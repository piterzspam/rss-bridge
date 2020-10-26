<?php
class OpinieWPplBridge extends BridgeAbstract {
	const NAME = 'Opinie WP.pl - strona autora';
	const URI = 'https://opinie.wp.pl/';
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
		)
	);



	public function collectData()
	{
		$given_url = $this->getInput('url');
//		$given_url = 'https://opinie.wp.pl/autor/kataryna/6119008086550145';
		$html_main_page = getSimpleHTMLDOM($given_url);
		$articles_list = $html_main_page->find('DIV[data-st-area="list-topic"]', 0)->first_child()->first_child();

		foreach($articles_list->childNodes() as $article)
		{
			if (FALSE === is_null($article->find('A', 0)))
			{
				$urls[] = 'https://opinie.wp.pl'.$article->find('A', 0)->getAttribute('href');
			}
		}

		foreach($urls as $url)
		{
			$html_article = getSimpleHTMLDOM($url);
			$article = $html_article->find('ARTICLE.article', 0);
	
			$tags = array();
			foreach($article->find('DIV[data-st-area="article-header"]', 0)->find('A[href^="/tag/"]') as $tag_element)
			{
				$tags[] = $tag_element->plaintext;
				$tag_element->outertext = '';
			}
			$article->find('DIV[data-st-area="article-header"]', 0)->outertext = $article->find('DIV.signature', 0)->outertext;
			$article->find('DIV.article--lead', 0)->next_sibling()->outertext = '';


			$this->deleteAncestorIfChildMatches($article, array('div', 'div', 'IMG[src*="v.wpimg.pl"][!width]'));

			foreach($article->find('IMG[src][data-src]') as $img)
			{
				$new_url = $img->getAttribute('data-src');
				$img->src = $new_url;
			}

			foreach($article->find('DIV[id^="video-player"]') as $video_player)
			{
				$parent = $video_player->parent();
				$previous = $parent->prev_sibling();
				if ($previous->children(0)->tag === 'h2')
					$previous->outertext = '';
				$parent->outertext = '';
			}

			foreach($article->find('comment') as $comment)
			{
				$comment->outertext = '';
			}

			foreach($article->find('P') as $paragraph)
			{
				$this->deleteAncestorIfContainsText($paragraph, 'Masz newsa, zdjęcie lub filmik? Prześlij nam przez');
				$this->deleteAncestorIfContainsText($paragraph, 'Zobacz też: ');
				$this->deleteAncestorIfContainsText($paragraph, 'Zobacz wideo: ');
				$this->deleteAncestorIfContainsText($paragraph, 'Zobacz także: ');
			}
			
			$this->items[] = array(
				'uri' => $url,
				'title' => $article->find('H1.article--title', 0)->plaintext,
				'timestamp' => $article->find('time', 0)->getAttribute('datetime'),
				'author' => $article->find('SPAN.signature--author', 0)->plaintext,
				'content' => $article,
				'categories' => $tags
			);
			
//			echo '<br><br><br><br>article: po<br>';
//			echo $article;
		}
	}

	private function deleteAncestorIfContainsText($ancestor, $descendant_string)
	{
		if (FALSE === is_null($ancestor))
			if (FALSE !== strpos($ancestor->plaintext, $descendant_string))
				$ancestor->outertext = '';
	}

	private function deleteAncestorIfChildMatches($element, $hierarchy)
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

	private function deleteDescendantIfExists($ancestor, $descendant_string)
	{
		if (FALSE === is_null($descendant = $ancestor->find($descendant_string, 0)))
			$descendant->outertext = '';
	}
}