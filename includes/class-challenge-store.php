<?php
/**
 * Sfide del captcha matematico.
 *
 * La sfida viaggia nel modulo come payload firmato (HMAC): la verifica è
 * quindi autosufficiente e non dipende dalla cache oggetti. I transient
 * servono solo a impedire il riuso dello stesso invio: se la cache viene
 * persa si perde la protezione dal riuso, non l'accesso al sito.
 *
 * @package MM_Recaptcha
 */

namespace MM_Recaptcha;

defined( 'ABSPATH' ) || exit;

class Challenge_Store {

	const USED_PREFIX  = 'mm_rc_used_';
	const TOKEN_PREFIX = 'mm_rc_tk_';

	/**
	 * Crea una nuova sfida.
	 *
	 * @return array{id:string,question:string}
	 */
	public static function create() {
		$ops = array( '+', '-', 'x' );
		$op  = $ops[ wp_rand( 0, 2 ) ];

		if ( 'x' === $op ) {
			$a = wp_rand( 2, 9 );
			$b = wp_rand( 2, 9 );
		} else {
			$a = wp_rand( 1, 19 );
			$b = wp_rand( 1, 19 );
		}

		if ( '-' === $op && $b > $a ) {
			$tmp = $a;
			$a   = $b;
			$b   = $tmp;
		}

		switch ( $op ) {
			case '-':
				$answer = $a - $b;
				break;
			case 'x':
				$answer = $a * $b;
				break;
			default:
				$answer = $a + $b;
				break;
		}

		$ttl = max( 60, (int) Options::get( 'math.ttl', 600 ) );

		$claims = array(
			'j' => wp_generate_password( 16, false, false ),
			'e' => time() + $ttl,
			'a' => '',
		);

		$claims['a'] = self::answer_hash( $answer, $claims['j'] );

		return array(
			'id'       => self::sign( $claims ),
			'question' => sprintf( '%d %s %d', $a, $op, $b ),
		);
	}

	/**
	 * Verifica una risposta e consuma la sfida.
	 *
	 * @param string $payload Payload firmato ricevuto dal modulo.
	 * @param string $answer  Risposta dell'utente.
	 * @return true|string True se valida, altrimenti il codice di errore.
	 */
	public static function consume( $payload, $answer ) {
		$claims = self::parse( $payload );

		if ( ! $claims ) {
			return 'invalid';
		}

		if ( $claims['e'] < time() ) {
			return 'expired';
		}

		$answer = self::normalise( $answer );

		if ( '' === $answer ) {
			return 'empty';
		}

		if ( ! hash_equals( $claims['a'], self::answer_hash( (int) $answer, $claims['j'] ) ) ) {
			return 'wrong';
		}

		$key = self::USED_PREFIX . $claims['j'];

		if ( get_transient( $key ) ) {
			return 'replay';
		}

		set_transient( $key, 1, max( 60, $claims['e'] - time() ) );

		return true;
	}

	/**
	 * Normalizza la risposta inviata.
	 *
	 * @param mixed $answer Risposta.
	 * @return string
	 */
	protected static function normalise( $answer ) {
		if ( is_array( $answer ) ) {
			return '';
		}

		$answer = trim( (string) $answer );
		$answer = str_replace( array( ' ', '.', ',' ), '', $answer );

		return preg_match( '/^-?\d{1,6}$/', $answer ) ? $answer : '';
	}

	/**
	 * Firma i claim.
	 *
	 * @param array $claims Claim.
	 * @return string
	 */
	protected static function sign( array $claims ) {
		$body = self::b64( wp_json_encode( $claims ) );

		return 'v1.' . $body . '.' . self::b64( hash_hmac( 'sha256', $body, self::key(), true ) );
	}

	/**
	 * Legge e verifica un payload.
	 *
	 * @param string $payload Payload.
	 * @return array|false
	 */
	protected static function parse( $payload ) {
		if ( ! is_string( $payload ) ) {
			return false;
		}

		$parts = explode( '.', $payload );

		if ( 3 !== count( $parts ) || 'v1' !== $parts[0] ) {
			return false;
		}

		$expected = self::b64( hash_hmac( 'sha256', $parts[1], self::key(), true ) );

		if ( ! hash_equals( $expected, $parts[2] ) ) {
			return false;
		}

		$claims = json_decode( self::unb64( $parts[1] ), true );

		if ( ! is_array( $claims ) || ! isset( $claims['j'], $claims['e'], $claims['a'] ) ) {
			return false;
		}

		return $claims;
	}

	/**
	 * HMAC della risposta, legato all'identificativo della sfida.
	 *
	 * @param int    $answer Risposta.
	 * @param string $jti    Identificativo.
	 * @return string
	 */
	protected static function answer_hash( $answer, $jti ) {
		return hash_hmac( 'sha256', (string) (int) $answer . '|' . $jti, self::key() );
	}

	/**
	 * Chiave di firma.
	 *
	 * @return string
	 */
	protected static function key() {
		return wp_salt( 'mm_recaptcha_challenge' );
	}

	/**
	 * Base64 compatibile con gli URL.
	 *
	 * @param string $value Valore.
	 * @return string
	 */
	protected static function b64( $value ) {
		return rtrim( strtr( base64_encode( $value ), '+/', '-_' ), '=' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
	}

	/**
	 * Decodifica base64 compatibile con gli URL.
	 *
	 * @param string $value Valore.
	 * @return string
	 */
	protected static function unb64( $value ) {
		return (string) base64_decode( strtr( $value, '-_', '+/' ), true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
	}

	/**
	 * Marca un token del provider come già usato.
	 *
	 * @param string $token Token.
	 * @return bool True se il token è nuovo.
	 */
	public static function claim_token( $token ) {
		if ( ! is_string( $token ) || '' === $token ) {
			return false;
		}

		$key = self::TOKEN_PREFIX . substr( hash( 'sha256', $token ), 0, 32 );

		if ( get_transient( $key ) ) {
			return false;
		}

		set_transient( $key, 1, 5 * MINUTE_IN_SECONDS );

		return true;
	}
}
