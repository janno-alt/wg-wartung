<?php
/**
 * Spielt das Update genau eines Plugins ein und schaltet es danach wieder
 * ein, wenn es vorher aktiv war.
 *
 * Bewusst je Aufruf nur ein Plugin: So läuft keine Anfrage in die Zeitgrenze
 * des Servers, und das CRM kann nach jedem Plugin die Seite prüfen und den
 * Lauf anhalten, bevor das nächste kommt.
 *
 * Eingespielt wird nur, was WordPress selbst als Update kennt, also aus
 * WordPress.org oder der hinterlegten Update-Quelle des Plugins (bei
 * Lizenz-Plugins mit deren Lizenz). Fremden Code kann das CRM darüber nicht
 * auf die Seite bringen.
 *
 * @package WG_Wartung
 */

defined( 'ABSPATH' ) || exit;

final class WG_Wartung_Aktualisieren {

	const SPERRE = 'wg_wartung_sperre';

	/**
	 * @param string $datei Plugin-Datei wie „wordpress-seo/wp-seo.php“
	 * @return array|WP_Error
	 */
	public static function plugin( $datei ) {
		if ( false !== get_transient( self::SPERRE ) ) {
			return new WP_Error( 'wg_wartung_belegt', 'Es läuft schon ein Update auf dieser Seite.', array( 'status' => 409 ) );
		}
		set_transient( self::SPERRE, 1, 10 * MINUTE_IN_SECONDS );

		try {
			return self::einspielen( $datei );
		} finally {
			delete_transient( self::SPERRE );
		}
	}

	private static function einspielen( $datei ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/misc.php';
		require_once ABSPATH . 'wp-admin/includes/update.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 300 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		}

		$alle = get_plugins();
		if ( ! isset( $alle[ $datei ] ) ) {
			return new WP_Error( 'wg_wartung_unbekannt', 'Dieses Plugin ist auf der Seite nicht installiert.', array( 'status' => 404 ) );
		}
		$von = (string) $alle[ $datei ]['Version'];

		// Frische Update-Informationen, damit nur eingespielt wird, was es wirklich gibt
		delete_site_transient( 'update_plugins' );
		wp_update_plugins();
		$updates = get_site_transient( 'update_plugins' );
		$angebot = ( is_object( $updates ) && isset( $updates->response[ $datei ] ) ) ? $updates->response[ $datei ] : null;
		if ( null === $angebot ) {
			return array( 'datei' => $datei, 'von' => $von, 'nach' => $von, 'ok' => true, 'meldung' => 'Kein Update verfügbar.' );
		}
		if ( empty( $angebot->package ) ) {
			return array( 'datei' => $datei, 'von' => $von, 'nach' => $von, 'ok' => false, 'meldung' => 'Update ohne Paket, meist fehlt die Lizenz. Bitte im Backend einspielen.' );
		}

		if ( ! WP_Filesystem() ) {
			return new WP_Error( 'wg_wartung_dateien', 'WordPress hat keinen direkten Schreibzugriff auf die Dateien.', array( 'status' => 500 ) );
		}

		$war_aktiv = is_plugin_active( $datei );
		$netzweit  = is_multisite() && is_plugin_active_for_network( $datei );

		$skin      = new WP_Ajax_Upgrader_Skin();
		$upgrader  = new Plugin_Upgrader( $skin );
		$ergebnis  = $upgrader->upgrade( $datei );

		wp_clean_plugins_cache( true );
		$alle_neu = get_plugins();
		$nach     = isset( $alle_neu[ $datei ] ) ? (string) $alle_neu[ $datei ]['Version'] : '';

		$fehler = array();
		if ( is_wp_error( $ergebnis ) ) {
			$fehler[] = $ergebnis->get_error_message();
		} elseif ( true !== $ergebnis ) {
			// Liefert die gesammelten Meldungen als einen Text
			$meldungen = (string) $skin->get_error_messages();
			if ( '' !== $meldungen ) {
				$fehler[] = $meldungen;
			}
			if ( ! $fehler ) {
				$fehler[] = 'Das Update wurde nicht eingespielt.';
			}
		}

		// Nach dem Update ist das Plugin oft ausgeschaltet: wieder einschalten, wenn es vorher lief
		$reaktiviert = false;
		if ( $war_aktiv && '' !== $nach && ! is_plugin_active( $datei ) ) {
			$a = activate_plugin( $datei, '', $netzweit );
			if ( is_wp_error( $a ) ) {
				$fehler[] = 'Wieder einschalten fehlgeschlagen: ' . $a->get_error_message();
			} else {
				$reaktiviert = true;
			}
		}

		$ok = ! $fehler && '' !== $nach && version_compare( $nach, $von, '>' );
		WG_Wartung_Protokoll::eintragen( 'Update ' . $datei, $ok ? $von . ' → ' . $nach : implode( ' ', $fehler ) );

		return array(
			'datei'       => $datei,
			'von'         => $von,
			'nach'        => '' !== $nach ? $nach : null,
			'ok'          => $ok,
			'aktiv'       => is_plugin_active( $datei ),
			'reaktiviert' => $reaktiviert,
			'meldung'     => $fehler ? implode( ' ', array_map( 'wp_strip_all_tags', $fehler ) ) : null,
		);
	}
}
