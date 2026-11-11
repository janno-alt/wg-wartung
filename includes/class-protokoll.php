<?php
/**
 * Kurzes Protokoll der letzten Aufträge, sichtbar unter Werkzeuge > WG Wartung.
 *
 * @package WG_Wartung
 */

defined( 'ABSPATH' ) || exit;

final class WG_Wartung_Protokoll {

	const OPTION = 'wg_wartung_protokoll';
	const MAX    = 30;

	public static function eintragen( $aktion, $ergebnis ) {
		$liste = get_option( self::OPTION, array() );
		if ( ! is_array( $liste ) ) {
			$liste = array();
		}
		array_unshift(
			$liste,
			array(
				'zeit'     => gmdate( 'c' ),
				'aktion'   => self::kuerzen( sanitize_text_field( (string) $aktion ), 100 ),
				'ergebnis' => self::kuerzen( sanitize_text_field( (string) $ergebnis ), 300 ),
			)
		);
		update_option( self::OPTION, array_slice( $liste, 0, self::MAX ), false );
	}

	/** Kürzt, ohne ein Umlaut-Zeichen zu zerschneiden (sonst zeigt esc_html gar nichts) */
	private static function kuerzen( $text, $max ) {
		return function_exists( 'mb_substr' ) ? mb_substr( $text, 0, $max, 'UTF-8' ) : substr( $text, 0, $max );
	}

	public static function liste() {
		$liste = get_option( self::OPTION, array() );
		return is_array( $liste ) ? $liste : array();
	}
}
