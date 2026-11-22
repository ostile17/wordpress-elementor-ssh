# Responsive e impaginazione

Elementor genera CSS a partire da pannelli, e i pannelli nascondono cosa succede davvero. Quasi tutti
i difetti «di design» che restano dopo una consegna nascono qui, e quasi nessuno si vede alla
larghezza in cui si sta lavorando.

Indice:

1. Le fasce non sono i dispositivi
2. Spostare il punto di rottura globale: la scorciatoia che costa cara
3. Riquadri della stessa riga alti diversi
4. Un margine giusto in colonna e sbagliato in riga
5. Un rientro su un lato solo dentro una colonna centrata
6. Una misura fissa che corregge una deformazione ne crea un'altra
7. Una regola giusta per il telefono e sbagliata per il tablet
8. Vincere sul CSS di Elementor senza `!important`
9. Regole che non fanno niente

---

## 1. Le fasce non sono i dispositivi

«Tablet» non è una misura. Lo stesso apparecchio, ruotato, attraversa tre fasce diverse:

| | verticale | orizzontale |
|---|---|---|
| Galaxy Tab | ~800 | ~1280 |
| iPad Air | ~820 | 1180 |
| iPad Pro 11" | 834 | 1194 |
| iPad Pro 12.9" | 1024 | 1366 |

**Nessuna di queste larghezze sta tutta dentro la fascia 768-1024**, che è quella che Elementor chiama
tablet. Un tablet in verticale può dichiarare **meno di 768** e ricadere nelle regole del telefono pur
avendo spazio in abbondanza; ruotato ne dichiara più di 1024 e ricade in quelle da scrivania.

Il sintomo tipico: scrivi una regola dentro `@media (min-width:768px)` perché la richiesta era «su
tablet mettili in riga», e la stessa persona **dallo stesso tablet** continua a vederli impilati.

**La soglia si ricava dal contenuto, non dal nome del dispositivo.** Misura la cosa più larga che non
deve andare a capo e fai i conti:

```
139px  il riquadro piu' largo che non deve spezzarsi
x 3    quanti ne vuoi in riga
+ 44   gli spazi fra loro
= 461  piu' i margini della fascia: circa 500px di finestra
```

Poi verifica **al valore esatto in cui scatta**, sopra e sotto: a 605 i riquadri sono larghi 174 e ci
stanno, a 585 restano impilati. Una soglia scelta senza questa prova è un'opinione.

E prima di scrivere una soglia nuova, **conta quelle che il sito usa già**: un terzo numero introdotto
per un caso singolo diventa una fascia in più da controllare per sempre.

## 2. Spostare il punto di rottura globale: la scorciatoia che costa cara

Elementor permette di abbassare il punto di rottura mobile, per esempio da 767 a 620. Una riga sola, e
tutto il layout tablet varrebbe da 620 in su. Sembra la soluzione elegante al problema del paragrafo
precedente.

Prima di farlo, conta cosa eredita cosa:

```
colonne totali                                     313
con una larghezza tablet impostata esplicitamente   71
SENZA tablet e piu' strette del 100% sul desktop   167
```

Quelle 167 **erediterebbero la larghezza del desktop a 620px**: una colonna al 33% diventerebbe larga
193. Molto peggio dell'impilamento che volevi evitare. È la trappola del tablet che eredita dal
desktop, vista dall'altro lato.

Conviene quasi sempre agire su gruppi scelti invece che sulla soglia globale. Come si scelgono:

- elementi **paritari** fra loro (colonne con colonne, non un titolo con il suo paragrafo: quelli
  devono restare impilati);
- in numero **pari**, per non lasciare un orfano su una riga;
- il cui contenuto sta in metà larghezza **con almeno 10px di margine**, misurato nel caso più stretto.

Il primo filtro da solo toglie più della metà dei candidati, e non è ovvio: un titolo e il suo
paragrafo hanno entrambi righe corte, quindi passano il test della larghezza pur non dovendo mai
stare affiancati.

## 3. Riquadri della stessa riga alti diversi

**Elementor pareggia le colonne, non quello che ci sta dentro.** La scheda vera — quella con fondo,
bordo e raggio — si dimensiona sul proprio testo: se una descrizione va su due righe e le altre su
una, quella scheda sporge e le vicine restano corte, con un gradino visibile proprio perché hanno un
bordo.

Il difetto **dipende dalla larghezza**, ed è per questo che sfugge: a 1100 le tre descrizioni vanno
tutte su due righe e sembra a posto, a 1280 ne va a capo una sola, da 1440 in su due. **Va provato ad
almeno tre larghezze**, altrimenti si dichiara chiuso qualcosa che è a posto solo dove hai guardato.

### Il rimedio: propagare l'altezza fino alla scheda

La colonna è già alta quanto la più alta della riga. Basta far arrivare quell'altezza fino in fondo,
trasformando ogni anello della catena in un elemento flessibile che si allunga:

```css
.sezione .elementor-widget-wrap            { display:flex; flex-direction:column; }
.sezione .elementor-widget-jkit_icon_box   { flex:1 1 auto; display:flex; }
.sezione .elementor-widget-jkit_icon_box > .jkit-icon-box { flex:1 1 auto; display:flex; }
.sezione .jkit-icon-box-wrapper            { flex:1 1 auto; }
```

Sotto i 768 le colonne si impilano e sono alte quanto il contenuto, quindi la regola non ha effetto:
non serve nessuna media query.

Aggiungi `min-width: 0` sulla stessa catena. Un elemento flessibile non scende sotto la larghezza
della parola più lunga che contiene: un titolo come PERSONALIZZAZIONE fa allargare la scheda oltre il
rientro della colonna, e la differenza si vede solo alle larghezze strette.

### `height: 100%` non fa riempire niente

Se la catena sopra non ha altezze definite, la percentuale non si risolve e resta l'altezza naturale.
Serve la catena flessibile, non la percentuale.

E `grid-auto-rows: 1fr` **non** garantisce righe uguali: significa `minmax(auto, 1fr)`, quindi una riga
il cui contenuto supera la quota cresce oltre la traccia. Provato e misurato: 222/222 sulla prima riga
e 222/**244** sulla seconda.

### `align-items: stretch` pareggia il MARGINE, non il contenuto

Questa è la causa che si trova per ultima, dopo aver provato tutto il resto.

Se due colonne affiancate hanno `margin: 0 0 22px` e `margin: 0`, lo stretch le rende alte uguali
**margine compreso**: quella con il margine ha 22px di contenuto in meno. Lo scarto è identico su
tutte le coppie del sito — ed è il segnale che riconosce il caso: **quando lo scarto è lo stesso
numero ovunque, non è il testo, è una misura scritta da qualche parte**.

```css
.colonna-accoppiata { margin-top:0 !important; margin-bottom:0 !important; }
```

### Quando vale la pena e quando no

Solo se la scheda ha un contorno visibile: fondo, bordo o ombra. È il bordo a rendere evidente il
gradino. Dove il riquadro è trasparente il dislivello non si vede, e pareggiarlo è lavoro invisibile.

Se resti con una differenza sola e ostinata, guarda **il testo**: 156 caratteri contro 131 fanno 51px
di scheda a certe larghezze. Quello non si corregge con il CSS, si corregge accorciando la frase — ed
è una decisione di chi scrive i testi, non tua.

## 4. Un margine giusto in colonna e sbagliato in riga

Quattro passi numerati, il primo con margine superiore 0 e gli altri tre con 20px. Impilati è
esattamente giusto: il margine separa un passo dal successivo, e sopra il primo non c'è niente da
separare.

Affiancandoli a due a due, quel margine diventa un difetto: il passo 1 parte venti pixel più in alto
del passo 2. È lo stesso schema di `:first-child { margin-top: 0 }`, corretto finché gli elementi
stanno in fila verticale e sbagliato appena vanno a capo in griglia.

Stessa famiglia: tre pannelli affiancati dove due hanno `margin-top: 30px` e il terzo no. Finiscono
alla stessa altezza in basso (399/399/**429**) e uno sporge di 30px in cima.

**Da controllare ogni volta che si passa da una colonna a due:** i margini pensati per la sequenza
verticale, e le altezze che prima erano irrilevanti perché nessuno le confrontava.

## 5. Un rientro su un lato solo dentro una colonna centrata

Il testo è centrato dentro la sua scatola, ma la scatola è più stretta della colonna e appoggiata a un
lato: **il centro del testo cade a metà del rientro**. Un `padding: 0 40px 0 0` produce uno scarto di
20px, esattamente mezzo rientro.

Non si nota guardando il titolo. Si nota perché l'occhio confronta titolo, paragrafo e pulsante
incolonnati e li vede tutti e tre spostati insieme rispetto ai bordi della fascia.

Nei casi reali il rientro non era sbagliato in sé: serviva a lasciare spazio a una fotografia
accanto. Era sbagliato **dove quella fotografia non c'era più**, cioè sotto la soglia in cui le
colonne si impilano. Il rimedio è toglierlo lì, non ovunque.

**Dove cercare il rientro.** Non è sul widget e non è su `.elementor-column`: Elementor scrive il
padding della colonna sul contenitore interno.

```css
.elementor-73 .elementor-element.elementor-element-XXXX > .elementor-element-populated { padding: 0 40px 0 0 }
```

Chi mira al widget non trova niente e conclude che «la regola non funziona». Il bersaglio è
`> .elementor-widget-wrap` (che porta anche la classe `.elementor-element-populated`).

**Si cerca con uno strumento, non a occhio.** La condizione è precisa:

```js
// colonne con rientro orizzontale asimmetrico e contenuto centrato e largo
Math.abs(paddingLeft - paddingRight) >= 6 && figliCentratiLarghi >= 2
```

Su dodici pagine questa condizione ha prodotto **due** risultati, ed erano veri entrambi.

## 6. Una misura fissa che corregge una deformazione ne crea un'altra

Un tondino diventava ovale (75×96) perché Elementor gli dava `width: 93%` e altezza fissa. Bloccarlo a
`96×96 !important` risolve l'ovale.

Ma il 93% non era un capriccio: era **il tondino che rimpicciolisce insieme alla colonna**. Tolta
quella proprietà, nella fascia in cui la colonna scende a 97px con 20 di rientro — 57px di posto — il
tondino da 96 **si sovrappone al testo accanto**, fino a 19px.

Un difetto sostituito da un altro, più difficile da vedere: l'ovale si nota subito, una
sovrapposizione di 10px sembra spaziatura sbagliata.

**Quando sostituisci una misura relativa con una fissa, la domanda non è «adesso è giusta?» ma «a
quali larghezze diventa troppo grande?»**. Una percentuale contiene una promessa — mi adatto — che un
valore in pixel non può mantenere.

Il rimedio migliore ridà la proporzione senza rimettere la deformazione, e quasi sempre **il valore
giusto è già nel disegno**: in quel caso, la misura che il tondino ha già da telefono.

## 7. Una regola giusta per il telefono e sbagliata per il tablet

Il breakpoint mobile di Elementor arriva **fino a 767**, cioè copre da 320 a 767: più del doppio.

Un pulsante con `max-width: 305px` impostato guardando l'anteprima a 375 è giusto lì e assurdo a 767,
dove la colonna è larga 727: il pulsante resta inchiodato a 305 e l'etichetta si spezza in tre righe.

**Per ogni misura fissa messa nella fascia mobile, chiediti se ha senso anche a 767.** Se è nata
guardando l'anteprima del telefono, quasi certamente no, e la si limita con una soglia bassa
(`@media (max-width:479px)`) lasciando che sopra valga il comportamento naturale.

## 8. Vincere sul CSS di Elementor senza `!important`

`!important` va evitato perché rende la regola impossibile da scavalcare a chi metterà mano al widget
dopo. Ma **anteporre `body` non basta quasi mai**, ed è utile sapere perché: una classe pesa più di un
elemento.

| Selettore | Peso |
|---|---|
| `body .elementor-element-XXXX .jkit-button-wrapper` | 0,2,1 |
| `.elementor-89 .elementor-element.elementor-element-XXXX .jeg-elementor-kit.jkit-button .jkit-button-wrapper` | **0,5,0** |

Il secondo vince: cinque classi contro due classi più un elemento.

La tecnica che funziona è **ripetere una classe già presente**. Non cambia quali elementi vengono
selezionati, alza solo il peso:

```css
body .elementor-element.elementor-element.elementor-element.elementor-element-XXXX
  .jeg-elementor-kit.jkit-button .jkit-button-wrapper { ... }
```

Un'alternativa più pulita quando esiste: **rispecchiare la catena di Elementor** invece di gonfiarla,
aggiungendo `body` in testa. Se la regola avversaria arriva a 0,5,0, `body` più le stesse cinque
classi fa 0,5,1 e vince di un soffio, restando leggibile.

Se la regola di Elementor ha anche `!important`, servono entrambe le cose: peso maggiore **e**
`!important`. In quel caso scrivi accanto un commento che dica contro cosa stai combattendo, perché
chi legge dopo deve sapere che non è capriccio.

### Se dall'altra parte c'è un ID, ripetere la classe non basta

La tecnica qui sopra vale finché l'avversario usa **solo classi**. Se nel suo selettore c'è un ID,
non può funzionare: un ID pesa più di qualunque numero di classi, quindi si può ripetere la classe
dieci volte e il valore calcolato non cambia di una virgola.

Caso reale, il cursore personalizzato di un tema:

```css
body:not(.wp-admin):not(.elementor-editor-active).has-mouse-circle #mouseCircle   /* 1,3,1 */
```

Una regola con tre classi ripetute vale 0,5,1 e perde comunque.

**Come si riconosce:** hai alzato il peso, la regola risulta presente, il selettore combacia
(`el.matches` restituisce `true`), e il valore calcolato non si sposta. Se aggiungere classi non
produce **nessun** cambiamento, non è questione di quante: c'è un ID dall'altra parte.

Si rimedia mettendo un ID anche nel proprio selettore e, se serve, ripetendolo
(`#mouseCircle#mouseCircle`) per superare il loro. Resta senza `!important`.

**Corollario di metodo, che costa più del resto:** quando stampi i selettori trovati per
diagnosticare una gara di specificità, **non troncarli**. La parte che decide sta spesso in fondo,
ed è esattamente quella che il troncamento mangia — in un caso reale la prima lettura era tagliata a
60 caratteri e l'ID stava oltre.

## 9. Regole che non fanno niente

Una regola può esserci, essere corretta, e non governare nulla perché un'altra vince. Il risultato
sembra giusto per un'altra ragione, e nessuno se ne accorge finché non cambia qualcosa.

Caso reale: `white-space: nowrap` su un pulsante, scavalcato da un `white-space: normal !important`
scritto mesi prima per un altro scopo. L'icona restava in linea, ma non grazie al `nowrap`: il
pulsante ci stava e basta. Funzionava per caso.

**Due regole in conflitto dove una vince sempre sono peggio di una regola sbagliata**: chi legge crede
che il comportamento dipenda da quella che ha davanti, la cambia, e non succede niente.

Come si scopre: leggere il **valore calcolato**, non fidarsi del fatto che l'effetto desiderato ci
sia.

```js
getComputedStyle(el).whiteSpace   // "normal" con un nowrap scritto tre righe sopra
```

E il rimedio spesso non è vincere il conflitto ma **evitarlo cambiando proprietà**: `display:
inline-flex` tiene testo e icona affiancati per costruzione, senza toccare `white-space`, quindi la
regola preesistente continua a fare il suo mestiere e non c'è nessuna gara da vincere.

### Quando non è una gara di specificità: `min-height` batte `height`

Un campo con `height: 52px` dichiarata da un selettore più pesante di quello del tema può avere
altezza calcolata **60px**. Si perde tempo a contare la specificità — 0,2,0 contro 0,1,2, vinceva la
nostra — prima di guardare la proprietà giusta: il tema dichiarava `min-height: 60px`.

`min-height` alza sempre l'altezza finale qualunque sia `height` e qualunque sia il peso del
selettore. **Non è cascata, è modello di box**: la cascata decide quale `height` vince, poi
`min-height` la corregge comunque.

**Come si distingue dai due casi qui sopra:** la regola risulta **applicata** nel pannello degli
stili — non barrata — e l'altezza calcolata è diversa da quella scritta, sempre verso il più grande.
Se fosse specificità, la regola apparirebbe barrata.

Si rimedia quasi sempre **non combattendo**: la misura del tema è quella che hanno tutti gli altri
campi del sito, quindi ci si allinea e si porta alla stessa misura l'elemento affiancato. In un caso
reale il pulsante era rimasto a 52 accanto a un campo da 60: 8px di disallineamento visibile solo a
occhio attento. Se invece serve davvero imporre la propria, si dichiara `min-height`, non `height`.

**Corollario che vale da solo:** quando si cercano le regole che colpiscono un elemento scorrendo
`document.styleSheets`, il ciclo sulle `cssRules` **non entra nelle `@media`**, che sono
`CSSMediaRule` con le proprie `cssRules` annidate. Un controllo che non ricorre dice «nessuna regola»
mentre il colpevole è lì dentro.
