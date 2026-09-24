<?php
/**
 * Google reCAPTCHA Enterprise (Google Cloud, createAssessment).
 *
 * @package MM_Recaptcha
 */

namespace MM_Recaptcha\Providers;

defined( 'ABSPATH' ) || exit;

class Recaptcha_Enterprise extends Provider {

	public function id() {
		return 'recaptcha_enterprise';
	}

	public function label() {
		return __( 'Google reCAPTCHA Enterprise', 'mm-recaptcha' );
	}

	public function description() {
		return __( 'La versione Google Cloud di reCAPTCHA: stesso widget, ma la valutazione passa dall’API createAssessment del tuo progetto Cloud, con motivazioni dettagliate.', 'mm-recaptcha' );
	}

	public function highlights() {
		return array(
			__( 'Protezione massima e diagnostica dettagliata', 'mm-recaptcha' ),
			__( 'Richiede un progetto Google Cloud con fatturazione attiva', 'mm-recaptcha' ),
			__( 'Quota gratuita mensile, poi a consumo', 'mm-recaptcha' ),
		);
	}

	public function key_help() {
		return array(
			'needs' => array(
				__( 'Site key (chiave reCAPTCHA Enterprise per il sito)', 'mm-recaptcha' ),
				__( 'ID del progetto Google Cloud', 'mm-recaptcha' ),
				__( 'API key con accesso all’API reCAPTCHA Enterprise', 'mm-recaptcha' ),
			),
			'links' => array(
				array(
					'label' => __( 'Chiavi reCAPTCHA Enterprise (Google Cloud)', 'mm-recaptcha' ),
					'url'   => 'https://console.cloud.google.com/security/recaptcha',
				),
				array(
					'label' => __( 'Crea una API key (Credenziali)', 'mm-recaptcha' ),
					'url'   => 'https://console.cloud.google.com/apis/credentials',
				),
				array(
					'label' => __( 'Abilita l’API reCAPTCHA Enterprise', 'mm-recaptcha' ),
					'url'   => 'https://console.cloud.google.com/apis/library/recaptchaenterprise.googleapis.com',
				),
			),
			'steps' => array(
				__( 'Crea (o scegli) un progetto Google Cloud con la fatturazione attiva e annota l’ID del progetto.', 'mm-recaptcha' ),
				__( 'Abilita l’API "reCAPTCHA Enterprise" per quel progetto.', 'mm-recaptcha' ),
				__( 'In Sicurezza → reCAPTCHA crea una chiave di tipo "Sito web", scegliendo punteggio o casella di controllo.', 'mm-recaptcha' ),
				sprintf(
					/* translators: %s: dominio del sito. */
					__( 'Indica %s fra i domini consentiti della chiave.', 'mm-recaptcha' ),
					wp_parse_url( home_url(), PHP_URL_HOST )
				),
				__( 'In API e servizi → Credenziali crea una API key e limitala all’API reCAPTCHA Enterprise.', 'mm-recaptcha' ),
			),
			'notes' => array(
				__( 'Limita sempre la API key (per API e, se possibile, per indirizzo IP del server): ha valore di credenziale.', 'mm-recaptcha' ),
				__( 'Le chiavi Enterprise non funzionano con gli endpoint classici e viceversa.', 'mm-recaptcha' ),
			),
		);
	}

	public function settings_fields() {
		return array(
			array(
				'key'         => 'site_key',
				'label'       => __( 'Site key', 'mm-recaptcha' ),
				'type'        => 'text',
				'description' => __( 'Chiave del sito creata in Google Cloud → Sicurezza → reCAPTCHA.', 'mm-recaptcha' ),
			),
			array(
				'key'         => 'project_id',
				'label'       => __( 'ID progetto Google Cloud', 'mm-recaptcha' ),
				'type'        => 'text',
				'placeholder' => 'mio-progetto-123456',
				'description' => __( 'Solo l’ID del progetto, non il nome visualizzato.', 'mm-recaptcha' ),
			),
			array(
				'key'         => 'api_key',
				'label'       => __( 'API key', 'mm-recaptcha' ),
				'type'        => 'password',
				'description' => __( 'Usata per autenticare le chiamate a createAssessment.', 'mm-recaptcha' ),
			),
			array(
				'key'     => 'mode',
				'label'   => __( 'Tipo di chiave', 'mm-recaptcha' ),
				'type'    => 'select',
				'options' => array(
					'score'    => __( 'Punteggio (nessuna interazione)', 'mm-recaptcha' ),
					'checkbox' => __( 'Casella di controllo', 'mm-recaptcha' ),
				),
				'description' => __( 'Deve corrispondere al tipo di chiave creata su Google Cloud.', 'mm-recaptcha' ),
			),
			array(
				'key'         => 'score',
				'label'       => __( 'Soglia di punteggio', 'mm-recaptcha' ),
				'type'        => 'number',
				'min'         => 0,
				'max'         => 1,
				'step'        => 0.1,
				'description' => __( 'Applicata alle chiavi di tipo punteggio.', 'mm-recaptcha' ),
			),
			array(
				'key'         => 'action',
				'label'       => __( 'Nome azione', 'mm-recaptcha' ),
				'type'        => 'text',
				'description' => __( 'Inviato come expectedAction nella valutazione.', 'mm-recaptcha' ),
			),
			array(
				'key'         => 'hide_badge',
				'label'       => __( 'Nascondi il badge', 'mm-recaptcha' ),
				'type'        => 'checkbox',
				'description' => __( 'Nasconde il badge di Google e aggiunge la nota di attribuzione richiesta dalle condizioni d’uso.', 'mm-recaptcha' ),
			),
		);
	}

	public function required_keys() {
		return array( 'site_key', 'project_id', 'api_key' );
	}

	public function is_invisible() {
		return 'score' === $this->config( 'mode', 'score' );
	}

	public function script_url() {
		if ( $this->is_invisible() ) {
			return add_query_arg(
				array(
					'render' => rawurlencode( (string) $this->config( 'site_key', '' ) ),
					'onload' => 'mmRecaptchaOnload',
				),
				'https://www.google.com/recaptcha/enterprise.js'
			);
		}

		return add_query_arg(
			array(
				'render' => 'explicit',
				'onload' => 'mmRecaptchaOnload',
			),
			'https://www.google.com/recaptcha/enterprise.js'
		);
	}

	public function response_field() {
		return 'g-recaptcha-response';
	}

	public function widget_data( array $args ) {
		return array(
			'provider' => $this->id(),
			'api'      => 'grecaptcha.enterprise',
			'mode'     => $this->is_invisible() ? 'score' : 'checkbox',
			'field'    => $this->response_field(),
			'params'   => array(
				'sitekey' => (string) $this->config( 'site_key', '' ),
				'action'  => (string) $this->config( 'action', 'mm_recaptcha' ),
				'theme'   => 'light',
			),
		);
	}

	public function render( array $args ) {
		$data = wp_json_encode( $this->widget_data( $args ) );

		$inner = $this->is_invisible()
			? sprintf( '<input type="hidden" name="%s" class="mm-recaptcha-token" value="" autocomplete="off" />', esc_attr( $this->response_field() ) )
			: '';

		$html = sprintf(
			'<div class="mm-recaptcha-widget mm-recaptcha-enterprise" id="%1$s" data-mm-captcha="%2$s">%3$s</div>',
			esc_attr( $args['id'] ),
			esc_attr( $data ),
			$inner
		);

		if ( $this->config( 'hide_badge', 0 ) ) {
			$html .= '<p class="mm-recaptcha-legal">' . wp_kses_post(
				sprintf(
					/* translators: 1: link privacy Google, 2: link termini Google. */
					__( 'Questo sito è protetto da reCAPTCHA: si applicano le %1$s e i %2$s di Google.', 'mm-recaptcha' ),
					'<a href="https://policies.google.com/privacy" target="_blank" rel="noopener">' . __( 'norme sulla privacy', 'mm-recaptcha' ) . '</a>',
					'<a href="https://policies.google.com/terms" target="_blank" rel="noopener">' . __( 'termini di servizio', 'mm-recaptcha' ) . '</a>'
				)
			) . '</p>';
		}

		return $html;
	}

	public function verify( $token, array $ctx ) {
		if ( '' === (string) $token ) {
			return $this->fail(
				'mm_rc_missing_token',
				__( 'Completa la verifica captcha prima di inviare il modulo.', 'mm-recaptcha' )
			);
		}

		$project = (string) $this->config( 'project_id', '' );
		$api_key = (string) $this->config( 'api_key', '' );

		$url = sprintf(
			'https://recaptchaenterprise.googleapis.com/v1/projects/%s/assessments?key=%s',
			rawurlencode( $project ),
			rawurlencode( $api_key )
		);

		$event = array(
			'token'          => $token,
			'siteKey'        => (string) $this->config( 'site_key', '' ),
			'expectedAction' => (string) $this->config( 'action', 'mm_recaptcha' ),
		);

		if ( ! empty( $ctx['remoteip'] ) ) {
			$event['userIpAddress'] = $ctx['remoteip'];
		}

		if ( ! empty( $_SERVER['HTTP_USER_AGENT'] ) ) {
			$event['userAgent'] = substr( sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ), 0, 500 );
		}

		$this->last_data = array();

		$data = $this->remote_call(
			$url,
			array(
				'headers' => array( 'Content-Type' => 'application/json' ),
				'body'    => wp_json_encode( array( 'event' => $event ) ),
			)
		);

		if ( is_wp_error( $data ) ) {
			return $data;
		}

		$this->last_data = $data;

		if ( isset( $data['error'] ) ) {
			$message = isset( $data['error']['message'] ) ? $data['error']['message'] : 'errore sconosciuto';

			return $this->fail(
				'mm_rc_enterprise_api',
				__( 'Il servizio captcha ha rifiutato la richiesta. Controlla ID progetto e API key.', 'mm-recaptcha' ),
				array( 'detail' => $message )
			);
		}

		$props = isset( $data['tokenProperties'] ) ? $data['tokenProperties'] : array();

		if ( empty( $props['valid'] ) ) {
			$reason = isset( $props['invalidReason'] ) ? $props['invalidReason'] : 'UNKNOWN';

			return $this->fail(
				'mm_rc_failed',
				__( 'Verifica captcha non superata. Riprova.', 'mm-recaptcha' ),
				array( 'detail' => 'invalidReason: ' . $reason )
			);
		}

		$expected = (string) $this->config( 'action', 'mm_recaptcha' );

		if ( isset( $props['action'] ) && '' !== $expected && $props['action'] !== $expected ) {
			return $this->fail(
				'mm_rc_action',
				__( 'Verifica captcha non superata. Riprova.', 'mm-recaptcha' ),
				array( 'detail' => sprintf( 'action "%s" != "%s"', $props['action'], $expected ) )
			);
		}

		if ( $this->is_invisible() ) {
			$threshold = (float) $this->config( 'score', 0.5 );
			$score     = isset( $data['riskAnalysis']['score'] ) ? (float) $data['riskAnalysis']['score'] : 0.0;

			if ( $score < $threshold ) {
				$reasons = isset( $data['riskAnalysis']['reasons'] ) ? implode( ',', (array) $data['riskAnalysis']['reasons'] ) : '';

				return $this->fail(
					'mm_rc_score',
					__( 'La richiesta è stata classificata come automatica. Se sei una persona, riprova o contattaci.', 'mm-recaptcha' ),
					array(
						'detail' => sprintf( 'score %.2f < %.2f %s', $score, $threshold, $reasons ),
						'score'  => $score,
					)
				);
			}
		}

		$hostname = isset( $props['hostname'] ) ? $props['hostname'] : '';

		return $this->common_checks( array( 'hostname' => $hostname ), $token );
	}
}
