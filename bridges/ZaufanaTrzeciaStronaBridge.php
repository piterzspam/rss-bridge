<?php
class ZaufanaTrzeciaStronaBridge extends FeedExpander {

	const MAINTAINER = 'No maintainer';
	const NAME = 'Zaufana Trzecia Strona';
	const URI = 'https://zaufanatrzeciastrona.pl/';
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
        $this->collectExpandableDatas('https://zaufanatrzeciastrona.pl/feed/');
    }

	private function setGlobalArticlesParams()
	{
		$GLOBALS['my_debug'] = FALSE;
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
		$returned_array = my_get_html($item['uri']);
		if (200 === $returned_array['code'])
		{
			$article_html = $returned_array['html'];
		}
		else
		{
			return $item;
		}
		$article_html = str_get_html(prepare_article($article_html, "https://zaufanatrzeciastrona.pl"));
		$date_published = get_text_from_attribute($article_html, 'META[property="article:published_time"][content]', 'content', "");
		$date_modified = get_text_from_attribute($article_html, 'META[property="article:modified_time"][content]', 'content', "");

		$article = $article_html->find('DIV#main DIV.postcontent', 0);
		$tags = return_tags_array($article, 'DIV.dolna-ramka A[href*="/tag/"][rel="tag"]');
		
		$selectors_array[] = 'comment';
		$selectors_array[] = 'script';
		$selectors_array[] = 'DIV.thumbnail-wrap P';
		$selectors_array[] = 'DIV.dolna-ramka';
		$article = foreach_delete_element_array($article, $selectors_array);

		$article = format_article_photos($article, 'DIV.thumbnail-wrap', TRUE, 'src', 'FIGCAPTION');
		$article = format_article_photos($article, '.wp-block-image', FALSE, 'src', 'FIGCAPTION');

		
		$article = insert_html($article, 'DIV.authors IMG.author.photo', '<div class="author photo holder">', '</div>');
		$article = str_get_html('<div class="article"><ARTICLE>'.$article->save().'</ARTICLE></div>');


		//https://zaufanatrzeciastrona.pl/post/wygraj-darmowa-wejsciowke-na-swietna-konferencje-black-hat-usa-2021/
		$article = replace_tag_and_class($article, 'PRE', 'multiple', 'BLOCKQUOTE', NULL);
		
		$article = replace_tag_and_class($article, 'H1', 'single', 'H1', 'title');
		$article = replace_tag_and_class($article, 'P', 'single', 'STRONG', 'lead');
		$article = replace_tag_and_class($article, 'DIV.postcontent', 'single', 'DIV', 'article body');
		
		
		$article = move_element($article, 'H1.title', 'ARTICLE', 'innertext', 'before');
		$article = insert_html($article, 'H1.title', '', get_date_outertext($date_published, $date_modified));
		$article = move_element($article, 'DIV.article.body', 'DIV.dates', 'outertext', 'after');
		$article = insert_html($article, 'DIV.article.body', '', '<div class="authors">'.$item['author'].'</div>');
		$article = insert_html($article, 'DIV.authors', '', '', '<HR>');

		$article = $article->find('ARTICLE', 0);

		$article = foreach_replace_outertext_with_innertext($article, 'FIGURE.photoWrapper A[href*="zaufanatrzeciastrona.pl/wp-content/uploads/"]');
		$article = add_style($article, 'FIGURE.photoWrapper.mainPhoto', array('float: left;', 'margin-right: 15px;'));
		$article = add_style($article, 'FIGURE.photoWrapper', getStylePhotoParent());
		$article = add_style($article, 'FIGURE.photoWrapper IMG', getStylePhotoImg());
		$article = add_style($article, 'FIGCAPTION', getStylePhotoCaption());
		$article = add_style($article, 'BLOCKQUOTE', getStyleQuote());

		$item['content'] = $article;
		$item['categories'] = $tags;
		if (FALSE === in_array("konferencja", $item['categories']))
		{
			return $item;
		}
	}

}
// Imaginary empty line!