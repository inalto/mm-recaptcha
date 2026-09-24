<?php
/**
 * Integrazione con i moduli del core di WordPress.
 *
 * @package MM_Recaptcha
 */

namespace MM_Recaptcha\Integrations;

use MM_Recaptcha\Renderer;
use MM_Recaptcha\Verifier;
use WP_Error;

defined( 'ABSPATH' ) || exit;

class Integration_WP extends Integration {

	public function init() {
		add_action( 'login_enqueue_scripts', array( $this, 'login_assets' ) );

		// Accesso.
		add_action( 'login_form', array( $this, 'render_login' ), 20 );
		add_filter( 'login_form_middle', array( $this, 'filter_login_middle' ), 20, 2 );
		add_filter( 'authenticate', array( $this, 'verify_login' ), 30, 3 );

		// Registrazione.
		add_action( 'register_form', array( $this, 'render_register' ), 20 );
		add_filter( 'registration_errors', array( $this, 'verify_register' ), 20, 3 );

		// Password dimenticata e reimpostazione.
		add_action( 'lostpassword_form', array( $this, 'render_lostpassword' ), 20 );
		add_action( 'resetpass_form', array( $this, 'render_resetpassword' ), 20 );
		add_action( 'lostpassword_post', array( $this, 'verify_lostpassword' ), 20, 2 );
		add_action( 'validate_password_reset', array( $this, 'verify_resetpassword' ), 20, 2 );

		// Commenti.
		add_filter( 'comment_form_submit_field', array( $this, 'render_comment' ), 20, 2 );
		add_filter( 'preprocess_comment', array( $this, 'verify_comment' ), 20, 1 );
	}

	/**
	 * Asset delle schermate di wp-login.php.
	 */
	public function login_assets() {
		Renderer::preload_assets( array( 'wp_login', 'wp_register', 'wp_lostpassword', 'wp_resetpassword' ) );
	}

	/* ---------------------------------------------------------------- *
	 * Accesso
	 * ---------------------------------------------------------------- */

	public function render_login() {
		// WooCommerce disegna il proprio widget: evita il doppio inserimento
		// nei temi che richiamano anche gli hook del core (come "creator").
		if ( Renderer::in_scope( 'wc_login' ) ) {
			return;
		}

		$this->render( 'wp_login' );
	}

	/**
	 * Variante a filtro, usata da wp_login_form() nei temi e nei widget.
	 *
	 * @param string $content Contenuto corrente.
	 * @param array  $args    Argomenti del modulo.
	 * @return string
	 */
	public function filter_login_middle( $content, $args = array() ) {
		return $content . $this->field( 'wp_login' );
	}

	/**
	 * Verifica al momento dell'autenticazione.
	 *
	 * Gira dopo il controllo credenziali del core (priorità 20) senza rimuoverlo:
	 * così gli errori di password restano quelli standard e il captcha non viene
	 * mai scavalcato.
	 *
	 * @param null|\WP_User|WP_Error $user     Utente.
	 * @param string                 $username Nome utente.
	 * @param string                 $password Password.
	 * @return null|\WP_User|WP_Error
	 */
	public function verify_login( $user, $username = '', $password = '' ) {
		if ( ! $this->is_login_post() ) {
			return $user;
		}

		$surface = $this->resolve_surface( array( 'wp_login', 'wc_login' ), 'wp_login' );
		$result  = $this->verify( $surface );

		if ( is_wp_error( $result ) ) {
			return new WP_Error(
				$result->get_error_code(),
				'<strong>' . esc_html__( 'Errore:', 'mm-recaptcha' ) . '</strong> ' . Verifier::message( $result )
			);
		}

		return $user;
	}

	/**
	 * Siamo davanti a un vero invio del modulo di accesso?
	 *
	 * @return bool
	 */
	protected function is_login_post() {
		if ( 'POST' !== ( isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) ) : '' ) ) {
			return false;
		}

		if ( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST ) {
			return false;
		}

		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return false;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- semplice rilevamento del tipo di richiesta.
		return isset( $_POST['log'] ) || isset( $_POST['username'] );
	}

	/* ---------------------------------------------------------------- *
	 * Registrazione
	 * ---------------------------------------------------------------- */

	public function render_register() {
		if ( Renderer::in_scope( 'wc_register' ) ) {
			return;
		}

		$this->render( 'wp_register' );
	}

	/**
	 * @param WP_Error $errors     Errori.
	 * @param string   $user_login Nome utente.
	 * @param string   $user_email Email.
	 * @return WP_Error
	 */
	public function verify_register( $errors, $user_login = '', $user_email = '' ) {
		$result = $this->verify( 'wp_register' );

		if ( is_wp_error( $result ) ) {
			if ( ! is_wp_error( $errors ) ) {
				$errors = new WP_Error();
			}

			// Si aggiunge all'elenco: gli altri errori di registrazione restano visibili.
			$errors->add( $result->get_error_code(), '<strong>' . esc_html__( 'Errore:', 'mm-recaptcha' ) . '</strong> ' . Verifier::message( $result ) );
		}

		return $errors;
	}

	/* ---------------------------------------------------------------- *
	 * Password
	 * ---------------------------------------------------------------- */

	public function render_lostpassword() {
		if ( Renderer::in_scope( 'wc_lostpassword' ) ) {
			return;
		}

		$this->render( 'wp_lostpassword' );
	}

	public function render_resetpassword() {
		if ( Renderer::in_scope( 'wc_resetpassword' ) ) {
			return;
		}

		$this->render( 'wp_resetpassword' );
	}

	/**
	 * @param WP_Error $errors    Errori.
	 * @param mixed    $user_data Utente.
	 */
	public function verify_lostpassword( $errors, $user_data = null ) {
		if ( ! is_wp_error( $errors ) ) {
			return;
		}

		$surface = $this->resolve_surface( array( 'wp_lostpassword', 'wc_lostpassword' ), 'wp_lostpassword' );
		$result  = $this->verify( $surface );

		if ( is_wp_error( $result ) ) {
			$errors->add( $result->get_error_code(), '<strong>' . esc_html__( 'Errore:', 'mm-recaptcha' ) . '</strong> ' . Verifier::message( $result ) );
		}
	}

	/**
	 * @param WP_Error $errors Errori.
	 * @param mixed    $user   Utente.
	 */
	public function verify_resetpassword( $errors, $user = null ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- il modulo ha il proprio controllo, qui serve solo distinguere l'invio.
		if ( ! isset( $_POST['pass1'] ) || ! is_wp_error( $errors ) ) {
			return;
		}

		$surface = $this->resolve_surface( array( 'wp_resetpassword', 'wc_resetpassword' ), 'wp_resetpassword' );
		$result  = $this->verify( $surface );

		if ( is_wp_error( $result ) ) {
			$errors->add( $result->get_error_code(), '<strong>' . esc_html__( 'Errore:', 'mm-recaptcha' ) . '</strong> ' . Verifier::message( $result ) );
		}
	}

	/* ---------------------------------------------------------------- *
	 * Commenti
	 * ---------------------------------------------------------------- */

	/**
	 * Inserisce il captcha subito prima del pulsante di invio.
	 *
	 * Questo filtro scatta una sola volta e sia per utenti anonimi sia per
	 * utenti collegati, quindi evita i doppi inserimenti dei temi.
	 *
	 * @param string $submit_field Campo di invio.
	 * @param array  $args         Argomenti.
	 * @return string
	 */
	public function render_comment( $submit_field, $args = array() ) {
		return $this->field( 'wp_comments' ) . $submit_field;
	}

	/**
	 * @param array $commentdata Dati del commento.
	 * @return array
	 */
	public function verify_comment( $commentdata ) {
		$result = $this->verify( 'wp_comments' );

		if ( is_wp_error( $result ) ) {
			wp_die(
				'<p><strong>' . esc_html__( 'Errore:', 'mm-recaptcha' ) . '</strong> ' . esc_html( wp_strip_all_tags( Verifier::message( $result ) ) ) . '</p>',
				esc_html__( 'Verifica di sicurezza non superata', 'mm-recaptcha' ),
				array(
					'response'  => 403,
					'back_link' => true,
				)
			);
		}

		return $commentdata;
	}
}
