<?php
class NoizzBridge extends FeedExpander {

	const MAINTAINER = 'No maintainer';
	const NAME = 'Noizz';
	const URI = 'https://noizz.pl/';
	const DESCRIPTION = 'No description provided';
	const CACHE_TIMEOUT = 86400;

	const PARAMETERS = array
	(
		'Parametry' => array
		(
			'url' => array
			(
				'name' => 'URL',
				'type' => 'text',
				'required' => true
			),
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

	public function getName()
	{
		switch($this->queriedContext)
		{
			case 'Parametry':
				$returned_array = my_get_html($this->getInput('url'));
				if (200 !== $returned_array['code'])
				{
					return parent::getName();
				}
				else
				{
					$channel_title = get_text_plaintext($returned_array['html'], 'TITLE', NULL);
				}
				if(isset($channel_title) && 1 < strlen($channel_title))
				{
					return $channel_title;
				}
				else
				{
					return parent::getName();
				}
				break;
			default:
				return parent::getName();
		}
	}

	public function getURI()
	{
		switch($this->queriedContext)
		{
			case 'Parametry':
					return $this->getInput('url');
				break;
			default:
				return parent::getURI();
		}
	}

    public function collectData(){
		include 'myFunctions.php';
		$this->setGlobalArticlesParams();
        $this->collectExpandableDatas($this->getInput('url')."?rss");
    }

	private function setGlobalArticlesParams()
	{
		$GLOBALS['my_debug'] = FALSE;
		if (TRUE === $GLOBALS['my_debug'])
		{
			$GLOBALS['all_articles_time'] = 0;
			$GLOBALS['all_articles_counter'] = 0;
		}
		if (TRUE === $this->getInput('include_not_downloaded'))
			$GLOBALS['include_not_downloaded'] = TRUE;
		else
			$GLOBALS['include_not_downloaded'] = FALSE;
		$GLOBALS['limit'] = intval($this->getInput('limit'));
		$GLOBALS['url_articles_list'] = $this->getInput('url');
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
		$item['uri'] = $this->getCustomizedLink($item['uri']);
		$returned_array = my_get_html($item['uri']);
		if (200 !== $returned_array['code'])
		{
			return;
		}
		else
		{
			$article_html = $returned_array['html'];
		}
		$datePublished = get_json_value($article_html, 'SCRIPT[type="application/ld+json"]', 'datePublished');
		$dateModified = get_json_value($article_html, 'SCRIPT[type="application/ld+json"]', 'dateModified');
		$article_html = $this->remove_useless_elements($article_html);
		$article_html = move_element($article_html, 'DIV#page DIV.source', 'DIV#page ARTICLE', 'innertext', 'after');
		$article_html = move_element($article_html, 'DIV#page DIV.tags', 'DIV#page ARTICLE', 'innertext', 'after');
		$article_html = str_get_html(prepare_article($article_html));
		$article = $article_html->find('DIV#page', 0);
//		$article = replace_date($article, 'SPAN.date', $datePublished, $dateModified);
		$article = replace_date($article, 'DIV.dateAuthor', $datePublished, $dateModified);
		$author = return_authors_as_string($article, 'DIV.dateAuthor SPAN.authorName');
		$tags = return_tags_array($article, 'DIV.tags A.tagItem');

		
		$article = replace_tag_and_class($article, 'ARTICLE P.hyphenate', 'single', 'STRONG', 'lead');
		$article = replace_tag_and_class($article, 'FIGURE.lead', 'single', 'FIGURE', 'first_image');
		$article = format_article_photos($article, 'FIGURE.first_image', TRUE, 'src', 'DIV.authorSource');
		$article = format_article_photos($article, 'FIGURE.lead', FALSE, 'src', 'DIV.authorSource');
		$article = move_element($article, 'STRONG.lead', 'DIV.dates', 'outertext', 'after');
		$article = move_element($article, 'DIV.dateAuthor', 'ARTICLE', 'innertext', 'after');
		$article = insert_html($article, 'DIV.dateAuthor', '<HR>', '');
			$article = insert_html($article, 'STRONG.lead', '<div class="lead">', '</div>');
		$article = $article->find('ARTICLE', 0);

		$article = add_style($article, 'FIGURE.photoWrapper', getStylePhotoParent());
		$article = add_style($article, 'FIGURE.photoWrapper IMG', getStylePhotoImg());
		$article = add_style($article, 'FIGCAPTION', getStylePhotoCaption());
		$article = add_style($article, 'BLOCKQUOTE', getStyleQuote());

		$item['content'] = $article;
		$item['timestamp'] = $datePublished;
		$item['author'] = $author;
		$item['categories'] = $tags;
		unset($item['enclosures']);
		
				
		return $item;
	}

	private function getCustomizedLink($url)
	{
		$url_array = parse_url($url);
		$article_domain = $url_array["scheme"].'://'.$url_array["host"];
		$ampproject_link = str_replace(".", "-", $article_domain);
		$url_without_scheme = str_replace($url_array["scheme"].'://', "", $url);
		$ampproject_link = $ampproject_link.".cdn.ampproject.org/v/s/".$url_without_scheme.".amp?amp_js_v=0.1";
		return $ampproject_link;
	}


	private function remove_useless_elements($article)
	{
		$selectors_array[] = 'comment';
		$selectors_array[] = 'script';
		$selectors_array[] = 'DIV.adBoxTop';
		$selectors_array[] = 'DIV.adBox';
		$selectors_array[] = 'DIV.social-box';
		$article = foreach_delete_element_array($article, $selectors_array);

		$article = foreach_delete_element_containing_elements_hierarchy($article, array('LI', 'A[href*="onet.pl/#utm_source"]'));
		$article = foreach_delete_element_containing_elements_hierarchy($article, array('LI', 'A[href="https://www.onet.pl/"]'));
		
		//https://wiadomosci-onet-pl.cdn.ampproject.org/v/s/wiadomosci.onet.pl/tylko-w-onecie/wybory-w-usa-2020-andrzej-stankiewicz-dzis-jest-czas-wielkiej-smuty-w-pis/fcktclw.amp?amp_js_v=0.1
		//https://wiadomosci-onet-pl.cdn.ampproject.org/v/s/wiadomosci.onet.pl/kraj/koronawirus-piotr-glinski-komentuje-milionowe-dofinansowania-dla-artystow/bv013rl.amp?amp_js_v=0.1
		//Gliński łumaczy się kryteriami obiektywnymi.
		//$article = clear_paragraphs_from_taglinks($article, 'P.hyphenate', array('/onet\.pl\/[^\/]*$/'));
		$article = foreach_delete_element_containing_text_from_array($article, 'LI', array('Więcej informacji i podcastów znajdziesz na stronie głównej Onet.pl'));
		$article = foreach_delete_element_containing_text_from_array($article, 'P.hyphenate', 
			array(
				'Poniżej lista wszystkich dotychczasowych odcinków podcastu',
				'Cieszymy się, że jesteś z nami. Zapisz się na newsletter Onetu, aby otrzymywać od nas najbardziej wartościowe treści'
				)
		);
//https://wiadomosci-onet-pl.cdn.ampproject.org/v/s/wiadomosci.onet.pl/tylko-w-onecie/michal-cholewinski-krytykowal-orzeczenie-tk-ws-aborcji-zostal-zdjety-z-anteny/31zq2s2.amp?amp_js_v=0.1
//https://wiadomosci-onet-pl.cdn.ampproject.org/v/s/wiadomosci.onet.pl/kraj/20-lecie-platformy-obywatelskiej-partia-oklejona-nekrologami-analiza/7cwsve3.amp?amp_js_v=0.1
//https://wiadomosci-onet-pl.cdn.ampproject.org/v/s/wiadomosci.onet.pl/tylko-w-onecie/usa-donald-trump-andrzej-stankiewicz-jedyny-donald-ktorego-pokochalo-pis/xkyb0n7.amp?amp_js_v=0.1
//Dalsza część tekstu znajduje się pod wideo
//Agent Tomek w "Onet Rano" przeprasza Beatę Sawicką. Ciąg dalszy tekstu pod wideo:
//Pozostała część tekstu pod materiałem wideo
//https://noizz-pl.cdn.ampproject.org/v/s/noizz.pl/big-stories/prawie-3-mln-polakow-tonie-w-dlugach-tadeusz-czasem-mysli-by-odebrac-sobie-zycie/w5le1mw.amp?amp_js_v=0.1
//https://noizz-pl.cdn.ampproject.org/v/s/noizz.pl/big-stories/1-rocznica-zabojstwa-georgea-floyda-to-morderstwo-to-skutek-systemowego-rasizmu-w-usa/02qsjcd.amp?amp_js_v=0.1
//https://noizz-pl.cdn.ampproject.org/v/s/noizz.pl/big-stories/chce-sie-zaszczepic-przeciw-covid-19-ale-rodzice-nie-pozwalaja-zagrozili-ze-wyrzuca/x935kwy.amp?amp_js_v=0.1
//		print_element($article, "article przed");
		$video_array = array(
			'pod materiałem wideo',
			'pod wideo',
			'poniżej materiału wideo'
		);
		$see_also_array = array(
			'Zobacz także:',
			'Zobacz również:',
			'Zobacz też:',
		);
		foreach($article->find('P.hyphenate') as $paragraph)
		{
			if(check_string_contains_needle_from_array($paragraph->plaintext, $video_array))
			{
				$maybe_video_header = $paragraph->next_sibling();
				if ("p" == strtolower($maybe_video_header->tag) && "heading" == strtolower($maybe_video_header->class))
				{
					if(check_string_contains_needle_from_array($maybe_video_header->plaintext, $see_also_array))
					{
						$maybe_amp_iframe = $maybe_video_header->next_sibling();
						if ("amp-iframe" == strtolower($maybe_amp_iframe->tag))
						{
							$maybe_amp_iframe->outertext='';
							$maybe_video_header->outertext='';
							$paragraph->outertext='';
						}
					}
				}
			}
		}
		$article = str_get_html($article->save());
		foreach($article->find('P.heading') as $maybe_video_header)
		{
			if ("p" == strtolower($maybe_video_header->tag) && "heading" == strtolower($maybe_video_header->class))
			{
				if(check_string_contains_needle_from_array($maybe_video_header->plaintext, $see_also_array))
				{
					$maybe_amp_iframe = $maybe_video_header->next_sibling();
					if ("amp-iframe" == strtolower($maybe_amp_iframe->tag))
					{
						$maybe_amp_iframe->outertext='';
						$maybe_video_header->outertext='';
					}
				}
			}
		}
		$article = str_get_html($article->save());
		foreach($article->find('ARTICLE DIV[!class]') as $div)
		{
			if (FALSE === is_null($related_lead = $div->find("FIGURE.lead DIV.wrapper AMP-IMG", 0)))
			{
				$div->outertext = '';
			}
		}
		$article = str_get_html($article->save());

		$article_str = $article->save();
		foreach($article->find('UL LI A[href*="onet.pl/"][target="_top"]') as $element)
		{
			$parent = $element->parent;
			if (is_null($parent->prev_sibling()) && is_null($parent->next_sibling()))
			{
				$article_str = str_replace($parent->parent->outertext, '', $article_str);
			}
		}
		return str_get_html($article_str);
	}

}
