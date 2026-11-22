# Anatomia di uno script di modifica

Uno script che tocca il database di un sito in produzione ha due lettori: il server, che lo esegue, e
la persona che deve decidere se applicarlo. Il secondo conta più del primo.

## Lo scheletro

```php
<?php
/**
 * Cosa fa, e soprattutto PERCHE'.
 *
 * Se una scelta non era ovvia, qui va spiegata: chi legge il codice fra sei mesi
 * vede il "cosa", e il "perche'" lo sa solo chi l'ha scritto.
 */

require_once __DIR__ . '/helper.php';   // giro sull'albero, controlli, cache

$secco = getenv('SG_PROVA') === '1';    // prova a vuoto
global $wpdb;

/* 1. TABELLA DELLE MODIFICHE, non logica sparsa.
      Ogni voce dice: dove, cosa mi aspetto di trovare, cosa ci metto. */
$modifiche = array(
    123 => array(
        'idwidget' => array('editor',
            '<p>testo esatto che mi aspetto</p>',
            '<p>testo nuovo</p>'),
    ),
);

$fatti = array();
$mancanti = array();

foreach ($modifiche as $pid => $voci) {
    $grezzo = sg_leggi_dati($pid);          // legge con $wpdb, non con get_post_meta
    $dati   = json_decode($grezzo, true);

    if (!is_array($dati)) { $mancanti[] = "$pid: illeggibile"; continue; }

    /* 2. CONTROLLO DI ANDATA E RITORNO: se la ricodifica non e' identica,
          riscrivere il documento cambierebbe cose che non intendo toccare. */
    if (wp_json_encode($dati) !== $grezzo) { $mancanti[] = "$pid: codifica diversa"; continue; }

    sg_gira($dati, function (&$nodo) use ($voci, &$fatti, &$mancanti) {
        $id = $nodo['id'] ?? '';
        if (!isset($voci[$id])) { return; }
        list($campo, $atteso, $nuovo) = $voci[$id];

        /* 3. CONFRONTO ESATTO: se il testo e' cambiato da quando l'ho letto,
              qualcuno ci ha messo mano. Non tocco e segnalo. */
        if (($nodo['settings'][$campo] ?? null) !== $atteso) {
            $mancanti[] = "$id: valore inatteso";
            return;
        }
        $nodo['settings'][$campo] = $nuovo;
        $fatti[] = "$id.$campo";
    });

    if (!$fatti) { continue; }
    if ($secco)  { continue; }

    sg_scrivi_dati($pid, $dati);            // scrive e invalida la cache
}

/* 4. RESOCONTO LEGGIBILE: chi decide se applicare legge questo. */
echo "fatti: " . count($fatti) . "\n";
foreach ($fatti as $f) { echo "  $f\n"; }
if ($mancanti) {
    echo "MANCANTI: " . count($mancanti) . "\n";
    foreach ($mancanti as $m) { echo "  $m\n"; }
}

if ($secco) { echo "\n(prova a vuoto: non ho scritto niente)\n"; return; }

/* 5. CONTEGGIO FINALE riletto dal database. Zero e' una verifica;
      "il comando non ha dato errore" non lo e'. */
echo "\nresidui: " . sg_conta_occorrenze('la stringa che non deve piu esistere') . "\n";
```

## In `wp eval-file` le variabili in cima al file non sono globali

Lo scheletro qui sopra passa lo stato con le closure (`use (&$fatti, &$mancanti)`), e non per caso.
Uno script dato a `wp eval-file` **non** si comporta come un file PHP normale: WP-CLI lo esegue dentro
una funzione, quindi una variabile dichiarata in cima è **locale**. Una funzione con nome che fa
`global $errori` legge e scrive un'altra variabile, quella globale, che il corpo dello script non vede
mai.

Caso reale, con questa forma:

```php
$errori = 0;
function err($m){ global $errori; $errori++; echo "ERRORE: $m\n"; }
...
if (!$errori) { /* prepara le modifiche */ }
if (!$prova && !$errori) { /* scrive */ }
```

Un confronto esatto era fallito (il titolo conteneva un `<br>` inatteso), e la prova a vuoto ha
stampato «ERRORE» seguito da «Modifiche preparate» e «Nessun errore». I controlli leggevano sempre 0.
Senza la prova a vuoto, la scrittura vera avrebbe ignorato il blocco e scritto lo stesso: la
protezione «confronto esatto prima di sostituire» era finta.

**Come si riconosce:** nell'output compaiono insieme una riga di errore e il riepilogo «tutto ok».

**Come si rimedia:** lo stato condiviso fra corpo e funzioni va in `$GLOBALS`, e si legge sempre da
una funzione:

```php
$GLOBALS['errori'] = 0;
function err($m){ $GLOBALS['errori']++; echo "ERRORE: $m\n"; }
function ok(){ return $GLOBALS['errori'] === 0; }
if (!$prova && ok()) { ... }
```

Vale per qualunque stato condiviso — contatori, flag, configurazione — oppure si evitano le funzioni
con nome e si usano closure come nello scheletro. `global $wpdb;` in cima invece funziona, perché
importa una globale vera.

**Corollario: la prova a vuoto deve anche far scattare il blocco almeno una volta.** Un controllo che
non hai mai visto scattare non sai se funziona. Basta un valore atteso volutamente sbagliato in una
riga della tabella: se la prova non lo segnala, il blocco è finto.

## Perché una tabella e non logica sparsa

Una tabella di modifiche si legge in dieci secondi e si controlla riga per riga. La stessa cosa
scritta come sequenza di `if` va letta tutta per capire cosa cambia. Nella prova a vuoto la differenza
è enorme: la tabella produce un elenco, la logica sparsa produce un racconto.

## Se `$mancanti` non è vuoto, non scrivere

Quando lo script si aspetta dodici modifiche e ne trova undici, la dodicesima non è un dettaglio: è il
segnale che il sito non è come credevi. Meglio fermarsi e guardare.

Per le modifiche indipendenti fra loro va bene applicare quelle riuscite e segnalare le altre. Per una
ristrutturazione (togliere una colonna, rifare una sezione) fermati: metà ristrutturazione è peggio di
zero.

## Sostituzioni di testo: perché le regole generali sbagliano

Esempio reale: correggere «di Dr.» in «del Dr.» su tutto un sito. Sembra una sostituzione, sono cinque
regole con un ordine obbligato:

```php
$regole = array(
    'tra' => 'tra il',   // "tra Dr." -> "tra il Dr."
    'da'  => 'dal',
    'di'  => 'del',
    'con' => 'con il',
    'a'   => 'al',
);
```

`da` contiene `a`: senza `\b` la regola di `a` mangerebbe la `a` di `da`. E la maiuscola iniziale va
conservata quando la preposizione apre la frase.

Il controllo che chiude il lavoro non è «ho sostituito 15 occorrenze», è **rileggere e ricontare cosa
resta davanti a «Dr.»**, elencando le preposizioni trovate. Se ne compare una che non avevi previsto,
la vedi.

## I residui che il censimento non può trovare

Quando togli da un sito una cosa **strutturale** — una seconda sede, un servizio, una persona, un
listino — la ricerca per parola chiave trova le occorrenze del nome. Non trova le frasi che quella
cosa la **presuppongono**.

Caso reale: chiusa una delle due sedi, cercati e corretti tutti i «Viagrande», i «due studi», le
«sedi». Nel piè di pagina, quindi su ogni pagina del sito, restava:

> «…contatta lo studio e richiedi un appuntamento **nella sede per te più comoda**.»

Non contiene nessuna delle parole cercate. Con un solo studio non è falsa, è **priva di senso**: non
c'è nessuna scelta da fare. Il residuo era nel significato.

**Come si cercano.** Dopo la sostituzione, fai un secondo giro con le parole che descrivono la
*scelta*, il *confronto* o la *pluralità*, non la cosa rimossa:

```
"per te più comoda"   "la più vicina"   "entrambe"   "quale"   "scegli"
"tutte e due"   "in entrambi"   "l'altra"   "rispettivamente"
```

E cercale in tutti i posti, non solo nel contenuto delle pagine: `_elementor_data`, `post_content`, le
meta del plugin SEO, le opzioni, e i **template** (`elementor_library`), che è dove stanno intestazione
e piè di pagina e dove un residuo si moltiplica per il numero di pagine.

Le **revisioni** invece si escludono: non vengono mai mostrate, e correggerle è lavoro invisibile.

Un secondo modo, più affidabile ma più lento, è **rileggere le pagine servite** come le legge una
persona. Una frase senza senso salta all'occhio anche quando nessuna parola chiave la intercetta.

## Terminazioni di riga miste

Un file con **CRLF e LF mescolati** fa fallire le sostituzioni su blocchi di più righe in modo
incoerente: alcune combaciano, altre no, e non c'è nessun errore. Prima di sostituire blocchi
normalizza in memoria e riscrivi normalizzato:

```php
$s = str_replace(array("\r\n", "\r"), "\n", $s);
```

### Il blocco è visibilmente lì e la ricerca esatta trova zero

Il `post_content` di WordPress può contenere CRLF. Se lo si estrae con
`wp post get --field=post_content`, lo si salva in un file e lo si rilegge con Python in **modalità
testo**, le newline universali tolgono i CR — sia in lettura sia in scrittura. Un blocco di 578 byte
ne diventa 576, e la sostituzione non combacia più.

Il sintomo è preciso e fuorviante: lo script stampa «occorrenze del blocco da sostituire: 0»
**mentre il testo è visibilmente lì**, e un controllo di ripiego con espressione regolare lo trova.
Si è tentati di dare la colpa alla codifica degli accenti, che è la pista sbagliata.

La diagnosi si fa in trenta secondi facendo stampare allo script **sul server**
`substr_count($blocco, "\r")` e la lunghezza: se il numero di CR non è zero e la copia locale è più
corta di tanti byte quanti sono i CR, è questo.

Si rimedia leggendo ed estraendo in **binario** (`open(..., 'rb')`) e scrivendo il blocco nuovo con
gli stessi ritorni a capo del resto della pagina — `testo.replace(chr(10), chr(13)+chr(10))` — così
non si mescolano due convenzioni dentro lo stesso campo.

## Caricare un mu-plugin senza spegnere il sito

Un mu-plugin che non compila **spegne il sito nell'istante in cui tocca terra**: non c'è
un'attivazione da fare, WordPress li carica tutti sempre. Un errore di sintassi lì dentro non è «il
plugin non funziona», è errore fatale su ogni pagina, front-end e `wp-admin` compresi.

La sequenza sbagliata è questa, e sembra giusta:

```bash
scp file.php host:~/public_html/wp-content/mu-plugins/ && ssh host 'php -l .../file.php && ...'
```

Il controllo di sintassi c'è, ma viene **dopo** il caricamento: quando scopre l'errore il file è già
in produzione da qualche secondo e il sito è già giù. Il `&&` protegge i comandi successivi, non il
sito.

L'ordine giusto è caricare altrove, controllare, e solo allora spostare:

```bash
scp file.php host:~/prova-mu.php
ssh host 'php -l ~/prova-mu.php && mv ~/prova-mu.php ~/public_html/wp-content/mu-plugins/file.php'
```

Se il sito è già giù per questo motivo, la prima mossa **non** è correggere: è `rm` del file,
verificare che risponda 200, e correggere con calma.

Lo stesso vale per `functions.php` di un tema attivo. Per un tema intero: caricalo fuori dalla
cartella dei temi, passa `php -l` su **tutti** i suoi file, e solo allora spostalo e attivalo.

## Trappole del linguaggio che costano tempo

**Le virgolette angolari dentro le stringhe interpolate.** In PHP i byte alti sono caratteri validi per
i nomi di variabile, quindi `"«$nome»"` viene letto come la variabile `$nome»`, che non esiste. Usa le
graffe: `"«{$nome}»"`.

**`grep -c` conta le righe, non le occorrenze.** Se un documento JSON sta su una riga sola, `grep -c`
risponde `1` qualunque sia il numero di occorrenze. Usa `grep -o ... | wc -l`, o meglio `substr_count`
in PHP.

**Il confronto di byte su UTF-8.** Cercare `[—–]` con uno strumento orientato ai byte può far
combaciare caratteri diversi che condividono il primo byte. Se il conteggio sembra assurdo, cambia
strumento prima di credergli.

**L'apostrofo italiano chiude una stringa a virgolette singole.** In PHP `'...'` finisce al primo
apostrofo: del CSS incollato dentro `$css = '...'` con un commento come `a meta' larghezza` chiude
la stringa su «meta» e fa esplodere il parser su «larghezza». Non è un difetto teorico — in italiano
capita a ogni commento scritto senza accento. Per blocchi di CSS, HTML o SQL usa sempre un nowdoc,
dove apostrofi e virgolette non danno nessun fastidio:

```php
$css = <<<'CSS'
...qui apostrofi e virgolette non danno nessun fastidio...
CSS;
```

**Due option con oggetti dentro non sono mai identiche con `!==`.** Dopo `update_option()`, rileggere
il grezzo da `$wpdb`, farne `maybe_unserialize()` e confrontarlo con `!==` con quello scritto sembra
la verifica più severa. Su tre option di ChimpMatic su tre ha detto «non corrisponde», con una
scrittura perfetta: la chiave `listatags` contiene oggetti `stdClass`, e `!==` fra due oggetti
deserializzati separatamente confronta l'**identità**, non il contenuto. Lo stesso confronto fra
`get_option()` e il grezzo dava «diverso» anche su option mai toccate. Si riconosce perché la
differenza sta in una chiave che non hai cambiato, e compare anche senza scrivere niente. Si confronta
la forma serializzata, che è anche la più vicina a cosa sta davvero nel database:

```php
if ($grezzo !== maybe_serialize($atteso)) { /* davvero diverso */ }
```

## Le funzioni di salvataggio dei plugin portano con sé gli agganci di tutti

Il metodo «ufficiale» di un plugin per salvare un oggetto — `WPCF7_ContactForm::save()` è il caso
misurato — fa scattare i propri hook, e su quegli hook gli **altri** plugin salvano le loro
impostazioni leggendo il form di amministrazione. Da riga di comando quel form non c'è: chi legge
`$_POST` trova il vuoto e lo salva. Su un sito erano 6 agganci, di quattro plugin diversi.

Per **modificare**, si scrive il solo meta o la sola option che si intende cambiare e si rilegge con
`$wpdb`. Il metodo di alto livello si usa solo quando serve davvero — per **creare**, se genera dati
che a mano non si ricostruiscono. Prima di chiamarlo da uno script, si contano gli agganci
(`grep -rn "nome_dell_hook" wp-content/plugins`). Il caso completo, con Contact Form 7, è in
`consenso-e-integrazioni.md`.

## Modifiche ai file: patch mirate, non riscritture

Per i mu-plugin conviene una patch che cerca un blocco esatto e lo sostituisce, con un controllo che
il blocco esista **una volta sola**:

```php
if (substr_count($s, $vecchio) !== 1) {
    echo 'blocco trovato ' . substr_count($s, $vecchio) . " volte: non tocco niente\n";
    exit(1);
}
```

Trovato zero volte significa che il file non è come credevi. Trovato due volte significa che stai per
cambiare anche qualcosa che non avevi guardato.

Dopo ogni modifica a un file PHP: **`php -l`**. Sempre. Un errore di sintassi in un mu-plugin lascia il
sito bianco, e il sito bianco non ha un pannello da cui rimediare.

## Cancellare elementi dall'albero

Quando togli una colonna o una sezione, ricompatta l'array: `unset()` lascia un buco negli indici e
`json_encode` trasforma l'array in oggetto, cambiando la struttura che Elementor si aspetta.

```php
foreach ($nodi as $i => &$n) {
    if (($n['id'] ?? '') === $da_togliere) { unset($nodi[$i]); continue; }
    if (!empty($n['elements'])) { $pota($n['elements']); }
}
unset($n);
$nodi = array_values($nodi);   // <- indispensabile
```

E ricordati che togliere una colonna da una sezione a due lascia l'altra a metà larghezza: va portata
a `_inline_size` 100, e va guardata anche a tablet.
