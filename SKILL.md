---
name: wordpress-elementor-ssh
description: Modificare un sito WordPress costruito con Elementor lavorando dal server, con SSH e WP-CLI oppure via FTP per i soli file, invece di pilotare l'editor visuale dal browser. Usala ogni volta che si parla di intervenire su un sito WordPress o Elementor di un cliente, di correggere testi, CTA, meta title, dati strutturati, responsive, mu-plugin, moduli di contatto o footer, di ripulire un template kit, di lavorare in produzione via SSH o FTP, e ogni volta che compaiono termini come wp-cli, _elementor_data, widget, sezione, template Elementor, hello-elementor, JKit o LiteSpeed. Usala anche quando l'utente non nomina Elementor ma descrive un sito WordPress da modificare, perché se il sito usa Elementor il metodo cambia completamente. Fai partire questa skill PRIMA di aprire il browser o toccare qualsiasi cosa.
---

# WordPress + Elementor dal server

Metodo per modificare un sito WordPress con Elementor in modo ripetibile, verificabile e
reversibile. Nato da un sito reale portato in produzione, con gli errori già pagati.

## La regola che viene prima di tutte

**Non si modifica il sito pilotando l'editor di Elementor dal browser.** Niente clic simulati,
niente trascinamenti, niente compilazione di campi nell'interfaccia.

Non è una preferenza di stile. L'editor è pensato per una persona che guarda lo schermo: ogni azione
dipende da coordinate, animazioni, pannelli che si aprono e salvataggi automatici. Una sequenza di
clic non è ripetibile, non è verificabile, non si può provare a vuoto e non si può annullare in
blocco. Sbagliare un trascinamento su una pagina complessa sposta un blocco in un punto che nessuno
nota subito.

Il contenuto di Elementor è **un documento JSON dentro il database**: si legge, si trasforma con uno
script e si riscrive.

**Il browser serve, ma per un'altra cosa: verificare.** Misurare il risultato sulla pagina pubblica,
in sola lettura. Mai per produrre la modifica.

## Prima di cominciare: cosa chiedere

Non iniziare a lavorare finché non hai risposte. Se manca qualcosa, chiedila esplicitamente e
aspetta: una modifica fatta con le informazioni sbagliate costa più di mezz'ora di attesa.

Se hai a disposizione uno strumento per fare domande a scelta multipla, usalo: è più rapido per
l'utente che scrivere tutto a mano.

**Accesso**

1. **SSH**: host, utente, porta, e come autenticarsi. Se c'è già una voce in `~/.ssh/config`, basta
   il nome. Se serve una chiave nuova, dillo: la genera l'utente, non tu.
2. Se SSH non c'è: **FTP/SFTP** (host, utente, cartella radice del sito) **e**, separatamente, un
   accesso al database (phpMyAdmin, Adminer). Vedi i limiti più sotto.
3. **Percorso della radice del sito** sul server, se non è `~/public_html`.

**Contesto**

4. **A cosa serve il sito e chi è il cliente.** Cambia il tono dei testi e cosa è lecito inventare.
   Su contenuti sanitari, legali o finanziari: non si modifica il senso, non si aggiungono promesse
   di risultato, non si inventano orari, prezzi o recapiti.
5. **Il sito è già pubblico?** Se è ancora in `noindex` o in anteprima, si può cambiare uno slug
   senza rimandi e sperimentare. Se è online e indicizzato, ogni indirizzo che cambia va rimandato.
   E se il lavoro comprende lo sblocco dell'indicizzazione, non è un interruttore: accende insieme
   dati di esempio, segnaposto e voci di sitemap che nessuno ha mai controllato
   (`references/seo-e-indicizzazione.md`).
6. **C'è un ambiente di prova**, o si lavora direttamente in produzione? Se è produzione, dillo a
   voce alta una volta e comportati di conseguenza.
7. **Chi altro mette mano al sito** e con quali strumenti. Se qualcuno usa l'editor visuale, le tue
   modifiche possono essere sovrascritte al primo salvataggio.

**Vincoli**

8. **Cosa NON si deve toccare.** Quasi sempre c'è qualcosa: un plugin fragile, una pagina in
   revisione, un modulo collegato a un CRM.
9. **Dove finiscono le password.** Regola: non le chiedi, non le scrivi, non le fai passare da te.
   I segreti stanno in `wp-config.php` come costanti, inseriti dall'utente. Se una credenziale deve
   arrivare a qualcuno, si usa un gestore di password o un collegamento a lettura singola, mai il
   corpo di una mail o di una chat.

Se il sito è di un cliente e c'è un'agenzia in mezzo, chiedi anche **chi approva** le modifiche
visibili: cambiare le parole scritte da qualcun altro è una decisione loro, non tua.

## Se c'è solo FTP

Con il solo FTP puoi lavorare sui **file**: mu-plugin, CSS, JavaScript, immagini. È abbastanza per
comportamenti, stili e correzioni fatte con i filtri di WordPress.

**Non puoi toccare il contenuto delle pagine in sicurezza.** `_elementor_data` sta nel database, e
senza un modo di eseguire query non hai né la prova a vuoto né il controllo finale.

In quel caso hai due strade oneste, e le proponi all'utente invece di arrangiarti:

- chiedere un accesso SSH, anche temporaneo;
- chiedere phpMyAdmin o Adminer, e lavorare con query preparate a mano che l'utente incolla.

**Non caricare via FTP script PHP che eseguono comandi arbitrari** lasciandoli raggiungibili dal web.
È una porta aperta, e il fatto che sia temporanea non la rende sicura: bastano i secondi in cui un
crawler la trova.

## Connessione e ricognizione

Verifica la connessione e leggi com'è fatto il sito **prima** di proporre qualsiasi cosa. Dettagli e
diagnostica in `references/connessione.md`.

```bash
ssh NOME 'cd ~/public_html && pwd && wp --version'
```

Poi la ricognizione, che risponde in un colpo solo alle domande che servono sempre (prefisso delle
tabelle, versioni, template attivi, pagine, menu, plugin di cache, stato di indicizzazione):

```bash
scp scripts/ricognizione.php NOME:~/ && ssh NOME 'cd ~/public_html && wp eval-file ~/ricognizione.php'
```

Leggi l'output prima di scrivere una riga. Il prefisso delle tabelle spesso **non** è `wp_`.

## Il ciclo di lavoro

Ogni modifica al database segue questi passi. Saltarne uno è il modo in cui si rompono i siti.

1. **Leggi** la struttura e trova gli identificativi degli elementi da toccare.
2. **Scrivi uno script PHP** in una cartella temporanea locale, non sul server.
3. `scp` sul server, poi **`php -l`**: un errore di sintassi in uno script che gira dentro WordPress
   può lasciare il sito bianco.
4. **Prova a vuoto**: una variabile d'ambiente blocca la scrittura e lo script stampa cosa farebbe.
5. **Leggi l'output della prova.** Applica solo se corrisponde a quello che ti aspettavi.
6. **Rileggi dal database e riconta.** Non fidarti del fatto che il comando non abbia dato errore.
7. **Svuota la cache** del sito.
8. **Verifica sulla pagina pubblica** con il browser, in sola lettura.

```bash
scp patch.php NOME:~/ && ssh NOME 'php -l ~/patch.php && cd ~/public_html && SG_PROVA=1 wp eval-file ~/patch.php'
# letta la prova, e solo allora:
ssh NOME 'cd ~/public_html && wp eval-file ~/patch.php && wp litespeed-purge all'
```

**Poche connessioni, raggruppate.** Questo ciclo apre tre o quattro connessioni ed è giusto così. Una
connessione per ogni controllo no: circa quindici sessioni SSH in un'ora, insieme a qualche richiesta
a `xmlrpc.php` e `wp-login.php`, hanno fatto mettere in blacklist l'IP di casa del cliente su **tutti**
i server del suo hosting, bloccando anche gli altri suoi siti. Le domande di ricognizione si mettono in
un'unica sessione (un heredoc con tutti i comandi), e i file del core non si sondano mai dalla
connessione del cliente. Sintomi e sblocco in `references/connessione.md`.

Prima di riscrivere il contenuto di più pagine, fai una copia:

```bash
ssh NOME 'cd ~/public_html && mkdir -p ~/backup-$(date +%F) && wp db query "SELECT meta_id, post_id, meta_value FROM PREFISSO_postmeta WHERE meta_key=\"_elementor_data\"" --skip-column-names > ~/backup-$(date +%F)/elementor.tsv'
```

`scripts/helper.php` contiene le funzioni che ogni script di modifica riscriverebbe da zero: giro
ricorsivo sull'albero, controllo di sicurezza sulla codifica, invalidazione della cache, conteggio
finale. Includilo invece di reinventarle.

## Le protezioni che non si saltano

Sono tre righe che trasformano uno script pericoloso in uno script noioso. Dettagli e varianti in
`references/modifiche-sicure.md`.

**Confronto esatto prima di sostituire.** Se il valore attuale non è quello che ti aspetti, non
toccare niente e stampa cosa hai trovato. Una sostituzione alla cieca su un testo già cambiato da
qualcun altro è un danno silenzioso.

Questa protezione può essere **finta** senza che niente lo dica. `wp eval-file` esegue lo script dentro
una funzione: una variabile in cima al file non è globale, e un contatore di errori incrementato da una
funzione con `global $errori` resta a zero per il corpo dello script, che scrive lo stesso. Lo stato
condiviso va in `$GLOBALS`, o si usano closure. E nella prova a vuoto il blocco va **visto scattare**
almeno una volta, con un valore atteso volutamente sbagliato: un controllo mai visto scattare non sai
se funziona. Il caso per esteso in `references/modifiche-sicure.md`.

**Controllo di andata e ritorno del JSON.** Riscrivere l'intero documento di una pagina è
l'operazione più rischiosa. Se decodifica e ricodifica del valore originale non restituiscono il byte
identico, salta la pagina e segnalala:

```php
if (wp_json_encode(json_decode($grezzo, true)) !== $grezzo) { /* salta e segnala */ }
```

**Conteggio finale che rilegge dal database.** Alla fine dello script riconta le occorrenze del
problema che stavi correggendo. Zero è una verifica; «il comando è andato a buon fine» non lo è.

**Escludi le revisioni** dalle query di modifica (`post_status NOT IN ('auto-draft','trash','inherit')`)
e ricordati di escluderle anche dai conteggi. Una revisione con il testo vecchio non viene mai
mostrata: non è un residuo da correggere.

## La cache si butta, non si corregge

Dopo **ogni** modifica ai dati di una pagina:

```php
delete_post_meta($id, '_elementor_element_cache');
delete_post_meta($id, '_elementor_css');
```

E per i template, che vanno invalidati comunque perché intestazione e piè di pagina compaiono ovunque:

```php
foreach (get_posts(['post_type' => 'elementor_library', 'numberposts' => -1, 'fields' => 'ids']) as $t) {
    delete_post_meta($t, '_elementor_element_cache');
    delete_post_meta($t, '_elementor_css');
}
```

Poi la cache del sito (`wp litespeed-purge all`, o l'equivalente del plugin installato).

E non è finita lì: con «CSS Combine» di LiteSpeed il pacchetto rigenerato ha **lo stesso indirizzo** e
una scadenza di un anno, quindi il browser che aveva già aperto la pagina continua a usare il CSS
vecchio. Prima di concludere che una modifica di stile non ha funzionato, scarica il pacchetto con
`fetch(url, {cache: 'reload'})`: la procedura è in `references/verifica.md`.

**`_elementor_element_cache` impedisce l'esecuzione dei filtri `elementor/widget/render_content`.**
Se aggiungi un filtro sul contenuto reso e non succede niente, non è il filtro a essere sbagliato: è
la cache che restituisce l'HTML vecchio senza mai chiamarlo.

## Le trappole di Elementor

Le più costose, in breve. Spiegazione e rimedi in `references/elementor.md`, da leggere quando ne
incontri una.

| Trappola | Sintomo |
|---|---|
| Il tablet eredita dal **desktop**, non dal mobile | difetto solo fra 768 e 1024 px |
| `__globals__` vince sul valore locale | cambi un colore e non succede niente |
| I *dynamic tag* compongono il testo al momento | cerchi la frase nel database e non c'è |
| Widget che uniscono più campi | «di» e «Dr.» sono due campi: nessuna espressione regolare li vede insieme |
| «Predefinito» è quello di Elementor, non di WordPress | la data esce in formato americano |
| `elementor/frontend/the_content` scatta **una volta per documento** | la sezione aggiunta compare tre volte |
| `min-width: auto` sulle colonne flessibili | un'immagine larga sfonda la colonna |
| `post_content` delle pagine Elementor non viene mostrato | correggi testo che nessuno legge |
| Il campo «url» dei form diventa `type="url"` | `www.sito.it` rifiutato, candidature perse |

E quelle di impaginazione, che si vedono solo a certe larghezze — per esteso in
`references/responsive.md`:

| Trappola | Sintomo |
|---|---|
| «Tablet» non è una fascia: lo stesso apparecchio ne attraversa tre ruotando | la regola c'è, la persona dal tablet non la vede |
| Elementor pareggia le **colonne**, non le schede dentro | riquadri affiancati alti diversi, ma solo a certe larghezze |
| `align-items: stretch` pareggia il **margine**, non il contenuto | lo scarto è lo stesso numero su tutte le coppie |
| Un margine giusto in colonna è sbagliato in riga | il primo elemento parte più in alto degli altri |
| Un rientro su **un lato solo** in una colonna centrata | tutto il blocco sta mezzo rientro fuori asse |
| Una misura fissa che sostituisce una percentuale | corretta dove guardavi, sovrapposta altrove |
| Il breakpoint mobile arriva **fino a 767** | valore scelto a 375, assurdo a 767 |

## Comportamenti e stili: mu-plugin, non file del tema

Filtri PHP, CSS e JavaScript aggiuntivi vanno in `wp-content/mu-plugins/`. Sono sempre attivi, non si
disattivano per errore, sopravvivono agli aggiornamenti e non richiedono un tema figlio.

Per scavalcare il CSS che Elementor genera **non usare `!important`**: renderebbe la regola
impossibile da scavalcare a chi metterà mano al widget dopo.

Ma sappi che **anteporre `body` quasi mai basta**: una classe pesa più di un elemento, e i selettori
di Elementor arrivano a cinque classi. `body` più due classi vale 0,2,1 e perde contro 0,5,0. La
tecnica che funziona è **ripetere una classe già presente** (`.elementor-element.elementor-element`):
non cambia cosa viene selezionato, alza solo il peso.

**Ma solo finché dall'altra parte ci sono solo classi.** Se nel selettore avversario c'è un ID,
ripetere la classe non può funzionare — un ID pesa più di qualunque numero di classi — e si può
insistere all'infinito senza spostare di una virgola il valore calcolato. Il segnale è netto:
aggiungere classi non produce **nessun** cambiamento. Lì serve un ID anche dalla propria parte. Il
conto per esteso, e il perché il troncamento dei selettori nasconde proprio l'ID, in
`references/responsive.md`.

Se scrivi una regola e «non funziona», prima di riscriverla leggi il **valore calcolato**: quasi
sempre la regola c'è ed è un'altra a vincere. E se il valore calcolato è diverso da quello scritto ma
la regola **non** risulta barrata, non è una gara di specificità: guarda `min-height`, che alza
l'altezza qualunque cosa dica `height`.

Commenta ogni funzione e ogni blocco CSS spiegando **perché** esiste e cosa succederebbe senza. Il
cosa si legge dal codice; il perché lo sa solo chi l'ha scritto.

## Verificare con il browser, in sola lettura

Preferisci **misure numeriche** agli screenshot: sono più affidabili e non dipendono dalla
risoluzione della finestra. `scripts/verifica-layout.js` prova tutte le pagine a tutte le larghezze e
riporta solo quello che va guardato: scorrimento, elementi che sforano, riquadri affiancati alti
diversi, blocchi centrati fuori asse, etichette a capo, icone fuori centro.

Ogni volta che una misura sembrava assurda, il colpevole era lo strumento e non il sito.
Riconoscerle al volo fa risparmiare mezz'ora ciascuna: sono in `references/verifica.md`.

- Il pannello del browser largo **zero** produce overflow immaginari. Ogni funzione di misura deve
  cominciare controllando `clientWidth`, e dire «misura non valida» invece di restituire numeri.
- **La barra di scorrimento non conta per le media query, ma conta per `clientWidth`.** In un riquadro
  di prova largo 782 il documento dichiara 767: credi di stare in una fascia e il CSS ne applica
  un'altra. È il difetto di metodo più costoso di tutti, perché non produce numeri sbagliati ma numeri
  giusti riferiti a una larghezza diversa. Si toglie nascondendo la barra dentro il riquadro.
- Uno scarto di **15 px** fra `scrollWidth` e `clientWidth`, sulla pagina intera, è la stessa barra.
  La prova è provare a scorrere davvero: `window.scrollTo(9999,0); window.scrollX === 0`.
- **Chrome senza interfaccia su Windows non scende sotto ~500 px**: chiedi 390 e la pagina impagina a
  511, la foto è solo ritagliata. Stessa famiglia della barra: numeri giusti, larghezza sbagliata.
  Prima di ogni misura legata a una fascia, fai stampare `clientWidth` e il `matchMedia` della fascia.
- **Quando il tempo non scorre, qualcosa che funziona sembra rotto.** Le transizioni CSS non avanzano
  se il pannello non è visibile; con `--virtual-time-budget` gli eventi accodati (la `close` di un
  `<dialog>`) non arrivano mai. Se allungare l'attesa non cambia il risultato, è lo strumento: togli
  l'animazione, o pilota Chrome a tempo reale via CDP.
- **Misura l'inchiostro, non la scatola.** Un widget largo quanto la colonna con dentro un'immagine
  stretta ha la scatola centrata e il contenuto no.

E una che riguarda il database, non il browser: dopo un `$wpdb->update` diretto, **`get_post_meta`
nella stessa richiesta restituisce ancora il valore vecchio**. Nei controlli finali rileggi con
`$wpdb`, non con le funzioni che passano dalla cache degli oggetti.

## Quando la richiesta, applicata alla lettera, sbaglia

Capita spesso con le richieste dei clienti. Esempi reali:

- «Ogni elenco puntato con la maiuscola» è giusto per gli elenchi di voci autonome, ed è un errore di
  grammatica per gli elenchi che continuano una frase con il punto e virgola. E un indirizzo email non
  si maiuscola mai.
- «Togli i trattini» non vale per `fisico-strumentali`.
- «Sostituisci X con Y ovunque» lascia frasi sgrammaticate quando X regge un plurale o un articolo.

Non applicare alla lettera e non decidere in silenzio. **Fai la cosa giusta, e spiega nel resoconto
cosa hai fatto diversamente e perché**, con i numeri: «su 120 voci ne ho corrette 5, le altre 115
sono minuscole correttamente». Chi legge deve poter dire «no, fai come avevo chiesto».

## Un conteggio non è una diagnosi

I controlli automatici trovano candidati, non colpevoli. Prima di dire che restano nove problemi,
leggili: potrebbero essere otto revisioni invisibili e una domanda delle FAQ che nega proprio la cosa
che stai cercando.

E quando correggi un difetto misurato, **rimisura subito**. Se il numero non cambia, quello che hai
corretto era un sintomo: continua a scendere. Un solo giro di misura in meno e dichiari risolto
qualcosa che non lo è.

## Come questa skill impara

Questa skill è nata da un sito e cresce sugli altri. **Quando su un progetto qualsiasi incontri una
sorpresa costosa — mezz'ora persa dietro una cosa che nessuno avrebbe indovinato — scrivila qui
dentro prima di chiudere il lavoro**, altrimenti la ripaga qualcun altro fra sei mesi, e quel qualcuno
sei tu.

```bash
~/.claude/skills/wordpress-elementor-ssh/scripts/lezione.sh "Titolo breve" "Nome progetto" <<'FINE'
Cosa credevo, cosa succedeva davvero (con il numero), come si riconosce, come si rimedia.
FINE
```

Lo script trova da solo la cartella della skill, quindi funziona da qualunque progetto. Se non sai
dove sia installata: `ls ~/.claude/skills/wordpress-elementor-ssh` (personale, vale per tutti i
progetti) oppure `.claude/skills/` dentro il progetto.

**Il filtro è uno solo: succederebbe anche su un altro sito?** Un comportamento di Elementor, di un
kit, di un plugin di cache, uno strumento che restituisce numeri plausibili e falsi: sì. Che qui il
prefisso delle tabelle sia `wpbw_` o che la sezione dei contatti abbia un certo id: no, quello va
nelle note del progetto, insieme al sito. La skill è quello che ti porti al lavoro successivo.

E quando una lezione **contraddice** una regola che questa skill dà per buona, correggi la regola
invece di aggiungere un'eccezione: una skill che accumula eccezioni smette di essere un metodo. È già
successo tre volte — l'idea che bastasse `body` per vincere sulla specificità, uno script di verifica
che misurava 15px in meno di quanto credeva, e la tecnica di ripetere la classe, che era data per
sempre valida e non funziona contro un ID.

`references/diario.md` spiega il resto: cosa merita una voce, come si scrive perché serva davvero, e
quando riordinarla nei file di riferimento.

## File di riferimento

**Leggi `references/diario.md` all'inizio di ogni lavoro nuovo:** è la parte più fresca, contiene le
lezioni raccolte sugli ultimi siti e non ancora riordinate. Costa dieci secondi.

- `references/connessione.md` — SSH, FTP, WP-CLI, diagnostica dei problemi di accesso
- `references/elementor.md` — modello dei dati, le trappole per esteso, i punti di aggancio
- `references/responsive.md` — fasce, altezze, specificità: i difetti che si vedono solo a certe larghezze
- `references/modifiche-sicure.md` — anatomia di uno script di modifica, con esempi completi
- `references/verifica.md` — come misurare, cosa misurare, le misure che mentono
- `references/consenso-e-integrazioni.md` — banner cookie, tracciamento, API di iscrizione, CF7 e ChimpMatic: la parte che si rompe in silenzio
- `references/seo-e-indicizzazione.md` — sblocco dell'indicizzazione, sitemap, dati strutturati di Rank Math, vecchi indirizzi
- `references/diario.md` — lezioni recenti, e come aggiungerne

- `scripts/ricognizione.php` — fotografia del sito, da eseguire per prima cosa
- `scripts/helper.php` — funzioni comuni per gli script di modifica
- `scripts/verifica-layout.js` — prova di impaginazione su tutte le pagine e tutte le larghezze
- `scripts/lezione.sh` — aggiunge una lezione al diario, da qualunque progetto
