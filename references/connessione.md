# Connessione e accesso

## SSH: la strada buona

Se l'host è già in `~/.ssh/config` basta il suo nome. Una voce tipica:

```
Host nomesito
    HostName 203.0.113.10
    User utenteshell
    Port 22
    IdentityFile ~/.ssh/nomesito_ed25519
    IdentitiesOnly yes
    ServerAliveInterval 60
```

**La chiave la genera l'utente, non tu.** Se manca, chiedi all'utente di crearla e di caricare la
parte pubblica sul pannello dell'hosting. Non chiedere una password SSH da usare a mano: se
l'autenticazione è a password, chiedi di passare a chiave.

Alcuni hosting hanno reti che alterano i pacchetti e producono `Corrupted MAC on input`. In quel caso
si forzano gli algoritmi nella voce di configurazione:

```
    MACs hmac-sha2-512,hmac-sha2-256
    Ciphers aes256-ctr,aes128-ctr
    KexAlgorithms curve25519-sha256,curve25519-sha256@libssh.org,diffie-hellman-group14-sha256
    Compression yes
```

### Prova di connessione

```bash
ssh -o BatchMode=yes -o ConnectTimeout=15 nomesito 'cd ~/public_html && pwd && wp --version'
```

`BatchMode=yes` fa fallire subito invece di restare in attesa di una password: è quello che vuoi in
uno script.

Se compare un avviso sull'assenza di scambio di chiavi post-quantistico, è del server e non
riguarda il lavoro: filtralo dall'output per non sporcare i resoconti.

**`Server accepts key` seguito da `Permission denied` non è un problema di registrazione.** Quel
messaggio in `ssh -vv` dice che la chiave pubblica È autorizzata sul server: l'offerta è stata
accettata e il rifiuto arriva dopo, al momento della firma. La causa quasi sempre è che la chiave
privata ha una **passphrase** e `BatchMode=yes` non può chiederla, quindi ssh non riesce a firmare.
Porta fuori strada da solo, perché spinge a ricontrollare il pannello dell'hosting dove è tutto a
posto. Si accerta in un comando, senza toccare niente:

```bash
ssh-keygen -y -P "" -f ~/.ssh/nomesito_ed25519 >/dev/null 2>&1 && echo "senza passphrase" || echo "protetta"
```

Se è protetta le strade sono due: un agent SSH, oppure togliere la passphrase con
`ssh-keygen -p -N "" -f ~/.ssh/chiave` — che **lancia l'utente**, perché chiede quella vecchia.
L'agent è più sicuro ma non sopravvive se ogni comando gira in una shell nuova; in quel caso resta
solo la seconda, e va detto all'utente cosa comporta — la chiave diventa protetta dal solo
filesystem — insieme alle attenuanti: vale per un host solo e si revoca dal pannello in dieci secondi.

### Se WP-CLI non c'è

Cerca prima di concludere che manca: alcuni hosting lo installano altrove.

```bash
ssh nomesito 'which wp || ls ~/wp-cli.phar || ls /usr/local/bin/wp*'
```

Se davvero non c'è, si può scaricare il `.phar` nella home dell'utente **chiedendo prima**: è un
eseguibile in più su un server di produzione, non una cosa da fare di iniziativa.

## WP-CLI: quando fallisce senza dirlo

WP-CLI ha un terzo esito oltre a «riuscito» e «errore»: **non fa niente, non stampa niente, e a
volte esce comunque 0**. È il modo in cui si dichiarano fatte modifiche che non esistono.

**La regola che copre tutti i casi: dopo un comando verifica l'EFFETTO, non il codice di uscita.**
Il file esiste? Il conteggio è cambiato? Incapsulare il comando in una pipe (`| tail -2`) peggiora
le cose, perché la pipe maschera anche il codice di uscita e non resta nessun segnale.

### `wp eval-file` muto su una riga troppo lunga

Su un file che contiene una riga molto lunga — nel caso reale un base64 da 8855 caratteri su riga
unica — `wp eval-file` non esegue niente, non stampa niente e restituisce **exit 0**. `php -l` dice
«No syntax errors», il file sul server è integro, e lo stesso file caricato con
`php -r 'require "wp-load.php"; include ...'` gira correttamente.

Il sospetto è che WP-CLI tolga il `<?php` iniziale con un'espressione regolare e che su un soggetto
così lungo PCRE sfori `pcre.backtrack_limit`: `preg_replace` restituisce null, WP-CLI valuta una
stringa vuota, non succede niente.

È pericoloso soprattutto su uno script di **scrittura** lanciato senza prova a vuoto: «nessun output
ed exit 0» si legge come «è andato tutto bene» mentre non è stato toccato niente.

La prova decisiva è mettere `echo "PRIMA RIGA";` come prima istruzione: se non compare nemmeno
quella, è questo. Il controllo preventivo:

```bash
awk '{if(length($0)>m)m=length($0)}END{print m}' file.php
```

Si rimedia non producendo mai righe lunghissime: un base64 si spezza a 76 caratteri e si concatena.

```php
$dati = base64_decode('AAAA...'
    . 'BBBB...'
    . 'CCCC...');
```

L'altra stranezza di `wp eval-file` — il file gira dentro una funzione, quindi le variabili in cima
non sono globali — non è un problema di accesso ma di come si scrive lo script: sta in
`modifiche-sicure.md`.

### `wp db export` esce 255 senza una parola

Su un database da qualche centinaio di MB può uscire con codice **255 e zero output**: nessun
`Error:`, nessuna riga, e il file di dump non viene creato — mentre `mysqldump` e `mysql` sono
presenti e funzionanti e `wp db size` risponde.

Si rimedia bypassando WP-CLI, leggendo le credenziali da `wp-config` senza mai stamparle:

```bash
DB=$(wp config get DB_NAME); U=$(wp config get DB_USER)
PW=$(wp config get DB_PASSWORD); H=$(wp config get DB_HOST)
CNF=$HOME/.mycnf.tmp; umask 077
printf "[client]\nuser=%s\npassword=%s\nhost=%s\n" "$U" "$PW" "$H" > $CNF
mysqldump --defaults-extra-file=$CNF --single-transaction --quick \
  --add-drop-table --default-character-set=utf8mb4 "$DB" | gzip -6 > dump.sql.gz
rm -f $CNF
```

`--defaults-extra-file` deve essere la **prima** opzione e non espande la tilde: percorso completo.

**Un backup che non è stato riletto non è un backup.** Dopo l'export si verifica il file, non il
comando: `gzip -t`, il numero di `^CREATE TABLE`, e un confronto fra i contenuti nel dump e quelli
nel database vivo.

## Lavorare senza farsi bloccare dall'hosting

Lavorare via SSH e controllare dall'esterno qualche file del core sembra innocuo: è il sito del
cliente, con le sue credenziali. Per il firewall dell'hosting non lo è.

Caso reale, su VHosting: in un'ora circa **15 connessioni SSH**, più GET e POST a `xmlrpc.php` e poi
richieste in sequenza a `wp-login.php`, `wp-cron.php` e `wp-trackback.php`, tutto dalla connessione di
casa dell'utente — che è anche il suo IP di lavoro. L'hosting ha messo quell'IP in blacklist, e il
blocco era **condiviso fra tutti i suoi server**: sono rimasti fuori anche gli altri progetti del
cliente sullo stesso hosting, non solo quello su cui si lavorava. Nel frattempo AbuseIPDB aveva
registrato segnalazioni su quell'IP.

**Come si riconosce:** da un certo momento SSH va in timeout e i siti dello stesso hosting danno
errore di certificato — rispondono con quello del server (`*.vhosting-it.com`, su Windows
`SEC_E_WRONG_PRINCIPAL`) — mentre il resto di internet funziona. Dalla rete del telefono il sito si
apre.

**Come si evita:**

1. **Poche sessioni SSH.** Si raggruppano i comandi in una sessione sola (un heredoc con tutto il
   lavoro), non una connessione per ogni controllo. Il ciclo prova-applica descritto in `SKILL.md`
   usa tre o quattro connessioni ed è accettabile; quello che fa scattare il blocco è aprirne una a
   ogni domanda.
2. **Mai sondare `xmlrpc.php`, `wp-login.php` o altri file del core dalla connessione del cliente:**
   per un WAF è la firma di una scansione d'attacco. Se serve saperne qualcosa, si leggono i log di
   accesso via SSH, o si verifica dal cloud (WebFetch).
3. **Le verifiche HTTP dall'esterno sono poche e distanziate** (`sleep 0.5` fra una e l'altra), e
   solo su pagine pubbliche.

**Per sbloccarsi:** subito, l'hotspot del telefono, o il riavvio del router se l'IP è dinamico. Per
togliere il blocco: la rimozione dalla blacklist web si chiede dalla pagina dell'hosting (per VHosting
`tuoip.eu`), ma **la porta 22 è un blocco separato**: resta chiusa e va chiesta al supporto.

### Il server può non raggiungere il proprio dominio

La tentazione, dopo un blocco così, è spostare tutte le verifiche HTTP sul server: `curl` lanciato via
SSH non pesa sulla connessione del cliente. Ma su alcuni hosting il server **non raggiunge il proprio
dominio**. Su VHosting (cPanel con CloudLinux/CageFS) `curl https://dominio-del-sito/...` dal server
restituisce **000** su ogni indirizzo, e lo stesso con `--resolve dominio:443:127.0.0.1`; le chiamate
da PHP verso altri host (Google, IndexNow) funzionano. Nel caso reale: 72 URL su 72 «rotti». Sembrava
il sito giù, era il loopback.

**Come si riconosce:** 000 uniforme su tutto, compresa la home, che dall'esterno risponde 200.

Quindi, prima di affidare al server qualunque prova HTTP sul sito stesso, **una sola richiesta alla
home dal server**: se torna 000, quella strada è chiusa e le verifiche HTTP si fanno dall'esterno,
con le regole sopra. Per sapere se un indirizzo esiste ancora non serve affatto una richiesta HTTP:
si risolve dentro WordPress (`seo-e-indicizzazione.md`).

### Gli strumenti di raggiungibilità esterni non provano un guasto

Durante un blocco, la prova con check-host.net ha dato un falso allarme: i suoi nodi ricevono 403 o
timeout dai filtri di sicurezza degli hosting. «Raggiungibile da 3 nodi su 8», **senza** una misura di
confronto presa in un giorno normale, non dice niente. La prova giusta è una richiesta normale da una
rete pulita.

## Aggiornare il core

Prima di decidere *cosa* aggiornare e prima di scaricare qualcosa, due API di wordpress.org rispondono
in dieci secondi a domande che altrimenti costano un pomeriggio.

### Il pannello vulnerabilità non è una diagnosi

L'elenco vulnerabilità di un pannello di hosting (Hostinger usa Patchstack) è un confronto di
**intervalli di versione**, e sui rami più vecchi che ricevono backport di sicurezza produce falsi
positivi.

Caso misurato: sito su WordPress 6.9.5, il pannello segnala **12** vulnerabilità del core con
`fixed_in` 7.0.2 / 7.0.3 / 7.0.4. Dopo l'aggiornamento a 6.9.8 ne segnala ancora 12, identiche:
l'intervallo dice `<= 7.0.1`, e 6.9.8 è numericamente minore, quindi combacia. Sembra che
l'aggiornamento non sia servito a niente.

La fonte autorevole è WordPress stesso, che classifica ogni versione in tre stati (`insecure`,
`outdated`, `latest`):

```bash
curl -s "https://api.wordpress.org/core/stable-check/1.0/"
```

Sullo stesso caso: 6.9.5 `insecure`, 6.9.8 `outdated` (cioè **non** insicura), 7.0.1-7.0.4 tutte
`insecure`, 7.1.1 `latest`. Che la 7.0.4 sia insicura e la 6.9.8 no è la prova del backport: non
decide l'ordine dei numeri, decide il ramo.

Non è pignoleria: porta a due errori opposti e costosi. Il primo è credere che serva un salto di major
su un sito fragile — con un page builder vecchio significa romperlo per niente. Il secondo è il
contrario: se un giorno il pannello tace, non è detto che sia tutto a posto, perché misura solo quello
che ha in catalogo.

**Regola:** prima di pianificare un aggiornamento del core motivato dalla sicurezza, chiedi a
stable-check. Un minore sullo stesso ramo spesso chiude tutto, costa un decimo e non rompe niente. E
avvisa **sempre** il cliente che il pannello continuerà a mostrare l'allarme rosso: altrimenti ti
richiama fra un mese convinto che il lavoro non sia stato fatto.

### Il pacchetto localizzato di una major può non esistere ancora

`wp core download --version=X --locale=it_IT` fallisce con `Error: Release not found. Double-check
locale or version.` anche quando versione e locale sono entrambi giusti — e la tentazione è cercare
un errore di battitura che non c'è. I pacchetti localizzati vengono costruiti **dopo** il rilascio
inglese, e per una major appena uscita possono mancare per settimane.

Si accerta in dieci secondi: l'API risponde `locale: en_US` anche quando le chiedi `it_IT`.

```bash
curl -s "https://api.wordpress.org/core/version-check/1.7/?locale=it_IT"
```

Si rimedia senza rinunciare alla lingua, con risultato identico:

```bash
wp core download --version=7.1.1          # senza --locale
wp core install ...
wp language core install it_IT --activate
```

Vale per qualunque locale non inglese, e ricapita a ogni major.

## FTP o SFTP: cosa si può fare

**Si può:** caricare e modificare file. Quindi mu-plugin, CSS, JavaScript, immagini, e tutto quello
che si ottiene con i filtri di WordPress senza toccare il contenuto delle pagine.

**Non si può:** modificare `_elementor_data`, le meta, i menu, le opzioni. Sono nel database.

Senza accesso al database mancano le due cose che rendono sicura una modifica: la prova a vuoto e il
conteggio finale. Non si compensa con l'attenzione.

### Come chiederlo

Spiega il limite e proponi le alternative, invece di procedere in modo approssimativo:

> Con il solo FTP posso lavorare su stili e comportamenti, ma non sul contenuto delle pagine, che in
> Elementor vive nel database. Per quello mi serve un accesso SSH, anche temporaneo, oppure
> phpMyAdmin: con phpMyAdmin ti preparo io le query e le esegui tu, ma è più lento e ogni passaggio
> va controllato a mano.

### Se si lavora via phpMyAdmin

- Fai preparare **un backup della tabella** prima di ogni modifica, dal pannello stesso.
- Consegna query **una alla volta**, con accanto la query di verifica da eseguire dopo.
- Per il JSON di Elementor, evita `REPLACE()` su `meta_value`: una sostituzione cieca su un documento
  JSON può rompere la struttura. Meglio far scaricare il valore, trasformarlo in locale e farlo
  reincollare come `UPDATE ... SET meta_value = '...'` con l'escape corretto.

## Cosa serve sapere del sito

La ricognizione (`scripts/ricognizione.php`) risponde a tutto questo in una volta. Le voci che
contano di più:

| Cosa | Perché serve |
|---|---|
| **Prefisso delle tabelle** | spesso non è `wp_`; ogni query lo usa |
| **Versione di Elementor** e se c'è Pro | i moduli disponibili cambiano |
| **Template attivi** (intestazione, piè di pagina, articolo, archivio) | le modifiche globali stanno lì |
| **Kit di temi installato** | i widget hanno prefissi e chiavi proprie |
| **Plugin di cache** | senza svuotarla non vedi le modifiche |
| **`blog_public`** | se è `0` il sito è in `noindex`; prima di metterlo a `1` leggi `seo-e-indicizzazione.md` |
| **Plugin SEO** | dove stanno title, description e dati strutturati |
| **Costanti in `wp-config.php`** | credenziali già configurate da non duplicare |

## Sicurezza operativa

- **I segreti non passano da te.** Password SMTP, chiavi API e simili stanno in `wp-config.php` come
  costanti, inserite dall'utente. Tu scrivi il codice che le legge, non il valore.
- **Non stampare segreti** nell'output degli script, nemmeno parzialmente. E ricorda che un segreto
  può stare dove non lo cerchi: alcuni client mettono la chiave API **dentro l'URL**
  (`https://utente:CHIAVE@host/...`), quindi chi registra gli URL registra anche la chiave. Il caso
  e il rimedio sono in `consenso-e-integrazioni.md`.
- **Non lasciare script eseguibili raggiungibili dal web.** Gli script di modifica stanno nella home
  dell'utente (`~/`), non dentro `public_html`, e si eseguono con `wp eval-file`.
- **Cancella gli script di lavoro** quando hai finito, o almeno segnala all'utente che sono lì.
- Se cambia una password che il sito usa per spedire, **il modulo smette di funzionare in silenzio**:
  nessun errore, nessun avviso. Va coordinato, e subito dopo si fa una prova d'invio reale.
