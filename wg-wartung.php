<?php
/**
 * Plugin Name: WG Wartung
 * Description: Verbindung zum Servicebereich des WG-Digital-CRM. Meldet den Stand von WordPress, PHP und Plugins, spielt Plugin-Updates ein und installiert über die WG Suite freigegebene WG-Plugins, wenn das CRM das mit seinem Schlüssel signiert anfordert. Ohne gültige Signatur antwortet das Plugin nicht.
 * Version: 0.1.0
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author: WG-Digital
 * Update URI: https://wg-digitalmarketing.de/wg-suite/wg-wartung
 */

defined( 'ABSPATH' ) || exit;

define( 'WG_WARTUNG_VERSION', '0.1.0' );

/*
 * Öffentlicher Schlüssel des CRM unter crm.wg-digital.xyz (Ed25519, Base64),
 * übernommen am 11.11.2026 aus Service > Plugins > „WG Wartung“. Der geheime
 * Teil liegt nur im CRM und verlässt es nie. Wer diese Datei sieht, kann damit
 * nichts anfangen: Mit dem öffentlichen Schlüssel lässt sich eine Signatur nur
 * prüfen, nicht erzeugen.
 *
 * Bekommt das CRM einen neuen Schlüssel, braucht es eine neue Version dieses
 * Plugins. Für Tests lässt er sich in der wp-config.php vorab festlegen. Wer
 * dort schreiben kann, hat die Seite ohnehin in der Hand.
 */
if ( ! defined( 'WG_WARTUNG_CRM_SCHLUESSEL' ) ) {
	define( 'WG_WARTUNG_CRM_SCHLUESSEL', '36Eg7HdVqRMS03HfjdUzI70PQcweXrQOdH6tpIi+kCY=' );
}

require_once __DIR__ . '/includes/class-signatur.php';
require_once __DIR__ . '/includes/class-protokoll.php';
require_once __DIR__ . '/includes/class-bestand.php';
require_once __DIR__ . '/includes/class-aktualisieren.php';
require_once __DIR__ . '/includes/class-installieren.php';
require_once __DIR__ . '/includes/class-rest.php';
require_once __DIR__ . '/includes/class-seite.php';

add_action( 'rest_api_init', array( 'WG_Wartung_Rest', 'registrieren' ) );
add_action( 'admin_menu', array( 'WG_Wartung_Seite', 'menue' ) );
