<?php
/**
 * Google reCAPTCHA v2 (casella di controllo e badge invisibile).
 *
 * @package MM_Recaptcha
 */

namespace MM_Recaptcha\Providers;

defined( 'ABSPATH' ) || exit;

class Recaptcha_V2 extends Provider {

	const VERIFY_URL = 'https://www.google.com/recaptcha/api/siteverify';

	public function id() {
		return 'recaptcha_v2';
	}

	public function label() {
		return __( 'Google reCAPTCHA v2', 'mm-recaptcha' );
	}

	public function description() {
		return __( 'La classica casella "Non sono un robot", oppure il badge invisibile che chiede una verifica solo quando il traffico è sospetto.', 'mm-recaptcha' );
	}

	public function highlights() {
		return array(
			__( 'Protezione elevata', 'mm-recaptcha' ),
			__( 'Richiede chiavi API gratuite', 'mm-recaptcha' ),
			__( 'Servizio Google: valuta le implicazioni GDPR', 'mm-recaptcha' ),
		);
	}

	public function key_help() {
		return array(
			'needs' => array(
				__( 'Site key (chiave del sito, pubblica)', 'mm-recaptcha' ),
				__( 'Secret key (chiave segreta, lato server)', 'mm-recaptcha' ),
			),
			'links' => array(
				array(
					'label' => __( 'Crea le chiavi reCAPTCHA', 'mm-recaptcha' ),
					'url'   => 'https://www.google.com/recaptcha/admin/create',
				),
				array(
					'label' => __( 'Gestisci le chiavi esistenti', 'mm-recaptcha' ),
					'url'   => 'https://www.google.com/recaptcha/admin',
				),
			),
			'steps' => array(
				__( 'Apri la console reCAPTCHA e accedi con un account Google.', 'mm-recaptcha' ),
				__( 'Inserisci un’etichetta (per esempio il nome del sito).', 'mm-recaptcha' ),
				__( 'Scegli il tipo "Challenge (v2)" e poi "Casella di controllo Non sono un robot" oppure "Badge reCAPTCHA invisibile".', 'mm-recaptcha' ),
				sprintf(
					/* translators: %s: dominio del sito. */
					__( 'Aggiungi il dominio %s fra i domini autorizzati (senza https:// e senza barra finale).', 'mm-recaptcha' ),
					wp_parse_url( home_url(), PHP_URL_HOST )
				),
				__( 'Invia: Google mostra Site key e Secret key da incollare qui sotto.', 'mm-recaptcha' ),
			),
			'notes' => array(
				__( 'Le chiavi "classiche" v2 sono gratuite e non richiedono fatturazione su Google Cloud.', 'mm-recaptcha' ),
				__( 'Se lavori anche in locale aggiungi localhost fra i domini, altrimenti la verifica fallisce.', 'mm-recaptcha' ),
			),
		);
	}

	public function settings_fields() {
		return array(
			array(
				'key'         => 'site_key',
				'label'       => __( 'Site key', 'mm-recaptcha' ),
				'type'        => 'text',
				'description' => __( 'Chiave pubblica, visibile nel codice della pagina.', 'mm-recaptcha' ),
			),
			array(
				'key'         => 'secret_key',
				'label'       => __( 'Secret key', 'mm-recaptcha' ),
				'type'        => 'password',
				'description' => __( 'Chiave segreta, usata solo dal server per la verifica.', 'mm-recaptcha' ),
			),
			array(
				'key'     => 'mode',
				'label'   => __( 'Tipo di widget', 'mm-recaptcha' ),
				'type'    => 'select',
				'options' => array(
					'checkbox'  => __( 'Casella di controllo', 'mm-recaptcha' ),
					'invisible' => __( 'Invisibile', 'mm-recaptcha' ),
				),
				'description' => __( 'Deve corrispondere al tipo scelto quando hai creato le chiavi.', 'mm-recaptcha' ),
			),
			array(
				'key'     => 'theme',
				'label'   => __( 'Tema', 'mm-recaptcha' ),
				'type'    => 'select',
				'options' => array(
					'light' => __( 'Chiaro', 'mm-recaptcha' ),
					'dark'  => __( 'Scuro', 'mm-recaptcha' ),
				),
			),
			array(
				'key'     => 'size',
				'label'   => __( 'Dimensione', 'mm-recaptcha' ),
				'type'    => 'select',
				'options' => array(
					'normal'  => __( 'Normale', 'mm-recaptcha' ),
					'compact' => __( 'Compatta', 'mm-recaptcha' ),
				),
				'description' => __( 'Ignorata quando il widget è invisibile.', 'mm-recaptcha' ),
			),
			array(
				'key'     => 'badge',
				'label'   => __( 'Posizione del badge', 'mm-recaptcha' ),
				'type'    => 'select',
				'options' => array(
					'bottomright' => __( 'In basso a destra', 'mm-recaptcha' ),
					'bottomleft'  => __( 'In basso a sinistra', 'mm-recaptcha' ),
					'inline'      => __( 'In linea con il modulo', 'mm-recaptcha' ),
				),
				'description' => __( 'Vale solo per il widget invisibile.', 'mm-recaptcha' ),
			),
			array(
				'key'         => 'language',
				'label'       => __( 'Lingua', 'mm-recaptcha' ),
				'type'        => 'text',
				'placeholder' => 'it',
				'description' => __( 'Codice lingua ISO (it, en, de...). Vuoto per la rilevazione automatica.', 'mm-recaptcha' ),
			),
		);
	}

	public function is_invisible() {
		return 'invisible' === $this->config( 'mode', 'checkbox' );
	}

	public function script_url() {
		$args = array(
			'render' => 'explicit',
			'onload' => 'mmRecaptchaOnload',
		);

		$language = trim( (string) $this->config( 'language', '' ) );
		if ( '' !== $language ) {
			$args['hl'] = $language;
		}

		return add_query_arg( $args, 'https://www.google.com/recaptcha/api.js' );
	}

	public function response_field() {
		return 'g-recaptcha-response';
	}

	public function widget_data( array $args ) {
		return array(
			'provider' => $this->id(),
			'api'      => 'grecaptcha',
			'mode'     => $this->is_invisible() ? 'invisible' : 'checkbox',
			'field'    => $this->response_field(),
			'params'   => array(
				'sitekey' => (string) $this->config( 'site_key', '' ),
				'theme'   => (string) $this->config( 'theme', 'light' ),
				'size'    => $this->is_invisible() ? 'invisible' : (string) $this->config( 'size', 'normal' ),
				'badge'   => (string) $this->config( 'badge', 'bottomright' ),
			),
		);
	}

	public function render( array $args ) {
		$data = wp_json_encode( $this->widget_data( $args ) );

		return sprintf(
			'<div class="mm-recaptcha-widget mm-recaptcha-%1$s" id="%2$s" data-mm-captcha="%3$s"></div>',
			esc_attr( $this->is_invisible() ? 'invisible' : 'checkbox' ),
			esc_attr( $args['id'] ),
			esc_attr( $data )
		);
	}

	public function verify( $token, array $ctx ) {
		if ( '' === (string) $token ) {
			return $this->fail(
				'mm_rc_missing_token',
				__( 'Completa la verifica captcha prima di inviare il modulo.', 'mm-recaptcha' )
			);
		}

		$data = $this->remote_call(
			self::VERIFY_URL,
			array(
				'body' => array(
					'secret'   => (string) $this->config( 'secret_key', '' ),
					'response' => $token,
					'remoteip' => isset( $ctx['remoteip'] ) ? $ctx['remoteip'] : '',
				),
			)
		);

		if ( is_wp_error( $data ) ) {
			return $data;
		}

		$this->last_data = $data;

		if ( empty( $data['success'] ) ) {
			$codes = isset( $data['error-codes'] ) ? $data['error-codes'] : array();

			return $this->fail(
				'mm_rc_failed',
				__( 'Verifica captcha non superata. Riprova.', 'mm-recaptcha' ),
				array( 'detail' => $this->explain_codes( $codes ), 'codes' => $codes )
			);
		}

		return $this->common_checks( $data, $token );
	}
}
