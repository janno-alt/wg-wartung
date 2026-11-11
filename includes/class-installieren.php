<?php
/**
 * Installiert und schaltet WG-Plugins aus der Ferne, aber nur über die WG Suite
 * dieser Seite.
 *
 * WG Wartung lädt dafür selbst nichts herunter. Es fragt die Suite nach ihrem
 * Katalog und lässt sie installieren. Damit gelten genau die Regeln der Suite:
 *
 *  - Nur Plugins, die auf dem WG-Suite-Server für diese Seite freigegeben sind.
 *  - Nur Pakete vom eingestellten Suite-Server.
 *  - Nur Pakete mit gültiger Signatur des Release-Schlüssels, der allein auf
 *    Jannos Mac liegt.
 *
 * Wer den Schlüssel des CRM hätte, könnte so nur echte, signierte WG-Plugins
 * einspielen, die für diese Seite ohnehin erlaubt sind. WG Wartung und die
 * Suite selbst lassen sich auf diesem Weg weder installieren noch abschalten.
 *
 * @package WG_Wartung
 */

defined( 'ABSPATH' ) || exit;

final class WG_Wartung_Installieren {

	/** Diese Ordner fasst der Fernzugriff nie an */
	const GESCHUETZT = array( 'wg-wartung', 'wg-suite' );

	/** Ist die Suite da und mit ihrem Server verbunden? */
	private static function suite_bereit() {
		return class_exists( '\WG\Suite\Client\Client' )
			&& class_exists( '\WG\Suite\Client\Installer' )
			&& \WG\Suite\Client\Client::is_configured();
	}

	/**
	 * Stand der Suite für den Statusbericht.
	 *
	 * @return array
	 */
	public static function suite_stand() {
		if ( ! class_exists( '\WG\Suite\Client\Client' ) ) {
			return array( 'aktiv' => false, 'verbunden' => false, 'katalog' => array() );
		}
		$stand = array(
			'aktiv'     => true,
			'version'   => defined( 'WG_SUITE_VERSION' ) ? (string) WG_SUITE_VERSION : null,
			'verbunden' => \WG\Suite\Client\Client::is_configured(),
			'katalog'   => array(),
		);
		if ( ! $stand['verbunden'] ) {
			return $stand;
		}
		$katalog = \WG\Suite\Client\Client::catalog();
		if ( is_wp_error( $katalog ) ) {
			$stand['fehler'] = wp_strip_all_tags( $katalog->get_error_message() );
			return $stand;
		}
		$stand['katalog'] = array_values( array_map( 'strval', array_keys( (array) $katalog ) ) );
		return $stand;
	}

	/**
	 * Stand von WG Cache für den Statusbericht.
	 *
	 * @return array|null
	 */
	public static function cache_stand() {
		if ( ! class_exists( '\WGCache\Settings' ) ) {
			return null;
		}
		$test = get_transient( 'wg_cache_selftest' );
		return array(
			'eingeschaltet' => (bool) \WGCache\Settings::get( 'enabled' ),
			'selbsttest'    => is_array( $test ) ? array(
				'ok'   => ! empty( $test['ok'] ),
				'text' => wp_strip_all_tags( (string) ( $test['text'] ?? '' ) ),
			) : null,
		);
	}

	/**
	 * Katalogeintrag der Suite zu einem Ordner, wenn er für diese Seite freigegeben ist.
	 *
	 * @param string $ordner Ordnername.
	 * @return array|WP_Error
	 */
	private static function eintrag( $ordner ) {
		if ( in_array( $ordner, self::GESCHUETZT, true ) ) {
			return new WP_Error( 'wg_wartung_geschuetzt', 'WG Wartung und die WG Suite lassen sich aus der Ferne weder installieren noch abschalten.', array( 'status' => 403 ) );
		}
		if ( ! self::suite_bereit() ) {
			return new WP_Error( 'wg_wartung_ohne_suite', 'Die WG Suite ist auf dieser Seite nicht installiert oder nicht mit ihrem Server verbunden.', array( 'status' => 409 ) );
		}
		$katalog = \WG\Suite\Client\Client::catalog();
		if ( is_wp_error( $katalog ) ) {
			return new WP_Error( 'wg_wartung_katalog', 'Die WG Suite konnte ihren Katalog nicht laden: ' . wp_strip_all_tags( $katalog->get_error_message() ), array( 'status' => 502 ) );
		}
		if ( ! isset( $katalog[ $ordner ] ) ) {
			return new WP_Error( 'wg_wartung_nicht_freigegeben', 'Dieses Plugin ist für diese Seite in der WG Suite nicht freigegeben.', array( 'status' => 403 ) );
		}
		return array( 'katalog' => $katalog, 'eintrag' => $katalog[ $ordner ] );
	}

	/**
	 * Installiert und aktiviert ein Plugin aus dem Katalog der Suite.
	 *
	 * @param string $ordner Ordnername wie „wg-cache“.
	 * @return array|WP_Error
	 */
	public static function installieren( $ordner ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		$e = self::eintrag( $ordner );
		if ( is_wp_error( $e ) ) {
			return $e;
		}
		if ( false !== get_transient( WG_Wartung_Aktualisieren::SPERRE ) ) {
			return new WP_Error( 'wg_wartung_belegt', 'Es läuft schon ein Auftrag auf dieser Seite.', array( 'status' => 409 ) );
		}
		set_transient( WG_Wartung_Aktualisieren::SPERRE, 1, 10 * MINUTE_IN_SECONDS );

		try {
			if ( function_exists( 'set_time_limit' ) ) {
				@set_time_limit( 300 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
			}
			$katalog   = $e['katalog'];
			$vorhanden = \WG\Suite\Client\Client::installed( $katalog );
			$neu       = ! isset( $vorhanden[ $ordner ] );

			if ( $neu && empty( $e['eintrag']['reachable'] ) ) {
				$grund = trim( (string) ( $e['eintrag']['reason'] ?? '' ) );
				return new WP_Error( 'wg_wartung_nicht_verfuegbar', 'Die WG Suite bietet dafür gerade keine installierbare Version an. Meist fehlt die Signatur des Pakets oder der Suite-Server erreicht GitHub nicht.' . ( '' !== $grund ? ' Hinweis der Suite: ' . wp_strip_all_tags( $grund ) : '' ), array( 'status' => 409 ) );
			}
			if ( $neu ) {
				// Die Suite prüft Herkunft und Signatur des Pakets in ihren Upgrader-Filtern
				$r = \WG\Suite\Client\Installer::install( $e['eintrag'] );
				if ( is_wp_error( $r ) ) {
					WG_Wartung_Protokoll::eintragen( 'Installation ' . $ordner, $r->get_error_message() );
					return new WP_Error( 'wg_wartung_installation', wp_strip_all_tags( $r->get_error_message() ), array( 'status' => 500 ) );
				}
				wp_clean_plugins_cache( true );
				$vorhanden = \WG\Suite\Client\Client::installed( $katalog );
			}
			if ( ! isset( $vorhanden[ $ordner ] ) ) {
				return new WP_Error( 'wg_wartung_installation', 'Nach der Installation ist das Plugin nicht zu finden.', array( 'status' => 500 ) );
			}
			$datei = $vorhanden[ $ordner ]['file'];

			if ( ! is_plugin_active( $datei ) ) {
				$a = \WG\Suite\Client\Installer::activate( $datei );
				if ( is_wp_error( $a ) ) {
					WG_Wartung_Protokoll::eintragen( 'Aktivierung ' . $ordner, $a->get_error_message() );
					return new WP_Error( 'wg_wartung_aktivierung', 'Installiert, aber nicht aktiviert: ' . wp_strip_all_tags( $a->get_error_message() ), array( 'status' => 500 ) );
				}
			}

			$ergebnis = array(
				'ordner'  => $ordner,
				'datei'   => $datei,
				'version' => (string) $vorhanden[ $ordner ]['version'],
				'neu'     => $neu,
				'aktiv'   => is_plugin_active( $datei ),
			);
			WG_Wartung_Protokoll::eintragen( ( $neu ? 'Installiert ' : 'Aktiviert ' ) . $ordner, $ergebnis['version'] );
			return $ergebnis;
		} finally {
			delete_transient( WG_Wartung_Aktualisieren::SPERRE );
		}
	}

	/**
	 * Schaltet ein Plugin aus dem Katalog der Suite ab, ohne es zu löschen.
	 *
	 * @param string $ordner Ordnername.
	 * @return array|WP_Error
	 */
	public static function abschalten( $ordner ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		$e = self::eintrag( $ordner );
		if ( is_wp_error( $e ) ) {
			return $e;
		}
		$vorhanden = \WG\Suite\Client\Client::installed( $e['katalog'] );
		if ( ! isset( $vorhanden[ $ordner ] ) ) {
			return array( 'ordner' => $ordner, 'aktiv' => false, 'meldung' => 'Nicht installiert.' );
		}
		$datei = $vorhanden[ $ordner ]['file'];
		if ( is_plugin_active( $datei ) ) {
			\WG\Suite\Client\Installer::deactivate( $datei );
		}
		$aktiv = is_plugin_active( $datei );
		WG_Wartung_Protokoll::eintragen( 'Abgeschaltet ' . $ordner, $aktiv ? 'fehlgeschlagen' : 'ok' );
		return array( 'ordner' => $ordner, 'aktiv' => $aktiv );
	}

	/**
	 * Selbsttest von WG Cache: Kommt die Startseite aus dem Cache und ist sie lesbar?
	 *
	 * @return array|WP_Error
	 */
	public static function cache_selbsttest() {
		if ( ! class_exists( '\WGCache\SelfTest' ) ) {
			return new WP_Error( 'wg_wartung_ohne_cache', 'WG Cache ist auf dieser Seite nicht aktiv.', array( 'status' => 409 ) );
		}
		$r = \WGCache\SelfTest::run();
		return array(
			'ok'     => ! empty( $r['ok'] ),
			'text'   => wp_strip_all_tags( (string) ( $r['text'] ?? '' ) ),
			'zeilen' => array_map( 'wp_strip_all_tags', array_map( 'strval', (array) ( $r['lines'] ?? array() ) ) ),
		);
	}
}
