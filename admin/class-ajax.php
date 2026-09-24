<?php
/**
 * Endpoint AJAX di amministrazione.
 *
 * @package MM_Recaptcha
 */

namespace MM_Recaptcha;

use MM_Recaptcha\Providers\Provider;
use MM_Recaptcha\Providers\Math;

defined( 'ABSPATH' ) || exit;

class Admin_Ajax {

	/**
	 * Registra gli endpoint.
	 */
	public static function init() {
		add_action( 'wp_ajax_mm_recaptcha_test_prepare', array( __CLASS__, 'test_prepare' ) );
		add_action( 'wp_ajax_mm_recaptcha_test_verify', array( __CLASS__, 'test_verify' ) );
		add_action( 'wp_ajax_mm_recaptcha_clear_log', array( __CLASS__, 'clear_log' ) );
		add_action( 'wp_ajax_mm_recaptcha_migrate', array( __CLASS__, 'migrate' ) );
		add_action( 'wp_ajax_mm_recaptcha_dismiss_notice', array( __CLASS__, 'dismiss_notice' ) );
	}

	/**
	 * Controlli comuni.
	 */
	protected static function guard() {
		check_ajax_referer( 'mm_recaptcha_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permessi insufficienti.', 'mm-recaptcha' ) ), 403 );
		}
	}

	/**
	 * Provider richiesto, con le credenziali inviate dal modulo.
	 *
	 * @return Provider
	 */
	protected static function provider_from_request() {
		$id = isset( $_POST['provider'] ) ? sanitize_key( wp_unslash( $_POST['provider'] ) ) : '';

		$provider = Provider_Registry::get( $id );

		if ( ! $provider instanceof Provider ) {
			wp_send_json_error( array( 'message' => __( 'Provider sconosciuto.', 'mm-recaptcha' ) ), 400 );
		}

		$overrides = array();

		if ( isset( $_POST['credentials'] ) && is_array( $_POST['credentials'] ) ) {
			foreach ( wp_unslash( $_POST['credentials'] ) as $key => $value ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanificato subito sotto.
				if ( is_array( $value ) ) {
					continue;
				}

				$overrides[ sanitize_key( $key ) ] = trim( sanitize_text_field( $value ) );
			}
		}

		$provider->set_overrides( $overrides );

		return $provider;
	}

	/**
	 * Prepara il widget di prova.
	 */
	public static function test_prepare() {
		self::guard();

		$provider = self::provider_from_request();

		if ( $provider instanceof Math ) {
			wp_send_json_success(
				array(
					'offline'   => true,
					'challenge' => $provider->fresh_challenge(),
				)
			);
		}

		$missing = array();

		foreach ( $provider->required_keys() as $key ) {
			if ( '' === trim( (string) $provider->config( $key, '' ) ) ) {
				$missing[] = $key;
			}
		}

		if ( $missing ) {
			wp_send_json_error(
				array(
					'message' => sprintf(
						/* translators: %s: elenco dei campi mancanti. */
						__( 'Compila prima questi campi: %s', 'mm-recaptcha' ),
						implode( ', ', $missing )
					),
				),
				400
			);
		}

		wp_send_json_success(
			array(
				'offline'   => false,
				'provider'  => $provider->id(),
				'scriptUrl' => $provider->script_url(),
				'widget'    => $provider->widget_data( array( 'id' => 'mm-rc-test-widget', 'surface' => 'admin_test' ) ),
			)
		);
	}

	/**
	 * Verifica il token ottenuto dal widget di prova.
	 */
	public static function test_verify() {
		self::guard();

		$provider = self::provider_from_request();
		$token    = isset( $_POST['token'] ) ? sanitize_text_field( wp_unslash( $_POST['token'] ) ) : '';

		$context = array(
			'surface'  => 'admin_test',
			'remoteip' => Verifier::remote_ip(),
			'post'     => array(
				'mm_rc_answer' => isset( $_POST['answer'] ) ? sanitize_text_field( wp_unslash( $_POST['answer'] ) ) : '',
				'mm_rc_cid'    => isset( $_POST['cid'] ) ? sanitize_text_field( wp_unslash( $_POST['cid'] ) ) : '',
			),
		);

		$start  = microtime( true );
		$result = $provider->verify( $token, $context );
		$ms     = (int) round( ( microtime( true ) - $start ) * 1000 );

		$data = $provider->last_data;

		$payload = array(
			'elapsed'  => $ms,
			'score'    => isset( $data['score'] ) ? (float) $data['score'] : ( isset( $data['riskAnalysis']['score'] ) ? (float) $data['riskAnalysis']['score'] : null ),
			'hostname' => isset( $data['hostname'] ) ? $data['hostname'] : ( isset( $data['tokenProperties']['hostname'] ) ? $data['tokenProperties']['hostname'] : '' ),
			'action'   => isset( $data['action'] ) ? $data['action'] : ( isset( $data['tokenProperties']['action'] ) ? $data['tokenProperties']['action'] : '' ),
			'raw'      => self::redact( $provider, wp_json_encode( $data ) ),
		);

		if ( is_wp_error( $result ) ) {
			$error_data = $result->get_error_data();

			$payload['message'] = $result->get_error_message();
			$payload['code']    = $result->get_error_code();
			$payload['detail']  = isset( $error_data['detail'] ) ? $error_data['detail'] : '';

			if ( 'mm_rc_replay' === $result->get_error_code() ) {
				// Il token di prova è valido ma già consumato dal controllo anti riuso.
				$payload['message'] = __( 'Le chiavi funzionano: il token è stato accettato dal provider.', 'mm-recaptcha' );
				wp_send_json_success( $payload );
			}

			wp_send_json_error( $payload, 200 );
		}

		$payload['message'] = __( 'Verifica riuscita: le chiavi funzionano.', 'mm-recaptcha' );

		wp_send_json_success( $payload );
	}

	/**
	 * Nasconde le credenziali nel dump grezzo.
	 *
	 * @param Provider $provider Provider.
	 * @param string   $raw      Contenuto.
	 * @return string
	 */
	protected static function redact( Provider $provider, $raw ) {
		$raw = (string) $raw;

		foreach ( array( 'secret_key', 'api_key' ) as $key ) {
			$value = (string) $provider->config( $key, '' );

			if ( '' !== $value ) {
				$raw = str_replace( $value, '***', $raw );
			}
		}

		return $raw;
	}

	/**
	 * Svuota il registro.
	 */
	public static function clear_log() {
		self::guard();

		Log::clear();

		wp_send_json_success( array( 'message' => __( 'Registro svuotato.', 'mm-recaptcha' ) ) );
	}

	/**
	 * Importa le impostazioni dal vecchio plugin.
	 */
	public static function migrate() {
		self::guard();

		$result = Migrator::import();

		wp_send_json_success( $result );
	}

	/**
	 * Nasconde l'avviso di migrazione.
	 */
	public static function dismiss_notice() {
		self::guard();

		update_option( 'mm_recaptcha_migrated', current_time( 'mysql', true ), false );
		delete_option( 'mm_recaptcha_migration_notice' );

		wp_send_json_success();
	}
}
