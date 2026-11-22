# SEO e indicizzazione

Indicizzazione, sitemap, dati strutturati, vecchi indirizzi. Le impostazioni SEO hanno una proprietà
scomoda: un interruttore cambia cose che **non stai guardando**. Spegni un tipo di schema e sparisce
tutto il resto; accendi l'indicizzazione e vanno in pubblico dati che nessuno ha mai letto.

Il filo comune è uno solo: **dopo ogni modifica SEO si verifica una pagina interna, non solo la home**,
e si guarda cosa è diventato pubblico, non solo quello che si voleva cambiare. La home riceve un
trattamento speciale da quasi tutti i plugin SEO, quindi è proprio la pagina che nasconde i difetti.

---

## Sbloccare l'indicizzazione accende dati che nessuno ha mai controllato

Togliere «Scoraggia i motori di ricerca» (`blog_public` da 0 a 1) sembra una correzione isolata: il
sito diventa visibile e basta. Non è così, perché con `blog_public` = 0 il plugin SEO tiene spente
anche altre cose, e allo sblocco si accendono tutte insieme.

Caso reale, con Rank Math. Con `blog_public` = 0 stampava `noindex,nofollow` su tutte le pagine e
**non** pubblicava lo schema `LocalBusiness`. Appena messo a 1, la home ha cominciato a dichiarare a
Google `"openingHours": "Monday..Sunday 09:00-17:00"`: i valori di **esempio** del modulo Local SEO,
mai compilati da nessuno. Aperti la domenica, per un'officina chiusa la domenica.

Nello stesso momento diventano indicizzabili:

- i **segnaposto** rimasti pubblicati — nel caso reale una pagina «Lorem Ipsum» e la «Sample Page» di
  WordPress;
- i **tipi di contenuto interni dei page builder**, attivi nella sitemap di Rank Math in modo
  predefinito: `elementskit_template`, `elementskit_content`, `e-floating-buttons`, `elementor-hf`.
  Quattro tipi che non sono pagine e che nessuno deve trovare su Google.

**Come si riconosce**, a cose fatte: il JSON-LD della home contiene `openingHours` con lo stesso
orario 09:00-17:00 per tutti e sette i giorni.

**Regola: prima di mettere `blog_public` a 1, controllare tre cose.**

1. I segnaposto pubblicati: pagine e articoli con lorem ipsum, «Sample Page», «Hello world».
2. I post type inclusi nella sitemap di Rank Math (le chiavi `pt_*_sitemap`): tutto quello che non è
   contenuto per le persone va spento.
3. I dati aziendali del modulo Local SEO, in `rank-math-options-titles`: `opening_hours`,
   `local_address`, `phone_numbers`.

```bash
wp eval '$o = get_option("rank-math-options-titles");
  foreach (array("opening_hours","local_address","phone_numbers") as $k)
    echo $k, ": ", wp_json_encode($o[$k] ?? null), "\n";'
```

I dati di esempio **si tolgono, non si sostituiscono** con dati presi dal sito: se il sito mostra due
sedi con due orari, sceglierne una è inventare. Meglio nessuno schema `LocalBusiness` di uno falso; i
dati veri li dà il cliente.

Lo sblocco viene **per ultimo**, come ultima istruzione dello stesso script che ha fatto la pulizia:
così non c'è nessun intervallo in cui il sito è indicizzabile e ancora sporco.

## Rank Math: lo schema predefinito «nessuno» toglie tutto il JSON-LD, non solo il tipo

Portare `pt_page_default_rich_snippet` da `article` a `off` sembra togliere dalle pagine solo
`Article` e l'autore (che in quel caso era lo username dell'amministratore, cosa da evitare). Invece,
su **29 pagine interne su 29**, il JSON-LD è sparito del tutto: zero blocchi `ld+json`, niente
`Organization`, `WebSite`, `WebPage`. Sulla home il grafo restava, perché la pagina iniziale riceve
comunque l'entità aziendale.

**Come si riconosce:** sulla pagina interna `grep -c "ld+json"` dà 0, mentre title, robots e canonical
di Rank Math ci sono ancora. È ingannevole proprio perché controllando solo la home sembra tutto a
posto.

**Come si rimedia:** il predefinito delle pagine resta com'era, e lo schema si spegne sulla **singola
pagina** che non lo deve avere, con il meta `rank_math_rich_snippet` = `off`.

## Sapere se un vecchio indirizzo esiste ancora, senza chiederlo via HTTP

Per una migrazione o un controllo dei rimandi servono decine di vecchi indirizzi da verificare. Farlo
con `curl` ha due problemi: dalla connessione del cliente è una raffica di richieste che un firewall
può scambiare per una scansione, e dal server può non funzionare affatto, perché su alcuni hosting il
server non raggiunge il proprio dominio e restituisce 000 su tutto (`connessione.md`).

Non serve HTTP: un indirizzo si **risolve dentro WordPress**, che sa già cosa esiste.

| Tipo di indirizzo | Come si risolve |
|---|---|
| Pagine, articoli, prodotti | `get_page_by_path($slug, OBJECT, array('page','post','product'))` |
| Categorie e tag | `get_term_by('slug', $slug, 'category')` (e `post_tag`, `product_cat`…) |
| Rimandi già impostati | la tabella `PREFISSO_rank_math_redirections` |

Le verifiche HTTP vere restano, ma alla fine e poche: dall'esterno, distanziate (`sleep 0.5`), solo su
pagine pubbliche, mai su `wp-login.php`, `xmlrpc.php` o altri file del core.

E la regola di partenza, già in `SKILL.md`: se il sito è online e indicizzato, **ogni indirizzo che
cambia va rimandato**; se è ancora in `noindex`, si può cambiare uno slug senza rimandi — ma prima di
toglierlo, vedi la prima sezione.
