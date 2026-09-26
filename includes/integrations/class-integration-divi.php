<?php
/**
 * Integrazione con il modulo Contatti di Divi.
 *
 * Divi non offre un filtro di validazione: il modulo invia via AJAX alla
 * pagina stessa e l'email parte durante il render dello shortcode. Per questo
 * la verifica gira sull'hook "wp", prima del render: se fallisce si compila il
 * campo trappola di Divi, che scarta l'invio senza spedire nulla, e il
 * messaggio d'errore viene poi aggiunto all'output del modulo.
 *
 * @package MM_Recaptcha
 */

namespace MM_Recaptcha\Integrations;

use MM_Recaptcha\Verifier;

defined( 'ABSPATH' ) || exit;

class Integration_Divi extends Integration {

	const SLUG = 'et_pb_contact_form';

	/**
	 * Errori di verifica per numero di modulo.
	 *
	 * @var string[]
	 */
	protected $errors = array();

	public function init() {
		add_action( 'wp', array( $this, 'verify_submission' ), 5 );
		add_filter( self::SLUG . '_shortcode_output', array( $this, 'filter_output' ), 20, 2 );
	}

	/**
	 * Divi è il tema attivo (o il genitore) oppure è attivo il Divi Builder?
	 *
	 * @return bool
	 */
	public static function available() {
		return defined( 'ET_BUILDER_VERSION' ) || function_exists( 'et_setup_theme' ) || 'Divi' === get_template();
	}

	/**
	 * Numero del modulo Contatti inviato con la richiesta corrente.
	 *
	 * @return int|null
	 */
	protected function submitted_form() {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- semplice rilevamento del modulo inviato.
		foreach ( array_keys( $_POST ) as $key ) {
			if ( preg_match( '/^et_pb_contactform_submit_(\d+)$/', (string) $key, $match ) ) {
				return (int) $match[1];
			}
		}

		return null;
	}

	/**
	 * Verifica l'invio prima che Divi disegni il modulo e spedisca l'email.
	 */
	public function verify_submission() {
		if ( is_admin() || 'POST' !== ( isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) ) : '' ) ) {
			return;
		}

		$num = $this->submitted_form();

		if ( null === $num ) {
			return;
		}

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- il nonce viene verificato subito sotto.
		$nonce_key = '_wpnonce-et-pb-contact-form-submitted-' . $num;
		$trap_key  = 'et_pb_contact_et_number_' . $num;

		if ( ! empty( $_POST[ $trap_key ] ) ) {
			return;
		}

		if ( ! isset( $_POST[ $nonce_key ] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ $nonce_key ] ) ), 'et-pb-contact-form-submit' ) ) {
			// Divi rifiuta già l'invio da solo.
			return;
		}
		// phpcs:enable

		$this->strip_captcha_fields( $num );

		$result = $this->verify( 'divi' );

		if ( is_wp_error( $result ) ) {
			$this->errors[ $num ] = Verifier::message( $result );

			// Il campo trappola di Divi fa scartare l'invio: nessuna email.
			$_POST[ $trap_key ] = 'mm-recaptcha';
		}
	}

	/**
	 * Toglie i campi del captcha dall'elenco dei campi che Divi raccoglie in JS.
	 *
	 * Lo script di Divi prende tutti gli input di testo e le textarea del
	 * modulo (compresi honeypot, risposta matematica e g-recaptcha-response):
	 * lasciandoli, il confronto con la struttura salvata nel modulo fallirebbe
	 * con "Invalid submission".
	 *
	 * @param int $num Numero del modulo.
	 */
	protected function strip_captcha_fields( $num ) {
		$key = 'et_pb_contact_email_fields_' . $num;

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce già verificato dal chiamante.
		if ( ! isset( $_POST[ $key ] ) || ! is_string( $_POST[ $key ] ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- JSON decodificato e ricodificato senza alterarne i valori.
		$fields = json_decode( wp_unslash( $_POST[ $key ] ), true );

		if ( ! is_array( $fields ) ) {
			return;
		}

		$kept = array_values(
			array_filter(
				$fields,
				function ( $field ) {
					if ( ! is_array( $field ) ) {
						return true;
					}

					$id       = isset( $field['field_id'] ) ? (string) $field['field_id'] : '';
					$original = isset( $field['original_id'] ) ? (string) $field['original_id'] : '';

					// I campi di Divi hanno sempre un original_id e un id "et_pb_contact_…".
					return '' !== $original || 0 === strpos( $id, 'et_pb_contact_' );
				}
			)
		);

		if ( count( $kept ) === count( $fields ) ) {
			return;
		}

		// Divi toglie tutte le barre rovesciate prima di decodificare: niente escape di unicode e slash.
		$_POST[ $key ] = wp_slash( wp_json_encode( $kept, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) );
	}

	/**
	 * Aggiunge il captcha (e l'eventuale errore) all'HTML del modulo.
	 *
	 * @param string $output      HTML del modulo.
	 * @param string $render_slug Slug del modulo.
	 * @return string
	 */
	public function filter_output( $output, $render_slug = '' ) {
		if ( ! is_string( $output ) || '' === $output ) {
			return $output;
		}

		$num = preg_match( '/data-form_unique_num="(\d+)"/', $output, $match ) ? (int) $match[1] : null;

		if ( null !== $num && isset( $this->errors[ $num ] ) ) {
			// La classe et_pb_contact_error_text dice allo script di Divi che l'invio non è andato a buon fine.
			$output = self::insert( $output, '<div class="et-pb-contact-message">', '<p class="et_pb_contact_error_text">' . wp_kses_post( $this->errors[ $num ] ) . '</p>', true );
		}

		// Modulo già inviato con successo: resta solo il messaggio.
		if ( false === strpos( $output, 'et_contact_bottom_container' ) ) {
			return $output;
		}

		$field = $this->field( 'divi' );

		if ( '' === $field ) {
			return $output;
		}

		return self::insert( $output, '<div class="et_contact_bottom_container">', '<div class="mm-recaptcha-divi">' . $field . '</div>', false );
	}

	/**
	 * Inserisce un frammento prima o dopo la prima occorrenza di un marcatore.
	 *
	 * @param string $html   HTML.
	 * @param string $needle Marcatore.
	 * @param string $insert Frammento.
	 * @param bool   $after  Dopo il marcatore anziché prima.
	 * @return string
	 */
	protected static function insert( $html, $needle, $insert, $after ) {
		$pos = strpos( $html, $needle );

		if ( false === $pos ) {
			return $html;
		}

		if ( $after ) {
			$pos += strlen( $needle );
		}

		return substr_replace( $html, $insert, $pos, 0 );
	}
}
