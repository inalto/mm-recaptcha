=== MM reCAPTCHA ===

Contributors: inalto
Tags: captcha, recaptcha, hcaptcha, turnstile, antispam
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.1.1
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Protezione captcha completa per WordPress, WooCommerce e Contact Form 7. Tutti i provider funzionanti, nessuna funzione a pagamento.

== Descrizione ==

MM reCAPTCHA protegge i moduli del sito da bot e spam. Ogni tipo di captcha è pienamente implementato: non ci sono voci selezionabili che poi non fanno nulla.

Provider supportati:

* Google reCAPTCHA v2 — casella di controllo
* Google reCAPTCHA v2 — badge invisibile
* Google reCAPTCHA v3 — punteggio, con soglia e verifica dell'azione configurabili
* Google reCAPTCHA Enterprise — valutazione tramite Google Cloud (createAssessment)
* hCaptcha — normale e invisibile
* Cloudflare Turnstile — managed, non interattivo e invisibile
* Captcha matematico interno — nessun servizio esterno, nessuna chiave

In più, su qualsiasi provider: campo esca (honeypot) e tempo minimo di compilazione.

Moduli protetti:

* WordPress: accesso, registrazione, password dimenticata, reimpostazione password, commenti
* WooCommerce: accesso, registrazione, recupero password, checkout classico, pagamento di un ordine
* Contact Form 7: tag `[mm_captcha mm-captcha-1]` e inserimento automatico
* Divi: modulo Contatti (captcha inserito automaticamente prima del pulsante di invio)
* Moduli personalizzati: shortcode `[mm_recaptcha]` e funzioni PHP

== Come ottenere le chiavi ==

= Google reCAPTCHA v2 e v3 (chiavi classiche, gratuite) =

1. Apri https://www.google.com/recaptcha/admin/create
2. Inserisci un'etichetta per il sito.
3. Scegli il tipo: "Challenge (v2)" con casella di controllo o badge invisibile, oppure "Score based (v3)".
4. Aggiungi il dominio del sito (senza https:// e senza barra finale).
5. Copia Site key e Secret key nelle impostazioni del plugin.

Le chiavi v2 e v3 non sono intercambiabili.

= Google reCAPTCHA Enterprise =

1. Crea o scegli un progetto su https://console.cloud.google.com con la fatturazione attiva.
2. Abilita l'API: https://console.cloud.google.com/apis/library/recaptchaenterprise.googleapis.com
3. Crea la chiave del sito: https://console.cloud.google.com/security/recaptcha
4. Crea una API key limitata a quell'API: https://console.cloud.google.com/apis/credentials
5. Servono tre valori: Site key, ID progetto e API key.

= hCaptcha =

1. Registra il sito: https://dashboard.hcaptcha.com/sites (pulsante "Add Site") e copia la Sitekey.
2. Genera la chiave segreta: https://dashboard.hcaptcha.com/settings, pulsante "Generate New Secret".

La secret key è una sola per tutto l'account e viene mostrata una sola volta.

= Cloudflare Turnstile =

1. Apri https://dash.cloudflare.com/?to=/:account/turnstile
2. Premi "Add widget", assegna un nome e aggiungi l'hostname del sito.
3. Scegli la modalità (Managed, Non-interactive o Invisible).
4. Copia Site key e Secret key.

Non serve che il DNS del sito sia su Cloudflare.

= Captcha matematico =

Nessuna chiave: seleziona il provider e salva.

== Uso nei moduli personalizzati ==

Shortcode:

`[mm_recaptcha]`

PHP nel modulo:

`<?php mm_recaptcha_the_field( 'custom' ); ?>`

Verifica all'invio:

`$check = mm_recaptcha_verify( 'custom' );
if ( is_wp_error( $check ) ) { /* blocca */ }`

Altre funzioni: `mm_recaptcha_field()`, `mm_recaptcha_is_enabled()`, `mm_recaptcha_active_provider()`, `mm_recaptcha_get_option()`.

Filtri disponibili: `mm_recaptcha_providers`, `mm_recaptcha_active_provider`, `mm_recaptcha_should_protect`, `mm_recaptcha_should_render`, `mm_recaptcha_field_html`, `mm_recaptcha_verify_result`, `mm_recaptcha_error_message`, `mm_recaptcha_skip_actor`, `mm_recaptcha_checkout_hook`.

== Installazione ==

1. Carica la cartella in /wp-content/plugins/mm-recaptcha
2. Attiva il plugin dalla pagina Plugin.
3. Apri il menu "MM reCAPTCHA", scegli il provider, inserisci le chiavi e attiva i moduli da proteggere.
4. Se usavi Advanced Google reCAPTCHA, accetta l'importazione proposta e disattiva il vecchio plugin.

== Domande frequenti ==

= Il captcha compare due volte nella pagina Il mio account =

Non dovrebbe: il plugin riconosce i temi che richiamano sia gli hook di WooCommerce sia quelli del core. Se succede, controlla di non avere ancora attivo un altro plugin captcha.

= Funziona con il checkout a blocchi di WooCommerce? =

No: la protezione del checkout usa gli hook del checkout classico. Gli altri moduli WooCommerce funzionano comunque.

= Il captcha matematico e la cache =

La sfida viene rigenerata via JavaScript sulle pagine pubbliche, così la cache a pagina intera non congela una domanda già usata.

== Changelog ==

= 1.1.1 =
* Correzione: nel modulo Contatti di Divi 5 il captcha è allineato ai campi e staccato dal messaggio.

= 1.1.0 =
* Nuovo: integrazione con il modulo Contatti di Divi.
* Correzione: i captcha eseguiti all'invio fermano anche i gestori AJAX di altri plugin, che altrimenti inviavano il modulo senza token.

= 1.0.0 =
* Prima versione.
