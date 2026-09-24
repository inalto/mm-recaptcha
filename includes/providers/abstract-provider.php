<?php
/**
 * Contratto comune a tutti i provider captcha.
 *
 * @package MM_Recaptcha
 */

namespace MM_Recaptcha\Providers;

use MM_Recaptcha\Options;
use MM_Recaptcha\Challenge_Store;
use WP_Error;

defined( 'ABSPATH' ) || exit;

abstract class Provider {

	/**
	 * Ultima risposta grezza del provider (usata dal test delle chiavi).
	 *
	 * @var array
	 */
	public $last_data = array();

	/**
	 * Valori che sovrascrivono le opzioni salvate (usati per provare le chiavi).
	 *
	 * @var array
	 */
	protected $overrides = array();

	/**
	 * Imposta credenziali temporanee.
	 *
	 * @param array $values Valori.
	 */
	public function set_overrides( array $values ) {
		$this->overrides = $values;
	}

	/**
	 * Identificativo interno (coincide con la chiave delle opzioni).
	 */
	abstract public function id();

	/**
	 * Nome mostrato in amministrazione.
	 */
	abstract public function label();

	/**
	 * Descrizione breve mostrata nella scheda del provider.
	 */
	abstract public function description();

	/**
	 * Punti di forza mostrati nella scheda.
	 *
	 * @return string[]
	 */
	public function highlights() {
		return array();
	}

	/**
	 * Guida all'ottenimento delle chiavi.
	 *
	 * Struttura:
	 *   'needs'  => string[]  elenco delle chiavi necessarie
	 *   'links'  => array     [ ['label' => ..., 'url' => ...], ... ]
	 *   'steps'  => string[]  passi da seguire
	 *   'notes'  => string[]  avvertenze
	 *
	 * @return array
	 */
	abstract public function key_help();

	/**
	 * Campi di configurazione specifici del provider.
	 *
	 * Ogni campo: [ 'key', 'label', 'type' => text|password|select|number|checkbox,
	 *               'options' => [], 'description' => '', 'placeholder' => '' ]
	 *
	 * @return array
	 */
	abstract public function settings_fields();

	/**
	 * Il provider richiede chiavi remote?
	 */
	public function needs_keys() {
		return true;
	}

	/**
	 * Il widget è invisibile (nessuna interazione esplicita)?
	 */
	public function is_invisible() {
		return false;
	}

	/**
	 * URL dello script del provider, o stringa vuota se non serve.
	 */
	public function script_url() {
		return '';
	}

	/**
	 * Nome del campo POST che trasporta il token.
	 */
	abstract public function response_field();

	/**
	 * HTML del widget.
	 *
	 * @param array $args Argomenti di render (id univoco, contesto).
	 * @return string
	 */
	abstract public function render( array $args );

	/**
	 * Verifica il token inviato.
	 *
	 * @param string $token Token.
	 * @param array  $ctx   Contesto (context, remoteip).
	 * @return true|WP_Error
	 */
	abstract public function verify( $token, array $ctx );

	/**
	 * Dati passati al JavaScript di front-end.
	 *
	 * @param array $args Argomenti di render.
	 * @return array
	 */
	public function widget_data( array $args ) {
		return array(
			'provider' => $this->id(),
			'field'    => $this->response_field(),
		);
	}

	/**
	 * Legge una opzione del provider.
	 *
	 * @param string $key     Chiave.
	 * @param mixed  $default Ripiego.
	 * @param array  $options Opzioni alternative (usate in sanificazione).
	 * @return mixed
	 */
	public function config( $key, $default = null, $options = null ) {
		if ( array_key_exists( $key, $this->overrides ) ) {
			return $this->overrides[ $key ];
		}

		if ( is_array( $options ) ) {
			return isset( $options[ $this->id() ][ $key ] ) ? $options[ $this->id() ][ $key ] : $default;
		}

		return Options::get( $this->id() . '.' . $key, $default );
	}

	/**
	 * Il provider ha tutto il necessario per funzionare?
	 *
	 * @param array|null $options Opzioni alternative.
	 * @return bool
	 */
	public function is_configured( $options = null ) {
		if ( ! $this->needs_keys() ) {
			return true;
		}

		foreach ( $this->required_keys() as $key ) {
			if ( '' === trim( (string) $this->config( $key, '', $options ) ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Chiavi obbligatorie.
	 *
	 * @return string[]
	 */
	public function required_keys() {
		return array( 'site_key', 'secret_key' );
	}

	/**
	 * Esegue una chiamata di verifica e normalizza gli errori di trasporto.
	 *
	 * @param string $url  Endpoint.
	 * @param array  $args Argomenti per wp_remote_post.
	 * @return array|WP_Error Corpo decodificato o errore.
	 */
	protected function remote_call( $url, array $args ) {
		$args = wp_parse_args(
			$args,
			array(
				'timeout'    => 10,
				'user-agent' => 'MM reCAPTCHA/' . MM_RECAPTCHA_VERSION . '; ' . home_url( '/' ),
			)
		);

		$response = wp_remote_post( $url, $args );

		if ( is_wp_error( $response ) ) {
			return new WP_Error(
				'mm_rc_transport',
				__( 'Non è stato possibile contattare il servizio captcha. Riprova tra qualche istante.', 'mm-recaptcha' ),
				array( 'detail' => $response->get_error_message(), 'transport' => true )
			);
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( 200 !== $code || ! is_array( $data ) ) {
			return new WP_Error(
				'mm_rc_bad_response',
				__( 'Il servizio captcha ha risposto in modo inatteso. Riprova.', 'mm-recaptcha' ),
				array(
					'detail'    => 'HTTP ' . $code . ' ' . substr( (string) $body, 0, 200 ),
					'transport' => true,
				)
			);
		}

		return $data;
	}

	/**
	 * Errore di verifica standard.
	 *
	 * @param string $code    Codice.
	 * @param string $message Messaggio; vuoto per quello predefinito.
	 * @param array  $data    Dati aggiuntivi.
	 * @return WP_Error
	 */
	protected function fail( $code, $message = '', $data = array() ) {
		if ( '' === $message ) {
			$message = __( 'Verifica captcha non superata. Riprova.', 'mm-recaptcha' );
		}

		return new WP_Error( $code, $message, $data );
	}

	/**
	 * Traduce i codici di errore restituiti dai provider.
	 *
	 * @param array $codes Codici.
	 * @return string
	 */
	public function explain_codes( $codes ) {
		$map = array(
			'missing-input-secret'   => __( 'La chiave segreta non è stata inviata.', 'mm-recaptcha' ),
			'invalid-input-secret'   => __( 'La chiave segreta non è valida (controlla di non aver invertito site key e secret key).', 'mm-recaptcha' ),
			'missing-input-response' => __( 'Il token del captcha non è stato inviato.', 'mm-recaptcha' ),
			'invalid-input-response' => __( 'Il token del captcha non è valido o è scaduto.', 'mm-recaptcha' ),
			'bad-request'            => __( 'Richiesta malformata verso il servizio captcha.', 'mm-recaptcha' ),
			'timeout-or-duplicate'   => __( 'Il token è scaduto o è già stato usato.', 'mm-recaptcha' ),
			'invalid-keys'           => __( 'Le chiavi non sono valide per questo dominio.', 'mm-recaptcha' ),
			'sitekey-secret-mismatch' => __( 'Site key e secret key non appartengono allo stesso widget.', 'mm-recaptcha' ),
			'invalid-widget-id'      => __( 'Widget non valido.', 'mm-recaptcha' ),
			'internal-error'         => __( 'Errore interno del servizio captcha.', 'mm-recaptcha' ),
		);

		$out = array();

		foreach ( (array) $codes as $code ) {
			$code  = (string) $code;
			$out[] = isset( $map[ $code ] ) ? $map[ $code ] : $code;
		}

		return implode( ' ', $out );
	}

	/**
	 * Controlli comuni ai provider a token: hostname, replay, timestamp.
	 *
	 * @param array  $data  Risposta del provider.
	 * @param string $token Token.
	 * @return true|WP_Error
	 */
	protected function common_checks( array $data, $token ) {
		if ( Options::get( 'verify_hostname', 0 ) && ! empty( $data['hostname'] ) ) {
			$expected = wp_parse_url( home_url(), PHP_URL_HOST );
			if ( $expected && 0 !== strcasecmp( $data['hostname'], $expected ) ) {
				return $this->fail(
					'mm_rc_hostname',
					__( 'Il captcha è stato risolto su un dominio diverso da questo sito.', 'mm-recaptcha' ),
					array( 'detail' => $data['hostname'] )
				);
			}
		}

		if ( ! Challenge_Store::claim_token( $token ) ) {
			return $this->fail(
				'mm_rc_replay',
				__( 'Questo captcha è già stato usato. Ricarica la pagina e riprova.', 'mm-recaptcha' )
			);
		}

		return true;
	}
}
