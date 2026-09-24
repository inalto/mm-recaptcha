<?php
/**
 * Google reCAPTCHA v3 (punteggio, senza interazione).
 *
 * @package MM_Recaptcha
 */

namespace MM_Recaptcha\Providers;


defined( 'ABSPATH' ) || exit;

class Recaptcha_V3 extends Provider {

	const VERIFY_URL = 'https://www.google.com/recaptcha/api/siteverify';

	public function id() {
		return 'recaptcha_v3';
	}

	public function label() {
		return __( 'Google reCAPTCHA v3', 'mm-recaptcha' );
	}

	public function description() {
		return __( 'Nessuna interazione per l’utente: Google assegna un punteggio da 0.0 a 1.0 e il modulo viene bloccato sotto la soglia scelta.', 'mm-recaptcha' );
	}

	public function highlights() {
		return array(
			__( 'Nessun clic richiesto', 'mm-recaptcha' ),
			__( 'Soglia di punteggio configurabile', 'mm-recaptcha' ),
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
				__( 'Inserisci un’etichetta per il sito.', 'mm-recaptcha' ),
				__( 'Scegli il tipo "Score based (v3)".', 'mm-recaptcha' ),
				sprintf(
					/* translators: %s: dominio del sito. */
					__( 'Aggiungi il dominio %s fra i domini autorizzati.', 'mm-recaptcha' ),
					wp_parse_url( home_url(), PHP_URL_HOST )
				),
				__( 'Copia Site key e Secret key qui sotto e imposta la soglia (0.5 è il valore consigliato da Google).', 'mm-recaptcha' ),
			),
			'notes' => array(
				__( 'Le chiavi v3 sono diverse da quelle v2: non sono intercambiabili.', 'mm-recaptcha' ),
				__( 'Con una soglia troppo alta rischi di bloccare utenti legittimi: controlla il registro dei fallimenti prima di alzarla.', 'mm-recaptcha' ),
			),
		);
	}

	public function settings_fields() {
		return array(
			array(
				'key'         => 'site_key',
				'label'       => __( 'Site key', 'mm-recaptcha' ),
				'type'        => 'text',
				'description' => __( 'Chiave pubblica della coppia v3.', 'mm-recaptcha' ),
			),
			array(
				'key'         => 'secret_key',
				'label'       => __( 'Secret key', 'mm-recaptcha' ),
				'type'        => 'password',
				'description' => __( 'Chiave segreta della coppia v3.', 'mm-recaptcha' ),
			),
			array(
				'key'         => 'score',
				'label'       => __( 'Soglia di punteggio', 'mm-recaptcha' ),
				'type'        => 'number',
				'min'         => 0,
				'max'         => 1,
				'step'        => 0.1,
				'description' => __( '1.0 = quasi certamente umano, 0.0 = quasi certamente bot. Le richieste sotto la soglia vengono rifiutate.', 'mm-recaptcha' ),
			),
			array(
				'key'         => 'action',
				'label'       => __( 'Nome azione', 'mm-recaptcha' ),
				'type'        => 'text',
				'description' => __( 'Etichetta inviata a Google e visibile nelle statistiche della console.', 'mm-recaptcha' ),
			),
			array(
				'key'         => 'verify_action',
				'label'       => __( 'Verifica il nome azione', 'mm-recaptcha' ),
				'type'        => 'checkbox',
				'description' => __( 'Rifiuta i token generati con un’azione diversa: impedisce il riuso di token presi da altre pagine.', 'mm-recaptcha' ),
			),
			array(
				'key'         => 'hide_badge',
				'label'       => __( 'Nascondi il badge', 'mm-recaptcha' ),
				'type'        => 'checkbox',
				'description' => __( 'Le condizioni d’uso di Google richiedono, in questo caso, di citare reCAPTCHA nel testo del modulo: viene aggiunta automaticamente una nota con i link a privacy e termini.', 'mm-recaptcha' ),
			),
		);
	}

	public function is_invisible() {
		return true;
	}

	public function script_url() {
		return add_query_arg(
			array(
				'render' => rawurlencode( (string) $this->config( 'site_key', '' ) ),
				'onload' => 'mmRecaptchaOnload',
			),
			'https://www.google.com/recaptcha/api.js'
		);
	}

	public function response_field() {
		return 'g-recaptcha-response';
	}

	public function widget_data( array $args ) {
		return array(
			'provider' => $this->id(),
			'api'      => 'grecaptcha',
			'mode'     => 'score',
			'field'    => $this->response_field(),
			'params'   => array(
				'sitekey' => (string) $this->config( 'site_key', '' ),
				'action'  => (string) $this->config( 'action', 'mm_recaptcha' ),
			),
		);
	}

	public function render( array $args ) {
		$data = wp_json_encode( $this->widget_data( $args ) );

		$html = sprintf(
			'<div class="mm-recaptcha-widget mm-recaptcha-score" id="%1$s" data-mm-captcha="%2$s"><input type="hidden" name="%3$s" class="mm-recaptcha-token" value="" autocomplete="off" /></div>',
			esc_attr( $args['id'] ),
			esc_attr( $data ),
			esc_attr( $this->response_field() )
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
				__( 'Verifica antispam non completata: ricarica la pagina e riprova.', 'mm-recaptcha' )
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

		$expected_action = (string) $this->config( 'action', 'mm_recaptcha' );

		if ( $this->config( 'verify_action', 1 ) && isset( $data['action'] ) && $data['action'] !== $expected_action ) {
			return $this->fail(
				'mm_rc_action',
				__( 'Verifica captcha non superata. Riprova.', 'mm-recaptcha' ),
				array( 'detail' => sprintf( 'action "%s" != "%s"', $data['action'], $expected_action ) )
			);
		}

		$threshold = (float) $this->config( 'score', 0.5 );
		$score     = isset( $data['score'] ) ? (float) $data['score'] : 0.0;

		if ( $score < $threshold ) {
			return $this->fail(
				'mm_rc_score',
				__( 'La richiesta è stata classificata come automatica. Se sei una persona, riprova o contattaci.', 'mm-recaptcha' ),
				array( 'detail' => sprintf( 'score %.2f < %.2f', $score, $threshold ), 'score' => $score )
			);
		}

		$checked = $this->common_checks( $data, $token );

		if ( is_wp_error( $checked ) ) {
			return $checked;
		}

		return true;
	}
}
