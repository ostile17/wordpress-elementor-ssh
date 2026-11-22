/**
 * Prova di impaginazione su tutte le pagine e tutte le larghezze, in un colpo solo.
 *
 * COME SI USA
 * Va incollato nella console di una pagina DEL SITO: serve la stessa origine per
 * poter leggere il contenuto degli iframe.
 *
 *   sgProva(['/', '/contatti/'], [375, 620, 768, 1024, 1194, 1280, 1440]);
 *   sgRisultato();      // stato e problemi, chiamabile anche a meta' scansione
 *
 * PERCHE' GLI IFRAME E NON I RIDIMENSIONAMENTI
 * Ridimensionare la finestra e ricaricare costa un giro di strumento per ogni
 * combinazione: quindici pagine per sette larghezze sono centocinque giri. Un
 * iframe della larghezza voluta applica le stesse media query e si carica in
 * mezzo secondo, quindi la stessa prova sta in una chiamata sola.
 *
 * LA TRAPPOLA CHE RENDE FALSA TUTTA LA PROVA
 * La barra di scorrimento NON conta per le media query ma conta per
 * `clientWidth`. In un iframe largo 782 il documento dichiara 767: leggi "767" e
 * credi di essere nella fascia del telefono mentre il CSS applica quella del
 * tablet. Ogni misura risulta spostata di 15px, sempre nello stesso verso, e le
 * soglie piu' delicate (768 e 1025) cadono sistematicamente dalla parte
 * sbagliata.
 *
 * Su un apparecchio vero la barra e' sovrapposta e non toglie spazio. Qui si
 * ottiene lo stesso nascondendola dentro l'iframe: da quel momento clientWidth
 * coincide con la larghezza usata dalle media query, e la prova e' onesta.
 * Ogni misura comincia comunque verificando che le due coincidano.
 *
 * COSA MISURA
 *   scorrimento   la pagina scorre davvero in orizzontale
 *   sforamento    l'elemento piu' profondo che esce dal suo contenitore
 *   altezze       riquadri con bordo e sfondo affiancati, alti diversi
 *   asse          blocchi centrati che non stanno al centro della colonna
 *   etichette     testo dei pulsanti spezzato su piu' righe
 *   icone         glifo fuori centro dentro il tondino
 */

window.sgProva = function (pagine, larghezze, attesaMs) {
    pagine    = pagine    || ['/'];
    larghezze = larghezze || [375, 620, 768, 1024, 1194, 1280, 1440];
    attesaMs  = attesaMs  || 800;

    window.__sgRis = [];
    window.__sgFatto = false;
    window.__sgTotale = pagine.length * larghezze.length;

    const f = document.createElement('iframe');
    f.style.cssText = 'position:fixed;left:-99999px;top:0;height:900px;border:0';
    document.body.appendChild(f);

    /* ---------- utilita' comuni ---------- */

    const idw = el => {
        const m = String(el.className || '').match(/elementor-element-([0-9a-f]{6,8})/);
        return m ? m[1] : '';
    };

    const testo = el => (el.textContent || '').replace(/\s+/g, ' ').trim().slice(0, 34);

    /* Un elemento fuori flusso, invisibile o spinto fuori schermo non e' un
       difetto: e' testo per lettori di schermo, un salta-menu, un pannello
       chiuso. Filtrarli qui evita meta' dei falsi allarmi. */
    const visibile = (el, W) => {
        const r = el.getBoundingClientRect();
        if (r.width < 2 || r.height < 2) { return false; }
        if (r.left < -1000 || r.top < -5000) { return false; }
        const s = W.getComputedStyle(el);
        if (s.display === 'none' || s.visibility === 'hidden' || s.opacity === '0') { return false; }
        if (s.position === 'fixed' || s.position === 'sticky' || s.position === 'absolute') { return false; }
        return true;
    };

    /* L'INCHIOSTRO, non la scatola. Un widget largo quanto la colonna con dentro
       un'immagine stretta ha la scatola centrata e il contenuto no: confrontare
       le scatole non vede il difetto, e su elementi a larghezza libera ne inventa
       uno che non c'e'. Qui si misura dove finisce davvero il pixel acceso:
       il riquadro delle immagini, o i rettangoli di riga del testo. */
    const inchiostro = (el, D) => {
        const media = el.querySelectorAll('img, svg');
        let L = Infinity, R = -Infinity, n = 0;
        for (const m of media) {
            const r = m.getBoundingClientRect();
            if (r.width > 1) { L = Math.min(L, r.left); R = Math.max(R, r.right); n++; }
        }
        if (!n) {
            const it = D.createTreeWalker(el, NodeFilter.SHOW_TEXT);
            let t;
            while ((t = it.nextNode())) {
                if (!t.textContent.trim()) { continue; }
                const rg = D.createRange();
                rg.selectNodeContents(t);
                const rects = rg.getClientRects();
                for (let i = 0; i < rects.length; i++) {
                    if (rects[i].width > 1) {
                        L = Math.min(L, rects[i].left);
                        R = Math.max(R, rects[i].right);
                        n++;
                    }
                }
            }
        }
        return n ? { L: L, R: R, c: (L + R) / 2, w: R - L } : null;
    };

    /* Quante righe occupa DAVVERO un'etichetta. Molti kit duplicano il testo per
       l'effetto al passaggio del mouse: contando i rettangoli di tutti i nodi
       insieme risultano due righe dove ce n'e' una. Si conta dentro un nodo di
       testo per volta, e si raggruppano i rettangoli per quota verticale. */
    const righeTesto = (el, D) => {
        const it = D.createTreeWalker(el, NodeFilter.SHOW_TEXT);
        let t, massimo = 0;
        while ((t = it.nextNode())) {
            if (!t.textContent.trim()) { continue; }
            const rg = D.createRange();
            rg.selectNodeContents(t);
            const rects = rg.getClientRects(), quote = new Set();
            for (let i = 0; i < rects.length; i++) {
                if (rects[i].width > 1) { quote.add(Math.round(rects[i].top / 4)); }
            }
            massimo = Math.max(massimo, quote.size);
        }
        return massimo;
    };

    const haContorno = (el, W) => {
        const s = W.getComputedStyle(el);
        const bordo = parseFloat(s.borderTopWidth) || 0;
        const sfondo = s.backgroundColor;
        const pieno = sfondo && sfondo !== 'transparent' && !/rgba\(0, 0, 0, 0\)/.test(sfondo);
        return (bordo >= 1 && pieno) || (pieno && s.boxShadow !== 'none') || (bordo >= 1 && s.boxShadow !== 'none');
    };

    /* ---------- i controlli ---------- */

    const esamina = (D, W) => {
        const vw = D.documentElement.clientWidth;
        const R = { vw: vw, scorre: 0, sfora: [], altezze: [], asse: [], etichette: [], icone: [] };

        W.scrollTo(9999, 0);
        R.scorre = W.scrollX;
        W.scrollTo(0, 0);

        /* 1. sforamento del contenitore. Il piu' profondo e' il colpevole:
              i genitori si allargano per contenerlo. */
        const sforanti = [];
        D.querySelectorAll('.elementor-element').forEach(el => {
            if (!visibile(el, W)) { return; }
            const p = el.parentElement;
            if (!p) { return; }
            const ps = W.getComputedStyle(p);
            if (ps.overflow !== 'visible' || ps.overflowX !== 'visible') { return; }
            const pr = p.getBoundingClientRect(), r = el.getBoundingClientRect();
            const dx = Math.max(pr.left + parseFloat(ps.paddingLeft) - r.left,
                                r.right - (pr.right - parseFloat(ps.paddingRight)));
            if (dx > 2) { sforanti.push({ el: el, dx: Math.round(dx) }); }
        });
        R.sfora = sforanti
            .filter(o => !sforanti.some(q => q !== o && o.el.contains(q.el)))
            .slice(0, 5)
            .map(o => ({ id: idw(o.el), dx: o.dx, t: testo(o.el) }));

        /* 2. riquadri della stessa riga: cime e altezze.
              Le colonne si raggruppano per quota, perche' quando vanno a capo
              formano righe che il DOM non distingue. */
        D.querySelectorAll('.elementor-container').forEach(cont => {
            const colonne = [...cont.children].filter(c =>
                c.classList && c.classList.contains('elementor-column') && visibile(c, W));
            if (colonne.length < 2) { return; }
            const righe = new Map();
            colonne.forEach(c => {
                const q = Math.round(c.getBoundingClientRect().top / 40);
                if (!righe.has(q)) { righe.set(q, []); }
                righe.get(q).push(c);
            });
            righe.forEach(gruppo => {
                if (gruppo.length < 2) { return; }
                const dati = gruppo.map(c => {
                    const box = [...c.querySelectorAll('.jkit-icon-box-wrapper, .elementor-widget-wrap, .elementor-widget-container > div')]
                        .find(e => visibile(e, W) && haContorno(e, W) && e.getBoundingClientRect().width > 80);
                    if (!box) { return null; }
                    const r = box.getBoundingClientRect();
                    return { col: idw(c), h: Math.round(r.height), top: Math.round(r.top), t: testo(box) };
                }).filter(Boolean);
                if (dati.length < 2) { return; }
                const hs = dati.map(x => x.h), ts = dati.map(x => x.top);
                const dH = Math.max.apply(null, hs) - Math.min.apply(null, hs);
                const dT = Math.max.apply(null, ts) - Math.min.apply(null, ts);
                if (dH > 4 || dT > 3) {
                    R.altezze.push({ sez: idw(cont.closest('.elementor-section') || cont),
                                     scartoAltezza: dH, scartoCima: dT,
                                     riquadri: dati.map(x => x.h + '@' + x.top + ' ' + x.t.slice(0, 16)) });
                }
            });
        });

        /* 3. blocchi centrati fuori asse. Si guarda solo cio' che e' davvero
              centrato e largo: un'icona stretta appoggiata a un lato per disegno
              non e' un difetto, e a larghezza libera l'elemento si posiziona da
              se' e il confronto con l'asse della colonna non significa niente. */
        D.querySelectorAll('.elementor-column').forEach(col => {
            const s = W.getComputedStyle(col), r = col.getBoundingClientRect();
            if (r.width < 60 || r.height < 5) { return; }
            const sx = r.left + parseFloat(s.paddingLeft);
            const dx = r.right - parseFloat(s.paddingRight);
            const centro = (sx + dx) / 2, largh = dx - sx;
            const wrap = col.querySelector(':scope > .elementor-widget-wrap');
            if (!wrap) { return; }
            [...wrap.children].forEach(el => {
                if (!el.classList.contains('elementor-element')) { return; }
                if (/widget__width-initial/.test(el.className)) { return; }
                if (W.getComputedStyle(el).textAlign !== 'center') { return; }
                if (!visibile(el, W)) { return; }
                const k = inchiostro(el, D);
                if (!k || k.w < largh * 0.4) { return; }
                const fuori = Math.round(k.c - centro);
                if (Math.abs(fuori) >= 5) {
                    R.asse.push({ id: idw(el), fuori: fuori, colonna: Math.round(largh), t: testo(el) });
                }
            });
        });

        /* 4. etichette su piu' righe */
        D.querySelectorAll('.elementor-button, .jkit-button').forEach(b => {
            if (!visibile(b, W)) { return; }
            const n = righeTesto(b, D);
            if (n > 1) {
                R.etichette.push({ id: idw(b.closest('.elementor-element') || b), righe: n,
                                   larghezza: Math.round(b.getBoundingClientRect().width), t: testo(b) });
            }
        });

        /* 5. icone fuori centro nel tondino. Il tondino E' il widget: una
              scansione che guarda solo i discendenti lo salta. */
        D.querySelectorAll('.jkit-icon-box .icon, .jkit-icon-box-wrapper .icon, .elementor-icon').forEach(box => {
            if (!visibile(box, W)) { return; }
            const r = box.getBoundingClientRect(), s = W.getComputedStyle(box);
            if (parseFloat(s.borderTopLeftRadius) < Math.min(r.width, r.height) * 0.35) { return; }
            if (Math.abs(r.width - r.height) > 6) { return; }
            const dentro = box.querySelector('svg, i, img');
            if (!dentro) { return; }
            const ir = dentro.getBoundingClientRect();
            if (ir.width < 2) { return; }
            const dx = Math.round(((ir.left + ir.right) / 2 - (r.left + r.right) / 2) * 10) / 10;
            const dy = Math.round(((ir.top + ir.bottom) / 2 - (r.top + r.bottom) / 2) * 10) / 10;
            if (Math.abs(dx) > 2 || Math.abs(dy) > 2) {
                R.icone.push({ id: idw(box.closest('.elementor-element') || box), dx: dx, dy: dy });
            }
        });

        return R;
    };

    /* ---------- il giro ---------- */

    (async () => {
        for (const L of larghezze) {
            f.style.width = L + 'px';
            for (const u of pagine) {
                /* Niente marca in coda alla URL: una stringa di interrogazione fa
                   servire dalla cache una copia diversa da quella che vede un
                   visitatore, e la prova misurerebbe un'altra pagina. Il
                   passaggio da about:blank forza comunque il ricaricamento. */
                await new Promise(r => { f.onload = r; f.src = 'about:blank'; });
                await new Promise(r => { f.onload = () => setTimeout(r, attesaMs); f.src = u; });
                try {
                    const D = f.contentDocument, W = f.contentWindow;

                    /* la barra di scorrimento sparisce: da qui in poi clientWidth
                       coincide con la larghezza delle media query */
                    const st = D.createElement('style');
                    st.textContent = 'html::-webkit-scrollbar{width:0;height:0;display:none}' +
                                     'html{scrollbar-width:none;-ms-overflow-style:none}';
                    D.head.appendChild(st);
                    await new Promise(r => setTimeout(r, 120));

                    const vw = D.documentElement.clientWidth;
                    if (!vw || Math.abs(vw - L) > 2) {
                        window.__sgRis.push({ larghezza: L, pagina: u, errore:
                            'MISURA NON VALIDA: clientWidth ' + vw + ' invece di ' + L });
                        continue;
                    }

                    const R = esamina(D, W);
                    R.larghezza = L;
                    R.pagina = u;
                    R.titolo = (D.title || '').slice(0, 46);
                    window.__sgRis.push(R);
                } catch (e) {
                    window.__sgRis.push({ larghezza: L, pagina: u, errore: String(e) });
                }
            }
        }
        f.remove();
        window.__sgFatto = true;
    })();

    return 'avviato: ' + pagine.length + ' pagine x ' + larghezze.length +
           ' larghezze = ' + window.__sgTotale + ' prove';
};

/**
 * Stato e problemi.
 *
 * Riporta solo le combinazioni con qualcosa da guardare. `puliti` che cresce e
 * il resto vuoto e' il risultato che si vuole.
 *
 * ATTENZIONE, e vale ogni volta: questi sono CANDIDATI, non colpevoli. Prima di
 * riferire che restano nove problemi, guardali: piu' della meta' delle
 * segnalazioni di un controllo automatico, la prima volta, sono difetti del
 * controllo. E dopo ogni correzione rimisura: se il numero non cambia, quello
 * che hai corretto era un sintomo.
 */
window.sgRisultato = function () {
    const r = window.__sgRis || [];
    const dove = x => x.larghezza + 'px ' + x.pagina;
    const conProblemi = x => !x.errore && (x.scorre > 0 || x.sfora.length || x.altezze.length ||
                                           x.asse.length || x.etichette.length || x.icone.length);
    return {
        fatte: r.length,
        su: window.__sgTotale,
        finito: !!window.__sgFatto,
        puliti: r.filter(x => !x.errore && !conProblemi(x)).length,
        errori:     r.filter(x => x.errore).map(x => dove(x) + ' -> ' + x.errore),
        scorrimento: r.filter(x => !x.errore && x.scorre > 0).map(x => dove(x) + ' -> scorre ' + x.scorre + 'px'),
        sforamenti: r.filter(x => !x.errore && x.sfora.length).map(x => dove(x) + ' -> ' +
            x.sfora.map(s => s.id + ' +' + s.dx + 'px "' + s.t + '"').join(', ')),
        altezze:    r.filter(x => !x.errore && x.altezze.length).map(x => dove(x) + ' -> ' +
            x.altezze.map(a => a.sez + ' cime ' + a.scartoCima + ' altezze ' + a.scartoAltezza).join(', ')),
        fuoriAsse:  r.filter(x => !x.errore && x.asse.length).map(x => dove(x) + ' -> ' +
            x.asse.map(a => a.id + ' ' + a.fuori + 'px "' + a.t + '"').join(', ')),
        etichette:  r.filter(x => !x.errore && x.etichette.length).map(x => dove(x) + ' -> ' +
            x.etichette.map(e => '"' + e.t + '" su ' + e.righe + ' righe').join(', ')),
        icone:      r.filter(x => !x.errore && x.icone.length).map(x => dove(x) + ' -> ' +
            x.icone.map(i => i.id + ' (' + i.dx + ',' + i.dy + ')').join(', ')),
        dettaglio: r.filter(x => x.errore || conProblemi(x))
    };
};
