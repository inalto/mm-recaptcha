<?php
/**
 * Integrazione con WooCommerce.
 *
 * @package MM_Recaptcha
 */

namespace MM_Recaptcha\Integrations;

use MM_Recaptcha\Renderer;
use MM_Recaptcha\Verifier;
use WP_Error;

defined( 'ABSPATH' ) || exit;

class Integration_WooCommerce extends Integration {

	public function init() {
		// Ambiti: servono a impedire che i temi che richiamano anche gli hook
		// del core (come "creator") disegnino un secondo widget.
		add_action( 'woocommerce_login_form_start', array( $this, 'enter_login_scope' ), 1 );
		add_action( 'woocommerce_login_form_end', array( $this, 'exit_login_scope' ), 999 );
		add_action( 'woocommerce_register_form_start', array( $this, 'enter_register_scope' ), 1 );
		add_action( 'woocommerce_register_form_end', array( $this, 'exit_register_scope' ), 999 );

		// Accesso.
		add_action( 'woocommerce_login_form', array( $this, 'render_login' ), 20 );
		add_filter( 'woocommerce_process_login_errors', array( $this, 'verify_login' ), 20, 3 );

		// Registrazione.
		add_action( 'woocommerce_register_form', array( $this, 'render_register' ), 20 );
		add_filter( 'woocommerce_process_registration_errors', array( $this, 'verify_register' ), 20, 4 );

		// Password.
		add_action( 'woocommerce_lostpassword_form', array( $this, 'render_lostpassword' ), 20 );
		add_action( 'woocommerce_resetpassword_form', array( $this, 'render_resetpassword' ), 20 );

		// Checkout classico.
		add_action( $this->checkout_hook(), array( $this, 'render_checkout' ), 20 );
		add_action( 'woocommerce_after_checkout_validation', array( $this, 'verify_checkout' ), 20, 2 );

		// Pagamento di un ordine esistente.
		add_action( 'woocommerce_pay_order_before_submit', array( $this, 'render_pay' ), 20 );
		add_action( 'woocommerce_before_pay_action', array( $this, 'verify_pay' ), 20, 1 );
	}

	/**
	 * Punto di inserimento nel checkout.
	 *
	 * Per impostazione predefinita si usa un hook esterno al frammento
	 * rigenerato via AJAX: così il widget non viene distrutto a ogni
	 * aggiornamento del carrello o dell'indirizzo.
	 *
	 * @return string
	 */
	protected function checkout_hook() {
		/**
		 * Consente di spostare il captcha nel checkout.
		 *
		 * @param string $hook Nome dell'hook.
		 */
		return (string) apply_filters( 'mm_recaptcha_checkout_hook', 'woocommerce_checkout_after_order_review' );
	}

	/* ---------------------------------------------------------------- *
	 * Ambiti
	 * ---------------------------------------------------------------- */

	public function enter_login_scope() {
		Renderer::enter_scope( 'wc_login' );
	}

	public function exit_login_scope() {
		Renderer::exit_scope( 'wc_login' );
	}

	public function enter_register_scope() {
		Renderer::enter_scope( 'wc_register' );
	}

	public function exit_register_scope() {
		Renderer::exit_scope( 'wc_register' );
	}

	/* ---------------------------------------------------------------- *
	 * Render
	 * ---------------------------------------------------------------- */

	public function render_login() {
		$this->render( 'wc_login' );
	}

	public function render_register() {
		$this->render( 'wc_register' );
	}

	public function render_lostpassword() {
		Renderer::enter_scope( 'wc_lostpassword' );
		$this->render( 'wc_lostpassword' );
		Renderer::exit_scope( 'wc_lostpassword' );
	}

	public function render_resetpassword() {
		Renderer::enter_scope( 'wc_resetpassword' );
		$this->render( 'wc_resetpassword' );
		Renderer::exit_scope( 'wc_resetpassword' );
	}

	public function render_checkout() {
		$this->render( 'wc_checkout' );
	}

	public function render_pay() {
		$this->render( 'wc_pay' );
	}

	/* ---------------------------------------------------------------- *
	 * Verifica
	 * ---------------------------------------------------------------- */

	/**
	 * @param WP_Error $validation_error Errori.
	 * @param string   $username         Nome utente.
	 * @param string   $password         Password.
	 * @return WP_Error
	 */
	public function verify_login( $validation_error, $username = '', $password = '' ) {
		return $this->add_error( $validation_error, 'wc_login' );
	}

	/**
	 * @param WP_Error $validation_error Errori.
	 * @param string   $username         Nome utente.
	 * @param string   $password         Password.
	 * @param string   $email            Email.
	 * @return WP_Error
	 */
	public function verify_register( $validation_error, $username = '', $password = '', $email = '' ) {
		return $this->add_error( $validation_error, 'wc_register' );
	}

	/**
	 * @param array    $data   Dati del checkout.
	 * @param WP_Error $errors Errori.
	 */
	public function verify_checkout( $data, $errors = null ) {
		$result = $this->verify( 'wc_checkout' );

		if ( ! is_wp_error( $result ) ) {
			return;
		}

		if ( is_wp_error( $errors ) ) {
			$errors->add( $result->get_error_code(), Verifier::message( $result ) );
		} elseif ( function_exists( 'wc_add_notice' ) ) {
			wc_add_notice( Verifier::message( $result ), 'error' );
		}
	}

	/**
	 * Pagamento di un ordine.
	 *
	 * L'hook gira fuori dal try/catch di WooCommerce: qui non si solleva mai
	 * un'eccezione, si aggiunge una notifica di errore che blocca da sola il
	 * proseguimento del pagamento.
	 *
	 * @param mixed $order Ordine.
	 */
	public function verify_pay( $order = null ) {
		$result = $this->verify( 'wc_pay' );

		if ( is_wp_error( $result ) && function_exists( 'wc_add_notice' ) ) {
			wc_add_notice( Verifier::message( $result ), 'error' );
		}
	}

	/**
	 * Aggiunge l'errore all'oggetto di WooCommerce.
	 *
	 * @param WP_Error $errors  Errori.
	 * @param string   $surface Superficie.
	 * @return WP_Error
	 */
	protected function add_error( $errors, $surface ) {
		$result = $this->verify( $surface );

		if ( is_wp_error( $result ) ) {
			if ( ! is_wp_error( $errors ) ) {
				$errors = new WP_Error();
			}

			$errors->add( $result->get_error_code(), Verifier::message( $result ) );
		}

		return $errors;
	}
}
