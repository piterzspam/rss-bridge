<?php
class KrytykaPolitycznaBridge extends FeedExpander {

	const MAINTAINER = 'No maintainer';
	const NAME = 'Krytyka polityczna';
	const URI = 'https://krytykapolityczna.pl/';
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
        $this->collectExpandableDatas('https://krytykapolityczna.pl/feed/');
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
//		$item['uri'] = 'https://krytykapolityczna.pl/swiat/ue/lewica-wybory-lokalne-w-niemczech-traczyk-komentarz/';
//		$item['uri'] = 'https://krytykapolityczna.pl/kraj/egoizm-klimatyczny-polakow-czyli-jak-nas-zmienia-koronawirus/';
//		$item['uri'] = 'https://krytykapolityczna.pl/swiat/jagpda-grondecka-afganistan-talibowie-chca-znow-rzadzic/';
//		$item['uri'] = 'https://krytykapolityczna.pl/swiat/wielki-kapital-chcial-nas-zatruc-olowiem-nie-tylko-on/';
		
		$article_html = getSimpleHTMLDOMCached($item['uri'], 86400 * 14);
		$article_html = str_get_html(prepare_article($article_html));
		$article = $article_html->find('DIV#wrapper', 0);

		//tagi
		$tags1 = return_tags_array($article, 'HEADER.entry-header A[rel="category tag"]');
		$tags2 = return_tags_array($article, 'DIV.single-post-tags A[rel="tag"]');
		$tags = array_unique(array_merge($tags1, $tags2));

		$selectors_array = array();
		$selectors_array[] = 'HEADER.entry-header ASIDE#post-header-sidebar';
		$selectors_array[] = 'HEADER.entry-header H5';
		$selectors_array[] = 'HEADER.entry-header DIV.entry-meta';
		$selectors_array[] = 'ASIDE#after-post-sidebar';
		$selectors_array[] = 'DIV.comments-full';
		$selectors_array[] = 'FOOTER#site-footer';
		$selectors_array[] = 'DIV#mobile-menu-bg';
		$selectors_array[] = 'HEADER#mobile-site-header';
		$selectors_array[] = 'HEADER#site-header';
		$selectors_array[] = 'DIV.entry-details-holder.inner H5';
		$selectors_array[] = 'DIV.entry-details-holder.inner DIV.entry-meta';
		$selectors_array[] = 'NOSCRIPT';
		$selectors_array[] = 'LINK';
		$selectors_array[] = 'DIV.entry-meta-footer';
		$selectors_array[] = 'DIV.read-also';
		$selectors_array[] = 'ASIDE.book-item.site-commerc';
		$selectors_array[] = 'DIV.addthis_tool';
		$selectors_array[] = 'DIV.article-donate-bottom';
		$selectors_array[] = 'DIV[id^="kppromo"]';
		$selectors_array[] = 'DIV.hidden-meta';
		$selectors_array[] = 'DIV.article-top-advertisement';
		$selectors_array[] = 'DIV.single-post-tags';
		$selectors_array[] = 'BLOCKQUOTE.wp-embedded-content[data-secret]';
		//https://krytykapolityczna.pl/nauka/jas-kapela-karolina-holda-dieta-weganska-wegetarianska-dla-psow-i-kot/
		$selectors_array[] = 'IMG.avatar[alt][!src]';
		foreach_delete_element_array($article, $selectors_array);

		combine_two_elements($article, 'IMG.pre-content.pre-content-image', 'DIV.mnky-featured-image-caption', 'DIV', 'super_photo');
		combine_two_elements($article, 'DIV.post-preview IMG', 'DIV.mnky-featured-image-caption', 'DIV', 'super_photo');
		move_element($article, 'DIV#container HEADER.entry-header.clearfix', 'DIV#content', 'innertext', 'before');
		foreach_delete_element_containing_elements_hierarchy($article, array('BLOCKQUOTE', 'P', 'A[href^="https://krytykapolityczna.pl/"]'));
		foreach_delete_element_containing_elements_hierarchy($article, array('DIV', 'A[href][rel="author"]'));
		
		$article = str_get_html($article->save());
		insert_html($article, 'TIME.published', '', '', 'Publikacja: ', '');
		$article = str_get_html($article->save());
		insert_html($article, 'TIME.updated', '<br>', '<br><br>', '', '');
		$article = str_get_html($article->save());
		insert_html($article, 'TIME.updated', '', '', 'Aktualizacja: ', '');
		$article = str_get_html($article->save());
		insert_html($article, 'DIV.author-vcard-holder', '<hr>', '', '', '');
		$article = str_get_html($article->save());
		foreach_replace_outertext_with_innertext($article, 'HEADER.entry-header');
		$article = str_get_html($article->save());
		foreach_replace_outertext_with_innertext($article, 'ARTICLE[id^="post-"]');
		$article = str_get_html($article->save());
		foreach_replace_outertext_with_innertext($article, 'DIV.post-preview');
		$article = str_get_html($article->save());
		foreach_replace_outertext_with_innertext($article, 'DIV.entry-content');
		$article = str_get_html($article->save());
		foreach_replace_outertext_with_innertext($article, 'ARTICLE[id^="post-"]');
		$article = str_get_html($article->save());
		//START - https://krytykapolityczna.pl/swiat/jagpda-grondecka-afganistan-talibowie-chca-znow-rzadzic/
		fix_article_photos($article, 'DIV.content-image', FALSE, 'src', 'FIGCAPTION');
		$article = str_get_html($article->save());
		//STOP - https://krytykapolityczna.pl/swiat/jagpda-grondecka-afganistan-talibowie-chca-znow-rzadzic/
		//START - https://krytykapolityczna.pl/swiat/wielki-kapital-chcial-nas-zatruc-olowiem-nie-tylko-on/
		fix_article_photos($article, 'FIGURE[id^="attachment_"]', FALSE, 'src', 'FIGCAPTION');
		$article = str_get_html($article->save());
		//STOP - https://krytykapolityczna.pl/swiat/wielki-kapital-chcial-nas-zatruc-olowiem-nie-tylko-on/
		//START - https://krytykapolityczna.pl/nauka/jas-kapela-karolina-holda-dieta-weganska-wegetarianska-dla-psow-i-kot/
		fix_article_photos($article, 'DIV.super_photo', TRUE, 'src', 'DIV.mnky-featured-image-caption');
		$article = str_get_html($article->save());
		//STOP - https://krytykapolityczna.pl/nauka/jas-kapela-karolina-holda-dieta-weganska-wegetarianska-dla-psow-i-kot/

		foreach_replace_outertext_with_subelement_outertext($article, 'ASIDE.single-post-sidebar', 'SPAN.meta-date');
		foreach_replace_outertext_with_subelement_outertext($article, 'DIV.single-post-content-holder', 'DIV.single-post-content');
		$article = str_get_html($article->save());

		add_style($article, 'P.post-lead', array('font-weight: bold;'));
		add_style($article, 'FIGURE.photoWrapper', getStylePhotoParent());
		add_style($article, 'FIGURE.photoWrapper IMG', getStylePhotoImg());
		add_style($article, 'FIGCAPTION', getStylePhotoCaption());
		add_style($article, 'BLOCKQUOTE', getStyleQuote());
		$article = str_get_html($article->save());

		$item['content'] = $article;
		$item['categories'] = $tags;
		return $item;
	}
}
// Imaginary empty line!