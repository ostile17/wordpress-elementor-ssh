<?php
/**
 * Funzioni comuni per gli script di modifica di un sito Elementor.
 *
 * Serve a non riscrivere ogni volta le stesse quattro cose: il giro ricorsivo
 * sull'albero, la lettura che non passa dalla cache, la scrittura con
 * invalidazione, il conteggio finale.
 *
 * Uso:
 *   scp scripts/helper.php scripts/miamodifica.php NOME:~/
 *   ssh NOME 'cd ~/public_html && SG_PROVA=1 wp eval-file ~/miamodifica.php'
 *
 * e nello script:
 *   require_once __DIR__ . '/helper.php';
 */

if (!defined('ABSPATH')) { echo "eseguire con: wp eval-file\n"; return; }

/**
 * Il documento Elementor di un contenuto, letto con $wpdb.
 *
 * NON usa get_post_meta di proposito: dopo una scrittura diretta la cache degli
 * oggetti restituirebbe il valore vecchio nella stessa richiesta, e i controlli
 * finali direbbero il falso.
 */
function sg_leggi_dati($post_id) {
    global $wpdb;
    return $wpdb->get_var($wpdb->prepare(
        "SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = '_elementor_data' LIMIT 1",
        $post_id
    ));
}

/**
 * Il documento decodificato, ma solo se e' sicuro riscriverlo.
 *
 * Restituisce null se il JSON e' illeggibile oppure se la ricodifica non
 * restituisce il byte identico: in quel caso riscrivere il documento
 * cambierebbe cose che non si intendeva toccare, e la pagina va guardata a mano.
 */
function sg_leggi_albero($post_id, &$motivo = null) {
    $grezzo = sg_leggi_dati($post_id);
    if ($grezzo === null) { $motivo = 'nessun documento Elementor'; return null; }

    $dati = json_decode($grezzo, true);
    if (!is_array($dati)) { $motivo = 'JSON illeggibile'; return null; }

    if (wp_json_encode($dati) !== $grezzo) {
        $motivo = 'la ricodifica non e\' identica all\'originale';
        return null;
    }
    return $dati;
}

/**
 * Giro ricorsivo su tutti i nodi dell'albero, con il nodo passato per
 * riferimento: la funzione di richiamo puo' modificarlo.
 *
 *   sg_gira($dati, function (&$nodo) { ... });
 */
function sg_gira(&$nodi, callable $azione) {
    foreach ($nodi as &$n) {
        $azione($n);
        if (!empty($n['elements'])) { sg_gira($n['elements'], $azione); }
    }
    unset($n);
}

/** Il nodo con un dato id, o null. Copia, non riferimento: per leggere. */
function sg_trova($nodi, $id) {
    foreach ($nodi as $n) {
        if (($n['id'] ?? '') === $id) { return $n; }
        if (!empty($n['elements'])) {
            $t = sg_trova($n['elements'], $id);
            if ($t) { return $t; }
        }
    }
    return null;
}

/**
 * Elimina i nodi con gli id indicati e ricompatta gli array.
 *
 * `array_values` non e' un vezzo: senza, unset lascia buchi negli indici e
 * json_encode trasforma l'array in oggetto, cambiando la struttura che
 * Elementor si aspetta.
 *
 * Restituisce gli id effettivamente trovati, cosi' chi chiama puo' accorgersi
 * se qualcosa non c'era.
 */
function sg_pota(&$nodi, array $id_da_togliere) {
    $tolti = array();
    $giro = function (&$lista) use (&$giro, $id_da_togliere, &$tolti) {
        foreach ($lista as $i => &$n) {
            if (in_array($n['id'] ?? '', $id_da_togliere, true)) {
                $tolti[] = $n['id'];
                unset($lista[$i]);
                continue;
            }
            if (!empty($n['elements'])) { $giro($n['elements']); }
        }
        unset($n);
        $lista = array_values($lista);
    };
    $giro($nodi);
    return $tolti;
}

/**
 * Scrive il documento e butta la cache renderizzata.
 *
 * `_elementor_element_cache` contiene l'HTML gia' reso: non si corregge, si
 * butta. Contiene anche il motivo per cui i filtri su
 * `elementor/widget/render_content` a volte "non funzionano": se la cache c'e',
 * il filtro non viene mai chiamato.
 */
function sg_scrivi_dati($post_id, array $dati) {
    global $wpdb;
    $wpdb->update(
        $wpdb->postmeta,
        array('meta_value' => wp_json_encode($dati)),
        array('post_id' => $post_id, 'meta_key' => '_elementor_data')
    );
    sg_butta_cache($post_id);
}

/** Butta la cache renderizzata di un contenuto. */
function sg_butta_cache($post_id) {
    delete_post_meta($post_id, '_elementor_element_cache');
    delete_post_meta($post_id, '_elementor_css');
    clean_post_cache($post_id);
}

/**
 * Butta la cache di TUTTI i template.
 *
 * Va fatto anche quando si e' toccata una pagina sola: intestazione e pie' di
 * pagina compaiono su ogni pagina, e il loro HTML reso e' in cache a parte.
 */
function sg_butta_cache_template() {
    foreach (get_posts(array(
        'post_type' => 'elementor_library', 'numberposts' => -1,
        'fields' => 'ids', 'post_status' => 'any',
    )) as $id) {
        delete_post_meta($id, '_elementor_element_cache');
        delete_post_meta($id, '_elementor_css');
    }
}

/**
 * Quante volte una stringa compare nei contenuti VIVI.
 *
 * Le revisioni sono escluse di proposito: contengono il testo vecchio, non
 * vengono mai mostrate, e contarle fa sembrare non finito un lavoro finito.
 *
 * $dove: 'elementor' | 'meta' | 'testo' | 'tutto'
 */
function sg_conta_occorrenze($stringa, $dove = 'tutto') {
    global $wpdb;
    $n = 0;
    $vivi = "p.post_status IN ('publish','draft') AND p.post_type NOT IN ('revision')";

    if ($dove === 'elementor' || $dove === 'tutto') {
        foreach ($wpdb->get_col(
            "SELECT m.meta_value FROM {$wpdb->postmeta} m
               JOIN {$wpdb->posts} p ON p.ID = m.post_id AND $vivi
              WHERE m.meta_key = '_elementor_data'") as $v) {
            $n += substr_count($v, $stringa);
        }
    }
    if ($dove === 'meta' || $dove === 'tutto') {
        foreach ($wpdb->get_col(
            "SELECT m.meta_value FROM {$wpdb->postmeta} m
               JOIN {$wpdb->posts} p ON p.ID = m.post_id AND $vivi
              WHERE m.meta_key NOT LIKE '%_cache%' AND m.meta_key <> '_elementor_data'
                AND m.meta_key <> '_elementor_css'") as $v) {
            $n += substr_count((string) $v, $stringa);
        }
    }
    if ($dove === 'testo' || $dove === 'tutto') {
        foreach ($wpdb->get_col(
            "SELECT post_content FROM {$wpdb->posts} p WHERE $vivi") as $v) {
            $n += substr_count((string) $v, $stringa);
        }
        foreach ($wpdb->get_col(
            "SELECT post_excerpt FROM {$wpdb->posts} p WHERE $vivi") as $v) {
            $n += substr_count((string) $v, $stringa);
        }
    }
    return $n;
}

/**
 * Dove compare una stringa, con un po' di contesto. Per capire prima di agire.
 *
 * Restituisce righe pronte da stampare: contenuto, chiave, frase.
 */
function sg_dove_compare($stringa, $contorno = 70) {
    global $wpdb;
    $out = array();
    $vivi = "p.post_status IN ('publish','draft') AND p.post_type NOT IN ('revision')";
    $like = '%' . $wpdb->esc_like($stringa) . '%';

    $righe = $wpdb->get_results($wpdb->prepare(
        "SELECT m.post_id, m.meta_key, m.meta_value FROM {$wpdb->postmeta} m
           JOIN {$wpdb->posts} p ON p.ID = m.post_id AND $vivi
          WHERE m.meta_value LIKE %s AND m.meta_key NOT LIKE '%%_cache%%'
            AND m.meta_key <> '_elementor_css'", $like));

    foreach ($righe as $r) {
        $re = '/.{0,' . (int) $contorno . '}' . preg_quote($stringa, '/') . '.{0,' . (int) $contorno . '}/su';
        if (preg_match_all($re, $r->meta_value, $m)) {
            foreach ($m[0] as $x) {
                $out[] = sprintf('%-6s %-26s ...%s...', $r->post_id, mb_substr($r->meta_key, 0, 26),
                    trim(preg_replace('/\s+/u', ' ', strip_tags($x))));
            }
        }
    }

    foreach ($wpdb->get_results($wpdb->prepare(
        "SELECT ID, post_content FROM {$wpdb->posts} p WHERE $vivi AND post_content LIKE %s", $like)) as $r) {
        $re = '/.{0,' . (int) $contorno . '}' . preg_quote($stringa, '/') . '.{0,' . (int) $contorno . '}/su';
        if (preg_match_all($re, $r->post_content, $m)) {
            foreach ($m[0] as $x) {
                $out[] = sprintf('%-6s %-26s ...%s...', $r->ID, 'post_content',
                    trim(preg_replace('/\s+/u', ' ', strip_tags($x))));
            }
        }
    }
    return $out;
}

/** Vero se lo script sta girando a vuoto. */
function sg_prova() { return getenv('SG_PROVA') === '1'; }

/**
 * Resoconto finale uniforme.
 *
 * Chi legge deve capire in dieci secondi se applicare o no, quindi: cosa e'
 * stato fatto, cosa e' mancato, e niente altro.
 */
function sg_resoconto(array $fatti, array $mancanti = array()) {
    echo 'fatti: ' . count($fatti) . "\n";
    foreach ($fatti as $f) { echo "  $f\n"; }
    if ($mancanti) {
        echo 'MANCANTI: ' . count($mancanti) . "\n";
        foreach ($mancanti as $m) { echo "  $m\n"; }
    }
    if (sg_prova()) { echo "\n(prova a vuoto: non ho scritto niente)\n"; }
    return empty($mancanti);
}
