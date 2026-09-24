<?php
/**
 * Captcha matematico interno: nessun servizio esterno, nessuna chiave.
 *
 * Ogni sfida è generata sul server, dura al massimo il TTL impostato e
 * viene consumata al primo invio: non è riutilizzabile né precalcolabile.
 *
 * @package MM_Recaptcha
 */

namespace MM_Recaptcha\Providers;

use MM_Recaptcha\Challenge_Store;

defined( 'ABSPATH' ) || exit;

class Math extends Provider {

	public function id() {
		return 'math';
	}

	public function label() {
		return __( 'Captcha matematico interno', 'mm-recaptcha' );
	}

	public function description() {
		return __( 'Una semplice operazione da risolvere, generata e verificata dal tuo server. Nessuna chiamata a servizi esterni e nessun dato inviato a terzi.', 'mm-recaptcha' );
	}

	public function highlights() {
		return array(
			__( 'Nessuna chiave da richiedere', 'mm-recaptcha' ),
			__( 'Nessun servizio di terze parti, compatibile GDPR', 'mm-recaptcha' ),
			__( 'Protezione di base: efficace contro i bot generici', 'mm-recaptcha' ),
		);
	}

	public function key_help() {
		return array(
			'needs' => array( __( 'Nessuna chiave richiesta.', 'mm-recaptcha' ) ),
			'links' => array(),
			'steps' => array(
				__( 'Non devi registrarti da nessuna parte: seleziona questo provider e salva.', 'mm-recaptcha' ),
			),
			'notes' => array(
				__( 'Se il sito usa una cache a pagina intera sui moduli pubblici (per esempio i commenti), la sfida viene rigenerata via JavaScript per non restare congelata nella cache.', 'mm-recaptcha' ),
				__( 'Per una protezione più robusta contro bot mirati, valuta Turnstile o hCaptcha: sono gratuiti.', 'mm-recaptcha' ),
			),
		);
	}

	public function settings_fields() {
		return array(
			array(
				'key'         => 'challenge_text',
				'label'       => __( 'Testo della domanda', 'mm-recaptcha' ),
				'type'        => 'text',
				'description' => __( 'Mostrato prima dell’operazione, ad esempio "Quanto fa: 3 + 4".', 'mm-recaptcha' ),
			),
			array(
				'key'         => 'ttl',
				'label'       => __( 'Durata della sfida (secondi)', 'mm-recaptcha' ),
				'type'        => 'number',
				'min'         => 60,
				'max'         => 3600,
				'step'        => 60,
				'description' => __( 'Dopo questo tempo la sfida scade e l’utente deve ricaricare la pagina.', 'mm-recaptcha' ),
			),
		);
	}

	public function needs_keys() {
		return false;
	}

	public function response_field() {
		return 'mm_rc_answer';
	}

	public function widget_data( array $args ) {
		$surface = isset( $args['surface'] ) ? $args['surface'] : 'custom';

		// Sulle schermate di wp-login.php non c'è cache a pagina intera: inutile
		// rigenerare la sfida. Altrove sì, così la cache non congela una domanda.
		$login_surfaces = array( 'wp_login', 'wp_register', 'wp_lostpassword', 'wp_resetpassword' );

		return array(
			'provider' => $this->id(),
			'api'      => '',
			'mode'     => 'math',
			'field'    => $this->response_field(),
			'refresh'  => ! in_array( $surface, $login_surfaces, true ),
		);
	}

	public function render( array $args ) {
		$challenge = Challenge_Store::create();
		$label     = (string) $this->config( 'challenge_text', 'Quanto fa:' );
		$field_id  = $args['id'] . '-answer';
		$data      = wp_json_encode( $this->widget_data( $args ) );

		$html  = sprintf(
			'<div class="mm-recaptcha-widget mm-recaptcha-math" id="%1$s" data-mm-captcha="%2$s">',
			esc_attr( $args['id'] ),
			esc_attr( $data )
		);
		$html .= sprintf(
			'<label class="mm-recaptcha-math-label" for="%1$s">%2$s <span class="mm-recaptcha-math-question">%3$s</span> =</label> ',
			esc_attr( $field_id ),
			esc_html( $label ),
			esc_html( $challenge['question'] )
		);
		$html .= sprintf(
			'<input type="text" class="mm-recaptcha-math-input input" id="%1$s" name="%2$s" value="" size="4" inputmode="numeric" autocomplete="off" required aria-required="true" />',
			esc_attr( $field_id ),
			esc_attr( $this->response_field() )
		);
		$html .= sprintf(
			'<input type="hidden" class="mm-recaptcha-math-id" name="mm_rc_cid" value="%s" />',
			esc_attr( $challenge['id'] )
		);
		$html .= '</div>';

		return $html;
	}

	public function verify( $token, array $ctx ) {
		$answer = isset( $ctx['post']['mm_rc_answer'] ) ? $ctx['post']['mm_rc_answer'] : '';
		$cid    = isset( $ctx['post']['mm_rc_cid'] ) ? $ctx['post']['mm_rc_cid'] : '';

		if ( is_array( $answer ) || is_array( $cid ) ) {
			return $this->fail( 'mm_rc_math_malformed' );
		}

		$answer = trim( (string) $answer );

		if ( '' === $answer ) {
			return $this->fail(
				'mm_rc_math_empty',
				__( 'Rispondi alla domanda di sicurezza per continuare.', 'mm-recaptcha' )
			);
		}

		if ( '' === trim( (string) $cid ) ) {
			return $this->fail(
				'mm_rc_math_expired',
				__( 'La domanda di sicurezza non è più valida: ricarica la pagina e riprova.', 'mm-recaptcha' )
			);
		}

		$outcome = Challenge_Store::consume( $cid, $answer );

		if ( true === $outcome ) {
			return true;
		}

		switch ( $outcome ) {
			case 'expired':
				return $this->fail(
					'mm_rc_math_expired',
					__( 'La domanda di sicurezza è scaduta: ricarica la pagina e riprova.', 'mm-recaptcha' )
				);

			case 'replay':
				return $this->fail(
					'mm_rc_math_replay',
					__( 'Questa domanda di sicurezza è già stata usata: ricarica la pagina e riprova.', 'mm-recaptcha' )
				);

			case 'invalid':
				return $this->fail(
					'mm_rc_math_invalid',
					__( 'La domanda di sicurezza non è valida: ricarica la pagina e riprova.', 'mm-recaptcha' ),
					array( 'detail' => 'payload non firmato correttamente' )
				);

			default:
				return $this->fail(
					'mm_rc_math_failed',
					__( 'La risposta alla domanda di sicurezza non è corretta.', 'mm-recaptcha' )
				);
		}
	}

	/**
	 * Genera una nuova sfida per l'aggiornamento via AJAX.
	 *
	 * @return array
	 */
	public function fresh_challenge() {
		$challenge = Challenge_Store::create();

		return array(
			'id'       => $challenge['id'],
			'question' => $challenge['question'],
			'label'    => (string) $this->config( 'challenge_text', 'Quanto fa:' ),
		);
	}
}
