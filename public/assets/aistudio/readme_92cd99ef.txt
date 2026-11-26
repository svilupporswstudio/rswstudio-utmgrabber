=== RSW UTM Grabber ===
Contributors: rsw
Requires at least: 5.8
Requires PHP: 7.4
Stable tag: 1.4.2
License: GPLv2 or later

Compatibile con la sintassi legacy HandL UTM Grabber nei form Contact Form 7.

== Funzionamento ==
- lascia invariato il contenuto salvato dei form esistenti;
- cerca gli <input> UTM/click ID e sostituisce %s in base al loro attributo name;
- priorita dei valori: URL -> cookie -> stringa vuota;
- rimuove i soli wrapper [utm_*_i] dall'HTML finale, senza modificare il form salvato;
- salva i valori in cookie con durata configurabile da Impostazioni -> RSW UTM Grabber;
- usa cookie con Path=/, quindi compatibili anche con WordPress installato in una sottocartella;
- include un fallback JavaScript per full-page cache, CDN e form ricostruiti dinamicamente;
- ricontrolla i valori immediatamente prima del submit;
- integra i dati inviati e i mail-tag di Contact Form 7.

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
In WordPress Multisite il plugin deve essere attivato in ciascun sito interessato oppure attivato a livello di rete.
