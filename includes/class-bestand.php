<?php
/**
 * Stand der Seite für das CRM: WordPress, PHP, Plugins mit verfügbaren
 * Updates, Themes. Nur lesend. Fragt vorher bei WordPress.org und den
 * Update-Quellen der Plugins nach, damit die Liste der Updates frisch ist.
 *
 * @package WG_Wartung
 */

defined( 'ABSPATH' ) || exit;

final class WG_Wartung_Bestand {

	public static function erfassen() {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		require_once ABSPATH . 'wp-admin/includes/update.php';
		require_once ABSPATH . 'wp-admin/includes/theme.php';

		// Frische Update-Informationen holen (eine Anfrage an WordPress.org)
		delete_site_transient( 'update_plugins' );
		wp_update_plugins();
		wp_version_check();
		wp_update_themes();

		$updates = get_site_transient( 'update_plugins' );
		$antwort = ( is_object( $updates ) && isset( $updates->response ) && is_array( $updates->response ) ) ? $updates->response : array();

		$plugins = array();
		foreach ( get_plugins() as $datei => $daten ) {
			$ordner = dirname( $datei );
			$upd    = isset( $antwort[ $datei ] ) ? $antwort[ $datei ] : null;
			$plugins[] = array(
				'datei'      => $datei,
				'slug'       => '.' === $ordner ? basename( $datei, '.php' ) : $ordner,
				'name'       => wp_strip_all_tags( (string) $daten['Name'] ),
				'version'    => (string) $daten['Version'],
				'autor'      => wp_strip_all_tags( (string) $daten['AuthorName'] ),
				'aktiv'      => is_plugin_active( $datei ),
				'update'     => null !== $upd,
				'neu'        => ( $upd && isset( $upd->new_version ) ) ? (string) $upd->new_version : null,
				// Ohne Paket-Adresse (meist fehlende Lizenz) lässt sich das Update nicht einspielen
				'paket'      => ( $upd && ! empty( $upd->package ) ),
			);
		}

		$kern       = null;
		$angebote   = function_exists( 'get_core_updates' ) ? get_core_updates() : array();
		if ( is_array( $angebote ) ) {
			foreach ( $angebote as $a ) {
				if ( isset( $a->response ) && 'upgrade' === $a->response ) {
					$kern = (string) $a->current;
					break;
				}
			}
		}

		$theme = wp_get_theme();

		return array(
			'plugin'  => 'wg-wartung',
			'version' => WG_WARTUNG_VERSION,
			'home'    => home_url(),
			'wp'      => get_bloginfo( 'version' ),
			'php'     => PHP_VERSION,
			'kern'    => $kern,
			'theme'   => array(
				'name'    => wp_strip_all_tags( (string) $theme->get( 'Name' ) ),
				'version' => (string) $theme->get( 'Version' ),
			),
			'plugins' => $plugins,
			// Was diese Version kann, damit das CRM keine Versionsnummern vergleichen muss
			'kann'    => array( 'status', 'plugin-update', 'plugin-install', 'plugin-deactivate', 'cache-selbsttest' ),
			'suite'   => WG_Wartung_Installieren::suite_stand(),
			'cache'   => WG_Wartung_Installieren::cache_stand(),
			'zeit'    => gmdate( 'c' ),
		);
	}
}
