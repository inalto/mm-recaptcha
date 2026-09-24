<?php
/**
 * Cloudflare Turnstile.
 *
 * @package MM_Recaptcha
 */

namespace MM_Recaptcha\Providers;

defined( 'ABSPATH' ) || exit;

class Turnstile extends Provider {

	const VERIFY_URL = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

	public function id() {
		return 'turnstile';
	}

	public function label() {
		return __( 'Cloudflare Turnstile', 'mm-recaptcha' );
	}

	public function description() {
		return __( 'Verifica quasi sempre automatica, senza enigmi da risolvere e senza profilazione pubblicitaria. Gratuito e non richiede che il sito sia su Cloudflare.', 'mm-recaptcha' );
	}

	public function highlights() {
		return array(
			__( 'Protezione elevata con attrito minimo', 'mm-recaptcha' ),
			__( 'Nessun costo, nessun limite di dominio', 'mm-recaptcha' ),
			__( 'Non richiede che il DNS sia su Cloudflare', 'mm-recaptcha' ),
		);
	}

	public function key_help() {
		return array(
			'needs' => array(
				__( 'Site key del widget', 'mm-recaptcha' ),
				__( 'Secret key del widget', 'mm-recaptcha' ),
			),
			'links' => array(
				array(
					'label' => __( 'Apri Turnstile nel pannello Cloudflare', 'mm-recaptcha' ),
					'url'   => 'https://dash.cloudflare.com/?to=/:account/turnstile',
				),
				array(
					'label' => __( 'Registrati su Cloudflare', 'mm-recaptcha' ),
					'url'   => 'https://dash.cloudflare.com/sign-up',
				),
			),
			'steps' => array(
				__( 'Accedi al pannello Cloudflare (basta un account gratuito).', 'mm-recaptcha' ),
				__( 'Vai in Turnstile e premi "Add widget".', 'mm-recaptcha' ),
				sprintf(
					/* translators: %s: dominio del sito. */
					__( 'Dai un nome al widget e aggiungi l’hostname %s.', 'mm-recaptcha' ),
					wp_parse_url( home_url(), PHP_URL_HOST )
				),
				__( 'Scegli la modalità del widget: Managed (consigliata), Non-interactive o Invisible.', 'mm-recaptcha' ),
				__( 'Alla creazione Cloudflare mostra Site key e Secret key: copiale qui sotto.', 'mm-recaptcha' ),
			),
			'notes' => array(
				__( 'La modalità scelta su Cloudflare e l’aspetto impostato qui devono essere coerenti.', 'mm-recaptcha' ),
				__( 'La secret key si può rigenerare dal widget con Settings → Rotate Secret Key.', 'mm-recaptcha' ),
			),
		);
	}

	public function settings_fields() {
		return array(
			array(
				'key'         => 'site_key',
				'label'       => __( 'Site key', 'mm-recaptcha' ),
				'type'        => 'text',
				'description' => __( 'Chiave pubblica del widget Turnstile.', 'mm-recaptcha' ),
			),
			array(
				'key'         => 'secret_key',
				'label'       => __( 'Secret key', 'mm-recaptcha' ),
				'type'        => 'password',
				'description' => __( 'Chiave segreta dello stesso widget.', 'mm-recaptcha' ),
			),
			array(
				'key'     => 'appearance',
				'label'   => __( 'Aspetto', 'mm-recaptcha' ),
				'type'    => 'select',
				'options' => array(
					'always'           => __( 'Sempre visibile', 'mm-recaptcha' ),
					'execute'          => __( 'Solo durante la verifica', 'mm-recaptcha' ),
					'interaction-only' => __( 'Solo se serve interazione', 'mm-recaptcha' ),
				),
			),
			array(
				'key'     => 'theme',
				'label'   => __( 'Tema', 'mm-recaptcha' ),
				'type'    => 'select',
				'options' => array(
					'auto'  => __( 'Automatico', 'mm-recaptcha' ),
					'light' => __( 'Chiaro', 'mm-recaptcha' ),
					'dark'  => __( 'Scuro', 'mm-recaptcha' ),
				),
			),
			array(
				'key'     => 'size',
				'label'   => __( 'Dimensione', 'mm-recaptcha' ),
				'type'    => 'select',
				'options' => array(
					'normal'   => __( 'Normale', 'mm-recaptcha' ),
					'flexible' => __( 'Flessibile', 'mm-recaptcha' ),
					'compact'  => __( 'Compatta', 'mm-recaptcha' ),
				),
			),
		);
	}

	public function script_url() {
		return add_query_arg(
			array(
				'render' => 'explicit',
				'onload' => 'mmRecaptchaOnload',
			),
			'https://challenges.cloudflare.com/turnstile/v0/api.js'
		);
	}

	public function response_field() {
		return 'cf-turnstile-response';
	}

	public function widget_data( array $args ) {
		return array(
			'provider' => $this->id(),
			'api'      => 'turnstile',
			'mode'     => 'checkbox',
			'field'    => $this->response_field(),
			'params'   => array(
				'sitekey'               => (string) $this->config( 'site_key', '' ),
				'theme'                 => (string) $this->config( 'theme', 'auto' ),
				'size'                  => (string) $this->config( 'size', 'normal' ),
				'appearance'            => (string) $this->config( 'appearance', 'always' ),
				'response-field-name'   => $this->response_field(),
				'language'              => $this->language(),
			),
		);
	}

	protected function language() {
		$locale = get_locale();
		$locale = str_replace( '_', '-', $locale );

		return strtolower( substr( $locale, 0, 5 ) );
	}

	public function render( array $args ) {
		$data = wp_json_encode( $this->widget_data( $args ) );

		return sprintf(
			'<div class="mm-recaptcha-widget mm-recaptcha-turnstile" id="%1$s" data-mm-captcha="%2$s"></div>',
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
