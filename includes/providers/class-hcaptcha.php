<?php
/**
 * hCaptcha (widget normale e invisibile).
 *
 * @package MM_Recaptcha
 */

namespace MM_Recaptcha\Providers;

defined( 'ABSPATH' ) || exit;

class HCaptcha extends Provider {

	const VERIFY_URL = 'https://api.hcaptcha.com/siteverify';

	public function id() {
		return 'hcaptcha';
	}

	public function label() {
		return __( 'hCaptcha', 'mm-recaptcha' );
	}

	public function description() {
		return __( 'Alternativa a reCAPTCHA orientata alla privacy, con server in Europa e condizioni pensate per il GDPR.', 'mm-recaptcha' );
	}

	public function highlights() {
		return array(
			__( 'Protezione elevata', 'mm-recaptcha' ),
			__( 'Attento alla privacy, compatibile GDPR', 'mm-recaptcha' ),
			__( 'Piano gratuito generoso', 'mm-recaptcha' ),
		);
	}

	public function key_help() {
		return array(
			'needs' => array(
				__( 'Sitekey (una per ogni sito registrato)', 'mm-recaptcha' ),
				__( 'Secret key (una sola per tutto l’account)', 'mm-recaptcha' ),
			),
			'links' => array(
				array(
					'label' => __( 'Registra il sito e ottieni la sitekey', 'mm-recaptcha' ),
					'url'   => 'https://dashboard.hcaptcha.com/sites',
				),
				array(
					'label' => __( 'Genera la secret key (Impostazioni account)', 'mm-recaptcha' ),
					'url'   => 'https://dashboard.hcaptcha.com/settings',
				),
				array(
					'label' => __( 'Crea un account hCaptcha', 'mm-recaptcha' ),
					'url'   => 'https://www.hcaptcha.com/signup-interstitial',
				),
			),
			'steps' => array(
				__( 'Accedi alla dashboard hCaptcha (o registra un account gratuito).', 'mm-recaptcha' ),
				__( 'Nella scheda "Sites" premi "Add Site", inserisci un nome e aggiungi il dominio.', 'mm-recaptcha' ),
				sprintf(
					/* translators: %s: dominio del sito. */
					__( 'Il dominio da inserire è %s (senza https:// e senza barra finale).', 'mm-recaptcha' ),
					wp_parse_url( home_url(), PHP_URL_HOST )
				),
				__( 'Copia la Sitekey mostrata nell’elenco dei siti.', 'mm-recaptcha' ),
				__( 'Vai in Impostazioni account e premi "Generate New Secret" per ottenere la secret key.', 'mm-recaptcha' ),
			),
			'notes' => array(
				__( 'La secret key è unica per l’account e vale per tutti i siti: viene mostrata una sola volta, copiala subito.', 'mm-recaptcha' ),
				__( 'Rigenerare la secret invalida quella precedente e va aggiornata ovunque sia in uso.', 'mm-recaptcha' ),
			),
		);
	}

	public function settings_fields() {
		return array(
			array(
				'key'         => 'site_key',
				'label'       => __( 'Sitekey', 'mm-recaptcha' ),
				'type'        => 'text',
				'description' => __( 'Chiave pubblica del sito registrato su hCaptcha.', 'mm-recaptcha' ),
			),
			array(
				'key'         => 'secret_key',
				'label'       => __( 'Secret key', 'mm-recaptcha' ),
				'type'        => 'password',
				'description' => __( 'Chiave segreta dell’account (Impostazioni → Generate New Secret).', 'mm-recaptcha' ),
			),
			array(
				'key'     => 'mode',
				'label'   => __( 'Tipo di widget', 'mm-recaptcha' ),
				'type'    => 'select',
				'options' => array(
					'normal'    => __( 'Casella di controllo', 'mm-recaptcha' ),
					'invisible' => __( 'Invisibile', 'mm-recaptcha' ),
				),
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
			),
		);
	}

	public function is_invisible() {
		return 'invisible' === $this->config( 'mode', 'normal' );
	}

	public function script_url() {
		return add_query_arg(
			array(
				'render' => 'explicit',
				'onload' => 'mmRecaptchaOnload',
			),
			'https://js.hcaptcha.com/1/api.js'
		);
	}

	public function response_field() {
		return 'h-captcha-response';
	}

	public function widget_data( array $args ) {
		return array(
			'provider' => $this->id(),
			'api'      => 'hcaptcha',
			'mode'     => $this->is_invisible() ? 'invisible' : 'checkbox',
			'field'    => $this->response_field(),
			'params'   => array(
				'sitekey' => (string) $this->config( 'site_key', '' ),
				'theme'   => (string) $this->config( 'theme', 'light' ),
				'size'    => $this->is_invisible() ? 'invisible' : (string) $this->config( 'size', 'normal' ),
			),
		);
	}

	public function render( array $args ) {
		$data = wp_json_encode( $this->widget_data( $args ) );

		return sprintf(
			'<div class="mm-recaptcha-widget mm-recaptcha-hcaptcha" id="%1$s" data-mm-captcha="%2$s"></div>',
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
					'sitekey'  => (string) $this->config( 'site_key', '' ),
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
