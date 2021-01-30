<?php
class KlubJagiellonskiBridge extends FeedExpander {

	const MAINTAINER = 'No maintainer';
	const NAME = 'Klub Jagielloński';
	const URI = '';
	const DESCRIPTION = 'No description provided';
	const CACHE_TIMEOUT = 86400;

	const PARAMETERS = array
	(
		'Parametry' => array
		(
			'wanted_number_of_articles' => array
			(
				'name' => 'Liczba artykułów',
				'type' => 'text',
				'required' => true
			)
		)
	);

    public function collectData(){
		include 'myFunctions.php';
        $this->collectExpandableDatas('https://klubjagiellonski.pl/feed/');
    }

	protected function parseItem($newsItem)
	{
		$item = parent::parseItem($newsItem);
		if (count($this->items) >= $this->getInput('wanted_number_of_articles'))
		{
			return $item;
		}
		$article_page = getSimpleHTMLDOMCached($item['uri'], 86400 * 14);
		$article_post = $article_page->find('ARTICLE', 0);
		deleteAllDescendantsIfExist($article_post, 'SECTION.block-content_breaker_sharer');
		deleteAllDescendantsIfExist($article_post, 'DIV[data-source="ramka-newsletter"]');
		deleteAllDescendantsIfExist($article_post, 'DIV[data-source="ramka-zbiorka"]');
		deleteAllDescendantsIfExist($article_post, 'DIV[data-source="ramka-polecane"]');
		deleteAllDescendantsIfExist($article_post, 'DIV.meta_mobile.desktop-hide');
		deleteAllDescendantsIfExist($article_post, 'qqqqqqqqqqqqqq');
		deleteAllDescendantsIfExist($article_post, 'qqqqqqqqqqqqqq');
		deleteAllDescendantsIfExist($article_post, 'qqqqqqqqqqqqqq');
		deleteAllDescendantsIfExist($article_post, 'qqqqqqqqqqqqqq');
		deleteAllDescendantsIfExist($article_post, 'qqqqqqqqqqqqqq');
		deleteAllDescendantsIfExist($article_post, 'qqqqqqqqqqqqqq');
		deleteAllDescendantsIfExist($article_post, 'qqqqqqqqqqqqqq');
		deleteAllDescendantsIfExist($article_post, 'qqqqqqqqqqqqqq');
		deleteAllDescendantsIfExist($article_post, 'qqqqqqqqqqqqqq');
		deleteAllDescendantsIfExist($article_post, 'qqqqqqqqqqqqqq');
/*		
		deleteAllDescendantsIfExist($article_post, 'script');
		deleteAllDescendantsIfExist($article_post, 'DIV.kl-10lat-box');
		deleteAllDescendantsIfExist($article_post, 'DIV.go-to-comments');
		deleteAllDescendantsIfExist($article_post, 'DIV.nr-info');
		deleteAllDescendantsIfExist($article_post, 'DIV.more-in-number-container');
		deleteAllDescendantsIfExist($article_post, 'DIV.fb-comm');
		deleteAllDescendantsIfExist($article_post, 'P.section-name.mobile-section-name');
		//https://kulturaliberalna.pl/2021/01/12/cena-osobnosci-nie-jest-wysoka-na-razie/
		deleteAllDescendantsIfExist($article_post, 'DIV.promobox');
*/
		$tags = returnTagsArray($article_post, 'A.block-catbox SPAN.catboxfg');
		$author = returnAuthorsAsString($article_post, 'A.block-author_bio P.imienazwisko');
/*		$interview_quote_style = array(
			'border: dashed;'
		);
		addStyle($article_post, 'blockquote', $interview_quote_style);
		foreach($article_post->find('IMG') as $photo_element)
		{
			if(isset($photo_element->width)) $photo_element->width = NULL;
			if(isset($photo_element->height)) $photo_element->height = NULL;
			if(isset($photo_element->srcset)) $photo_element->srcset = NULL;
			if(isset($photo_element->sizes)) $photo_element->sizes = NULL;
		}
*/
		foreach($article_post->find('A.block-author_bio') as $block_author)
		{
			if (FALSE === is_null($bio = $block_author->find('DIV.bio', 0)))
			{
				$bio_text = $bio->plaintext;
				$bio->outertext = '';
				$block_author->outertext = $block_author->outertext.'<div class="bio">'.$bio_text.'</div>';
			}
		}
		foreach($article_post->find('DIV.block-wyimki DIV.row') as $row)
		{
			$row->outertext = '<strong>'.'- '.$row->plaintext.'</strong><br><br>';
		}
		foreach($article_post->find('DIV.pix') as $pix)
		{
			if (FALSE === is_null($cat = $pix->find('DIV.cat', 0)))
				$cat->outertext = '';
			if (FALSE === is_null($pixbox_desktop = $pix->find('DIV.pixbox_desktop.mobile-hide[style^="background-image: "]', 0)))
				$pixbox_desktop->outertext = '';
		}
		foreach($article_post->find('A.block-author_bio') as $author_bio)
		{
			$author_bio->outertext = '<br>'.$author_bio->outertext;
		}
		
		$item['categories'] = $tags;
		$item['author'] = $author;
		$item['content'] = $article_post;
		return $item;
	}

}
// Imaginary empty line!