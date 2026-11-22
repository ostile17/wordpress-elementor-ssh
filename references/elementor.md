# Elementor: modello dei dati e trappole

## Dove stanno le cose

| Cosa | Dove |
|---|---|
| Struttura e testi di una pagina | `postmeta` → `_elementor_data`, JSON |
| CSS generato per la pagina | `postmeta` → `_elementor_css` (si cancella, si rigenera) |
| HTML già reso in cache | `postmeta` → `_elementor_element_cache` (si cancella, mai si corregge) |
| Colori e tipografia globali | il «kit»: `get_option('elementor_active_kit')` → `_elementor_page_settings` |
| Intestazione, piè di pagina, template d'archivio | post di tipo `elementor_library` |
| Copia di servizio del contenuto | `posts.post_content` — **non viene mostrata** per le pagine Elementor |

## Forma dell'albero

`_elementor_data` è un array di sezioni. Ogni nodo ha `id`, `elType` e `settings`; i contenitori hanno
`elements`. I widget hanno anche `widgetType`.

```
section
  └─ column
       ├─ widget (widgetType: heading)
       └─ section (isInner: true)
            └─ column
                 └─ widget (widgetType: jkit_icon_box)
```

Gli `id` sono stringhe esadecimali brevi e **stabili**: si possono usare per puntare a un elemento.
Cambiano solo se qualcuno ricostruisce il blocco nell'editor.

## Le trappole, per esteso

### 1. Il tablet eredita dal desktop, non dal mobile

Ogni controllo responsive ha tre valori. Se quello tablet è vuoto prende il **desktop**. Impostare
solo il mobile lascia la fascia 768-1024 px con i numeri pensati per lo schermo largo, e il difetto si
vede solo lì.

Il caso classico: sezioni con `padding: 70px 0` (giusto a schermo largo, perché il contenitore è
limitato a 1140 px) e `padding: 50px 20px` su mobile. A tablet il contenitore riempie la finestra e il
contenuto va a filo bordo.

L'altro caso: colonne che a tablet passano a larghezza 100% e si impilano, ma conservano i margini
orizzontali pensati come gronda fra colonne affiancate, e non hanno margine inferiore. Risultato:
riquadri attaccati e sfalsati.

**Quando imposti un valore mobile, chiediti sempre cosa succede a tablet.** E se il sito ha già una
convenzione per il tablet, copiala invece di inventare un terzo numero: contala prima.

E tieni presente che «tablet» non è una fascia: lo stesso apparecchio, ruotato, ne attraversa tre. Le
soglie vanno ricavate dal contenuto, non dal nome del dispositivo — e la scorciatoia di spostare il
punto di rottura globale costa più di quanto sembri. Tutto in `responsive.md`, insieme agli altri
difetti che si vedono solo a certe larghezze: altezze diverse fra schede affiancate, margini giusti in
colonna e sbagliati in riga, rientri su un lato solo, specificità.

### 2. `__globals__` vince sul valore locale

Se un widget ha `"__globals__": {"text_color": "globals/colors?id=xxxx"}`, cambiare il colore locale
non produce alcun effetto: il valore arriva dal kit. Lo stesso per la tipografia.

Un globale **si può** cambiare, ma solo dopo aver verificato dove finisce: conta le occorrenze
**rese** sulla pagina, non quelle dichiarate nel database, e guarda su che sfondo stanno. Schiarire un
colore usato anche su fondo chiaro peggiora quello che volevi migliorare.

### 3. Il testo può non essere testo

I *dynamic tag* compongono la stringa al momento di mostrare la pagina: cercare la frase nel database
non la trova. Esempio reale: un copyright fatto di `before` + anno + `after`.

Anche i widget che uniscono più campi accostano i pezzi solo in fase di rendering, **spesso senza
separatore**. Un titolo composto da «…con il supporto di» più «Mario Rossi» si legge
«supporto diMario Rossi», e nessuna espressione regolare su un singolo campo può vederlo.

Quando devi agganciarti a un testo composto, aggancia il **risultato** invece dell'identificativo:

```php
add_filter('elementor/widget/render_content', function ($contenuto, $widget) {
    if (!is_object($widget) || $widget->get_name() !== 'text-editor') { return $contenuto; }
    if (strpos($contenuto, 'la frase che cerco') === false) { return $contenuto; }
    if (strpos($contenuto, 'sg-marcatore') !== false) { return $contenuto; }  // niente doppioni
    return $contenuto . '...';
}, 10, 2);
```

Il controllo sul marcatore serve perché lo stesso widget può essere reso più di una volta.

### 4. «Predefinito» non significa quello che pensi

Nel widget della data, `date_format: 'default'` è il predefinito **di Elementor**, che è americano,
non quello impostato in WordPress. Verifica sempre cosa esce, non cosa dice l'etichetta.

E l'italiano di WordPress restituisce i mesi con l'iniziale **maiuscola**: va corretta a parte,
filtrando `date_i18n`, `wp_date` e `get_the_date`.

### 5. `elementor/frontend/the_content` scatta una volta per documento

Non una volta per pagina. Su una pagina i documenti sono intestazione, contenuto e piè di pagina: una
sezione aggiunta con quel filtro viene stampata **tre volte**.

Una guardia statica non basta, anzi peggiora: blocca dopo la prima esecuzione, che è l'intestazione, e
la sezione finisce in cima alla pagina. Serve sapere quale documento si sta rendendo:

```php
$doc = \Elementor\Plugin::$instance->documents->get_current();
if (!$doc || (int) $doc->get_main_id() !== ID_PAGINA) { return $contenuto; }
```

### 6. `min-width: auto` sulle colonne flessibili

Le colonne sono elementi flessibili, e un elemento flessibile non si restringe sotto la larghezza
minima del proprio contenuto. Un'immagine da 420 px tiene la colonna a 420 px anche dove il
contenitore ne aveva 380 da distribuire: le colonne insieme escono dal contenitore, e il difetto
compare solo in una fascia stretta di larghezze.

Il limite va messo su tutta la catena, non solo in cima:

```css
.elementor-column { min-width: 0; }
.elementor-column > .elementor-widget-wrap { max-width: 100%; min-width: 0; }
```

### 7. Le etichette lunghe nei pulsanti

Molti kit danno ai pulsanti `white-space: nowrap`: non vanno a capo e non si restringono. Basta
allungare un'etichetta perché il pulsante esca dalla sua colonna e venga tagliato dal bordo dello
schermo, a certe larghezze e non ad altre.

Inseguire i singoli pulsanti significa trovarne uno a ogni larghezza di prova. Una riga li sistema
tutti, compresi quelli futuri:

```css
.jkit-button-wrapper, .icon-box-link { max-width: 100%; white-space: normal; }
```

Se lo spazio manca davvero, cercalo nella **spaziatura fra lettere** prima che nelle parole: i kit ne
usano molta (2-3 px), e su venti caratteri sono sessanta pixel di solo respiro. Le parole scritte dal
cliente si cambiano solo con il suo consenso.

### 8. `post_content` delle pagine Elementor non viene mostrato

È una copia di servizio. Nei siti nati da un template kit contiene ancora il lorem ipsum. Prima di
correggere testo lì dentro, verifica che compaia davvero in una pagina servita: quasi sempre non
compare, e stai lavorando per niente.

Per gli **articoli** invece `post_content` è il contenuto vero.

### 9. Ogni container ha già un `::before`, e il tuo lo eredita

Uno `::before` con `position: absolute`, `right`, `width` e `height` scritto nel CSS personalizzato di
un container non basta a posizionarlo: la banda esce dal lato **opposto** a quello richiesto.

Il motivo è che Elementor dichiara già un proprio `::before` su ogni container — la regola che
implementa bordi e sovrapposizioni di sfondo:

```css
.e-con:before, .e-con>.elementor-background-slideshow:before, ... { left: ...; ... }
```

Quel `left` resta in piedi, perché la regola personalizzata non lo tocca. Lo pseudo-elemento si
ritrova `left`, `right` **e** `width` tutti non-auto, cioè sovra-vincolato: in quel caso il CSS impone
di ignorarne uno, e in direzione `ltr` quello ignorato è il `right`. La banda parte dal bordo sinistro
e si estende per tutta la `width` verso destra, esattamente al contrario di quanto scritto. Misurato:
da x=187 a x=1627 invece che da x=-1187 a x=253.

**Come si riconosce:** il colore compare dal lato sbagliato, e nel valore calcolato si legge `left: 0px`
insieme a un `right` valorizzato. Se fosse specificità il `right` risulterebbe barrato o assente; qui
c'è ed è corretto, semplicemente non viene usato.

Si rimedia con una riga: `left: auto;`

Ma non finisce col posizionamento. La stessa regola impone al proprio `::before` anche
`border-radius: var(--border-radius)`, oltre a `border-*`, `opacity: var(--overlay-opacity)` e
`mix-blend-mode`. Il `border-radius` è il secondo che morde ed è più subdolo: la banda eredita
l'arrotondamento del container e non si salda più a filo. Si riconosce come un gradino curvo nel punto
in cui la banda finisce e il container non è ancora cominciato — cioè in mezzo alla forma, non ai suoi
estremi.

**Regola generale, valida per qualunque libreria che usi `::before` per i propri decori:** quando
costruisci uno pseudo-elemento su un elemento che ne ha già uno dichiarato dal framework, non scrivere
solo quello che serve — **azzera esplicitamente** tutte le proprietà che quella regola imposta e che
non vuoi (`left`/`right`/`top`/`bottom`, `border-radius`, `border`, `opacity`, `mix-blend-mode`). Leggi
una volta la regola del framework per intero e trattala come l'elenco delle cose da neutralizzare: si
scopre tutto in un colpo invece che un difetto per volta.

### 10. Il campo «url» dei form rifiuta l'indirizzo che la gente scrive

Un campo dei form Elementor di tipo «url» diventa `<input type="url">`, e il browser rifiuta
`www.sito.it` senza `https://` davanti. Chi compila vede un errore che non capisce e spesso
abbandona. Per una domanda come «il sito del tuo hotel» meglio un campo di testo, con l'esempio nel
segnaposto: è proprio il punto in cui si perdono le candidature.

## Punti di aggancio utili

| Serve | Filtro |
|---|---|
| Modificare l'HTML di un widget | `elementor/widget/render_content` |
| Aggiungere qualcosa a una pagina intera | `elementor/frontend/the_content` (attenzione al documento) |
| Cambiare la query di un blocco di articoli JKit | `jeg_default_query_args` (supporta `post__not_in`) |
| Togliere azioni predefinite dai form Pro | `elementor_pro/forms/default_submit_actions` |
| Validazione personalizzata di un form Pro | `elementor_pro/forms/validation` |
| Agire dopo un invio valido (redirect, CRM) | `elementor_pro/forms/new_record` |

Come provare i form che usano questi agganci senza browser, anche con la pagina in bozza: in
`verifica.md`, «La prova funzionale che si dimentica sempre».

## Menu, non widget

I menu di navigazione sono di WordPress, non di Elementor: si modificano con `wp menu item ...` e non
toccando il widget.

Un caso che vale la pena conoscere: con i menu a scomparsa configurati «apri col titolo», il tocco sul
titolo di una voce con sottomenu **non naviga mai**, quindi la pagina genitore diventa irraggiungibile
da telefono. La soluzione non è nel widget: si aggiunge la pagina genitore come **primo figlio di sé
stessa** («Tutti i trattamenti» → `/trattamenti/`). Il genitore apre, il primo figlio naviga.

**Questo vale anche quando sembra che serva il database.** Prima di rassegnarsi a non poter toccare un
piè di pagina Elementor senza accesso alle tabelle, guarda il markup: se la colonna monta un widget
`nav-menu.default`, i link **non** sono scritti dentro Elementor, vengono da un menu di WordPress. Si
aggiungono da Aspetto → Menu, il piè di pagina si aggiorna da solo, e non serve né toccare il template
né caricare codice sul server.

```bash
curl -s https://ilsito.it/ | grep -o 'data-widget_type="nav-menu[^"]*"'
```

Nota di lettura: nell'elenco delle voci del menu, «Bozza» accanto a una voce spiega un 404 misterioso.
Le pagine esistono, non sono pubblicate — e un link a una bozza dà 404 ai visitatori mentre a un
amministratore collegato può funzionare.
