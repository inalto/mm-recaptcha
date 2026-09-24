<?php
/**
 * Base per le integrazioni con i moduli.
 *
 * @package MM_Recaptcha
 */

namespace MM_Recaptcha\Integrations;

use MM_Recaptcha\Renderer;
use MM_Recaptcha\Verifier;

defined( 'ABSPATH' ) || exit;

abstract class Integration {

	/**
	 * Istanze.
	 *
	 * @var array
	 */
	protected static $instances = array();

	/**
	 * Istanza della singola integrazione.
	 *
	 * @return static
	 */
	public static function instance() {
		$class = static::class;

		if ( ! isset( self::$instances[ $class ] ) ) {
			self::$instances[ $class ] = new $class();
		}

		return self::$instances[ $class ];
	}

	/**
	 * Registra gli hook.
	 */
	abstract public function init();

	/**
	 * Stampa il captcha.
	 *
	 * @param string $surface Superficie.
	 */
	protected function render( $surface ) {
		Renderer::render_echo( $surface );
	}

	/**
	 * Restituisce l'HTML del captcha.
	 *
	 * @param string $surface Superficie.
	 * @return string
	 */
	protected function field( $surface ) {
		return Renderer::render( $surface );
	}

	/**
	 * Verifica.
	 *
	 * @param string $surface Superficie.
	 * @return true|\WP_Error
	 */
	protected function verify( $surface ) {
		return Verifier::verify( $surface );
	}

	/**
	 * Alcuni hook del core vengono rilanciati anche da WooCommerce.
	 *
	 * Si preferisce la superficie dichiarata dal modulo inviato, ma solo se è
	 * effettivamente protetta: altrimenti si ricade sulla prima superficie
	 * equivalente attiva. Così un invio che dichiara di arrivare da un modulo
	 * disattivato non può saltare il controllo di quello attivo.
	 *
	 * @param string[] $allowed  Superfici equivalenti.
	 * @param string   $fallback Superficie predefinita.
	 * @return string
	 */
	protected function resolve_surface( array $allowed, $fallback ) {
		$submitted = Verifier::submitted_surface();

		if ( in_array( $submitted, $allowed, true ) && Verifier::should_protect( $submitted ) ) {
			return $submitted;
		}

		foreach ( $allowed as $surface ) {
			if ( Verifier::should_protect( $surface ) ) {
				return $surface;
			}
		}

		return $fallback;
	}
}
