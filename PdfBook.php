<?php
/**
 * PdfBook extension
 * - Composes a book from articles in a category and exports as a PDF book
 *
 * See http://www.mediawiki.org/Extension:PdfBook for installation and usage details
 * See http://www.organicdesign.co.nz/Extension_talk:PdfBook for development notes and disucssion
 *
 * Started: 2007-08-08
 * 
 * @file
 * @ingroup Extensions
 * @author Aran Dunkley (http://www.organicdesign.co.nz/aran)
 * @copyright © 2007-2015 Aran Dunkley
 * @licence GNU General Public Licence 2.0 or later
 */
if( !defined( 'MEDIAWIKI' ) ) die( "Not an entry point." );

define( 'PDFBOOK_VERSION', "1.2.4, 2015-06-18" );

$dir = dirname( __FILE__ );
$wgAutoloadClasses['PdfBookHooks'] = $dir . '/PdfBook.hooks.php';
$wgExtensionMessagesFiles['PdfBook'] = $dir . '/PdfBook.i18n.php';

$wgExtensionCredits['parserhook'][] = array(
	'path'           => __FILE__,
	'name'           => "PdfBook",
	'author'         => "[http://www.organicdesign.co.nz/aran Aran Dunkley]",
	'url'            => "http://www.mediawiki.org/wiki/Extension:PdfBook",
	'version'        => PDFBOOK_VERSION,
	'descriptionmsg' => 'pdfbook-desc',
);

// Whether or not an action tab is wanted for printing to PDF
$wgPdfBookTab = false;

// Whether the files should be downloaded or view in-browser
$wgPdfBookDownload = true;

$wgHooks['UnknownAction'][] = 'PdfBookHooks::onUnknownAction';

// Hooks for pre-Vector and Vector addtabs.
$wgHooks['SkinTemplateTabs'][] = 'PdfBookHooks::onSkinTemplateTabs';
$wgHooks['SkinTemplateNavigation'][] = 'PdfBookHooks::onSkinTemplateNavigation';

// Add a new pdf log type
$wgLogTypes[]             = 'pdf';
$wgLogNames  ['pdf']      = 'pdflogpage';
$wgLogHeaders['pdf']      = 'pdflogpagetext';
$wgLogActions['pdf/book'] = 'pdflogentry';
