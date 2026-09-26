/**
 * MM reCAPTCHA - caricatore unico di front-end.
 *
 * Nessuna dipendenza esterna. Gestisce piu' widget nella stessa pagina,
 * il render esplicito, i widget invisibili e il reset sui moduli AJAX.
 */
( function () {
	'use strict';

	var settings = window.mmRecaptchaSettings || {};
	var widgets = [];
	var apiReady = false;
	var lastSubmitter = null;

	var REFRESH_MS = 100000; // I token dei provider scadono dopo ~120 secondi.

	function each( list, fn ) {
		Array.prototype.forEach.call( list, fn );
	}

	function resolveApi( path ) {
		if ( ! path ) {
			return null;
		}

		return path.split( '.' ).reduce( function ( carrier, part ) {
			return carrier ? carrier[ part ] : null;
		}, window );
	}

	function closestForm( el ) {
		return el.closest ? el.closest( 'form' ) : null;
	}

	function fieldWrapper( el ) {
		return el.closest ? el.closest( '.mm-recaptcha-field' ) : null;
	}

	function setToken( widget, token ) {
		widget.token = token || '';
		widget.tokenTime = token ? Date.now() : 0;

		var wrapper = fieldWrapper( widget.el );

		if ( ! wrapper ) {
			return;
		}

		var field = wrapper.querySelector( '.mm-recaptcha-token-field' );

		if ( field ) {
			field.value = widget.token;
		}
	}

	function tokenIsFresh( widget ) {
		return widget.token && Date.now() - widget.tokenTime < REFRESH_MS;
	}

	function collect() {
		each( document.querySelectorAll( '[data-mm-captcha]' ), function ( el ) {
			if ( el.getAttribute( 'data-mm-initialised' ) ) {
				return;
			}

			var cfg;

			try {
				cfg = JSON.parse( el.getAttribute( 'data-mm-captcha' ) );
			} catch ( e ) {
				return;
			}

			el.setAttribute( 'data-mm-initialised', '1' );

			var widget = {
				el: el,
				cfg: cfg,
				form: closestForm( el ),
				id: null,
				token: '',
				tokenTime: 0,
				executing: false,
				queued: null
			};

			widgets.push( widget );

			if ( 'math' === cfg.mode ) {
				initMath( widget );
				return;
			}

			bindForm( widget );

			if ( apiReady ) {
				renderWidget( widget );
			}
		} );
	}

	/* ------------------------------------------------------------------ *
	 * Captcha matematico
	 * ------------------------------------------------------------------ */

	function initMath( widget ) {
		if ( ! widget.cfg.refresh || ! settings.ajaxUrl ) {
			return;
		}

		refreshMath( widget );
	}

	function refreshMath( widget ) {
		var body = new window.FormData();
		body.append( 'action', 'mm_recaptcha_refresh' );
		body.append( 'nonce', settings.nonce || '' );

		window
			.fetch( settings.ajaxUrl, {
				method: 'POST',
				credentials: 'same-origin',
				body: body
			} )
			.then( function ( response ) {
				return response.json();
			} )
			.then( function ( payload ) {
				if ( ! payload || ! payload.success || ! payload.data ) {
					return;
				}

				var question = widget.el.querySelector( '.mm-recaptcha-math-question' );
				var cid = widget.el.querySelector( '.mm-recaptcha-math-id' );
				var input = widget.el.querySelector( '.mm-recaptcha-math-input' );

				if ( question ) {
					question.textContent = payload.data.question;
				}

				if ( cid ) {
					cid.value = payload.data.id;
				}

				if ( input ) {
					input.value = '';
				}
			} )
			.catch( function () {
				/* La sfida disegnata dal server resta valida. */
			} );
	}

	/* ------------------------------------------------------------------ *
	 * Provider a token
	 * ------------------------------------------------------------------ */

	function renderWidget( widget ) {
		var api = resolveApi( widget.cfg.api );

		if ( ! api || widget.id !== null ) {
			return;
		}

		if ( 'score' === widget.cfg.mode ) {
			// reCAPTCHA v3 / Enterprise: nessun widget da disegnare, solo esecuzione.
			widget.id = 'score';
			execute( widget );
			schedule( widget );
			return;
		}

		var params = {};
		var key;

		for ( key in widget.cfg.params ) {
			if ( Object.prototype.hasOwnProperty.call( widget.cfg.params, key ) ) {
				params[ key ] = widget.cfg.params[ key ];
			}
		}

		params.callback = function ( token ) {
			setToken( widget, token );

			if ( widget.queued ) {
				var queued = widget.queued;
				widget.queued = null;
				submitForm( widget, queued );
			}
		};

		params[ 'expired-callback' ] = function () {
			setToken( widget, '' );
		};

		params[ 'error-callback' ] = function () {
			widget.executing = false;
			setToken( widget, '' );
		};

		try {
			widget.id = api.render( widget.el, params );
		} catch ( e ) {
			widget.id = null;
		}
	}

	function schedule( widget ) {
		if ( widget.timer ) {
			window.clearInterval( widget.timer );
		}

		widget.timer = window.setInterval( function () {
			if ( 'score' === widget.cfg.mode ) {
				execute( widget );
			}
		}, REFRESH_MS );
	}

	function execute( widget, onDone ) {
		var api = resolveApi( widget.cfg.api );

		if ( ! api ) {
			if ( onDone ) {
				onDone( false );
			}
			return;
		}

		if ( 'score' === widget.cfg.mode ) {
			var run = function () {
				api
					.execute( widget.cfg.params.sitekey, { action: widget.cfg.params.action } )
					.then( function ( token ) {
						setToken( widget, token );
						if ( onDone ) {
							onDone( true );
						}
					} )
					.catch( function () {
						if ( onDone ) {
							onDone( false );
						}
					} );
			};

			if ( api.ready ) {
				api.ready( run );
			} else {
				run();
			}

			return;
		}

		if ( null === widget.id ) {
			if ( onDone ) {
				onDone( false );
			}
			return;
		}

		try {
			widget.executing = true;
			api.execute( widget.id );
		} catch ( e ) {
			widget.executing = false;

			if ( onDone ) {
				onDone( false );
			}
		}
	}

	function needsExecute( widget ) {
		if ( 'score' === widget.cfg.mode ) {
			return true;
		}

		if ( 'invisible' === widget.cfg.mode ) {
			return true;
		}

		return 'execute' === ( widget.cfg.params && widget.cfg.params.appearance );
	}

	function submitForm( widget, submitter ) {
		var form = widget.form;

		if ( ! form ) {
			return;
		}

		widget.bypass = true;

		if ( form.requestSubmit ) {
			// requestSubmit conserva nome e valore del pulsante premuto:
			// form.submit() li perderebbe e WooCommerce non registrerebbe l'invio.
			form.requestSubmit( submitter && form.contains( submitter ) ? submitter : undefined );
		} else {
			if ( submitter && submitter.name ) {
				var proxy = document.createElement( 'input' );
				proxy.type = 'hidden';
				proxy.name = submitter.name;
				proxy.value = submitter.value || '';
				form.appendChild( proxy );
			}

			form.submit();
		}
	}

	function bindForm( widget ) {
		if ( ! widget.form || widget.form.getAttribute( 'data-mm-bound' ) ) {
			return;
		}

		widget.form.setAttribute( 'data-mm-bound', '1' );

		widget.form.addEventListener(
			'submit',
			function ( event ) {
				if ( widget.bypass ) {
					widget.bypass = false;
					return;
				}

				if ( ! needsExecute( widget ) ) {
					return;
				}

				if ( tokenIsFresh( widget ) ) {
					return;
				}

				event.preventDefault();
				// Ferma anche i gestori AJAX (Divi, Contact Form 7) che altrimenti
				// invierebbero subito senza token: il modulo viene reinviato dopo.
				event.stopImmediatePropagation();

				var submitter = event.submitter || lastSubmitter;

				if ( 'score' === widget.cfg.mode ) {
					execute( widget, function ( ok ) {
						if ( ok ) {
							submitForm( widget, submitter );
						}
					} );

					return;
				}

				widget.queued = submitter;
				execute( widget );
			},
			true
		);
	}

	/* ------------------------------------------------------------------ *
	 * Reset sui moduli AJAX
	 * ------------------------------------------------------------------ */

	function resetAll() {
		widgets.forEach( function ( widget ) {
			if ( 'math' === widget.cfg.mode ) {
				if ( widget.cfg.refresh ) {
					refreshMath( widget );
				}
				return;
			}

			var api = resolveApi( widget.cfg.api );

			setToken( widget, '' );

			if ( api && api.reset && null !== widget.id && 'score' !== widget.cfg.mode ) {
				try {
					api.reset( widget.id );
				} catch ( e ) {
					/* niente da fare */
				}
			}

			if ( 'score' === widget.cfg.mode ) {
				execute( widget );
			}
		} );
	}

	function rescan() {
		collect();

		widgets.forEach( function ( widget ) {
			if ( 'math' !== widget.cfg.mode && null === widget.id && apiReady ) {
				renderWidget( widget );
			}
		} );
	}

	/* ------------------------------------------------------------------ *
	 * Avvio
	 * ------------------------------------------------------------------ */

	window.mmRecaptchaOnload = function () {
		apiReady = true;

		widgets.forEach( function ( widget ) {
			if ( 'math' !== widget.cfg.mode ) {
				renderWidget( widget );
			}
		} );
	};

	document.addEventListener( 'mousedown', function ( event ) {
		var target = event.target;

		if ( target && target.closest ) {
			lastSubmitter = target.closest( 'button, input[type="submit"], input[type="image"]' );
		}
	}, true );

	function boot() {
		collect();

		if ( window.jQuery ) {
			window.jQuery( document.body ).on( 'checkout_error', resetAll );
			window.jQuery( document.body ).on( 'updated_checkout', rescan );
		}

		document.addEventListener( 'wpcf7invalid', resetAll );
		document.addEventListener( 'wpcf7spam', resetAll );
		document.addEventListener( 'wpcf7mailsent', resetAll );

		if ( window.MutationObserver ) {
			var observer = new window.MutationObserver( function ( mutations ) {
				var added = false;

				mutations.forEach( function ( mutation ) {
					if ( mutation.addedNodes && mutation.addedNodes.length ) {
						added = true;
					}
				} );

				if ( added ) {
					rescan();
				}
			} );

			observer.observe( document.documentElement, { childList: true, subtree: true } );
		}
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', boot );
	} else {
		boot();
	}

	window.mmRecaptcha = {
		reset: resetAll,
		rescan: rescan,
		widgets: widgets
	};
}() );
