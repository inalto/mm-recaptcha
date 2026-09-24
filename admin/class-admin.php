<?php
/**
 * Amministrazione: menu, asset, avvisi.
 *
 * @package MM_Recaptcha
 */

namespace MM_Recaptcha;

use MM_Recaptcha\Integrations\Integration_CF7;

defined( 'ABSPATH' ) || exit;

class Admin {

	const SLUG = 'mm-recaptcha';

	/**
	 * Registra gli hook di amministrazione.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_init', array( 'MM_Recaptcha\\Options', 'register' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
		add_action( 'admin_notices', array( __CLASS__, 'notices' ) );
		add_filter( 'plugin_action_links_' . MM_RECAPTCHA_BASENAME, array( __CLASS__, 'action_links' ) );

		Admin_Ajax::init();
	}

	/**
	 * Voce di menu.
	 */
	public static function menu() {
		add_menu_page(
			__( 'MM reCAPTCHA', 'mm-recaptcha' ),
			__( 'MM reCAPTCHA', 'mm-recaptcha' ),
			'manage_options',
			self::SLUG,
			array( 'MM_Recaptcha\\Settings', 'render_page' ),
			'dashicons-shield',
			76
		);
	}

	/**
	 * Collegamenti nella pagina dei plugin.
	 *
	 * @param array $links Collegamenti.
	 * @return array
	 */
	public static function action_links( $links ) {
		$settings = sprintf(
			'<a href="%s">%s</a>',
			esc_url( admin_url( 'admin.php?page=' . self::SLUG ) ),
			esc_html__( 'Impostazioni', 'mm-recaptcha' )
		);

		array_unshift( $links, $settings );

		return $links;
	}

	/**
	 * Asset della pagina.
	 *
	 * @param string $hook Schermata corrente.
	 */
	public static function enqueue( $hook ) {
		$is_settings_page = ( 'toplevel_page_' . self::SLUG === $hook );

		// Sulle altre schermate lo script serve solo ai pulsanti dell'avviso di migrazione.
		if ( ! $is_settings_page && ! Migrator::available() ) {
			return;
		}

		if ( $is_settings_page ) {
			wp_enqueue_style( 'mm-recaptcha-admin', MM_RECAPTCHA_URL . 'admin/css/admin.css', array(), MM_RECAPTCHA_VERSION );
		}

		wp_enqueue_script( 'mm-recaptcha-admin', MM_RECAPTCHA_URL . 'admin/js/admin.js', array(), MM_RECAPTCHA_VERSION, true );

		wp_localize_script(
			'mm-recaptcha-admin',
			'mmRecaptchaAdmin',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'mm_recaptcha_admin' ),
				'strings' => array(
					'testing'   => __( 'Verifica in corso…', 'mm-recaptcha' ),
					'solve'     => __( 'Risolvi la verifica qui sotto, poi premi "Invia la prova".', 'mm-recaptcha' ),
					'submit'    => __( 'Invia la prova', 'mm-recaptcha' ),
					'loadError' => __( 'Impossibile caricare il widget: controlla la site key e che il dominio sia autorizzato.', 'mm-recaptcha' ),
					'confirm'   => __( 'Vuoi davvero svuotare il registro?', 'mm-recaptcha' ),
					'show'      => __( 'Mostra', 'mm-recaptcha' ),
					'hide'      => __( 'Nascondi', 'mm-recaptcha' ),
					'answer'    => __( 'Risposta', 'mm-recaptcha' ),
					'score'     => __( 'Punteggio', 'mm-recaptcha' ),
					'hostname'  => __( 'Dominio', 'mm-recaptcha' ),
					'action'    => __( 'Azione', 'mm-recaptcha' ),
					'elapsed'   => __( 'Tempo di risposta', 'mm-recaptcha' ),
				),
			)
		);
	}

	/**
	 * Avvisi.
	 */
	public static function notices() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		self::migration_notice();
		self::conflict_notice();
	}

	/**
	 * Proposta di importazione dal vecchio plugin.
	 */
	protected static function migration_notice() {
		if ( ! Migrator::available() ) {
			return;
		}

		$preview = Migrator::preview();

		echo '<div class="notice notice-info mm-rc-migration">';
		echo '<p><strong>' . esc_html__( 'MM reCAPTCHA', 'mm-recaptcha' ) . '</strong> — ' . esc_html__( 'Ho trovato le impostazioni di Advanced Google reCAPTCHA. Vuoi importarle?', 'mm-recaptcha' ) . '</p>';

		if ( ! empty( $preview['rows'] ) ) {
			echo '<ul style="margin-left:18px;list-style:disc">';
			foreach ( $preview['rows'] as $row ) {
				echo '<li>' . esc_html( $row['label'] ) . ': <code>' . esc_html( $row['value'] ) . '</code></li>';
			}
			echo '</ul>';
		}

		printf(
			'<p><button type="button" class="button button-primary mm-rc-migrate">%s</button> <button type="button" class="button mm-rc-dismiss-migration">%s</button></p>',
			esc_html__( 'Importa le impostazioni', 'mm-recaptcha' ),
			esc_html__( 'No, configuro da zero', 'mm-recaptcha' )
		);

		echo '</div>';
	}

	/**
	 * Avvisi su conflitti con altri plugin.
	 */
	protected static function conflict_notice() {
		if ( Migrator::legacy_active() ) {
			echo '<div class="notice notice-warning"><p>';
			printf(
				/* translators: %s: nome del vecchio plugin. */
				esc_html__( 'MM reCAPTCHA e %s sono attivi entrambi: sugli stessi moduli comparirebbero due captcha. Disattiva il vecchio plugin.', 'mm-recaptcha' ),
				'<strong>Advanced Google reCAPTCHA</strong>'
			);
			echo ' <a href="' . esc_url( admin_url( 'plugins.php' ) ) . '">' . esc_html__( 'Vai ai plugin', 'mm-recaptcha' ) . '</a>';
			echo '</p></div>';
		}

		if ( defined( 'WPCF7_VERSION' ) && Options::get( 'forms.cf7_auto_inject', 0 ) ) {
			$conflict = Integration_CF7::native_conflict();

			if ( $conflict ) {
				echo '<div class="notice notice-warning"><p>';
				printf(
					/* translators: %s: nome del servizio nativo di Contact Form 7. */
					esc_html__( 'Contact Form 7 ha già %s configurato: l’inserimento automatico di MM reCAPTCHA resta sospeso per non mostrare due captcha nello stesso modulo.', 'mm-recaptcha' ),
					'<strong>' . esc_html( $conflict ) . '</strong>'
				);
				echo '</p></div>';
			}
		}
	}
}
