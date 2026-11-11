<?php
/**
 * Infoseite unter Werkzeuge > WG Wartung: Ist das CRM eingerichtet, und was
 * hat es zuletzt getan? Hier lässt sich nichts einstellen.
 *
 * @package WG_Wartung
 */

defined( 'ABSPATH' ) || exit;

final class WG_Wartung_Seite {

	public static function menue() {
		add_management_page( 'WG Wartung', 'WG Wartung', 'manage_options', 'wg-wartung', array( __CLASS__, 'anzeigen' ) );
	}

	public static function anzeigen() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		echo '<div class="wrap"><h1>WG Wartung</h1>';
		if ( WG_Wartung_Signatur::eingerichtet() ) {
			echo '<p>Diese Seite nimmt Aufträge des WG-Digital-CRM an, wenn sie mit dessen Schlüssel signiert sind. Fingerabdruck des Schlüssels: <code>' . esc_html( WG_Wartung_Signatur::fingerabdruck() ) . '</code></p>';
		} else {
			echo '<div class="notice notice-warning"><p>Es ist noch kein Schlüssel des CRM hinterlegt. Bis dahin nimmt diese Seite keine Aufträge an.</p></div>';
		}
		$liste = WG_Wartung_Protokoll::liste();
		echo '<h2>Letzte Aufträge</h2>';
		if ( ! $liste ) {
			echo '<p>Noch keine.</p>';
		} else {
			echo '<table class="widefat striped"><thead><tr><th>Zeit (UTC)</th><th>Auftrag</th><th>Ergebnis</th></tr></thead><tbody>';
			foreach ( $liste as $e ) {
				echo '<tr><td>' . esc_html( (string) ( $e['zeit'] ?? '' ) ) . '</td><td>' . esc_html( (string) ( $e['aktion'] ?? '' ) ) . '</td><td>' . esc_html( (string) ( $e['ergebnis'] ?? '' ) ) . '</td></tr>';
			}
			echo '</tbody></table>';
		}
		echo '</div>';
	}
}
