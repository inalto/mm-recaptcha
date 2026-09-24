/**
 * MM reCAPTCHA - interfaccia di amministrazione.
 */
( function () {
	'use strict';

	var cfg = window.mmRecaptchaAdmin || {};
	var strings = cfg.strings || {};
	var testState = {};

	function qs( selector, scope ) {
		return ( scope || document ).querySelector( selector );
	}

	function qsa( selector, scope ) {
		return Array.prototype.slice.call( ( scope || document ).querySelectorAll( selector ) );
	}

	function post( action, data ) {
		var body = new window.FormData();

		body.append( 'action', action );
		body.append( 'nonce', cfg.nonce || '' );

		Object.keys( data || {} ).forEach( function ( key ) {
			var value = data[ key ];

			if ( null !== value && 'object' === typeof value ) {
				Object.keys( value ).forEach( function ( inner ) {
					body.append( key + '[' + inner + ']', value[ inner ] );
				} );
				return;
			}

			body.append( key, value );
		} );

		return window
			.fetch( cfg.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body } )
			.then( function ( response ) {
				return response.json();
			} );
	}

	/* ---------------------------------------------------------------- *
	 * Schede
	 * ---------------------------------------------------------------- */

	function activateTab( id ) {
		qsa( '.mm-rc-tabs .nav-tab' ).forEach( function ( tab ) {
			tab.classList.toggle( 'nav-tab-active', tab.getAttribute( 'data-tab' ) === id );
		} );

		qsa( '.mm-rc-panel' ).forEach( function ( panel ) {
			panel.hidden = panel.getAttribute( 'data-panel' ) !== id;
		} );

		var form = qs( '#mm-rc-form' );

		if ( form ) {
			// Il pulsante di salvataggio serve solo alle schede che contengono campi.
			var editable = 'provider' === id || 'forms' === id || 'advanced' === id;
			form.hidden = ! editable;
		}
	}

	function initTabs() {
		var tabs = qsa( '.mm-rc-tabs .nav-tab' );

		if ( ! tabs.length ) {
			return;
		}

		tabs.forEach( function ( tab ) {
			tab.addEventListener( 'click', function ( event ) {
				event.preventDefault();
				var id = tab.getAttribute( 'data-tab' );
				window.location.hash = id;
				activateTab( id );
			} );
		} );

		var initial = window.location.hash.replace( '#', '' );

		if ( ! initial || ! qs( '.mm-rc-panel[data-panel="' + initial + '"]' ) ) {
			initial = 'provider';
		}

		activateTab( initial );
	}

	/* ---------------------------------------------------------------- *
	 * Selezione del provider
	 * ---------------------------------------------------------------- */

	function initProviderCards() {
		qsa( '.mm-rc-card input[type="radio"]' ).forEach( function ( radio ) {
			radio.addEventListener( 'change', function () {
				var id = radio.value;

				qsa( '.mm-rc-card' ).forEach( function ( card ) {
					card.classList.toggle( 'is-selected', card.contains( radio ) );
				} );

				qsa( '.mm-rc-provider-settings' ).forEach( function ( box ) {
					box.hidden = box.getAttribute( 'data-provider' ) !== id;
				} );
			} );
		} );
	}

	function initReveal() {
		qsa( '.mm-rc-reveal' ).forEach( function ( button ) {
			button.addEventListener( 'click', function () {
				var input = button.parentNode.querySelector( 'input' );

				if ( ! input ) {
					return;
				}

				var hidden = 'password' === input.type;
				input.type = hidden ? 'text' : 'password';
				button.textContent = hidden ? strings.hide : strings.show;
			} );
		} );
	}

	/* ---------------------------------------------------------------- *
	 * Prova delle chiavi
	 * ---------------------------------------------------------------- */

	function credentialsFor( providerId ) {
		var box = qs( '.mm-rc-provider-settings[data-provider="' + providerId + '"]' );
		var out = {};

		if ( ! box ) {
			return out;
		}

		qsa( '[data-key]', box ).forEach( function ( input ) {
			out[ input.getAttribute( 'data-key' ) ] = input.value;
		} );

		qsa( 'select', box ).forEach( function ( select ) {
			var name = select.name.match( /\[([^\]]+)\]$/ );

			if ( name ) {
				out[ name[ 1 ] ] = select.value;
			}
		} );

		return out;
	}

	function resolveApi( path ) {
		if ( ! path ) {
			return null;
		}

		return path.split( '.' ).reduce( function ( carrier, part ) {
			return carrier ? carrier[ part ] : null;
		}, window );
	}

	function feedback( providerId, message, type ) {
		var box = qs( '.mm-rc-provider-settings[data-provider="' + providerId + '"] .mm-rc-test-feedback' );

		if ( ! box ) {
			return;
		}

		box.textContent = message || '';
		box.className = 'mm-rc-test-feedback' + ( type ? ' is-' + type : '' );
	}

	function report( providerId, payload, ok ) {
		var panel = qs( '.mm-rc-provider-settings[data-provider="' + providerId + '"] .mm-rc-test-panel' );

		if ( ! panel ) {
			return;
		}

		var rows = [];

		if ( null !== payload.score && undefined !== payload.score ) {
			rows.push( strings.score + ': ' + payload.score );
		}

		if ( payload.hostname ) {
			rows.push( strings.hostname + ': ' + payload.hostname );
		}

		if ( payload.action ) {
			rows.push( strings.action + ': ' + payload.action );
		}

		if ( payload.elapsed ) {
			rows.push( strings.elapsed + ': ' + payload.elapsed + ' ms' );
		}

		if ( payload.detail ) {
			rows.push( payload.detail );
		}

		panel.hidden = false;
		panel.innerHTML = '';

		var title = document.createElement( 'p' );
		title.className = ok ? 'mm-rc-result-ok' : 'mm-rc-result-error';
		title.textContent = payload.message || '';
		panel.appendChild( title );

		if ( rows.length ) {
			var list = document.createElement( 'ul' );

			rows.forEach( function ( row ) {
				var item = document.createElement( 'li' );
				item.textContent = row;
				list.appendChild( item );
			} );

			panel.appendChild( list );
		}

		if ( payload.raw ) {
			var pre = document.createElement( 'pre' );
			pre.className = 'mm-rc-raw';
			pre.textContent = payload.raw;
			panel.appendChild( pre );
		}
	}

	function verifyTest( providerId, data ) {
		feedback( providerId, strings.testing, 'pending' );

		var body = { provider: providerId, credentials: credentialsFor( providerId ) };

		Object.keys( data ).forEach( function ( key ) {
			body[ key ] = data[ key ];
		} );

		post( 'mm_recaptcha_test_verify', body ).then( function ( response ) {
			feedback( providerId, '', '' );
			report( providerId, response.data || {}, !! response.success );
		} );
	}

	function renderMathTest( providerId, challenge ) {
		var panel = qs( '.mm-rc-provider-settings[data-provider="' + providerId + '"] .mm-rc-test-panel' );

		panel.hidden = false;
		panel.innerHTML = '';

		var label = document.createElement( 'p' );
		label.textContent = challenge.label + ' ' + challenge.question + ' =';
		panel.appendChild( label );

		var input = document.createElement( 'input' );
		input.type = 'text';
		input.className = 'small-text';
		input.setAttribute( 'aria-label', strings.answer );
		panel.appendChild( input );

		var button = document.createElement( 'button' );
		button.type = 'button';
		button.className = 'button button-primary';
		button.textContent = strings.submit;
		button.style.marginLeft = '8px';
		panel.appendChild( button );

		button.addEventListener( 'click', function () {
			verifyTest( providerId, { answer: input.value, cid: challenge.id } );
		} );
	}

	function renderWidgetTest( providerId, data ) {
		var panel = qs( '.mm-rc-provider-settings[data-provider="' + providerId + '"] .mm-rc-test-panel' );

		panel.hidden = false;
		panel.innerHTML = '';

		var hint = document.createElement( 'p' );
		hint.textContent = strings.solve;
		panel.appendChild( hint );

		var mount = document.createElement( 'div' );
		mount.className = 'mm-rc-test-widget';
		panel.appendChild( mount );

		var button = document.createElement( 'button' );
		button.type = 'button';
		button.className = 'button button-primary';
		button.textContent = strings.submit;
		button.disabled = true;
		panel.appendChild( button );

		testState = {
			providerId: providerId,
			data: data,
			mount: mount,
			button: button,
			token: ''
		};

		button.addEventListener( 'click', function () {
			verifyTest( providerId, { token: testState.token } );
		} );

		window.mmRecaptchaAdminOnload = function () {
			var api = resolveApi( data.widget.api );

			if ( ! api ) {
				feedback( providerId, strings.loadError, 'error' );
				return;
			}

			if ( 'score' === data.widget.mode ) {
				var run = function () {
					api.execute( data.widget.params.sitekey, { action: data.widget.params.action } ).then( function ( token ) {
						testState.token = token;
						button.disabled = false;
					} );
				};

				if ( api.ready ) {
					api.ready( run );
				} else {
					run();
				}

				return;
			}

			var params = {};

			Object.keys( data.widget.params ).forEach( function ( key ) {
				params[ key ] = data.widget.params[ key ];
			} );

			params.callback = function ( token ) {
				testState.token = token;
				button.disabled = false;
			};

			params[ 'error-callback' ] = function () {
				feedback( providerId, strings.loadError, 'error' );
			};

			if ( 'invisible' === params.size || 'invisible' === data.widget.mode ) {
				params.size = 'invisible';
			}

			var id = api.render( mount, params );

			if ( 'invisible' === data.widget.mode ) {
				api.execute( id );
			}
		};

		var existing = qs( '#mm-rc-test-script' );

		if ( existing ) {
			existing.parentNode.removeChild( existing );
		}

		var script = document.createElement( 'script' );
		script.id = 'mm-rc-test-script';
		script.src = data.scriptUrl.replace( 'mmRecaptchaOnload', 'mmRecaptchaAdminOnload' );
		script.async = true;
		script.defer = true;
		script.onerror = function () {
			feedback( providerId, strings.loadError, 'error' );
		};

		document.head.appendChild( script );
	}

	function initTestButtons() {
		qsa( '.mm-rc-test' ).forEach( function ( button ) {
			button.addEventListener( 'click', function () {
				var providerId = button.getAttribute( 'data-provider' );

				feedback( providerId, strings.testing, 'pending' );

				post( 'mm_recaptcha_test_prepare', {
					provider: providerId,
					credentials: credentialsFor( providerId )
				} ).then( function ( response ) {
					if ( ! response.success ) {
						feedback( providerId, ( response.data && response.data.message ) || strings.loadError, 'error' );
						return;
					}

					feedback( providerId, '', '' );

					if ( response.data.offline ) {
						renderMathTest( providerId, response.data.challenge );
						return;
					}

					renderWidgetTest( providerId, response.data );
				} );
			} );
		} );
	}

	/* ---------------------------------------------------------------- *
	 * Registro e migrazione
	 * ---------------------------------------------------------------- */

	function initLog() {
		var button = qs( '.mm-rc-clear-log' );

		if ( ! button ) {
			return;
		}

		button.addEventListener( 'click', function () {
			if ( ! window.confirm( strings.confirm ) ) {
				return;
			}

			post( 'mm_recaptcha_clear_log', {} ).then( function () {
				window.location.reload();
			} );
		} );
	}

	function initMigration() {
		var migrate = qs( '.mm-rc-migrate' );
		var dismiss = qs( '.mm-rc-dismiss-migration' );

		if ( migrate ) {
			migrate.addEventListener( 'click', function () {
				migrate.disabled = true;

				post( 'mm_recaptcha_migrate', {} ).then( function () {
					window.location.href = window.location.pathname + '?page=mm-recaptcha';
				} );
			} );
		}

		if ( dismiss ) {
			dismiss.addEventListener( 'click', function () {
				post( 'mm_recaptcha_dismiss_notice', {} ).then( function () {
					var notice = qs( '.mm-rc-migration' );

					if ( notice ) {
						notice.parentNode.removeChild( notice );
					}
				} );
			} );
		}
	}

	function boot() {
		initTabs();
		initProviderCards();
		initReveal();
		initTestButtons();
		initLog();
		initMigration();
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', boot );
	} else {
		boot();
	}
}() );
