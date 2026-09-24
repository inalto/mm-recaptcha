<?php
/**
 * API pubblica per temi e plugin.
 *
 * @package MM_Recaptcha
 */

namespace MM_Recaptcha {

	defined( 'ABSPATH' ) || exit;

	/**
	 * Shortcode [mm_recaptcha].
	 */
	class Shortcode {

		/**
		 * Registra lo shortcode.
		 */
		public static function init() {
			add_shortcode( 'mm_recaptcha', array( __CLASS__, 'render' ) );
		}

		/**
		 * @param array $atts Attributi.
		 * @return string
		 */
		public static function render( $atts ) {
			$atts = shortcode_atts(
				array(
					'surface' => 'custom',
					'class'   => '',
				),
				$atts,
				'mm_recaptcha'
			);

			return Renderer::render(
				sanitize_key( $atts['surface'] ),
				array( 'class' => sanitize_text_field( $atts['class'] ) )
			);
		}
	}
}

namespace {

	use MM_Recaptcha\Options;
	use MM_Recaptcha\Provider_Registry;
	use MM_Recaptcha\Renderer;
	use MM_Recaptcha\Verifier;

	defined( 'ABSPATH' ) || exit;

	/**
	 * HTML del campo captcha, da inserire in un modulo personalizzato.
	 *
	 * @param string $surface Identificativo della superficie.
	 * @param array  $args    Argomenti opzionali (class).
	 * @return string
	 */
	function mm_recaptcha_field( $surface = 'custom', array $args = array() ) {
		return Renderer::render( sanitize_key( $surface ), $args );
	}

	/**
	 * Stampa il campo captcha.
	 *
	 * @param string $surface Superficie.
	 * @param array  $args    Argomenti.
	 */
	function mm_recaptcha_the_field( $surface = 'custom', array $args = array() ) {
		echo mm_recaptcha_field( $surface, $args ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Verifica l'invio corrente.
	 *
	 * @param string $surface Superficie.
	 * @return true|WP_Error
	 */
	function mm_recaptcha_verify( $surface = 'custom' ) {
		return Verifier::verify( sanitize_key( $surface ) );
	}

	/**
	 * Il captcha è attivo per questa superficie?
	 *
	 * @param string $surface Superficie.
	 * @return bool
	 */
	function mm_recaptcha_is_enabled( $surface = 'custom' ) {
		return Verifier::should_protect( sanitize_key( $surface ) );
	}

	/**
	 * Identificativo del provider attivo.
	 *
	 * @return string
	 */
	function mm_recaptcha_active_provider() {
		$provider = Provider_Registry::active();

		return $provider ? $provider->id() : '';
	}

	/**
	 * Legge una impostazione del plugin.
	 *
	 * @param string $path    Percorso puntato.
	 * @param mixed  $default Ripiego.
	 * @return mixed
	 */
	function mm_recaptcha_get_option( $path, $default = null ) {
		return Options::get( $path, $default );
	}
}
