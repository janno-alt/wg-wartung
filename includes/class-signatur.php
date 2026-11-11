<?php
/**
 * Prüft, ob eine Anfrage vom CRM kommt.
 *
 * Das CRM signiert mit Ed25519 diese Nachricht:
 *
 *     wg-wartung-v1
 *     <Methode, etwa POST>
 *     <Host der Seite, etwa firma.de>
 *     <Route, etwa /wg-wartung/v1/status>
 *     <Zeit in Sekunden seit 1970>
 *     <Einmal-Kennung, 32 Hex-Zeichen>
 *     <SHA-256 des Inhalts, hex>
 *
 * Damit gilt eine Signatur nur für genau diese Seite, diesen Auftrag und
 * diesen Inhalt, nur fünf Minuten lang und nur einmal. Ein mitgeschnittener
 * Auftrag lässt sich weder wiederholen noch an eine andere Seite schicken.
 *
 * @package WG_Wartung
 */

defined( 'ABSPATH' ) || exit;

final class WG_Wartung_Signatur {

	/** Wie weit die Uhr des CRM von der Uhr der Seite abweichen darf, in Sekunden */
	const FENSTER = 300;

	/** Ist ein gültiger öffentlicher Schlüssel hinterlegt? */
	public static function eingerichtet() {
		return null !== self::schluessel();
	}

	/** Kurzer Fingerabdruck des Schlüssels für die Anzeige im Backend */
	public static function fingerabdruck() {
		$s = self::schluessel();
		return $s ? substr( hash( 'sha256', $s ), 0, 16 ) : '';
	}

	private static function schluessel() {
		$roh = base64_decode( (string) WG_WARTUNG_CRM_SCHLUESSEL, true );
		return ( is_string( $roh ) && 32 === strlen( $roh ) ) ? $roh : null;
	}

	/**
	 * Hosts, unter denen die Seite angesprochen werden darf: der eigene, mit
	 * und ohne „www.“. Andere Hosts gelten nicht, auch wenn die Signatur
	 * sonst stimmen würde.
	 */
	private static function hosts() {
		$host = strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) );
		if ( '' === $host ) {
			return array();
		}
		$ohne = preg_replace( '/^www\./', '', $host );
		return array_values( array_unique( array( $host, $ohne, 'www.' . $ohne ) ) );
	}

	/**
	 * @param WP_REST_Request $anfrage
	 * @return bool
	 */
	public static function pruefen( $anfrage ) {
		$schluessel = self::schluessel();
		if ( null === $schluessel || ! function_exists( 'sodium_crypto_sign_verify_detached' ) ) {
			return false;
		}

		$zeit      = (string) $anfrage->get_header( 'x_wg_zeit' );
		$nonce     = (string) $anfrage->get_header( 'x_wg_nonce' );
		$signatur  = base64_decode( (string) $anfrage->get_header( 'x_wg_signatur' ), true );

		if ( ! ctype_digit( $zeit ) || abs( time() - (int) $zeit ) > self::FENSTER ) {
			return false;
		}
		if ( ! preg_match( '/^[a-f0-9]{32}$/', $nonce ) ) {
			return false;
		}
		if ( ! is_string( $signatur ) || 64 !== strlen( $signatur ) ) {
			return false;
		}

		$inhalt  = (string) $anfrage->get_body();
		$methode = strtoupper( $anfrage->get_method() );
		$route   = $anfrage->get_route();

		$gueltig = false;
		foreach ( self::hosts() as $host ) {
			$nachricht = implode( "\n", array( 'wg-wartung-v1', $methode, $host, $route, $zeit, $nonce, hash( 'sha256', $inhalt ) ) );
			try {
				if ( sodium_crypto_sign_verify_detached( $signatur, $nachricht, $schluessel ) ) {
					$gueltig = true;
					break;
				}
			} catch ( Exception $e ) {
				return false;
			}
		}
		if ( ! $gueltig ) {
			return false;
		}

		// Jede Einmal-Kennung nur einmal, solange die Signatur zeitlich gelten könnte
		$merker = 'wg_wartung_n_' . $nonce;
		if ( false !== get_transient( $merker ) ) {
			return false;
		}
		set_transient( $merker, 1, 2 * self::FENSTER );
		return true;
	}
}
