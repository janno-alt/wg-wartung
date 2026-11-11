<?php
/**
 * Schnittstelle für das CRM. Jede Route prüft die Signatur, bevor irgendetwas
 * passiert; ohne gültige Signatur gibt es nur „nicht erlaubt“.
 *
 * @package WG_Wartung
 */

defined( 'ABSPATH' ) || exit;

final class WG_Wartung_Rest {

	const NAMENSRAUM = 'wg-wartung/v1';

	public static function registrieren() {
		register_rest_route(
			self::NAMENSRAUM,
			'/status',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'status' ),
				'permission_callback' => array( 'WG_Wartung_Signatur', 'pruefen' ),
			)
		);
		register_rest_route(
			self::NAMENSRAUM,
			'/plugin-update',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'plugin_update' ),
				'permission_callback' => array( 'WG_Wartung_Signatur', 'pruefen' ),
			)
		);
	}

	public static function status() {
		$bestand = WG_Wartung_Bestand::erfassen();
		WG_Wartung_Protokoll::eintragen( 'Stand abgefragt', count( $bestand['plugins'] ) . ' Plugins' );
		return rest_ensure_response( $bestand );
	}

	/**
	 * @param WP_REST_Request $anfrage
	 */
	public static function plugin_update( $anfrage ) {
		$daten = json_decode( (string) $anfrage->get_body(), true );
		$datei = is_array( $daten ) && isset( $daten['datei'] ) ? (string) $daten['datei'] : '';
		// Nur „ordner/datei.php“ oder „datei.php“, keine Pfadsprünge
		if ( ! preg_match( '#^[A-Za-z0-9._-]+(/[A-Za-z0-9._-]+)?\.php$#', $datei ) || false !== strpos( $datei, '..' ) ) {
			return new WP_Error( 'wg_wartung_eingabe', 'Ungültige Plugin-Datei.', array( 'status' => 400 ) );
		}
		$ergebnis = WG_Wartung_Aktualisieren::plugin( $datei );
		return is_wp_error( $ergebnis ) ? $ergebnis : rest_ensure_response( $ergebnis );
	}
}
