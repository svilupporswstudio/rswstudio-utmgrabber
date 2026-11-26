=== RSW UTM Grabber ===
Contributors: rswstudio
Requires at least: 7.0
Tested up to: 7.2
Requires PHP: 7.4
Stable tag: 1.5.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Acquisisce parametri UTM e click ID dall'URL, li conserva nei cookie e mantiene la compatibilità con la sintassi legacy HandL UTM Grabber nei form Contact Form 7.

== Description ==
RSW UTM Grabber acquisisce automaticamente i parametri UTM e i click ID dall'URL, li salva nei cookie lato server e lato browser e popola i campi dei form (inclusi i form Contact Form 7 con sintassi legacy HandL UTM Grabber) e gli shortcode WordPress.

== Funzionamento ==
- lascia invariato il contenuto salvato dei form esistenti;
- cerca gli `<input>` UTM/click ID tramite l'API nativa `WP_HTML_Tag_Processor` e sostituisce `%s` in base al loro attributo `name`;
- priorità dei valori: URL -> cookie -> stringa vuota;
- rimuove i soli wrapper `[utm_*_i]` dall'HTML finale, senza modificare il form salvato;
- salva i valori in cookie con durata configurabile da Impostazioni -> RSW UTM Grabber;
- usa cookie con `Path=/` e `SameSite=Lax`, quindi compatibili anche con WordPress installato in una sottocartella;
- include lo script JavaScript `assets/rsw-utm-grabber.js` (caricato con strategia `defer`) come fallback per full-page cache, CDN e form ricostruiti dinamicamente;
- ricontrolla i valori immediatamente prima del submit;
- integra i dati inviati e i mail-tag di Contact Form 7, con recupero da cookie/URL se il campo inviato è vuoto o non sostituito.

Parametri supportati:
utm_campaign, utm_source, utm_medium, utm_term, utm_content, gclid, fbclid.

Mail tag CF7 supportati:
[utm_campaign]
[utm_source]
[utm_medium]
[utm_term]
[utm_content]
[gclid]
[fbclid]

== Multisite ==
In WordPress Multisite il plugin deve essere attivato in ciascun sito interessato oppure attivato a livello di rete. Alla disinstallazione, le opzioni vengono rimosse da tutti i siti della rete.

== Changelog ==
= 1.5.0 =
* Aggiornati i requisiti minimi a WordPress 7.0 e verificata la compatibilità fino a WordPress 7.2.
* Aggiornato l'autore a `rswstudio`.
* Verificata e rafforzata la compatibilità con PHP 8.3 (tipizzazione rigorosa nelle opzioni di `setcookie()`, controllo tipi su tag/shortcode/hook e gestione sicura delle stringhe UTF-8).
* Sostituito `wp_localize_script()` con `wp_add_inline_script()` e `wp_json_encode()`, adottando la strategia `defer` in `wp_enqueue_script()` secondo gli standard di WordPress 7.x.
* Aggiornato `replace_cf7_placeholders()` all'uso di `WP_HTML_Tag_Processor` per una sostituzione affidabile degli attributi `<input>` su WordPress 7.0–7.2.
* Aggiunto lo script mancante `assets/rsw-utm-grabber.js` per il fallback con full-page cache, form dinamici e aggiornamento pre-submit.
* Corretta la gestione degli shortcode annidati e prevenuta la riesecuzione di shortcode sui valori utente in `render_wrapper_shortcode()`.
* Migliorata la pulizia in `uninstall.php` con supporto completo per installazioni WordPress Multisite.
* Evitata la scrittura dei cookie durante richieste admin, AJAX, Cron, XML-RPC e WP-CLI.

= 1.4.2 =
* Versione precedente con supporto cookie, shortcode wrapper legacy e integrazione Contact Form 7.
