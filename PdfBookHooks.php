<?php
class PdfBookHooks {

	/**
	 * Register our pdfbook action and logging type
	 */
	public static function onRegistration() {
		global $wgActions, $wgLogTypes, $wgLogNames, $wgLogHeaders, $wgLogActions;
		$wgActions['pdfbook']     = 'PdfBookAction';
		$wgLogTypes[]             = 'pdf';
		$wgLogNames  ['pdf']      = 'pdflogpage';
		$wgLogHeaders['pdf']      = 'pdflogpagetext';
		$wgLogActions['pdf/book'] = 'pdflogentry';
	}

	/**
	 * Add PDF to actions tabs in skins
	 */
	public static function onSkinTemplateNavigationUniversal( $skin, &$actions ) {
		global $wgPdfBookTab;
		if ( $wgPdfBookTab && $skin->getUser()->isRegistered() ) {
			$actions['views']['pdfbook'] = [
				'class' => false,
				'text' => wfMessage( 'pdfbook-action' )->text(),
				'href' => self::actionLink( $skin )
			];
		}
	}

	/**
	 * Get the URL for the action link
	 */
	public static function actionLink( $skin ) {
		$qs = 'action=pdfbook&format=single';
		foreach ( $_REQUEST as $k => $v ) {
			if ( $k != 'title' && is_string( $v ) ) {
				$qs .= "&$k=$v";
			}
		}
		return $skin->getTitle()->getLocalURL( $qs );
	}
}
