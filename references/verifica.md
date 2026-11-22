# Verificare

## Cosa vale come prova

In ordine di affidabilità:

1. **Rileggere dal database e ricontare.** Vale per il contenuto.
2. **Misurare l'HTML servito** con `curl`. Vale per quello che arriva davvero al browser, cache
   compresa.
3. **Misurare nel browser con JavaScript.** Vale per il layout, che esiste solo dopo il rendering.
4. **Guardare uno screenshot.** Ultima scelta: dipende dalla risoluzione, non dà numeri, e non si può
   confrontare con la volta precedente.

«Il comando non ha dato errore» non è nessuna delle quattro.

## Misurare l'HTML servito

Utile perché include la cache: se il valore è giusto nel database ma sbagliato qui, il problema è la
cache, non i dati.

```bash
for p in "" "chi-siamo/" "contatti/"; do
  printf "%-20s " "/$p"
  curl -s "https://sito.it/$p" | grep -o "stringa" | wc -l
  sleep 0.5
done
```

`grep -o ... | wc -l` conta le occorrenze. `grep -c` conta le **righe**, e l'HTML di una pagina
Elementor può stare su una riga sola: risponderebbe `1` sempre. (Uno zero di `grep -c` invece è
affidabile: zero righe vuol dire zero occorrenze.)

**Da dove si lancia conta quanto cosa si misura.** Dalla connessione del cliente: poche richieste,
distanziate come sopra, solo su pagine pubbliche e **mai** su `wp-login.php`, `xmlrpc.php` o altri
file del core — una raffica di richieste così ha già fatto mettere in blacklist l'IP di un cliente su
tutti i server del suo hosting. Dal server: solo dopo aver visto che il server raggiunge il proprio
dominio, perché su alcuni hosting `curl` verso sé stesso restituisce **000** su ogni indirizzo, e
sembra il sito giù. Entrambi i casi per esteso in `connessione.md`.

## Misurare il layout nel browser

Numeri, non impressioni. Le misure che servono più spesso:

```js
document.documentElement.clientWidth                                   // larghezza utile
document.documentElement.scrollWidth - document.documentElement.clientWidth   // sospetto overflow
elemento.getBoundingClientRect()                                       // posizione e dimensioni reali
[...range.getClientRects()]                                            // un rettangolo per riga di testo
```

Per il contrasto, calcolalo invece di giudicarlo a occhio: la differenza fra 4,1 e 4,7 non si vede ma
una delle due passa la soglia di accessibilità e l'altra no.

## Le misure che mentono

Ogni volta che un numero sembrava assurdo, il colpevole era lo strumento. Qui ci sono quelle del
browser e della pagina. Fuori dal browser mentono allo stesso modo il pannello vulnerabilità
dell'hosting (falsi positivi sui rami con backport), i nodi di check-host.net (bloccati dai filtri
degli hosting) e `curl` lanciato dal server verso il proprio dominio (000 su tutto): sono in
`connessione.md`. E mente un **200** di un'API esterna che non ha cambiato niente: in
`consenso-e-integrazioni.md`.

### Il pannello largo zero

Se `document.documentElement.clientWidth` vale **0**, il pannello del browser non ha dimensioni e ogni
misura di larghezza è priva di senso. Produce overflow immaginari di centinaia di pixel. Controlla
sempre la larghezza prima di credere a una misura di larghezza.

### Lo scarto di 15 px è la barra di scorrimento

`scrollWidth - clientWidth` vale 15 su molte pagine a schermo largo, e non è un difetto: `clientWidth`
esclude la barra, `scrollWidth` no. La prova definitiva è provare a scorrere davvero:

```js
window.scrollTo(9999, 0);
const scorreDavvero = window.scrollX;   // 0 = nessun overflow
window.scrollTo(0, 0);
```

Attenzione anche a `body { overflow-x: hidden }`: nasconde lo scorrimento ma **non** il fatto che un
elemento venga tagliato. Se un pulsante finisce oltre `clientWidth`, viene ritagliato anche senza
barra di scorrimento.

### La stessa barra, dentro un riquadro di prova: qui mente sul serio

Il caso sopra è innocuo: si riconosce e si scarta. Questo no, ed è il difetto di metodo più costoso di
tutti, perché **non produce numeri sbagliati: produce numeri giusti riferiti a una larghezza diversa
da quella che credi**.

La barra di scorrimento **non** conta per le media query — che usano la larghezza del riquadro di
visualizzazione, barra compresa — ma **conta** per `clientWidth`, che la esclude.

```
iframe largo 782  ->  clientWidth 767  ->  @media (max-width:767px)  NON si applica
```

Leggi «767», credi di essere nella fascia del telefono, e il CSS sta applicando quella del tablet.
Ogni misura risulta spostata di quindici pixel, sempre nello stesso verso. E le soglie più delicate —
768 e 1025, cioè esattamente i due punti in cui il layout cambia — cadono **sistematicamente dalla
parte sbagliata**: quello che chiami «1024» è in realtà 1039, cioè già oltre il salto. Le fasce
768-782 e 1025-1039 non vengono mai controllate, ed è lì che si nascondono i difetti che sopravvivono
a due revisioni.

Su un apparecchio vero la barra è sovrapposta e non toglie spazio: le due misure coincidono. È il
riquadro di prova a mentire, proprio perché è un riquadro dentro una finestra.

**Il rimedio**, da iniettare nel documento di prova subito dopo il caricamento:

```css
html::-webkit-scrollbar { width:0; height:0; display:none }
html { scrollbar-width:none }
```

**Il controllo da fare prima di fidarsi di qualunque misura legata a una fascia:**

```js
W.matchMedia('(max-width:767px)').matches   // deve concordare con clientWidth
```

Se `clientWidth` dice 767 e `matchMedia` dice `false`, stai misurando un'altra larghezza. Vale anche
per il pannello del browser, non solo per gli iframe.

### Chrome senza interfaccia su Windows non scende sotto ~500 px

Stessa famiglia, altra causa. `chrome --headless --window-size=390,900 --screenshot` **non**
impagina la pagina a 390 px: su Windows la finestra non scende sotto una larghezza minima, e la sonda
dentro la pagina leggeva `clientWidth` = **511**. La foto era larga 390 solo perché ritagliata;
l'impaginazione era quella di uno schermo da 511.

**Come si riconosce:** nella foto «da telefono» restano due colonne dove a 390 ne dovrebbe restare
una (una griglia `minmax(220px, …)` a 390 non ci sta due volte), e il testo è tagliato sul bordo
destro invece di andare a capo. Sembra un difetto del sito, è lo strumento.

È pericoloso in due direzioni: si correggono difetti che a 390 non esistono, e si dichiarano a posto
pagine che a 360 o 390 sbordano davvero.

**Si rimedia in due modi.** Il migliore è pilotare Chrome con il suo protocollo di debug (CDP, sotto,
in «Quando il tempo non scorre»): `Emulation.setDeviceMetricsOverride` dà una larghezza **vera**,
anche sotto i 500 px. Senza CDP, si carica la pagina dentro un iframe largo esattamente quanto serve —
la finestra può essere allargata a forza, il riquadro interno no — e la sonda gira nell'iframe e
scrive il risultato nel documento padre. Per i file locali serve `--allow-file-access-from-files`:

```
<iframe src="pagina.html" style="width:390px;height:1200px;border:0"></iframe>

chrome --headless=new --allow-file-access-from-files --virtual-time-budget=12000
       --window-size=520,1200 --dump-dom file:///.../involucro.html
```

Dentro l'iframe vale quanto detto sopra sulla barra di scorrimento: va nascosta, altrimenti l'iframe
da 390 misura 375.

**Il controllo che copre tutti e due i casi:** prima di credere a qualunque misura legata a una
larghezza, far stampare alla pagina `document.documentElement.clientWidth` e il `matchMedia` della
fascia. Se non sono il numero chiesto e la fascia attesa, la misura non vale.

### Misurare la scatola invece dell'inchiostro

Un widget largo quanto la colonna con dentro un'immagine stretta ha la **scatola** centrata e il
**contenuto** no: confrontare le scatole non vede il difetto. E sugli elementi a larghezza libera —
quelli con `elementor-widget__width-initial`, che si dimensionano sul contenuto — ne inventa uno che
non esiste, perché la scatola si posiziona da sé e il confronto con l'asse della colonna non
significa niente.

Misura dove finisce il pixel acceso: il riquadro delle immagini, o i rettangoli di riga del testo
presi con un `Range` sui nodi di testo. `scripts/verifica-layout.js` ha la funzione già scritta.

### Contare le righe di un'etichetta

`range.getClientRects().length` **non** è il numero di righe. Molti kit duplicano l'etichetta per
l'effetto al passaggio del mouse, e la copia sta a un'altezza diversa: contando i rettangoli di tutti
i nodi insieme risultano due righe dove ce n'è una. Su undici pulsanti segnalati «a capo», sei erano
falsi allarmi.

Si conta dentro **un nodo di testo per volta**, raggruppando i rettangoli per quota verticale. La
conferma indipendente è l'altezza del pulsante: 52px contro 78px dice da sola quante righe ci sono.
Quando due metodi disaccordano, ha ragione quello che misura una cosa fisica.

### Un elenco di candidati non è un elenco di colpevoli

Vale la pena metterlo qui perché ricapita ogni volta: il primo giro di un controllo automatico su un
sito vero ha prodotto **36 segnalazioni**, di cui 8 reali. Le altre 28 erano difetti del controllo.

Le famiglie che tornano sempre, e che conviene escludere in partenza:

| Falso allarme | Perché |
|---|---|
| Testo «rientrato di 25px» | misuravo il testo della voce, non l'icona che la precede |
| Due pulsanti «disallineati di 318px» | sono affiancati sulla stessa riga: avere centri diversi è come devono stare |
| Elementi a `-10000px` o `-1px` | testo per lettori di schermo e salta-menu, non difetti |
| «Titolo su due righe» | stimavo le righe dall'altezza, ma il padding sta sull'elemento interno |
| Scarto di «4989px» | un elemento fuori flusso trascinato dentro il calcolo |
| Il tondino «fuori di 18px» | il tondino **è** il widget: la scansione guardava solo i discendenti |
| Schede «fuori centro di 10px» | appoggiate ai bordi esterni con il corridoio in mezzo, per disegno |

Ogni correzione al metodo ha ridotto le segnalazioni **senza toccare il sito**. Un report automatico
non verificato fa perdere mezza giornata a inseguire problemi che non esistono, e — peggio — fa
perdere fiducia nei sei che erano veri.

### Quando il tempo non scorre

Due strumenti diversi, lo stesso inganno: la pagina è ferma nel tempo, e qualcosa che funziona sembra
rotto.

**Le transizioni CSS non avanzano se il pannello non è visibile.** La pagina non compone fotogrammi, e
`getComputedStyle` durante una transizione restituisce il valore interpolato, che resta fermo al
valore di partenza. Un menu che funziona benissimo sembra bloccato fuori schermo. Prima di dichiarare
rotto qualcosa che dipende da un'animazione, togli l'animazione:

```js
el.style.transition = 'none';
el.classList.remove('attivo'); void el.offsetWidth; el.classList.add('attivo');
// ora getComputedStyle dice la verita'
```

**Con `--virtual-time-budget` gli eventi accodati non arrivano.** Chrome senza interfaccia con il tempo
virtuale non esegue la pagina «come un browser vero, solo più in fretta». Provando un menu mobile
fatto con `<dialog>`: `dialog.close()` chiudeva davvero (`open` = false), ma l'evento `close` non
arrivava mai, nemmeno dopo 480 ms. `aria-expanded` restava `"true"` e il focus restava su un link
**dentro** il dialogo ormai chiuso: sembrava un difetto grave di accessibilità. Lo stesso test su
Chrome pilotato a tempo reale via CDP: evento `close` dopo **4 ms**, `aria-expanded` false, focus di
nuovo sul pulsante. L'evento `close` viene accodato in un task a parte, e il tempo virtuale non lo fa
scattare.

**Come si riconosce, in entrambi i casi:** lo stato principale è cambiato (la classe c'è, il dialogo
è chiuso) ma tutto ciò che dipende dal passo successivo è fermo, e un'attesa più lunga non cambia
niente. **Se allungare l'attesa non sposta il risultato, il tempo non scorre.**

**Regola:** per qualunque prova con eventi asincroni — `dialog`, `transitionend`,
`IntersectionObserver`, focus — niente `--virtual-time-budget`. Si pilota Chrome via CDP, senza
dipendenze, perché Node 24 ha `WebSocket` integrato:

1. si lancia `chrome --headless=new --remote-debugging-port=N`;
2. si legge l'indirizzo della pagina da `http://127.0.0.1:N/json/list`;
3. si mandano `Page.navigate` e `Runtime.evaluate` con `awaitPromise`;
4. per una larghezza vera, anche sotto i ~500 px, `Emulation.setDeviceMetricsOverride`.

Uno strumento già scritto è in `design-system/strumenti/cdp.js` del progetto Massimo Agnoletti.

### La cache degli oggetti di WordPress

Dopo un `$wpdb->update` diretto su `postmeta`, `get_post_meta()` **nella stessa richiesta** restituisce
ancora il valore vecchio. Un controllo finale scritto con `get_post_meta` segnala residui su pagine già
pulite. Nei controlli rileggi con `$wpdb`, o chiama `clean_post_cache($id)`.

### `getComputedStyle` restituisce un oggetto vivo

Per provare una correzione CSS senza toccare il sito: leggo lo stato, inietto una regola di prova,
rileggo, confronto. Il confronto dice che prima e dopo sono **identici**, cioè che la correzione non
serve a niente. È falso.

`getComputedStyle` non restituisce un'istantanea, restituisce un `CSSStyleDeclaration` **vivo**,
agganciato all'elemento. Il riferimento salvato in una variabile prima della modifica riflette lo stato
al momento in cui si **leggono** le proprietà, non al momento in cui è stato ottenuto. Leggendo
entrambi gli oggetti alla fine della funzione, si leggono due volte lo stato finale.

**Come si riconosce, e porta fuori strada:** prima e dopo coincidono su *tutte* le proprietà, comprese
quelle che la regola iniettata cambia di sicuro. Se una modifica certa non produce differenza, non è la
modifica a non funzionare: è la misura.

Si rimedia copiando i valori in stringhe al momento giusto, non conservando l'oggetto:

```js
const snap = () => { const p = getComputedStyle(el, '::before');
                     return {left: p.left + '', right: p.right + '', width: p.width + ''}; };
const prima = snap();   // valori congelati
inietta();
const dopo = snap();
```

Vale anche per `getBoundingClientRect` dentro la stessa funzione se in mezzo si modifica il layout: lì
l'oggetto è statico, ma va comunque riletto **dopo** la modifica, altrimenti si confronta una geometria
vecchia con proprietà nuove.

### La cache del sito maschera un cambio di accesso

Dopo aver modificato chi può raggiungere il sito — un `.htaccess`, una regola del firewall, una
protezione con password — la verifica immediata può restituire lo stato **precedente**: un proxy come
LiteSpeed serve la copia in cache della pagina che hai appena visitato tu per provarla.

È il caso peggiore di misura che mente, perché mente in entrambe le direzioni: puoi credere che una
chiusura non abbia funzionato (e riaprirla per sbaglio), o che abbia funzionato mentre non è così.

Si smaschera chiedendo qualcosa che nessuno ha ancora messo in cache:

```bash
curl -s -o /dev/null -w "%{http_code}\n" "https://ilsito.it/$RANDOM/"
curl -s -o /dev/null -H "Cache-Control: no-cache" -w "%{http_code}\n" "https://ilsito.it/?cb=$RANDOM"
```

Se una chiusura d'accesso è davvero in vigore, **anche un URL inesistente deve rispondere con il
rifiuto**, non con un 404: il rifiuto arriva prima che il sito venga interpellato.

### La cache del browser tiene il CSS combinato di LiteSpeed per un anno

Cambiato `_elementor_data`, cancellato `_elementor_css`, svuotato LiteSpeed: la pagina dovrebbe
mostrare lo stile nuovo, e non lo fa.

Con «CSS Combine» attivo, LiteSpeed rigenera il pacchetto con lo **stesso nome**
(`wp-content/litespeed/css/814a0cc….css?ver=c0517`, dove anche `ver` deriva dal nome). Il contenuto
cambia, l'indirizzo no, e il file esce con `Cache-Control: max-age=31557600`: un anno. Nel caso reale
il file sul server era giusto (con `grep` c'era il `clamp()` nuovo), ma il browser, che aveva caricato
la pagina prima della modifica, riusava la sua copia e il valore calcolato restava quello vecchio.

**Come si riconosce:** il `<link>` punta al pacchetto giusto, ma le regole lette da
`document.styleSheets` per quell'elemento sono quelle vecchie. Il confronto decisivo:

```js
(await (await fetch(urlDelPacchetto, {cache: 'reload'})).text()).includes('il valore nuovo')
```

Se il testo scaricato contiene il valore nuovo, il colpevole è la cache del browser.

**Come si verifica senza farsi ingannare:**

1. `wp litespeed-purge all`;
2. due richieste alla pagina, per far rigenerare il pacchetto;
3. `grep` del valore nuovo sul file in `wp-content/litespeed/css/`;
4. nel browser, `fetch(..., {cache: 'reload'})` del pacchetto, che aggiorna anche la cache, e poi
   ricarica.

E va detto al cliente: chi aveva già aperto la pagina deve forzare il ricaricamento (Ctrl+F5); chi
arriva per la prima volta vede subito la versione nuova.

### Il bersaglio del clic non è quello che credi

Due trappole trovate lavorando su pannelli di terze parti, utili ovunque si debba cliccare qualcosa.

**Un pulsante può essere coperto** da un altro elemento a quell'altezza di finestra, e il clic finisce
sull'elemento sopra. Si verifica prima di cliccare, senza indovinare:

```js
const r = bottone.getBoundingClientRect();
document.elementFromPoint(r.x + r.width/2, r.y + r.height/2)   // chi c'è davvero lì
```

Se torna un elemento diverso dal bottone, il clic andrà a vuoto.

**Le coordinate CSS non sono quelle dello screenshot.** Con viewport 1920 e frame catturato 1512 il
fattore è 0,7875: si converte sempre `coordinataCss * (frame / innerWidth)`, altrimenti si clicca a
caso.

## Trovare l'elemento che sfora

L'elemento più profondo che sfora è il colpevole; i suoi genitori lo ereditano.

```js
const vw = document.documentElement.clientWidth;
[...document.querySelectorAll('body *')]
  .map(e => ({e, r: e.getBoundingClientRect()}))
  .filter(o => o.r.width > 0 && o.r.x + o.r.width > vw + 1)
  .filter(o => ![...o.e.children].some(c => {
      const q = c.getBoundingClientRect();
      return q.width > 0 && q.x + q.width > vw + 1;
  }));
```

Se non trova nessun elemento ma `scrollWidth` è maggiore, l'eccedenza viene da **contenuto** che sfora
senza allargare il suo riquadro: una parola non spezzabile, o un elemento con `overflow` visibile il
cui `scrollWidth` supera il `clientWidth`. Cerca così:

```js
[...document.querySelectorAll('body *')].filter(e => e.scrollWidth > e.clientWidth + 2 && e.clientWidth > 0)
```

**E rimisura dopo ogni correzione.** Se il numero non cambia, quello che hai corretto era un sintomo:
continua a scendere nella catena. Può capitare tre volte di fila, con tre difetti tutti veri e nessuno
dei quali era la causa.

## Misurare il testo senza ricaricare la pagina

Per sapere come va a capo un titolo a sei larghezze diverse non servono sei ricaricamenti: il taglio
delle righe dipende solo da font, dimensione e larghezza disponibile.

```js
const box = document.createElement('div');
box.style.cssText = 'position:fixed;left:-99999px;top:0;visibility:hidden';
['fontFamily','fontWeight','fontStyle','letterSpacing','wordSpacing','textTransform','lineHeight']
  .forEach(p => box.style[p] = getComputedStyle(sorgente)[p]);
document.body.appendChild(box);
box.style.width = (larghezzaSchermo - 40) + 'px';   // meno il padding laterale
box.textContent = testo;
const righe = [...(() => { const r = document.createRange(); r.selectNodeContents(box.firstChild); return r.getClientRects(); })()]
  .map(r => Math.round(r.width));
```

**Il controllo che rende affidabile il metodo:** fai riprodurre alla simulazione un caso già misurato
sulla pagina vera. Se coincide, puoi fidarti; altrimenti è una simulazione di cui fidarsi per fede.

## La prova funzionale che si dimentica sempre

Se hai toccato un modulo, **invialo per davvero**. Togliere un campo può rompere il template della
notifica senza che nulla lo segnali: la pagina mostra il messaggio di successo e la mail non arriva, o
arriva con un buco.

Cosa controllare dopo l'invio:

- il messaggio di conferma dice ancora cose vere (se hai tolto un campo, non deve citarlo);
- nel registro degli errori PHP non è comparsa una riga di invio fallito;
- se l'archivio degli invii è spento, il conteggio non è aumentato;
- il template della notifica non richiama campi che non esistono più.

Se la casella di posta è su un altro server, **dillo chiaramente**: puoi provare che la mail è partita,
non che è arrivata. Sono due affermazioni diverse e solo una è tua.

### Un form Elementor Pro si prova senza browser, anche con la pagina in bozza

Per provare un form con logica personalizzata (agganci `elementor_pro/forms/validation` e
`elementor_pro/forms/new_record`) non serve pubblicare la pagina né pilotare l'anteprima da
collegati. Il gestore `elementor_pro_forms_send_form` accetta l'invio anche quando la pagina è in
**bozza**, e non chiede nonce. Una POST a `/wp-admin/admin-ajax.php` con `action`, `post_id`,
`form_id`, `queried_id` e i campi `form_fields[...]` restituisce il JSON vero: `success`, gli `errors`
campo per campo e il `redirect_url`.

```bash
curl -s https://ilsito.it/wp-admin/admin-ajax.php \
  -d action=elementor_pro_forms_send_form -d post_id=ID -d form_id=IDFORM -d queried_id=ID \
  -d 'form_fields[email]=nome+test@dominio.it' -d 'form_fields[nome]=Prova'
```

Così si provano in pochi secondi tutti i rami: la validazione condizionale, il redirect scelto
dall'aggancio, le azioni (ActiveCampaign, email). Poi si rileggono i contatti via API del servizio,
con le credenziali già salvate nel sito.

Tre avvertenze:

- **Le azioni sono vere.** Un invio valido crea il contatto e fa partire le automazioni: si usano
  indirizzi di prova dell'utente (`nome+test@dominio`) e alla fine si chiede a lui di cancellare i
  contatti.
- **Da dove partono le richieste.** Meglio dal server (SSH più `curl`), per non far sembrare un attacco
  una raffica di POST dalla connessione del cliente. Ma solo se il server raggiunge il proprio
  dominio: se una richiesta alla home dal server restituisce 000, quella strada è chiusa
  (`connessione.md`) e le prove si fanno dall'esterno, poche e distanziate.
- **Non copre il JavaScript.** Pulsanti degli step, campi resi obbligatori nel browser: quel
  comportamento va visto a parte.

## Il resoconto

Chi legge deve poter contestare. Quindi: numeri, non aggettivi.

- «Zero occorrenze su diciassette pagine, verificato sull'HTML servito» si può controllare.
- «Ho sistemato tutto» no.

E se hai fatto meno di quanto era stato chiesto, o diversamente, **dillo con il numero**: «su 120 voci
ne ho corrette 5, le altre 115 sono minuscole correttamente perché continuano una frase». Chi legge
deve poter rispondere «no, fai come avevo chiesto».
