<?php
/**
 * Pagina delle impostazioni.
 *
 * @package MM_Recaptcha
 */

namespace MM_Recaptcha;

use MM_Recaptcha\Providers\Provider;

defined( 'ABSPATH' ) || exit;

class Settings {

	/**
	 * Disegna la pagina completa.
	 */
	public static function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Non hai i permessi per accedere a questa pagina.', 'mm-recaptcha' ) );
		}

		$tabs = array(
			'provider' => __( 'Captcha e chiavi', 'mm-recaptcha' ),
			'forms'    => __( 'Dove mostrarlo', 'mm-recaptcha' ),
			'advanced' => __( 'Avanzate', 'mm-recaptcha' ),
			'log'      => __( 'Registro', 'mm-recaptcha' ),
			'help'     => __( 'Come ottenere le chiavi', 'mm-recaptcha' ),
		);

		echo '<div class="wrap mm-rc-wrap">';
		echo '<h1>' . esc_html__( 'MM reCAPTCHA', 'mm-recaptcha' ) . '</h1>';

		settings_errors( Options::KEY );
		self::status_banner();

		echo '<h2 class="nav-tab-wrapper mm-rc-tabs">';
		foreach ( $tabs as $id => $label ) {
			printf(
				'<a href="#%1$s" class="nav-tab" data-tab="%1$s">%2$s</a>',
				esc_attr( $id ),
				esc_html( $label )
			);
		}
		echo '</h2>';

		echo '<form method="post" action="options.php" id="mm-rc-form">';
		settings_fields( Options::KEY );

		echo '<div class="mm-rc-panel" data-panel="provider">';
		self::tab_provider();
		echo '</div>';

		echo '<div class="mm-rc-panel" data-panel="forms">';
		self::tab_forms();
		echo '</div>';

		echo '<div class="mm-rc-panel" data-panel="advanced">';
		self::tab_advanced();
		echo '</div>';

		submit_button( __( 'Salva impostazioni', 'mm-recaptcha' ) );
		echo '</form>';

		echo '<div class="mm-rc-panel" data-panel="log">';
		self::tab_log();
		echo '</div>';

		echo '<div class="mm-rc-panel" data-panel="help">';
		self::tab_help();
		echo '</div>';

		echo '</div>';
	}

	/**
	 * Riquadro di stato in cima alla pagina.
	 */
	protected static function status_banner() {
		$provider = Provider_Registry::active();
		$ready    = $provider instanceof Provider && $provider->is_configured();

		$forms  = (array) Options::get( 'forms', array() );
		$active = 0;

		foreach ( $forms as $key => $value ) {
			if ( $value && 'cf7_auto_inject' !== $key ) {
				$active++;
			}
		}

		$class = $ready && $active ? 'mm-rc-status-ok' : 'mm-rc-status-warn';

		echo '<div class="mm-rc-status ' . esc_attr( $class ) . '">';

		if ( ! $provider ) {
			echo '<strong>' . esc_html__( 'Nessun provider selezionato.', 'mm-recaptcha' ) . '</strong>';
		} elseif ( ! $ready ) {
			printf(
				'<strong>%s</strong> %s',
				esc_html__( 'Captcha non attivo.', 'mm-recaptcha' ),
				sprintf(
					/* translators: %s: nome del provider. */
					esc_html__( 'Mancano le chiavi per %s: finché non le inserisci nessun modulo viene protetto.', 'mm-recaptcha' ),
					esc_html( $provider->label() )
				)
			);
		} elseif ( ! $active ) {
			printf(
				'<strong>%s</strong> %s',
				esc_html__( 'Captcha configurato ma non in uso.', 'mm-recaptcha' ),
				esc_html__( 'Attiva almeno un modulo nella scheda "Dove mostrarlo".', 'mm-recaptcha' )
			);
		} else {
			printf(
				'<strong>%s</strong> %s',
				esc_html__( 'Captcha attivo.', 'mm-recaptcha' ),
				sprintf(
					/* translators: 1: nome del provider, 2: numero di moduli protetti. */
					esc_html( _n( '%1$s protegge %2$d modulo.', '%1$s protegge %2$d moduli.', $active, 'mm-recaptcha' ) ),
					esc_html( $provider->label() ),
					(int) $active
				)
			);
		}

		echo '</div>';
	}

	/* ------------------------------------------------------------------ *
	 * Scheda provider
	 * ------------------------------------------------------------------ */

	protected static function tab_provider() {
		$providers = Provider_Registry::available();
		$active    = (string) Options::get( 'provider', 'math' );

		echo '<h2>' . esc_html__( 'Scegli il tipo di captcha', 'mm-recaptcha' ) . '</h2>';
		echo '<p class="description">' . esc_html__( 'Tutti i tipi sono pienamente funzionanti: non ci sono funzioni riservate a versioni a pagamento.', 'mm-recaptcha' ) . '</p>';

		echo '<div class="mm-rc-cards">';

		foreach ( $providers as $id => $provider ) {
			printf(
				'<label class="mm-rc-card%1$s" for="mm-rc-provider-%2$s">',
				$active === $id ? ' is-selected' : '',
				esc_attr( $id )
			);

			printf(
				'<input type="radio" id="mm-rc-provider-%1$s" name="%2$s[provider]" value="%1$s" %3$s />',
				esc_attr( $id ),
				esc_attr( Options::KEY ),
				checked( $active, $id, false )
			);

			echo '<span class="mm-rc-card-title">' . esc_html( $provider->label() ) . '</span>';
			echo '<span class="mm-rc-card-desc">' . esc_html( $provider->description() ) . '</span>';

			$highlights = $provider->highlights();

			if ( $highlights ) {
				echo '<ul class="mm-rc-card-list">';
				foreach ( $highlights as $item ) {
					echo '<li>' . esc_html( $item ) . '</li>';
				}
				echo '</ul>';
			}

			if ( ! $provider->needs_keys() ) {
				echo '<span class="mm-rc-badge mm-rc-badge-ok">' . esc_html__( 'Nessuna chiave', 'mm-recaptcha' ) . '</span>';
			} elseif ( $provider->is_configured() ) {
				echo '<span class="mm-rc-badge mm-rc-badge-ok">' . esc_html__( 'Chiavi presenti', 'mm-recaptcha' ) . '</span>';
			} else {
				echo '<span class="mm-rc-badge mm-rc-badge-warn">' . esc_html__( 'Chiavi da inserire', 'mm-recaptcha' ) . '</span>';
			}

			echo '</label>';
		}

		echo '</div>';

		foreach ( $providers as $id => $provider ) {
			printf(
				'<div class="mm-rc-provider-settings" data-provider="%s"%s>',
				esc_attr( $id ),
				$active === $id ? '' : ' hidden'
			);

			echo '<h2>' . esc_html( $provider->label() ) . '</h2>';

			self::key_help_box( $provider );

			echo '<table class="form-table" role="presentation"><tbody>';

			foreach ( $provider->settings_fields() as $field ) {
				self::render_field( $id, $field, $provider );
			}

			echo '</tbody></table>';

			if ( $provider->needs_keys() ) {
				printf(
					'<p><button type="button" class="button mm-rc-test" data-provider="%s">%s</button> <span class="mm-rc-test-feedback"></span></p>',
					esc_attr( $id ),
					esc_html__( 'Prova le chiavi', 'mm-recaptcha' )
				);
				echo '<div class="mm-rc-test-panel" hidden></div>';
			}

			echo '</div>';
		}
	}

	/**
	 * Riquadro con la guida alle chiavi.
	 *
	 * @param Provider $provider Provider.
	 */
	protected static function key_help_box( Provider $provider ) {
		$help = $provider->key_help();

		echo '<div class="mm-rc-help">';
		echo '<h3>' . esc_html__( 'Chiavi necessarie e dove ottenerle', 'mm-recaptcha' ) . '</h3>';

		if ( ! empty( $help['needs'] ) ) {
			echo '<ul class="mm-rc-help-needs">';
			foreach ( $help['needs'] as $need ) {
				echo '<li>' . esc_html( $need ) . '</li>';
			}
			echo '</ul>';
		}

		if ( ! empty( $help['links'] ) ) {
			echo '<p class="mm-rc-help-links">';
			foreach ( $help['links'] as $link ) {
				printf(
					'<a class="button button-secondary" href="%s" target="_blank" rel="noopener noreferrer">%s <span aria-hidden="true">&#8599;</span></a> ',
					esc_url( $link['url'] ),
					esc_html( $link['label'] )
				);
			}
			echo '</p>';
		}

		if ( ! empty( $help['steps'] ) ) {
			echo '<ol class="mm-rc-help-steps">';
			foreach ( $help['steps'] as $step ) {
				echo '<li>' . esc_html( $step ) . '</li>';
			}
			echo '</ol>';
		}

		if ( ! empty( $help['notes'] ) ) {
			echo '<ul class="mm-rc-help-notes">';
			foreach ( $help['notes'] as $note ) {
				echo '<li>' . esc_html( $note ) . '</li>';
			}
			echo '</ul>';
		}

		echo '</div>';
	}

	/**
	 * Disegna un campo di un provider.
	 *
	 * @param string   $provider_id Identificativo.
	 * @param array    $field       Definizione.
	 * @param Provider $provider    Provider.
	 */
	protected static function render_field( $provider_id, array $field, Provider $provider ) {
		$key   = $field['key'];
		$name  = sprintf( '%s[%s][%s]', Options::KEY, $provider_id, $key );
		$id    = sprintf( 'mm-rc-%s-%s', $provider_id, $key );
		$value = Options::get( $provider_id . '.' . $key, '' );
		$type  = isset( $field['type'] ) ? $field['type'] : 'text';

		echo '<tr>';
		echo '<th scope="row"><label for="' . esc_attr( $id ) . '">' . esc_html( $field['label'] ) . '</label></th>';
		echo '<td>';

		switch ( $type ) {
			case 'select':
				printf( '<select id="%s" name="%s">', esc_attr( $id ), esc_attr( $name ) );
				foreach ( $field['options'] as $option_value => $option_label ) {
					printf(
						'<option value="%s" %s>%s</option>',
						esc_attr( $option_value ),
						selected( (string) $value, (string) $option_value, false ),
						esc_html( $option_label )
					);
				}
				echo '</select>';
				break;

			case 'checkbox':
				printf(
					'<label><input type="checkbox" id="%s" name="%s" value="1" %s /> %s</label>',
					esc_attr( $id ),
					esc_attr( $name ),
					checked( (bool) $value, true, false ),
					esc_html__( 'Attivo', 'mm-recaptcha' )
				);
				break;

			case 'number':
				printf(
					'<input type="number" class="small-text" id="%s" name="%s" value="%s" min="%s" max="%s" step="%s" />',
					esc_attr( $id ),
					esc_attr( $name ),
					esc_attr( (string) $value ),
					esc_attr( isset( $field['min'] ) ? (string) $field['min'] : '' ),
					esc_attr( isset( $field['max'] ) ? (string) $field['max'] : '' ),
					esc_attr( isset( $field['step'] ) ? (string) $field['step'] : '1' )
				);
				break;

			case 'password':
				printf(
					'<input type="password" class="regular-text mm-rc-key" id="%s" name="%s" value="%s" autocomplete="off" spellcheck="false" data-key="%s" />',
					esc_attr( $id ),
					esc_attr( $name ),
					esc_attr( (string) $value ),
					esc_attr( $key )
				);
				printf(
					' <button type="button" class="button button-small mm-rc-reveal">%s</button>',
					esc_html__( 'Mostra', 'mm-recaptcha' )
				);
				break;

			default:
				printf(
					'<input type="text" class="regular-text mm-rc-key" id="%s" name="%s" value="%s" placeholder="%s" autocomplete="off" spellcheck="false" data-key="%s" />',
					esc_attr( $id ),
					esc_attr( $name ),
					esc_attr( (string) $value ),
					esc_attr( isset( $field['placeholder'] ) ? $field['placeholder'] : '' ),
					esc_attr( $key )
				);
				break;
		}

		if ( ! empty( $field['description'] ) ) {
			echo '<p class="description">' . esc_html( $field['description'] ) . '</p>';
		}

		echo '</td></tr>';
	}

	/* ------------------------------------------------------------------ *
	 * Scheda moduli
	 * ------------------------------------------------------------------ */

	protected static function tab_forms() {
		$groups = array(
			__( 'WordPress', 'mm-recaptcha' ) => array(
				'wp_login'        => array( __( 'Modulo di accesso', 'mm-recaptcha' ), __( 'Pagina wp-login.php e moduli di accesso nel tema.', 'mm-recaptcha' ) ),
				'wp_register'     => array( __( 'Registrazione', 'mm-recaptcha' ), __( 'Modulo di registrazione di WordPress.', 'mm-recaptcha' ) ),
				'wp_lostpassword' => array( __( 'Password dimenticata', 'mm-recaptcha' ), __( 'Richiesta e reimpostazione della password.', 'mm-recaptcha' ) ),
				'wp_comments'     => array( __( 'Commenti', 'mm-recaptcha' ), __( 'Modulo dei commenti degli articoli.', 'mm-recaptcha' ) ),
			),
			'WooCommerce'                     => array(
				'woo_login'        => array( __( 'Accesso', 'mm-recaptcha' ), __( 'Modulo di accesso nella pagina Il mio account e nel checkout.', 'mm-recaptcha' ) ),
				'woo_register'     => array( __( 'Registrazione', 'mm-recaptcha' ), __( 'Modulo di registrazione cliente.', 'mm-recaptcha' ) ),
				'woo_lostpassword' => array( __( 'Password dimenticata', 'mm-recaptcha' ), __( 'Recupero password dall’area clienti.', 'mm-recaptcha' ) ),
				'woo_checkout'     => array( __( 'Checkout', 'mm-recaptcha' ), __( 'Checkout classico. Non si applica al checkout a blocchi.', 'mm-recaptcha' ) ),
				'woo_pay'          => array( __( 'Pagamento ordine', 'mm-recaptcha' ), __( 'Pagina di pagamento di un ordine già creato.', 'mm-recaptcha' ) ),
			),
			'Contact Form 7'                  => array(
				'cf7'             => array( __( 'Moduli Contact Form 7', 'mm-recaptcha' ), __( 'Abilita il tag [mm_captcha mm-captcha-1] nei moduli.', 'mm-recaptcha' ) ),
				'cf7_auto_inject' => array( __( 'Inserimento automatico', 'mm-recaptcha' ), __( 'Aggiunge il captcha a tutti i moduli che non contengono già il tag.', 'mm-recaptcha' ) ),
			),
			__( 'Altro', 'mm-recaptcha' )     => array(
				'custom' => array( __( 'Moduli personalizzati', 'mm-recaptcha' ), __( 'Shortcode [mm_recaptcha] e funzioni mm_recaptcha_field() / mm_recaptcha_verify().', 'mm-recaptcha' ) ),
			),
		);

		$forms = (array) Options::get( 'forms', array() );

		echo '<h2>' . esc_html__( 'Dove mostrare il captcha', 'mm-recaptcha' ) . '</h2>';

		if ( ! class_exists( 'WooCommerce' ) ) {
			echo '<p class="description">' . esc_html__( 'WooCommerce non è attivo: le relative opzioni restano disponibili ma non hanno effetto.', 'mm-recaptcha' ) . '</p>';
		}

		if ( defined( 'WPCF7_VERSION' ) ) {
			$conflict = Integrations\Integration_CF7::native_conflict();

			if ( $conflict ) {
				echo '<div class="notice notice-warning inline"><p>' . sprintf(
					/* translators: %s: nome del servizio captcha nativo di Contact Form 7. */
					esc_html__( 'Contact Form 7 ha già il suo %s configurato: l’inserimento automatico resta disattivato per evitare due captcha nello stesso modulo.', 'mm-recaptcha' ),
					esc_html( $conflict )
				) . '</p></div>';
			}
		} else {
			echo '<p class="description">' . esc_html__( 'Contact Form 7 non è attivo: le relative opzioni non hanno effetto.', 'mm-recaptcha' ) . '</p>';
		}

		foreach ( $groups as $group_label => $fields ) {
			echo '<h3>' . esc_html( $group_label ) . '</h3>';
			echo '<table class="form-table" role="presentation"><tbody>';

			foreach ( $fields as $key => $meta ) {
				$id = 'mm-rc-form-' . $key;

				echo '<tr>';
				echo '<th scope="row">' . esc_html( $meta[0] ) . '</th>';
				echo '<td>';
				printf(
					'<label for="%1$s"><input type="checkbox" id="%1$s" name="%2$s[forms][%3$s]" value="1" %4$s /> %5$s</label>',
					esc_attr( $id ),
					esc_attr( Options::KEY ),
					esc_attr( $key ),
					checked( ! empty( $forms[ $key ] ), true, false ),
					esc_html__( 'Mostra il captcha', 'mm-recaptcha' )
				);
				echo '<p class="description">' . esc_html( $meta[1] ) . '</p>';
				echo '</td></tr>';
			}

			echo '</tbody></table>';
		}
	}

	/* ------------------------------------------------------------------ *
	 * Scheda avanzate
	 * ------------------------------------------------------------------ */

	protected static function tab_advanced() {
		$options = Options::all();

		echo '<h2>' . esc_html__( 'Impostazioni avanzate', 'mm-recaptcha' ) . '</h2>';
		echo '<table class="form-table" role="presentation"><tbody>';

		self::checkbox_row( 'honeypot', __( 'Campo esca (honeypot)', 'mm-recaptcha' ), __( 'Aggiunge un campo invisibile: se viene compilato l’invio è un bot.', 'mm-recaptcha' ), $options['honeypot'] );

		echo '<tr><th scope="row"><label for="mm-rc-timetrap">' . esc_html__( 'Tempo minimo di compilazione', 'mm-recaptcha' ) . '</label></th><td>';
		printf(
			'<input type="number" class="small-text" id="mm-rc-timetrap" name="%s[time_trap]" value="%d" min="0" max="60" /> %s',
			esc_attr( Options::KEY ),
			(int) $options['time_trap'],
			esc_html__( 'secondi', 'mm-recaptcha' )
		);
		echo '<p class="description">' . esc_html__( 'Rifiuta gli invii più rapidi di così. Imposta 0 per disattivare.', 'mm-recaptcha' ) . '</p>';
		echo '</td></tr>';

		self::checkbox_row( 'skip_logged_in', __( 'Salta gli utenti collegati', 'mm-recaptcha' ), __( 'Non mostra il captcha a chi ha già effettuato l’accesso.', 'mm-recaptcha' ), $options['skip_logged_in'] );

		echo '<tr><th scope="row">' . esc_html__( 'Ruoli sempre esclusi', 'mm-recaptcha' ) . '</th><td>';
		$roles = wp_roles()->get_names();
		foreach ( $roles as $role => $label ) {
			printf(
				'<label style="display:block"><input type="checkbox" name="%s[skip_roles][]" value="%s" %s /> %s</label>',
				esc_attr( Options::KEY ),
				esc_attr( $role ),
				checked( in_array( $role, (array) $options['skip_roles'], true ), true, false ),
				esc_html( translate_user_role( $label ) )
			);
		}
		echo '<p class="description">' . esc_html__( 'Utile se lasci il captcha attivo anche per gli utenti collegati.', 'mm-recaptcha' ) . '</p>';
		echo '</td></tr>';

		echo '<tr><th scope="row"><label for="mm-rc-ips">' . esc_html__( 'Indirizzi IP esclusi', 'mm-recaptcha' ) . '</label></th><td>';
		printf(
			'<textarea id="mm-rc-ips" class="large-text code" rows="4" name="%s[ip_allowlist]">%s</textarea>',
			esc_attr( Options::KEY ),
			esc_textarea( implode( "\n", (array) $options['ip_allowlist'] ) )
		);
		echo '<p class="description">' . esc_html__( 'Un indirizzo per riga. Utile per escludere la rete dell’ufficio o un servizio di monitoraggio.', 'mm-recaptcha' ) . '</p>';
		echo '</td></tr>';

		self::checkbox_row( 'verify_hostname', __( 'Verifica il dominio', 'mm-recaptcha' ), __( 'Rifiuta i captcha risolti su un dominio diverso da questo sito. Da disattivare se usi domini alternativi o un ambiente di staging.', 'mm-recaptcha' ), $options['verify_hostname'] );

		self::checkbox_row( 'fail_open', __( 'Consenti in caso di guasto del provider', 'mm-recaptcha' ), __( 'Se il servizio captcha non risponde, lascia passare l’invio invece di bloccarlo. Più comodo, meno sicuro.', 'mm-recaptcha' ), $options['fail_open'] );

		self::checkbox_row( 'log_failures', __( 'Registra i fallimenti', 'mm-recaptcha' ), __( 'Tiene traccia degli ultimi tentativi bloccati, con il motivo riportato dal provider.', 'mm-recaptcha' ), $options['log_failures'] );

		echo '<tr><th scope="row"><label for="mm-rc-logmax">' . esc_html__( 'Voci conservate', 'mm-recaptcha' ) . '</label></th><td>';
		printf(
			'<input type="number" class="small-text" id="mm-rc-logmax" name="%s[log_max]" value="%d" min="10" max="1000" step="10" />',
			esc_attr( Options::KEY ),
			(int) $options['log_max']
		);
		echo '</td></tr>';

		self::checkbox_row( 'uninstall_delete', __( 'Cancella i dati alla disinstallazione', 'mm-recaptcha' ), __( 'Alla rimozione del plugin elimina impostazioni e registro.', 'mm-recaptcha' ), $options['uninstall_delete'] );

		echo '</tbody></table>';
	}

	/**
	 * Riga con una casella di spunta di primo livello.
	 *
	 * @param string $key         Chiave.
	 * @param string $label       Etichetta.
	 * @param string $description Descrizione.
	 * @param mixed  $value       Valore corrente.
	 */
	protected static function checkbox_row( $key, $label, $description, $value ) {
		$id = 'mm-rc-' . $key;

		echo '<tr><th scope="row">' . esc_html( $label ) . '</th><td>';
		printf(
			'<label for="%1$s"><input type="checkbox" id="%1$s" name="%2$s[%3$s]" value="1" %4$s /> %5$s</label>',
			esc_attr( $id ),
			esc_attr( Options::KEY ),
			esc_attr( $key ),
			checked( ! empty( $value ), true, false ),
			esc_html__( 'Attivo', 'mm-recaptcha' )
		);
		echo '<p class="description">' . esc_html( $description ) . '</p>';
		echo '</td></tr>';
	}

	/* ------------------------------------------------------------------ *
	 * Registro
	 * ------------------------------------------------------------------ */

	protected static function tab_log() {
		$entries = Log::all();

		echo '<h2>' . esc_html__( 'Ultimi tentativi bloccati', 'mm-recaptcha' ) . '</h2>';

		if ( ! $entries ) {
			echo '<p>' . esc_html__( 'Nessun tentativo bloccato finora.', 'mm-recaptcha' ) . '</p>';
			return;
		}

		printf(
			'<p><button type="button" class="button mm-rc-clear-log">%s</button></p>',
			esc_html__( 'Svuota il registro', 'mm-recaptcha' )
		);

		echo '<table class="widefat striped"><thead><tr>';
		echo '<th>' . esc_html__( 'Data', 'mm-recaptcha' ) . '</th>';
		echo '<th>' . esc_html__( 'Modulo', 'mm-recaptcha' ) . '</th>';
		echo '<th>' . esc_html__( 'Provider', 'mm-recaptcha' ) . '</th>';
		echo '<th>' . esc_html__( 'Motivo', 'mm-recaptcha' ) . '</th>';
		echo '<th>' . esc_html__( 'IP', 'mm-recaptcha' ) . '</th>';
		echo '</tr></thead><tbody>';

		foreach ( $entries as $entry ) {
			echo '<tr>';
			echo '<td>' . esc_html( wp_date( 'd/m/Y H:i:s', (int) $entry['time'] ) ) . '</td>';
			echo '<td>' . esc_html( $entry['context'] ) . '</td>';
			echo '<td>' . esc_html( $entry['provider'] ) . '</td>';
			echo '<td><code>' . esc_html( $entry['code'] ) . '</code><br /><span class="description">' . esc_html( $entry['message'] ) . '</span></td>';
			echo '<td>' . esc_html( $entry['ip'] ) . '</td>';
			echo '</tr>';
		}

		echo '</tbody></table>';
	}

	/* ------------------------------------------------------------------ *
	 * Guida
	 * ------------------------------------------------------------------ */

	protected static function tab_help() {
		echo '<h2>' . esc_html__( 'Come ottenere le chiavi, provider per provider', 'mm-recaptcha' ) . '</h2>';
		echo '<p>' . esc_html__( 'Ogni servizio usa nomi diversi per le stesse cose. Qui trovi, per ciascuno, quali chiavi servono e il collegamento diretto alla pagina dove si creano.', 'mm-recaptcha' ) . '</p>';

		foreach ( Provider_Registry::available() as $provider ) {
			echo '<div class="mm-rc-help-card">';
			echo '<h3>' . esc_html( $provider->label() ) . '</h3>';
			echo '<p>' . esc_html( $provider->description() ) . '</p>';
			self::key_help_box( $provider );
			echo '</div>';
		}
	}
}
