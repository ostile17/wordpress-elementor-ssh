<?php
/**
 * Fotografia del sito. Da eseguire per prima cosa, prima di proporre qualsiasi
 * modifica.
 *
 *   scp scripts/ricognizione.php NOME:~/
 *   ssh NOME 'cd ~/public_html && wp eval-file ~/ricognizione.php'
 *
 * Sola lettura: non scrive niente, non cancella niente.
 *
 * Risponde alle domande che servono sempre e che altrimenti si scoprono una per
 * una a costo di un giro di richieste ciascuna: qual e' il prefisso delle
 * tabelle, quale template fa l'intestazione, dove stanno title e description,
 * quale plugin di cache va svuotato, se il sito e' visibile ai motori.
 */

if (!defined('ABSPATH')) { echo "eseguire con: wp eval-file\n"; return; }

global $wpdb;

function sg_r($etichetta, $valore) {
    printf("  %-34s %s\n", $etichetta, is_bool($valore) ? ($valore ? 'si' : 'no') : $valore);
}
function sg_titolo($t) { echo "\n" . str_repeat('=', 62) . "\n$t\n" . str_repeat('=', 62) . "\n"; }

/* ── il sito ────────────────────────────────────────────────────────────── */
sg_titolo('SITO');
sg_r('indirizzo', home_url('/'));
sg_r('prefisso tabelle', $wpdb->prefix);
sg_r('WordPress', get_bloginfo('version'));
sg_r('PHP', PHP_VERSION);
sg_r('lingua', get_locale());
sg_r('fuso orario', wp_timezone_string());
sg_r('formato data impostato', get_option('date_format'));
sg_r('visibile ai motori', get_option('blog_public') ? 'SI' : 'NO (noindex globale)');
sg_r('tema attivo', wp_get_theme()->get('Name') . ' ' . wp_get_theme()->get('Version'));
$padre = wp_get_theme()->parent();
sg_r('tema padre', $padre ? $padre->get('Name') : '(nessuno)');

/* ── plugin ─────────────────────────────────────────────────────────────── */
sg_titolo('PLUGIN ATTIVI');
$attivi = get_option('active_plugins', array());
$interessanti = array('elementor', 'cache', 'seo', 'rank-math', 'wordfence', 'smtp', 'iubenda',
                      'litespeed', 'wp-rocket', 'jeg', 'jkit', 'redirect', 'form');
foreach ($attivi as $p) {
    $dati = @get_plugin_data(WP_PLUGIN_DIR . '/' . $p);
    $nome = $dati['Name'] ?? $p;
    $ver  = $dati['Version'] ?? '';
    $nota = '';
    foreach ($interessanti as $k) {
        if (stripos($p, $k) !== false || stripos($nome, $k) !== false) { $nota = '  <-- rilevante'; break; }
    }
    printf("  %-46s %-10s%s\n", mb_substr($nome, 0, 46), $ver, $nota);
}

/* ── mu-plugins ─────────────────────────────────────────────────────────── */
sg_titolo('MU-PLUGINS (sempre attivi, non disattivabili dal pannello)');
$mu = glob(WPMU_PLUGIN_DIR . '/*.php');
if (!$mu) { echo "  (nessuno)\n"; }
foreach ((array) $mu as $f) {
    printf("  %-40s %6d righe\n", basename($f), count(file($f)));
}

/* ── Elementor ──────────────────────────────────────────────────────────── */
sg_titolo('ELEMENTOR');
sg_r('Elementor', defined('ELEMENTOR_VERSION') ? ELEMENTOR_VERSION : '(non attivo)');
sg_r('Elementor Pro', defined('ELEMENTOR_PRO_VERSION') ? ELEMENTOR_PRO_VERSION : 'no');
$kit = (int) get_option('elementor_active_kit');
sg_r('kit attivo (id)', $kit ?: '(nessuno)');

if ($kit) {
    $imp = get_post_meta($kit, '_elementor_page_settings', true);
    if (is_array($imp)) {
        echo "\n  colori globali:\n";
        foreach (array_merge($imp['system_colors'] ?? array(), $imp['custom_colors'] ?? array()) as $c) {
            printf("    %-12s %-22s %s\n", $c['_id'] ?? '?', mb_substr($c['title'] ?? '', 0, 22), $c['color'] ?? '');
        }
        echo "\n  tipografie globali:\n";
        foreach (array_merge($imp['system_typography'] ?? array(), $imp['custom_typography'] ?? array()) as $t) {
            printf("    %-12s %-22s %s %s\n", $t['_id'] ?? '?', mb_substr($t['title'] ?? '', 0, 22),
                $t['typography_font_family'] ?? '', json_encode($t['typography_font_size'] ?? null));
        }
    }
}

/* ── template ───────────────────────────────────────────────────────────── */
sg_titolo('TEMPLATE (elementor_library)');
$tpl = get_posts(array('post_type' => 'elementor_library', 'numberposts' => -1, 'post_status' => 'any'));
$condizioni = get_option('elementor_pro_theme_builder_conditions', array());
foreach ($tpl as $t) {
    $tipo = get_post_meta($t->ID, '_elementor_template_type', true);
    $cond = '';
    foreach ($condizioni as $gruppo) {
        if (isset($gruppo[$t->ID])) { $cond = implode(', ', (array) $gruppo[$t->ID]); }
    }
    printf("  %-5s %-30s %-16s %s%s\n", $t->ID, mb_substr($t->post_title, 0, 30), $tipo,
        $t->post_status !== 'publish' ? '[' . $t->post_status . '] ' : '',
        $cond ? 'ATTIVO: ' . $cond : '');
}
echo "\n  I template senza condizioni non vengono mostrati: spesso sono avanzi del template kit.\n";

/* ── pagine e articoli ──────────────────────────────────────────────────── */
sg_titolo('PAGINE E ARTICOLI PUBBLICATI');
$righe = $wpdb->get_results(
    "SELECT p.ID, p.post_title, p.post_type, p.post_name,
            (SELECT COUNT(*) FROM {$wpdb->postmeta} m
              WHERE m.post_id = p.ID AND m.meta_key = '_elementor_data') AS elementor
       FROM {$wpdb->posts} p
      WHERE p.post_status = 'publish' AND p.post_type IN ('page','post')
      ORDER BY p.post_type, p.ID"
);
foreach ($righe as $r) {
    printf("  %-5s %-9s %-34s /%s/%s\n", $r->ID, $r->post_type, mb_substr($r->post_title, 0, 34),
        $r->post_name, $r->elementor ? '  [elementor]' : '');
}
sg_r('pagina iniziale', get_option('show_on_front') === 'page'
    ? get_option('page_on_front') . ' (' . get_the_title(get_option('page_on_front')) . ')'
    : 'ultimi articoli');
sg_r('pagina del blog', get_option('page_for_posts') ?: '(nessuna)');

/* ── menu ───────────────────────────────────────────────────────────────── */
sg_titolo('MENU');
foreach (wp_get_nav_menus() as $m) {
    $posizioni = array_keys(get_nav_menu_locations(), $m->term_id, true);
    printf("  %s (slug: %s, %d voci)%s\n", $m->name, $m->slug, $m->count,
        $posizioni ? '  posizione: ' . implode(', ', $posizioni) : '  NON assegnato');
    foreach (wp_get_nav_menu_items($m->term_id) ?: array() as $i) {
        printf("      %-6s %-28s %-40s%s\n", $i->db_id, mb_substr($i->title, 0, 28),
            str_replace(home_url(), '', $i->url), $i->menu_item_parent ? '  figlio di ' . $i->menu_item_parent : '');
    }
}

/* ── SEO ────────────────────────────────────────────────────────────────── */
sg_titolo('SEO');
$chiavi = $wpdb->get_col(
    "SELECT DISTINCT meta_key FROM {$wpdb->postmeta}
      WHERE meta_key LIKE 'rank_math%' OR meta_key LIKE '_yoast%' OR meta_key LIKE '_aioseo%'
      LIMIT 20"
);
sg_r('chiavi meta SEO trovate', $chiavi ? implode(', ', array_slice($chiavi, 0, 6)) : '(nessuna)');
$lunghi = $wpdb->get_results(
    "SELECT pm.post_id, CHAR_LENGTH(pm.meta_value) AS n, pm.meta_value
       FROM {$wpdb->postmeta} pm JOIN {$wpdb->posts} p ON p.ID = pm.post_id AND p.post_status = 'publish'
      WHERE pm.meta_key IN ('rank_math_title','_yoast_wpseo_title') AND CHAR_LENGTH(pm.meta_value) > 60"
);
if ($lunghi) {
    echo "  title oltre 60 caratteri:\n";
    foreach ($lunghi as $l) { printf("    %-5s (%d) %s\n", $l->post_id, $l->n, $l->meta_value); }
}

/* ── cache ──────────────────────────────────────────────────────────────── */
sg_titolo('CACHE');
$cache = array(
    'LiteSpeed'          => defined('LSCWP_V') || is_plugin_active('litespeed-cache/litespeed-cache.php'),
    'WP Rocket'          => defined('WP_ROCKET_VERSION'),
    'W3 Total'           => defined('W3TC'),
    'WP Super'           => defined('WPCACHEHOME'),
    'cache oggetti esterna' => (bool) wp_using_ext_object_cache(),
);
foreach ($cache as $n => $c) { sg_r($n, $c); }
echo "\n  Comando di svuotamento, se LiteSpeed:  wp litespeed-purge all\n";

/* ── invii dei form ─────────────────────────────────────────────────────── */
$tab_invii = $wpdb->prefix . 'e_submissions';
if ($wpdb->get_var("SHOW TABLES LIKE '$tab_invii'") === $tab_invii) {
    sg_titolo('ARCHIVIO INVII DEI FORM');
    sg_r('righe in archivio', $wpdb->get_var("SELECT COUNT(*) FROM $tab_invii"));
    echo "  Attenzione: se contiene richieste vere, sono dati personali. Non stamparle.\n";
}

/* ── costanti in wp-config ──────────────────────────────────────────────── */
sg_titolo('COSTANTI IN WP-CONFIG.PHP (solo i nomi, mai i valori)');
/* Non uso get_defined_constants: i plugin ne definiscono centinaia e la vera
   informazione affoga nel rumore. Quello che serve sapere e' cosa ha configurato
   una PERSONA per questo sito, e quello sta in wp-config.php. */
$config = null;
foreach (array(ABSPATH . 'wp-config.php', dirname(ABSPATH) . '/wp-config.php') as $c) {
    if (is_readable($c)) { $config = $c; break; }
}
if (!$config) {
    echo "  (wp-config.php non leggibile da qui)\n";
} else {
    preg_match_all("/define\s*\(\s*['\"]([A-Z0-9_]+)['\"]/", file_get_contents($config), $m);
    $standard = array('DB_NAME','DB_USER','DB_PASSWORD','DB_HOST','DB_CHARSET','DB_COLLATE',
        'AUTH_KEY','SECURE_AUTH_KEY','LOGGED_IN_KEY','NONCE_KEY','AUTH_SALT','SECURE_AUTH_SALT',
        'LOGGED_IN_SALT','NONCE_SALT','WP_DEBUG','ABSPATH','WPLANG');
    $suoi = array_values(array_diff(array_unique($m[1]), $standard));
    echo '  ' . ($suoi ? implode(', ', $suoi) : '(nessuna oltre quelle standard)') . "\n";
}
echo "\n  I valori non vengono stampati di proposito: possono essere segreti.\n";
echo "  Se compare qualcosa tipo *_PASS, *_KEY o *_TOKEN, la credenziale e' gia'\n";
echo "  configurata: si riusa la costante, non si chiede il valore all'utente.\n";

sg_titolo('FATTO');
echo "Leggi questo output prima di scrivere una riga di codice.\n";
