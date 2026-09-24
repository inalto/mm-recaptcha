<?php
/**
 * Integrazione con Contact Form 7.
 *
 * @package MM_Recaptcha
 */

namespace MM_Recaptcha\Integrations;

use MM_Recaptcha\Options;
use MM_Recaptcha\Provider_Registry;
use MM_Recaptcha\Renderer;
use MM_Recaptcha\Verifier;
use MM_Recaptcha\Providers\Math;

defined( 'ABSPATH' ) || exit;

class Integration_CF7 extends Integration {

	const TAG = 'mm_captcha';

	public function init() {
		add_action( 'wpcf7_init', array( $this, 'register_tag' ), 20 );
		add_filter( 'wpcf7_form_elements', array( $this, 'auto_inject' ), 20, 1 );
		add_filter( 'wpcf7_validate_' . self::TAG, array( $this, 'validate' ), 20, 2 );
		add_filter( 'wpcf7_validate_' . self::TAG . '*', array( $this, 'validate' ), 20, 2 );
		add_filter( 'wpcf7_refill_response', array( $this, 'refill' ), 10, 1 );
		add_filter( 'wpcf7_feedback_response', array( $this, 'refill' ), 10, 1 );

		if ( is_admin() ) {
			add_action( 'wpcf7_admin_init', array( $this, 'tag_generator' ), 80 );
		}
	}

	/**
	 * Registra il form-tag [mm_captcha].
	 */
	public function register_tag() {
		if ( ! function_exists( 'wpcf7_add_form_tag' ) ) {
			return;
		}

		wpcf7_add_form_tag(
			array( self::TAG, self::TAG . '*' ),
			array( $this, 'tag_handler' ),
			array(
				'name-attr'     => true,
				'display-block' => true,
				'singular'      => true,
				'not-for-mail'  => true,
				'do-not-store'  => true,
			)
		);
	}

	/**
	 * HTML del tag.
	 *
	 * @param object $tag Form-tag.
	 * @return string
	 */
	public function tag_handler( $tag ) {
		$classes = method_exists( $tag, 'get_class_option' ) ? $tag->get_class_option( 'mm-recaptcha-cf7' ) : 'mm-recaptcha-cf7';

		return Renderer::render(
			'cf7',
			array(
				'class' => $classes,
			)
		);
	}

	/**
	 * Inserisce il captcha nei moduli che non contengono il tag.
	 *
	 * @param string $content HTML del modulo.
	 * @return string
	 */
	public function auto_inject( $content ) {
		if ( ! Options::get( 'forms.cf7_auto_inject', 0 ) ) {
			return $content;
		}

		if ( ! Verifier::should_protect( 'cf7' ) ) {
			return $content;
		}

		if ( self::native_conflict() ) {
			return $content;
		}

		if ( ! class_exists( 'WPCF7_ContactForm' ) || ! class_exists( 'WPCF7_FormTagsManager' ) ) {
			return $content;
		}

		$form = \WPCF7_ContactForm::get_current();

		if ( ! $form ) {
			return $content;
		}

		$tags = $form->scan_form_tags( array( 'type' => array( self::TAG, self::TAG . '*' ) ) );

		if ( ! empty( $tags ) ) {
			return $content;
		}

		$manager = \WPCF7_FormTagsManager::get_instance();

		return $manager->replace_all( '[' . self::TAG . ' mm-captcha-1]' ) . "\n" . $content;
	}

	/**
	 * Validazione del tag.
	 *
	 * @param object $result Risultato di validazione.
	 * @param object $tag    Form-tag.
	 * @return object
	 */
	public function validate( $result, $tag ) {
		$check = $this->verify( 'cf7' );

		if ( is_wp_error( $check ) ) {
			$result->invalidate( $tag, Verifier::message( $check ) );

			$submission = class_exists( 'WPCF7_Submission' ) ? \WPCF7_Submission::get_instance() : null;

			if ( $submission && method_exists( $submission, 'add_spam_log' ) ) {
				$submission->add_spam_log(
					array(
						'agent'  => 'mm-recaptcha',
						'reason' => $check->get_error_code(),
					)
				);
			}
		}

		return $result;
	}

	/**
	 * Rinnova la sfida del captcha matematico dopo un invio AJAX.
	 *
	 * @param array $items Dati restituiti a Contact Form 7.
	 * @return array
	 */
	public function refill( $items ) {
		$provider = Provider_Registry::active();

		if ( $provider instanceof Math ) {
			$items['mm_recaptcha'] = $provider->fresh_challenge();
		} else {
			$items['mm_recaptcha'] = array( 'reset' => true );
		}

		return $items;
	}

	/**
	 * Pannello nel generatore di tag di Contact Form 7.
	 */
	public function tag_generator() {
		if ( ! function_exists( 'wpcf7_add_tag_generator' ) ) {
			return;
		}

		wpcf7_add_tag_generator(
			self::TAG,
			__( 'MM Captcha', 'mm-recaptcha' ),
			'mm-recaptcha-tag-generator',
			array( $this, 'tag_generator_panel' )
		);
	}

	/**
	 * Contenuto del pannello del generatore di tag.
	 *
	 * @param object $contact_form Modulo.
	 * @param array  $args         Argomenti.
	 */
	public function tag_generator_panel( $contact_form, $args = '' ) {
		$args = wp_parse_args( $args, array() );

		echo '<div class="control-box"><fieldset>';
		echo '<legend>' . esc_html__( 'Inserisce il captcha configurato in MM reCAPTCHA.', 'mm-recaptcha' ) . '</legend>';
		echo '<p>' . esc_html__( 'Copia il tag qui sotto e incollalo nel modulo, nel punto in cui vuoi che compaia la verifica.', 'mm-recaptcha' ) . '</p>';
		echo '</fieldset></div>';

		echo '<div class="insert-box">';
		echo '<input type="text" name="' . esc_attr( self::TAG ) . '" class="tag code" readonly="readonly" onfocus="this.select()" value="[' . esc_attr( self::TAG ) . ' mm-captcha-1]" />';
		echo '<div class="submitbox"><input type="button" class="button button-primary insert-tag" value="' . esc_attr__( 'Inserisci tag', 'mm-recaptcha' ) . '" /></div>';
		echo '</div>';
	}

	/**
	 * Contact Form 7 ha già un proprio servizio captcha attivo?
	 *
	 * @return string Nome del servizio in conflitto, stringa vuota se nessuno.
	 */
	public static function native_conflict() {
		if ( ! class_exists( 'WPCF7' ) ) {
			return '';
		}

		$recaptcha = \WPCF7::get_option( 'recaptcha' );

		if ( ! empty( $recaptcha ) ) {
			return 'reCAPTCHA';
		}

		$turnstile = \WPCF7::get_option( 'turnstile' );

		if ( ! empty( $turnstile ) ) {
			return 'Turnstile';
		}

		return '';
	}
}
