# wordpress-elementor-ssh

Una skill per Claude Code che insegna a modificare un sito WordPress costruito con Elementor lavorando dal server (SSH e WP-CLI, oppure FTP per i soli file), senza mai pilotare l'editor visuale dal browser.

## L'idea

Elementor salva ogni pagina come un documento JSON dentro il database. Quel documento si può leggere, trasformare con uno script e riscrivere. Un clic simulato nell'editor, invece, non si può provare a vuoto, non si può annullare in blocco e se sbaglia sposta un blocco in un punto che nessuno nota subito.

Il browser serve per un'altra cosa: verificare il risultato sulla pagina pubblica, in sola lettura.

## Cosa fa la skill

- Prima di cominciare chiede quello che serve (accessi, stato di indicizzazione del sito, chi altro lavora sulle pagine).
- Guida la connessione: chiave SSH generata dall'utente, voce in `~/.ssh/config`, prova di connessione.
- Fa una ricognizione in sola lettura del sito (versioni, tema, plugin di cache e SEO, template attivi, menu).
- Impone il ciclo: copia di sicurezza, prova a vuoto, scrittura, rilettura dal database, controllo sulla pagina pubblica.
- Raccoglie in un diario le lezioni apprese sui siti veri, per non ripagare lo stesso errore.

## Contenuto

- `SKILL.md`: il metodo e le regole che vengono prima di tutto.
- `references/`: connessione, struttura di Elementor, modifiche sicure, verifica, responsive, SEO e indicizzazione, consenso cookie e integrazioni, diario.
- `scripts/`: `ricognizione.php` (fotografia del sito), `helper.php` (funzioni comuni per gli script di modifica), `verifica-layout.js` (misura del layout alle varie larghezze), `lezione.sh` (aggiunge una voce al diario).

## Installazione

Copia l'intera cartella in `~/.claude/skills/wordpress-elementor-ssh/` (su Windows: `C:\Users\<utente>\.claude\skills\wordpress-elementor-ssh\`). Alla sessione successiva Claude Code la carica e la usa da solo quando parli di un sito WordPress o Elementor.

## Cose da sapere

- È scritta in italiano e nasce da siti reali gestiti su hosting con cPanel (VHosting). Altri hosting possono avere comandi diversi.
- Dà per scontato un accesso SSH con WP-CLI. Dove manca, la skill spiega le alternative (FTP, phpMyAdmin), ma sono più limitate.
- Non è un prodotto ufficiale né testato da terzi. Sul sito vivo conviene sempre avere una copia di sicurezza e leggere cosa fa lo script prima di applicarlo.
- Gli esempi e il diario sono stati ripuliti da nomi di clienti, domini e indirizzi dei server. Se ne trovi qualcuno rimasto, segnalalo.

## Com'è nata

È nata da un lavoro vero: il completamento di un sito WordPress con Elementor per un professionista, fatto in produzione via SSH. Il metodo è stato scritto prima in un file di istruzioni da passare ad altri progetti, poi trasformato in questa skill. Da allora l'ho usata su altri siti, e ogni volta che qualcosa è andato storto la lezione è finita nel diario.
