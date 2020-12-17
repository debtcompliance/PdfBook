<?php
class PdfBookAction extends Action {

	public function getName() {
		return 'pdfbook';
	}

	/**
	 * Perform the export operation
	 */
	public function show() {
		global $wgPdfBookDownload, $wgParser, $wgServer, $wgScript,
			$wgArticlePath, $wgScriptPath, $wgUploadPath, $wgUploadDirectory;
		$user = $this->getUser();
		$output = $this->getOutput();
		$title = $this->getTitle();
		$page = WikiPage::factory( $title );
		$book = $title->getText();
		$opt = ParserOptions::newFromUser( $user );
		$parser = $wgParser->getFreshParser();

		// Log the export
		$msg = wfMessage( 'pdfbook-log', $user->getUserPage()->getPrefixedText() )->text();
		$log = new LogPage( 'pdf', false );
		$log->addEntry( 'book', $title, $msg, [], $user);

		// Initialise PDF variables
		$format    = $this->setProperty( 'format', '', '' );
		$nothumbs  = $this->setProperty( 'nothumbs', '', '' );
		$notitle   = $this->setProperty( 'notitle', '', '' );
		$layout    = $format == 'single' ? '--webpage' : '--firstpage toc';
		$charset   = $this->setProperty( 'Charset',     'iso-8859-1' );
		$left      = $this->setProperty( 'LeftMargin',  '1cm' );
		$right     = $this->setProperty( 'RightMargin', '1cm' );
		$top       = $this->setProperty( 'TopMargin',   '1cm' );
		$bottom    = $this->setProperty( 'BottomMargin','1cm' );
		$font      = $this->setProperty( 'Font',        'Arial' );
		$size      = $this->setProperty( 'FontSize',    '8' );
		$ls        = $this->setProperty( 'FontSpacing', 1.5 );
		$linkcol   = $this->setProperty( 'LinkColour',  '217A28' );
		$levels    = $this->setProperty( 'TocLevels',   '2' );
		$exclude   = $this->setProperty( 'Exclude',     array() );
		$width     = $this->setProperty( 'Width',       '' );
		$numbering = $this->setProperty( 'Numbering', 'yes' );
		$options   = $this->setProperty( 'Options',     '' );
		$width     = $width ? "--browserwidth $width" : '';
		$comments  = ExtensionRegistry::getInstance()->isLoaded( 'AjaxComments' )
			? $this->setProperty( 'comments', '', false )
			: '';

		if( !is_array( $exclude ) ) $exclude = preg_split( '\\s*,\\s*', $exclude );

		// Generate a list of the articles involved in this doc
		// - this is unconditional so that it can be used in cache key generation

		// Select articles from members if a category or links in content if not
		if( $format == 'single' || $format == 'html' ) $articles = array( $title );
		else {
			$articles = array();
			if( $title->getNamespace() == NS_CATEGORY ) {
				$db     = wfGetDB( DB_REPLICA );
				$cat    = $db->addQuotes( $title->getDBkey() );
				$result = $db->select(
					'categorylinks',
					'cl_from',
					"cl_to = $cat",
					'PdfBook',
					array( 'ORDER BY' => 'cl_sortkey' )
				);
				if( $result instanceof IResultWrapper ) $result = $result->result;
				while( $row = $db->fetchRow( $result ) ) $articles[] = Title::newFromID( $row[0] );
			} else {
				$text = $page->getContent()->getNativeData();
				$text = $parser->preprocess( $text, $title, $opt );
				if( preg_match_all( "/^\\*\\s*\\[{2}\\s*([^\\|\\]]+)\\s*.*?\\]{2}/m", $text, $links ) ) {
					foreach( $links[1] as $link ) $articles[] = Title::newFromText( $link );
				}
			}
		}

		// Create a cache filename from the hash of...
		$cache = json_encode( $_GET ); // the query-string of the request,
		$cache .= file_get_contents( __FILE__ ); // the contents of the rendering code (this script),
		foreach( $articles as $title ) $cache .= '-' . $title->getLatestRevID(); // and the latest revision(s) of the article(s)
		$cache = $wgUploadDirectory . '/pdf-book-cache-' . md5( $cache );

		// If the file doesn't exist, render the content now
		if( !file_exists( $cache ) ) {

			// Format the article(s) as a single HTML document with absolute URL's
			$html = '';
			$wgArticlePath = $wgServer . $wgArticlePath;
			$wgPdfBookTab = false;
			$wgScriptPath = $wgServer . $wgScriptPath;
			$wgUploadPath = $wgServer . $wgUploadPath;
			$wgScript = $wgServer . $wgScript;
			foreach( $articles as $title ) {
				$page = WikiPage::factory( $title );
				$ttext = $title->getPrefixedText();
				$turl = $title->getFullUrl();
				if( !in_array( $ttext, $exclude ) ) {
					$text = $page->getContent()->getNativeData();
					$text = preg_replace( "/<!--([^@]+?)-->/s", "@@" . "@@$1@@" . "@@", $text ); // preserve HTML comments
					$out = $parser->parse( $text, $title, $opt, true, true );
					$text = $out->getText([
							'allowTOC' => $format == 'single', // generate TOC if enough headings and format not 'single'
							'enableSectionEditLinks' => false,  // remove section-edit links
					]);
					if( $format == 'html' ) {
						$text = preg_replace( "|(<img[^>]+?src=\")(?=/)|", "$1$wgServer", $text ); // make image urls absolute
					} else {
						$pUrl = parse_url( $wgScriptPath );
						$imgpath = str_replace( '/' , '\/', $pUrl['path'] . '/' . basename( $wgUploadDirectory ) ); // the image's path
						$text = preg_replace( '| src="' . $imgpath. '([^"]*)|', ' src="' .$wgUploadDirectory . '$1', $text );
					}
					if( $nothumbs == 'true' ) $text = preg_replace( "|images/thumb/(\w+/\w+/[\w\.\-]+).*\"|", "images/$1\"", $text );   // Convert image links from thumbnail to full-size
					$text = preg_replace( "|<div\s*class=['\"]?noprint[\"']?>.+?</div>|s", "", $text ); // non-printable areas
					$text = preg_replace( "|@{4}([^@]+?)@{4}|s", "<!--$1-->", $text );                  // HTML comments hack
					$text = preg_replace_callback(
						"|<span[^>]+class=\"mw-headline\"[^>]*>(.+?)</span>|",
						function( $m ) {
							return preg_match( '|id="(.+?)"|', $m[0], $n ) ? "<a name=\"$n[1]\">$m[1]</a>" : $m[0];
						},
						$text ); // Make the doc heading spans in to A tags
					$ttext = basename( $ttext );
					$h1 = $notitle ? "" : "<center><h1>$ttext</h1></center>";

					// Add comments if selected and AjaxComments is installed
					$commentsForPDF = '';
					if( $comments ) {
						$commentResponse = AjaxComments::singleton()->getComments( $title->getArticleID() );
						foreach( $commentResponse as $comment ) {
							$commentsForPDF .= $comment['html'];
						}
					}
					$html .= utf8_decode( "$h1$text\n$commentsForPDF" );
				}
			}

			// Build the cache file
			if( $format == 'html' ) file_put_contents( $cache, $html );
			else {

				// Write the HTML to a tmp file
				if( !is_dir( $wgUploadDirectory ) ) mkdir( $wgUploadDirectory );
				$file = $wgUploadDirectory . '/' . uniqid( 'pdf-book' );
				file_put_contents( $file, $html );

				// Build the htmldoc command
				$numbering = $numbering == 'yes' ? '--numbered' : '';
				$footer = $format == 'single' ? "..." : ".1.";
				$toc = $format == 'single' ? "" : " --toclevels $levels";
				$cmd  = "--left $left --right $right --top $top --bottom $bottom"
					. " --header ... --footer $footer --headfootsize 8 --quiet --jpeg --color"
					. " --bodyfont $font --fontsize $size --fontspacing $ls --linkstyle plain --linkcolor $linkcol"
					. "$toc --no-title $numbering --charset $charset $options $layout $width";
				$cmd = $format == 'htmltoc'
					? "htmldoc -t html --format html $cmd \"$file\" "
					: "htmldoc -t pdf --format pdf14 $cmd \"$file\" ";

				// Execute the command outputting to the cache file
				putenv( "HTMLDOC_NOCGI=1" );
				shell_exec( "$cmd > \"$cache\"" );
				unlink( $file );
			}
		}

		// Output the cache file
		$output->disable();
		if( $format == 'html' || $format == 'htmltoc' ) {
			header( "Content-Type: text/html" );
			header( "Content-Disposition: attachment; filename=\"$book.html\"" );
		} else {
			header( "Content-Type: application/pdf" );
			if( $wgPdfBookDownload ) header( "Content-Disposition: attachment; filename=\"$book.pdf\"" );
			else header( "Content-Disposition: inline; filename=\"$book.pdf\"" );
		}
		readfile( $cache );
	}

	/**
	 * Return a sanitised property for htmldoc using global, request or passed default
	 */
	private function setProperty( $name, $val, $prefix = 'pdf' ) {
		$request = $this->getRequest();
		if( $request->getText( "$prefix$name" ) ) $val = $request->getText( "$prefix$name" );
		if( $request->getText( "amp;$prefix$name" ) ) $val = $request->getText( "amp;$prefix$name" ); // hack to handle ampersand entities in URL
		if( isset( $GLOBALS["wgPdfBook$name"] ) ) $val = $GLOBALS["wgPdfBook$name"];
		return preg_replace( '|[^-_:.a-z0-9]|i', '', $val );
	}
}
