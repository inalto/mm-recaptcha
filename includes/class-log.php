<?php
/**
 * Registro dei fallimenti captcha (opzione circolare, nessuna tabella custom).
 *
 * @package MM_Recaptcha
 */

namespace MM_Recaptcha;

defined( 'ABSPATH' ) || exit;

class Log {

	const KEY = 'mm_recaptcha_log';

	/**
	 * Aggiunge una voce al registro.
	 *
	 * @param string $context Superficie protetta (es. wp_login).
	 * @param string $code    Codice errore.
	 * @param string $message Messaggio leggibile.
	 */
	public static function add( $context, $code, $message = '' ) {
		if ( ! Options::get( 'log_failures', 1 ) ) {
			return;
		}

		$entries = self::all();

		array_unshift(
			$entries,
			array(
				'time'     => time(),
				'ip'       => self::ip(),
				'context'  => sanitize_key( $context ),
				'provider' => (string) Options::get( 'provider', '' ),
				'code'     => sanitize_text_field( $code ),
				'message'  => sanitize_text_field( $message ),
				'ua'       => isset( $_SERVER['HTTP_USER_AGENT'] ) ? substr( sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ), 0, 180 ) : '',
			)
		);

		$max     = (int) Options::get( 'log_max', 100 );
		$entries = array_slice( $entries, 0, max( 10, $max ) );

		update_option( self::KEY, $entries, false );
	}

	/**
	 * Tutte le voci.
	 */
	public static function all() {
		$entries = get_option( self::KEY, array() );
		return is_array( $entries ) ? $entries : array();
	}

	/**
	 * Svuota il registro.
	 */
	public static function clear() {
		delete_option( self::KEY );
	}

	/**
	 * IP del client, con supporto ai proxy più comuni.
	 */
	public static function ip() {
		$candidates = array( 'HTTP_CF_CONNECTING_IP', 'HTTP_X_REAL_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR' );

		foreach ( $candidates as $header ) {
			if ( empty( $_SERVER[ $header ] ) ) {
				continue;
			}

			$value = sanitize_text_field( wp_unslash( $_SERVER[ $header ] ) );
			$parts = explode( ',', $value );
			$ip    = trim( $parts[0] );

			if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
				return $ip;
			}
		}

		return '';
	}
}
