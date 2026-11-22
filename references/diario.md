# Diario di bordo

Lezioni raccolte lavorando su siti veri, non ancora sistemate nei file di riferimento.

**Leggilo all'inizio di ogni lavoro nuovo.** È la parte più fresca della skill: contiene quello che è
costato tempo di recente e che non è ancora stato riordinato. Costa dieci secondi e ogni voce qui
dentro è mezz'ora già pagata da qualcun altro.

---

## Come si aggiunge una voce

Quando su un progetto qualsiasi incontri una sorpresa costosa, aggiungila:

```bash
~/.claude/skills/wordpress-elementor-ssh/scripts/lezione.sh "Titolo breve" "Nome progetto" <<'FINE'
Cosa credevo, cosa succedeva davvero, come si riconosce, come si rimedia.
FINE
```

Lo script si trova da solo la propria cartella, quindi funziona da qualunque progetto e non ha bisogno
che tu sappia dove vive la skill. Se preferisci, puoi anche scrivere la voce a mano in fondo a questo
file usando lo stesso schema.

## Cosa merita una voce e cosa no

Il diario si riempie in fretta di cose vere e inutili. Il filtro è uno solo:

> **Succederebbe anche su un altro sito?**

| Merita | Non merita |
|---|---|
| Una regola che sembra applicarsi e non si applica | Che su questo sito il prefisso è `wpbw_` |
| Uno strumento che restituisce numeri plausibili e falsi | Che la sezione dei contatti ha id `1cfbc68f` |
| Un comportamento di Elementor, di un kit, di un plugin di cache | Che il cliente ha chiesto di togliere una sede |
| Una richiesta che applicata alla lettera sbaglia | La cronologia di cosa hai fatto e quando |

Le cose della colonna destra non vanno perse: vanno **nelle note del progetto**, non nella skill. Un
sito ha bisogno del suo diario — prefisso delle tabelle, kit, plugin di cache, quali id fanno cosa,
cosa non si tocca e perché — e quel diario resta lì, insieme al sito. La skill è quello che ti porti
al lavoro successivo.

Se una voce ti sembra a metà fra le due colonne, prova a riscriverla senza nominare il sito: se
sopravvive alla riscrittura è generale, se si sgonfia era una nota di progetto.

## Come si scrive una voce che serve

Non «attenzione a X». Servono quattro cose, e la prima è quella che si dimentica:

1. **Cosa credevo** — l'idea sbagliata è la parte riutilizzabile. Chi legge la riconosce in sé.
2. **Cosa succedeva davvero**, con il numero misurato.
3. **Come si riconosce** — il sintomo, prima della spiegazione. È quello che rende utile la voce fra
   sei mesi, quando ricapita e non ti ricordi la causa.
4. **Come si rimedia**, o perché non si rimedia.

Se hai un numero, scrivilo. «Lo scarto era 22px identico su tutte e tre le coppie» dice da solo che la
causa è una misura scritta da qualche parte e non il testo. «I riquadri erano un po' disallineati» non
dice niente.

## Quando si riordina

**Oltre le dieci voci, il diario smette di essere letto.** A quel punto vanno spostate nel file di
riferimento giusto e tolte da qui:

| Argomento | Dove va |
|---|---|
| Accesso, SSH, FTP, WP-CLI, hosting | `connessione.md` |
| Modello dei dati, widget, kit, filtri | `elementor.md` |
| Fasce, CSS, altezze, specificità | `responsive.md` |
| Script di modifica, sostituzioni, sicurezza | `modifiche-sicure.md` |
| Misurare, strumenti che mentono, prove | `verifica.md` |
| Banner cookie, tracciamento, CRM, API di iscrizione, plugin di form | `consenso-e-integrazioni.md` |
| Indicizzazione, sitemap, dati strutturati, plugin SEO, vecchi indirizzi | `seo-e-indicizzazione.md` |

Se un gruppo di voci non trova posto in nessuno di questi, si crea un file nuovo e lo si aggiunge
sia qui sia all'elenco «File di riferimento» in `SKILL.md`. Forzarle dentro un file che parla d'altro
è proprio l'accumulo di eccezioni che questa skill dice di evitare.

Riordinare non è ricopiare: una voce che finisce in un riferimento va **fusa** con quello che c'è già,
non appesa in fondo. Due paragrafi che dicono la stessa cosa in due punti diversi sono peggio di uno
solo.

Se una lezione cambia una regola che la skill dà per buona, **correggi la regola** invece di
aggiungere un'eccezione: una skill che accumula eccezioni smette di essere un metodo.

---

## Schema di una voce

```markdown
## Titolo che dice il difetto, non l'argomento

**Progetto:** nome — **Data:** AAAA-MM-GG

Cosa credevo. Cosa succedeva davvero, con il numero. Come si riconosce al volo. Come si rimedia.
```

Esempio, per capire il taglio:

> ## Il conteggio delle righe di un pulsante dice sempre due
>
> **Progetto:** esempio — **Data:** 2026-01-15
>
> Credevo che `range.getClientRects().length` desse il numero di righe di un'etichetta. Su undici
> pulsanti segnalati «a capo», sei erano su una riga sola: il kit **duplica l'etichetta** per
> l'effetto al passaggio del mouse, e la copia sta a un'altezza diversa. Si riconosce dall'altezza del
> pulsante: 52px contro 78px dice da sola quante righe ci sono. Si conta dentro **un solo nodo di
> testo**, non nell'unione di tutti.

---

---

# Voci

Ultimo riordino: **2026-10-07**, 15 voci ricollocate nei file di riferimento. Da questo riordino è
nato `seo-e-indicizzazione.md`: le voci su sblocco dell'indicizzazione e schema di Rank Math non
avevano casa nei sei file esistenti. Il riordino precedente (2026-09-18, 16 voci) aveva fatto nascere
`consenso-e-integrazioni.md` per la stessa ragione.


## Anche l'ul di paginate_links porta la classe page-numbers

**Progetto:** sito di un centro yoga — **Data:** 2026-10-14

Credevo che con paginate_links(type=list) la classe page-numbers stesse solo sugli <a> e sugli <span> dentro gli <li>. Ce l'ha anche l'<ul> esterno (<ul class="page-numbers">). Una regola scritta per le pillole (.pagination .page-numbers{border;border-radius:999px;background;min-height:42px}) veste anche la lista: lo screenshot mostra un riquadro bordato a tutta larghezza, con gli angoli stondati, intorno ai numeri; le misure sugli <a> sono tutte giuste e non lo rivelano. Si riconosce misurando il bordo dell'ul (era 1px, radius 999px). Rimedio: regole delle voci sotto "li > .page-numbers" e reset esplicito su "ul.page-numbers" (border:0; border-radius:0; background:transparent), perche' anche il tema puo' stilare l'ul.

## Form Elementor a più step con salto condizionale: un 'obbligatorio' messo dall'editor rompe il salto in silenzio

**Progetto:** sito di un partner nel settore eventi — **Data:** 2026-10-15

Cosa credevo: lasciare i campi dello step 2 non obbligatori in Elementor, e renderli obbligatori via codice solo per chi sceglie la strada lunga, bastava.

Cosa succedeva: il bivio inviava il form dallo step 1 con lo step 2 vuoto. Otto giorni dopo qualcuno, salvando dall'editor, ha segnato "obbligatorio" un campo dello step 2, e il server ha cominciato a respingere tutta la strada corta ("Si è verificato un errore durante l'invio", poi il form mostra lo step 2). Nessun allarme: il sito non salvava gli invii. Risultato: decine di invii respinti e iscrizioni perse. L'ha scoperto l'utente con un test.

Come si riconosce: confronta le revisioni della pagina (`post_type='revision'`, ognuna ha il suo `_elementor_data`) per trovare quando è cambiato `required`. Nei log di accesso, la DIMENSIONE della risposta di admin-ajax distingue l'esito di ogni invio (successo e errore hanno lunghezze diverse e costanti): così si contano gli invii respinti anche senza "Raccogli invii".

Come si rimedia, per sempre: nel codice, non nell'editor. In Elementor Pro 4.x `Form_Record::validate()` fa i controlli di obbligatorietà, poi lancia `elementor_pro/forms/validation`, poi restituisce `empty($ajax_handler->errors)`; `$errors` è pubblico. Un hook su quell'azione, con priorità alta, che fa `unset($ajax_handler->errors[$id])` per i campi dello step saltato, rende il salto immune a qualunque impostazione dell'editor. I campi da proteggere ricavali dalla struttura (`$record->get('form_settings')['form_fields']`, quelli dopo il secondo campo di tipo `step`), così valgono anche per campi aggiunti dopo.

Come si prova senza creare contatti: in `wp eval-file`, `new Ajax_Handler()` + `new Form_Record($campi, $elemento_form)` + `$record->validate($handler)`, con `$_POST['form_id']`/`post_id` impostati, e il `required` forzato in memoria. Le azioni (CRM, email, redirect) non partono. Fai girare la simulazione PRIMA con il codice vecchio: il caso rotto deve fallire, altrimenti il test non riproduce il problema.

## Il controllo di andata e ritorno del JSON fallisce su quasi ogni pagina Elementor: si fa chirurgia sul testo

**Progetto:** sito di un servizio di matrimoni — **Data:** 2026-11-09

Cosa credevo: che decodifica e ricodifica di _elementor_data restituisse il byte identico, e che se non lo fa si debba saltare la pagina. Cosa succedeva: su due pagine su due il confronto falliva (245219 byte vs 234083 in Python, e anche imitando lo stile PHP con ASCII e slash escapati restava una differenza), perche' Elementor scrive i float con 17 cifre ("_inline_size":66.23399999999999465...) e ogni ricodifica li riscrive come 66.234. Il valore e' lo stesso, il testo no. Come si riconosce: il primo carattere diverso cade sempre dentro un numero decimale. Come si rimedia: non riscrivere il documento intero. Si individua l'oggetto da cambiare sul testo originale (find di {"id":"X","elType":"column" e json.JSONDecoder().raw_decode per trovarne l'estremo), si sostituisce solo quel pezzo e si dimostra la correttezza confrontando json.loads(nuovo) con vecchio-decodificato + le sole modifiche previste. Il resto della pagina resta byte per byte identico. Sul server: sha1 del valore attuale confrontato con quello del file su cui si e' costruita la modifica (blocco se diverso), prova a vuoto con uno sha sbagliato per vedere scattare il blocco, copia su file con sha verificato, UPDATE con $wpdb->update, rilettura dal DB. Due note collaterali dello stesso lavoro: antiword legge i .doc in UTF-8 solo con "-m UTF-8" (con cp1252 gli apostrofi e gli accenti diventano un quadratino); e una lista "Coming soon" puo' essere gia' pronta ma nascosta con hide_desktop/hide_tablet/hide_mobile sulla sezione: prima di rifarla da zero cerca nel JSON.

## Il plugin ufficiale Meta Pixel 5.x crea _fbp lato server: il banner cookie non lo vede

**Progetto:** sito aziendale — **Data:** 2026-11-16

Cosa credevo: che _fbp prima del consenso venisse da fbevents.js nel browser, e che bastasse il blocco automatico del banner (iubenda). Cosa succedeva: "Meta Pixel for WordPress" (official-facebook-pixel 5.2.2) crea _fbp e _fbc in PHP, con un header Set-Cookie, alla prima richiesta di ogni visitatore (FacebookParamBuilder::server_setup, chiamato nel costruttore del plugin). Nessun blocco lato browser puo' fermarlo. Come si riconosce: curl -D - sulla home, senza cookie, mostra "set-cookie: _fbp=fb.1....AQECAQIB" (il suffisso AQECAQIB e' del ParamBuilder lato server). Come si rimedia: mu-plugin con header_register_callback che, se manca il consenso alla finalita' marketing nel cookie _iub_cs-<cookiePolicyId>, toglie dagli headers_list() le sole righe Set-Cookie _fbp/_fbc e rimette le altre. Il filtro 'facebook_signals_held' del plugin sembra la via pulita ma NON va usato: cambia anche lo stato "held" stampato nell'HTML, che con la cache di pagina resta held per tutti e il pixel non si inizializza piu' nemmeno dopo il consenso. Verifica nei due sensi con curl -b "_iub_cs-ID=<json urlencoded con purposes 5 true/false>". Seconda lezione dello stesso lavoro: controllare sempre anche il browser, senza consenso: il cookie lato server era solo una parte, il blocco automatico non fermava nessuno script (._iub_cs_activate = 0, connect.facebook.net contattato con il banner aperto).

## WP-CLI senza --user toglie gli <script> da _elementor_code

**Progetto:** sito aziendale — **Data:** 2026-11-17

Credevo che update_post_meta da wp eval-file scrivesse il valore così com'è. Sul meta `_elementor_code` (Codice personalizzato di Elementor Pro, post type elementor_snippet) Elementor registra una sanitizzazione che, se l'utente corrente non ha `unfiltered_html`, toglie i tag <script>. WP-CLI di default gira senza utente (id 0): il meta è stato salvato con il solo commento HTML e lo snippet delle notifiche push è sparito dal sito senza nessun errore.
Come si riconosce: la rilettura da $wpdb è diversa da quanto scritto, e manca proprio lo <script>. Il controllo di andata e ritorno l'ha preso subito.
Rimedio: `wp eval-file script.php --user=1` (un amministratore), e nello script fermarsi se `current_user_can('unfiltered_html')` è falso. Vale per qualunque meta o contenuto che passi da kses: post_content con script, widget HTML, snippet.

## WooCommerce: update_option() sui metodi di spedizione scrive nell'opzione sbagliata

**Progetto:** e-commerce di un parrucchiere — **Data:** 2026-11-18

Credevo che $metodo->update_option('cost', ...) di un metodo di spedizione (flat_rate, free_shipping) cambiasse la tariffa della zona. Invece scrive nell'opzione GLOBALE del tipo (woocommerce_flat_rate_settings), mentre le impostazioni vere stanno nell'opzione dell'istanza (woocommerce_flat_rate_5_settings). Il comando non da' errori e rileggendo con $m->get_option() nella stessa richiesta i valori sembrano a posto o no a seconda della cache: la prova e' stata il sito che mostrava ancora il vecchio titolo. Rimedio: aggiornare $m->instance_settings e fare update_option($m->get_instance_option_key(), $m->instance_settings), poi rileggere con un nuovo WC_Shipping_Zone e controllare l'opzione dell'istanza con wp option get; cancellare le opzioni globali create per errore. Il costo del flat rate e' al netto dell'IVA: con prezzi IVA inclusa, 4,99 lordi = 4,09016 netti. Con la soglia gratuita WooCommerce mostra insieme tariffa e gratuita: serve un filtro woocommerce_package_rates per nascondere la tariffa a pagamento.

## Un fbq non definito in un gestore submit fa partire l'invio nativo del checkout

**Progetto:** sito di un partner nel settore eventi — **Data:** 2026-11-20

Cosa credevo: che ordini WooCommerce falliti senza nota e con l'email vuota fossero un problema del gateway o di una versione dei plugin. Cosa succedeva: CartFlows (Pixel, evento Add Payment Info) aggancia a form.woocommerce-checkout un submit che chiama fbq() senza typeof; senza consenso cookie (iubenda) fbq non esiste, la ReferenceError interrompe la catena dei gestori jQuery prima del preventDefault di WooCommerce e il browser fa il POST NATIVO all'action del form (la pagina Cassa). In quel POST non-AJAX il server non legge billing_email (dati validati vuoti, nessun errore), crea l'ordine senza email e il gateway (WooPayments: payment_method_not_provided) lo mette "fallito" senza nota. Ordini e clienti persi per due settimane; intermittente perche' chi accetta i cookie compra.
Come si riconosce: nei log di accesso, agli orari dei fallimenti, POST alla pagina Cassa (risposta da decine di KB) invece di /?wc-ajax=checkout; nel browser, window.addEventListener('submit', e => e.defaultPrevented) dopo il clic su Acquista ora restituisce false e in console compare "fbq is not defined".
Come si rimedia: spegnere l'evento che chiama fbq all'invio (o proteggerlo con typeof); MAI definire uno stub window.fbq, perche' il codice base del Pixel fa "if(f.fbq)return" e non si caricherebbe piu' dopo il consenso. Per la diagnosi: un mu-plugin che logga presenza dell'email ai passi woocommerce_checkout_process / after_checkout_validation / checkout_create_order e l'eccezione su woocommerce_payments_order_failed (il log di WooPayments di solito e' spento).
