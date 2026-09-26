# MM reCAPTCHA

Protezione captcha per WordPress, WooCommerce, Contact Form 7 e Divi.

Sette provider, tutti pienamente implementati: nessuna voce selezionabile che poi non fa nulla, nessuna funzione riservata a una versione a pagamento.

- **Google reCAPTCHA v2** — casella di controllo o badge invisibile
- **Google reCAPTCHA v3** — punteggio, con soglia e verifica dell'azione configurabili
- **Google reCAPTCHA Enterprise** — valutazione tramite `createAssessment` su Google Cloud
- **hCaptcha** — normale o invisibile
- **Cloudflare Turnstile** — managed, non interattivo o invisibile
- **Captcha matematico interno** — nessun servizio esterno, nessuna chiave, compatibile GDPR
- **Honeypot e tempo minimo di compilazione** — attivabili insieme a qualunque provider

## Perché esiste

Nasce come sostituto di *Advanced Google reCAPTCHA* (WP Captcha 5.40), la cui versione gratuita presentava problemi sostanziali:

| Problema | Dove | Effetto |
|---|---|---|
| hCaptcha, Turnstile e Icon Captcha selezionabili ma privi di implementazione | `libs/functions.php:288-342` | La verifica cadeva nel `return true` finale: il sito sembrava protetto e non lo era |
| `remove_filter()` con priorità errata sul filtro `authenticate` | `advanced-google-recaptcha.php:184` | Il captcha di login veniva valutato solo quando le credenziali erano già corrette, quindi mai durante un attacco a forza bruta |
| Token del captcha matematico pari a `wp_hash($risposta)` | `libs/functions.php:602` | Deterministico, senza scadenza né uso singolo: riutilizzabile all'infinito e precalcolabile in 21 tentativi |
| Chiave segreta inviata in query string `GET` | `libs/functions.php:297` | Finiva nei log di proxy e CDN |
| Nessun `remoteip`, nessun controllo di `action` o `hostname`, soglia v3 fissa a 0.5 | idem | Token generati altrove venivano accettati |
| Bug di precedenza fra concatenazione e operatore ternario | `libs/functions.php:597` | HTML malformato su ogni modulo protetto |

## Moduli protetti

**WordPress** — accesso, registrazione, password dimenticata, reimpostazione password, commenti
**WooCommerce** — accesso, registrazione, recupero password, checkout classico, pagamento di un ordine esistente
**Contact Form 7** — tag `[mm_captcha mm-captcha-1]` e inserimento automatico su tutti i moduli
**Divi** — modulo Contatti, captcha inserito automaticamente prima del pulsante di invio
**Moduli personalizzati** — shortcode `[mm_recaptcha]` e API PHP

## Come ottenere le chiavi

Le stesse istruzioni sono disponibili nel pannello di amministrazione, con i link diretti accanto a ogni campo.

| Provider | Chiavi richieste | Dove ottenerle |
|---|---|---|
| reCAPTCHA v2 (checkbox e invisibile) | Site key + Secret key | [google.com/recaptcha/admin/create](https://www.google.com/recaptcha/admin/create) → tipo **Challenge (v2)** |
| reCAPTCHA v3 | Site key + Secret key | [google.com/recaptcha/admin/create](https://www.google.com/recaptcha/admin/create) → tipo **Score based (v3)** |
| reCAPTCHA Enterprise | Site key + ID progetto + API key | [chiavi](https://console.cloud.google.com/security/recaptcha) · [API key](https://console.cloud.google.com/apis/credentials) · [abilitazione API](https://console.cloud.google.com/apis/library/recaptchaenterprise.googleapis.com) |
| hCaptcha | Sitekey (per sito) + Secret (per account) | [sitekey](https://dashboard.hcaptcha.com/sites) · [secret](https://dashboard.hcaptcha.com/settings) |
| Cloudflare Turnstile | Site key + Secret key | [dash.cloudflare.com → Turnstile](https://dash.cloudflare.com/?to=/:account/turnstile) |
| Captcha matematico | nessuna | — |

Punti su cui si sbaglia più spesso:

- Le chiavi **v2 e v3 non sono intercambiabili**, e nemmeno quelle classiche con quelle Enterprise.
- Il dominio va inserito **senza** `https://` e **senza** barra finale; per lavorare in locale aggiungi anche `localhost`.
- Su hCaptcha la secret key è **una sola per tutto l'account**, non una per sito, e viene mostrata una volta soltanto.
- Turnstile **non richiede** che il DNS del sito sia su Cloudflare.
- La API key di Enterprise ha valore di credenziale: limitala all'API reCAPTCHA Enterprise e, se possibile, all'IP del server.

Il pannello include un pulsante **Prova le chiavi** che carica il widget reale, esegue una verifica completa e riporta punteggio, dominio rilevato e i codici di errore del provider tradotti in italiano (`invalid-input-secret`, `hostname-mismatch`, `timeout-or-duplicate`…).

## Installazione

1. Copia la cartella in `wp-content/plugins/mm-recaptcha`.
2. Attiva il plugin.
3. Apri il menu **MM reCAPTCHA**, scegli il provider, inserisci le chiavi e attiva i moduli da proteggere.

Se sul sito era presente *Advanced Google reCAPTCHA*, un avviso propone l'importazione automatica delle impostazioni (`wpcaptcha_options`, oppure `agr_options` per le versioni più vecchie): provider, chiavi e moduli protetti vengono convertiti nel nuovo schema e le opzioni originali restano intatte.

Con le impostazioni predefinite è attivo il captcha matematico su accesso, registrazione, recupero password e commenti: funziona da subito, senza registrarsi da nessuna parte.

## API per sviluppatori

```php
// Nel modulo
mm_recaptcha_the_field( 'custom' );

// Alla ricezione
$check = mm_recaptcha_verify( 'custom' );

if ( is_wp_error( $check ) ) {
    wp_die( esc_html( $check->get_error_message() ) );
}
```

Oppure lo shortcode `[mm_recaptcha]`.

Altre funzioni: `mm_recaptcha_field()`, `mm_recaptcha_is_enabled()`, `mm_recaptcha_active_provider()`, `mm_recaptcha_get_option()`.

Filtri e azioni: `mm_recaptcha_providers`, `mm_recaptcha_active_provider`, `mm_recaptcha_should_protect`, `mm_recaptcha_should_render`, `mm_recaptcha_field_html`, `mm_recaptcha_verify_result`, `mm_recaptcha_error_message`, `mm_recaptcha_skip_actor`, `mm_recaptcha_checkout_hook`, `mm_recaptcha_verification_failed`.

Per registrare un provider aggiuntivo basta estendere `MM_Recaptcha\Providers\Provider` e agganciarsi a `mm_recaptcha_providers`.

## Scelte tecniche

- **Verifica in POST** con `remoteip`, timeout, controllo del codice HTTP, gestione del JSON malformato e dei `WP_Error` di trasporto. La chiave segreta non viaggia mai in una URL.
- **Fail closed** per impostazione predefinita. L'opzione *fail open* vale solo per i guasti di rete, mai per un captcha effettivamente rifiutato, e l'evento viene registrato.
- **Protezione dal riuso** dei token e delle sfide.
- **Captcha matematico con payload firmato HMAC**: la verifica è autosufficiente, quindi la perdita della cache oggetti non blocca l'accesso al sito; il transient serve solo a impedire il riuso. Sulle pagine pubbliche la sfida viene rigenerata via JavaScript, così una cache a pagina intera non congela una domanda già consumata.
- **Nessuno script inline**: un solo file JavaScript, render esplicito, più widget per pagina con id distinti.
- **`requestSubmit()` al posto di `form.submit()`**, che perde nome e valore del pulsante premuto (con WooCommerce l'invio non veniva registrato).
- **Guardie di ambito** contro il doppio widget nei temi che richiamano sia gli hook di WooCommerce sia quelli del core.
- **Memoizzazione della verifica**: WooCommerce rilancia gli hook del core, e senza memo il secondo controllo fallirebbe come "token già usato".
- **Captcha del checkout ancorato fuori dal frammento** rigenerato via AJAX, così non viene distrutto a ogni aggiornamento del carrello.
- **Le richieste REST di Contact Form 7 non saltano la verifica** (WP-CLI, cron e XML-RPC restano esclusi).
- **Sanificazione con whitelist**: le chiavi sconosciute vengono scartate, gli enum validati, la soglia limitata fra 0 e 1, gli IP verificati.

## Requisiti

WordPress 6.0+ · PHP 7.4+ · WooCommerce, Contact Form 7 e Divi facoltativi

## Limiti noti

- Il checkout **a blocchi** di WooCommerce non è supportato: la protezione usa gli hook del checkout classico. Gli altri moduli WooCommerce funzionano comunque.
- Il captcha matematico offre una protezione di base: contro bot mirati conviene Turnstile o hCaptcha, entrambi gratuiti.

## Licenza

GPL-3.0-or-later
