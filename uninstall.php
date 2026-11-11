<?php
/**
 * Beim Löschen des Plugins das Protokoll entfernen.
 *
 * @package WG_Wartung
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'wg_wartung_protokoll' );
delete_transient( 'wg_wartung_sperre' );
