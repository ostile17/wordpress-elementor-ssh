# Consenso, tracciamento e integrazioni di terze parti

Banner cookie, pixel, moduli collegati a un CRM o a una piattaforma di email
marketing. Sono la parte del sito che si rompe **in silenzio**: nessun errore a
schermo, nessuna pagina bianca, e il difetto si scopre mesi dopo guardando dei
numeri che non tornano.

Il filo comune di tutto quello che c'è qui dentro è uno solo: **la verifica va
fatta nei due sensi.** Senza consenso non deve partire, e con il consenso DEVE
ripartire. Chi controlla solo la prima metà consegna un sito conforme e un
tracciamento morto, senza accorgersene.

---

## Il blocco automatico non vede gli script che si iniettano da soli

Un banner con blocco automatico (Iubenda, ma il meccanismo è lo stesso negli
altri) intercetta gli script leggendo l'attributo `src` dei tag `<script>` e
confrontando il dominio con un elenco di terze parti. **Se lo script non ha
`src`, non c'è niente da riconoscere.**

È il caso dello snippet "Connected Site" di Mailchimp, inline, che si fabbrica
il tag da solo a runtime:

```html
<script id="mcjs">!function(c,h,i,m,p){ m=c.createElement(h); m.src=i;
  p.parentNode.insertBefore(m,p) }(document,"script","https://chimpstatic.com/...")</script>
```

Il dominio compare solo come stringa dentro il codice. Il blocco lo lascia
passare e il tracciamento parte prima di qualunque consenso, con il banner
ancora aperto.

**La verifica va fatta nel browser**, perché `curl` non basta: in modalità
semplificata il banner non riscrive l'HTML, agisce a runtime.

```js
document.querySelectorAll('._iub_cs_activate').length   // zero = non sta bloccando niente
performance.getEntriesByType('resource')
  .map(r => new URL(r.name).hostname)
  .filter(h => !h.endsWith('iltuosito.it'))             // chi è stato contattato davvero
```

Si rimedia marcando lo script a mano con `type="text/plain"` e
`class="_iub_cs_activate"`. Il punto da cercare **non** è un filtro sull'output
della pagina: i plugin seri espongono un hook per la propria stampa. Mailchimp
ha `mailchimp_add_inline_footer_script`, che spegne la stampa nativa, più
`mailchimp_get_connected_site_script_fragment()` per rileggere lo stesso
frammento e ristamparlo marcato. Passare dall'hook previsto invece che da un
`ob_start` significa sopravvivere agli aggiornamenti.

Due salvaguardie che non sono opzionali:

1. **Controllare che il plugin del banner sia ATTIVO prima di marcare.** Se un
   domani viene disattivato, nessuno rimette il `type` buono e lo script resta
   congelato per sempre, senza un errore e senza un avviso: un guasto che
   nessuno collegherebbe a quel file.
2. **Se il frammento non ha la forma attesa, non stamparlo affatto.** In materia
   di consenso «nel dubbio non parte» è l'unico default difendibile.

## La finalità va scelta fra quelle che il banner dichiara

Marcando uno script di email marketing, `data-iub-purposes="5"` (Targeting e
Pubblicità) è a rigore la descrizione giusta. Risultato reale: lo script non si
è riattivato **nemmeno dopo aver accettato tutto**.

Il motivo è che quel sito dichiarava solo tre finalità. Si leggono così, a
consenso dato:

```js
_iub.cs.consent.purposes     // {"1":true,"3":true,"4":true} -> la 5 non esiste
```

Una finalità che il banner non offre non può essere concessa da nessuno, quindi
lo script sarebbe rimasto congelato per sempre. Corretto con la 4 (Misurazione).

**Come si riconosce:** lo script resta bloccato dopo un consenso pieno. Se la
marcatura fosse sbagliata nella sintassi non verrebbe bloccato affatto; qui
viene bloccato correttamente e non viene mai liberato.

Corollario: `acceptAll()` chiamato da console può non avere effetto — banner
ancora visibile, nessun cookie scritto. Se si vede quello, il test non è
fallito: **non è partito**. Cliccare il pulsante vero.

## Iubenda: in modalità semplificata il pannello non comanda

Si personalizzano i colori del banner su iubenda.com, si salva, la conferma
arriva, e il sito continua a servire i colori di serie. Nessuna cache di mezzo.

La causa non è un salvataggio fallito. Il plugin WordPress ha **due modalità**:

- **Semplificata**: il plugin *genera da sé* la configurazione a partire dai
  propri controlli (Posizione, Tema Scuro/Chiaro, Legislazione, pulsanti). Sono
  le uniche leve: i colori personalizzati non esistono in questa modalità, e
  quello che sta nel pannello viene ignorato.
- **Integrazione manuale**: si incolla il codice `_iub.csConfiguration`, e lì i
  colori si possono mettere tutti.

**L'indizio che smaschera la cosa** prima di perdere tempo: confrontare un campo
qualsiasi fra pannello e sito. In un caso reale IAB TCF risultava spento nel
pannello e `enableTcf: true` nella pagina. Quando pannello e sito dissentono su
un campo, il sito non sta leggendo dal pannello, e continuare a salvare lì non
cambierà mai niente.

Si legge così la configurazione che il sito serve davvero:

```bash
curl -s 'https://ilsito.it/' | grep -o '_iub.csConfiguration ={[^;]*'
```

Corollario pratico: le opzioni **funzionali** (blocco automatico, TCF, AMP,
blocco di Google Tag Manager) stanno nel PLUGIN, non nel pannello, in entrambe
le modalità. Si trovano in WordPress sotto iubenda → Privacy Controls and Cookie
Solution → Configura → Altre opzioni.

## Il salvataggio che sembra riuscito e non lo è

Succede con i pannelli e succede con le API, e il sintomo è lo stesso: tutto
dice «fatto», niente è cambiato.

**Nel pannello.** Nel configuratore Iubenda il pulsante Salva apre una finestra
«Le modifiche verranno applicate direttamente al tuo sito web. Vuoi
continuare?». Finché non si preme **Aggiorna la mia configurazione** non è stato
salvato niente — ma il pannello si chiude lo stesso e sembra fatto.

**Nell'API.** Un `PATCH /campaigns/{id}` a Mailchimp con
`settings.subject_line` e `preview_text`, su un'email di tipo
`automation-email` che appartiene a una Customer Journey (stato `sending`),
risponde **HTTP 200** con un corpo normale e nessun errore. Rileggendo con `GET`
l'oggetto è quello di prima. Le email delle journey non si modificano via API —
l'API delle journey espone solo il trigger, e le classiche `/automations` sono
un'altra cosa. Si cambiano dall'editor di Mailchimp (Journey builder → passo
email → Modifica), e lo fa una persona collegata.

Regola generale per qualunque servizio di terze parti: dopo una scrittura,
**rileggere** — dal sito per un pannello, con una `GET` per un'API. Né la
schermata che si chiude né un 200 sono una verifica: il difetto si vede solo
rileggendo.

Le due trappole di interazione che questo caso ha rivelato sono utili ovunque e
stanno in `verifica.md`: il pulsante coperto da un altro elemento, e le
coordinate CSS che non sono quelle dello screenshot.

## Il popup e il banner si contendono lo schermo, e vince il banner

Un popup a tempo (20-30 secondi) e un banner cookie finiscono contemporanei su
chiunque non scelga subito. Il banner sta sopra qualsiasi cosa — Iubenda usa
`z-index: 99999998`, e fa bene: la scelta sui cookie viene prima — quindi copre
la parte bassa del popup, cioè esattamente il campo email e il pulsante. Il
visitatore vede due finestre sovrapposte e non può completare né l'una né
l'altra.

**Non si risolve alzando lo z-index del popup**: mettersi sopra il banner dei
cookie è peggio, perché ostacola una scelta che deve restare libera. Il popup
aspetta il suo turno:

```js
function bannerCookieAperto() {
  var b = document.getElementById( 'iubenda-cs-banner' );
  return !!( b && b.offsetHeight > 0 );
}
function apri() {
  if ( bannerCookieAperto() ) { attesa = setTimeout( apri, 2000 ); return; }
  ...
}
```

Non è un rinvio a tempo indeterminato: quando l'utente sceglie, il banner
sparisce e al controllo successivo il popup parte.

**Si verifica solo aspettando davvero**: caricare la pagina con i cookie di
consenso azzerati e non toccare niente per più del ritardo del popup. Chi
durante le prove clicca subito «Accetta» per togliersi il banner di mezzo non
vedrà mai il difetto.

## Iscrivere un contatto: leggere lo stato prima di scrivere

Un `PUT` su `/lists/{id}/members/{hash}` con `status_if_new=pending` non basta
per iscrivere chiunque in doppia conferma. Vale solo per i contatti che
Mailchimp considera **nuovi**, e un contatto **archiviato** non lo è.

Su un archiviato la chiamata risponde **200**, i merge field e i tag vengono
aggiornati davvero, ma lo stato resta `archived`: nessuna email di conferma
parte e la persona resta fuori dalla lista.

**Come si riconosce**, da un sintomo che sembra impossibile: il form dice
«controlla la posta», l'API ha risposto senza errori, e nell'interfaccia il
contatto non si trova e il totale degli iscritti non cambia. Cercarlo per
indirizzo non dà risultati, perché gli archiviati non compaiono né nelle liste
né nei conteggi.

Non è un caso di scuola: capita a chiunque abbia ripulito una lista archiviando
in massa — in un caso reale 1.700 indirizzi — e proprio quelle persone sono le
più probabili a ripresentarsi al form.

Si rimedia mandando `'status' => 'pending'` **esplicito** oltre a
`status_if_new` quando la GET del contatto ha restituito `archived`. Mai
`subscribed`: la conferma la deve dare la persona. Per `unsubscribed` invece non
si forza niente, chi si è cancellato si riattiva da solo.

**La regola generale che ne esce, e che vale per qualunque API di iscrizione:**
prima di scrivere, leggere lo stato del contatto e trattare i quattro casi
(`subscribed`, `pending`, `unsubscribed`/`cleaned`, `archived`) come rami
distinti. Se ne manca uno, il ramo dimenticato non dà errore: dà un **falso
successo**.

Lo stesso vale per il consenso al marketing. Le «marketing permissions» GDPR
(quelle che ChimpMatic, per esempio, offre di compilare) funzionano solo se
l'audience Mailchimp ha i campi GDPR attivi: si legge da
`GET /lists/{id}` → `marketing_permissions`. Se è `false` non c'è dove
scriverle, e il consenso va tracciato altrove: un merge field di testo più
`timestamp_signup` / `ip_signup`.

## ChimpMatic e Contact Form 7: i valori di esempio diventano dati veri

Tre sorprese sullo stesso giro, tutte silenziose.

### I segnaposto della scheda Mailchimp sono configurazione salvata

I campi precompilati nella scheda Mailchimp di ChimpMatic sembrano
suggerimenti. Sono valori salvati nell'option `cf7_mch_<id>` del form, e usati:

| Valore di esempio | Cosa produce davvero |
|---|---|
| `labeltags_cm-tag = "genre, [card-brand]"` | due tag veri su ogni iscritto, «genre» e «card-brand»: «genre» non è un campo e resta testo, «[card-brand]» non si risolve e ChimpMatic toglie le parentesi. Nel caso reale, 12 contatti marcati |
| FNAME mappato su `_post_name` | lo slug della pagina, o il testo letterale «[_post_name]», nel saluto delle email |
| LNAME mappato su `_user_last_name` | il cognome dell'utente WordPress collegato, non di chi compila |
| `my-gdpr1`, `my-gdpr2`, `your-height` | puntano a campi che non esistono |

**Come si riconosce**, in Mailchimp: tag «genre» o «card-brand» sui contatti,
o un FNAME fra parentesi quadre.

**Come si rimedia.** Su ogni sito con ChimpMatic, leggere **tutte** le option
`cf7_mch_*` cercando questi valori prima di dichiarare un form «collegato»; e
prima di copiare la configurazione di un form su un altro, leggerla campo per
campo. La correzione va fatta in quest'ordine: prima l'option, chiave per chiave
(non con `save()`, vedi sotto), poi la pulizia dei contatti in Mailchimp. Al
contrario, il primo invio rimette i tag.

### `WPCF7_ContactForm::save()` da riga di comando sporca gli altri plugin

`save()` fa scattare `wpcf7_after_save`, dove ChimpMatic (Pro e Lite), CF7
Google Sheets Connector e Popup Maker salvano le proprie impostazioni **leggendo
il form di amministrazione**, che da riga di comando non c'è. Su quel sito gli
agganci erano 6. Si contano così:

```bash
grep -rn "wpcf7_after_save" wp-content/plugins
```

Per **modificare** un form esistente conviene `update_post_meta` sul solo meta
toccato (per esempio `_mail`), rileggendo con `$wpdb` che sia ancora un array
con le stesse chiavi. `save()` resta necessario per **creare** un form, perché
genera `_hash`. Il principio generale — le funzioni di salvataggio di alto
livello portano con sé gli agganci di tutti — è in `modifiche-sicure.md`.

### In prova, ChimpMatic non parla solo con Mailchimp

Appena attivato, ChimpMatic chiama `api.chimpmatic.com` (licenza) e `signls.dev`
(telemetria). Su un ambiente di prova, un blocco che ferma solo
`*.api.mailchimp.com` non basta: per la durata della prova va bloccato tutto il
traffico verso host diversi dallo staging.

## Osservare cosa un plugin manda fuori, senza registrare le chiavi

Per vedere cosa un plugin manda a un servizio esterno si registrano metodo, URL
e corpo da `pre_http_request`, lasciando fuori le intestazioni, dove di solito
sta `Authorization`. Sembra sufficiente per non toccare segreti. Non lo è:
ChimpMatic Lite mette la chiave Mailchimp **dentro l'URL**, come credenziali:

```
https://anystring:CHIAVE@us21.api.mailchimp.com/3.0/lists/...
```

Nel caso reale la chiave è finita nell'option di log e nell'output del
terminale al primo invio di prova.

**Come si riconosce:** una `@` nell'host dell'URL registrato.

**Come si rimedia:** mascherare la parte `//utente:password@` prima di salvare o
stampare, **sempre**, anche se «tanto il plugin usa le intestazioni» — un altro
client dello stesso plugin può non farlo:

```php
$url = preg_replace('#//[^/@]+@#', '//***@', $url);
```

Se è già successo: cancellare il log e dire all'utente che la chiave va ruotata.
