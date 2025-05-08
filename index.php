<?php
/**
 * WordPress Access Controller
 * Github repository: WP-Access-Control-type-G
 * Rif: https://claude.ai/chat/90553400-b193-4108-8b80-bc720f59762d
 * 20250508
 * Questo script funge da gatekeeper per WordPress, controllando l'accesso al sito
 * in diverse modalità (aperto, chiuso, manutenzione, ecc) e fornendo una console
 * di amministrazione accessibile tramite parametri URL.
 *
 * @version 1.0
 */

// Evita accesso diretto
if (!defined('ABSPATH') && !isset($_SERVER['SCRIPT_FILENAME']) && !preg_match('/index\.php$/', $_SERVER['SCRIPT_FILENAME'])) {
    die('Accesso diretto non consentito');
}

// -------------------------------------------------------------------------
// CONFIGURAZIONE INIZIALE
// -------------------------------------------------------------------------

// Configurazione statica - Modificare qui all'occorrenza
$config_static = [
    // Comando di attivazione e password superadmin
    'activation_command' => 'wpaccesscontrol',
    'superadmin_password' => 'superpassword',

    // IP superadmin (sempre autorizzati)
    'superadmin_ips' => [
        '127.0.0.1',  // Localhost
        '::1'
        // Aggiungi qui altri IP fidati
    ],

    // Percorso file configurazione
    'config_file' => 'wp-content/pin-access-manager-config.json',

    // Tempo di timeout operatore (in secondi)
    'operator_timeout' => 600,  // 10 minuti

    // Numero massimo di visitatori registrati da memorizzare
    'max_visitors' => 50,

    // Numero massimo di comandi in cronologia
    'command_history_size' => 20,

    // Numero massimo di eventi di log da conservare
    'log_max_entries' => 100,

    // Comportamento quando c'è solo il comando di attivazione
    'show_console_on_empty_activation' => true,  // true = mostra console, false = non fa nulla

    // Configurazione predefinita (utilizzata se il file non esiste)
    'default_config' => [
        'mode' => 'open',         // open, openfront, openback, closed, maintenance, message, redirect
        'default_mode' => 'open', // Modalità predefinita
        'message' => '',          // Messaggio personalizzato
        'redirect_url' => '',     // URL di redirect
        'blacklist_redirect' => '', // URL di redirect per IP in blacklist
        'expires' => 0,           // Timestamp di scadenza (0 = mai)
        'whitelist' => [],        // Array di IP in whitelist
        'blacklist' => [],        // Array di IP in blacklist
        'operators' => [],        // Array di operatori [ip => [expires => timestamp]]
        'visitors' => [],         // Array di visitatori [ip => [name => nome, timestamp => timestamp]]
        'password' => '',         // Password dinamica aggiuntiva
        'bypass_patterns' => [],  // Pattern URL da bypassare (array di stringhe/regex)
        'block_patterns' => [],   // Pattern URL da bloccare (array di stringhe/regex)
        'show_console_on_empty_activation' => true,  // Predefinito a true
        'logging' => false,       // Logging attivo/disattivo
        'log' => []               // Array di log [timestamp => [ip => ip, action => azione]]
    ]
];

// -------------------------------------------------------------------------
// INIZIALIZZAZIONE VARIABILI
// -------------------------------------------------------------------------

// Inizializzazione variabili di stato
$tool_activated = false;           // Tool attivato
$is_superadmin = false;            // Utente è superadmin
$is_operator = false;              // Utente è operatore
$bypass_blacklist = false;         // Ignorare la blacklist
$commands_to_execute = [];         // Comandi da eseguire
$save_config_needed = false;       // Necessario salvare la configurazione
$output = '';                      // Output per la console

// Ottieni IP corrente
$current_ip = $_SERVER['REMOTE_ADDR'];

// Ottieni timestamp corrente
$now = time();

// Controlla se l'utente è un superadmin (IP fidato)
if (in_array($current_ip, $config_static['superadmin_ips'])) {
    $is_superadmin = true;
    $bypass_blacklist = true;
}

// -------------------------------------------------------------------------
// CARICAMENTO CONFIGURAZIONE
// -------------------------------------------------------------------------

/**
 * Carica la configurazione dal file JSON
 */
function load_config_NEW($config_file, $default_config) {
    $config = $default_config;

    if (file_exists($config_file)) {
        $json = @file_get_contents($config_file);
        $loaded = ($json !== false)
            ? @json_decode($json, true)
            : null;

        if (is_array($loaded)) {
            // Sovrascrive solo i valori scalari, ma mantiene la struttura degli array di default
            $config = array_replace_recursive($default_config, $loaded);
        }
    }

    return $config;
}

function load_config($config_file, $default_config) {
    $config = $default_config;

    // Verifica se il file esiste
    if (file_exists($config_file)) {
        $json_content = @file_get_contents($config_file);
        if ($json_content !== false) {
            $loaded_config = @json_decode($json_content, true);
            if ($loaded_config !== null) {
                // Unisci la configurazione caricata con quella predefinita per aggiungere campi mancanti
                $config = array_merge($default_config, $loaded_config);
            }
        }
    }

    return $config;
}

/**
 * Salva la configurazione nel file JSON
 */
function save_config($config, $config_file) {
    $dir = dirname($config_file);
    if (!file_exists($dir)) {
        @mkdir($dir, 0755, true);
    }

    $json_content = json_encode($config, JSON_PRETTY_PRINT);
    $result = @file_put_contents($config_file, $json_content, LOCK_EX);

    return ($result !== false);
}

/**
 * Aggiunge un evento al log
 */
function add_log_entry(&$config, $action, $ip) {
    if (!$config['logging']) {
        return;
    }

    $entry = [
        'timestamp' => time(),
        'ip' => $ip,
        'action' => $action
    ];

    // Aggiungi in testa all'array per avere i più recenti all'inizio
    array_unshift($config['log'], $entry);

    // Limita il numero di voci
    if (count($config['log']) > $GLOBALS['config_static']['log_max_entries']) {
        $config['log'] = array_slice($config['log'], 0, $GLOBALS['config_static']['log_max_entries']);
    }
}

// Carica la configurazione (se non esiste, ritorna il default)
$config = load_config($config_static['config_file'], $config_static['default_config']);

// Se il file non c'era davvero, crealo subito
if (! file_exists($config_static['config_file']) ) {
    save_config($config, $config_static['config_file']);
}

// -------------------------------------------------------------------------
// FUNZIONI UTILITÀ
// -------------------------------------------------------------------------

/**
 * Formatta un timestamp in formato leggibile
 */
function format_timestamp($timestamp) {
    return date('Y-m-d H:i:s', $timestamp);
}

/**
 * Valida un indirizzo IP
 */
function is_valid_ip($ip) {
    return filter_var($ip, FILTER_VALIDATE_IP) !== false;
}

/**
 * Converte una stringa di durata (es. 1h, 30m, 2d) in secondi
 */
function duration_to_seconds($duration) {
    if (empty($duration)) {
        return 0; // Durata permanente
    }

    // Cerca un numero seguito da un'unità (m, h, d, w)
    if (preg_match('/^(\d+)([mhdw])$/i', $duration, $matches)) {
        $value = (int)$matches[1];
        $unit = strtolower($matches[2]);

        switch ($unit) {
            case 'm': return $value * 60;        // Minuti
            case 'h': return $value * 3600;      // Ore
            case 'd': return $value * 86400;     // Giorni
            case 'w': return $value * 604800;    // Settimane
        }
    }

    // Formato non riconosciuto, prova a interpretare come secondi
    return intval($duration);
}

/**
 * Controlla se un pattern corrisponde all'URI corrente
 */
function uri_matches_pattern($pattern, $uri) {
    // Se il pattern inizia e finisce con '/', trattalo come regex
    if (substr($pattern, 0, 1) === '/' && substr($pattern, -1) === '/') {
        return @preg_match($pattern, $uri) === 1;
    }

    // Altrimenti cerca la stringa nell'URI
    return stripos($uri, $pattern) !== false;
}

/**
 * Pulisce gli operatori scaduti dalla configurazione
 */
function cleanup_expired_operators(array &$config, $now) {
    if (! isset($config['operators']) || ! is_array($config['operators'])) {
        // niente da fare se non è un array
        return false;
    }

    $changed = false;
    foreach ($config['operators'] as $ip => $data) {
        if ($data['expires'] > 0 && $data['expires'] < $now) {
            unset($config['operators'][$ip]);
            $changed = true;
        }
    }
    return $changed;
}

/**
 * Formatta l'output dello stato corrente
 */
function format_status_output($config, $now, $is_superadmin, $current_ip) {
    $output = "=== STATO DEL SISTEMA ===\n";

    // Modalità sito
    $output .= "Modalità: " . $config['mode'];

    // Stato temporaneo o predefinito
    if ($config['expires'] > 0) {
        $remaining = $config['expires'] - $now;
        if ($remaining > 0) {
            $output .= " (temporanea, scade tra " . floor($remaining / 60) . " minuti)";
        } else {
            $output .= " (SCADUTO)";
        }
        $output .= "\nModalità predefinita: " . $config['default_mode'];
    } else {
        $output .= " (predefinita)";
    }
    $output .= "\n";

    // Messaggio personalizzato
    if ($config['mode'] === 'message' && !empty($config['message'])) {
        $output .= "Messaggio: " . $config['message'] . "\n";
    }

    // URL di reindirizzamento
    if ($config['mode'] === 'redirect' && !empty($config['redirect_url'])) {
        $output .= "Reindirizza a: " . $config['redirect_url'] . "\n";
    }

    // IP corrente e status
    $output .= "IP Corrente: " . $current_ip;
    if ($is_superadmin) {
        $output .= " (SUPERADMIN)";
    }

    // Controlla se è un operatore
    $is_operator = isset($config['operators'][$current_ip]);
    if ($is_operator) {
        $expires = $config['operators'][$current_ip]['expires'];
        $remaining = $expires - $now;
        $output .= " (OPERATORE, scade tra " . floor($remaining / 60) . " minuti)";
    }

    // Controlla se è in whitelist
    if (in_array($current_ip, $config['whitelist'])) {
        $output .= " (in WHITELIST)";
    }

    // Controlla se è in blacklist
    if (in_array($current_ip, $config['blacklist'])) {
        $output .= " (in BLACKLIST)";
    }
    $output .= "\n";

    // Aggiungi lo stato dell'autoshow
    $show_console_value = isset($config['show_console_on_empty_activation'])
        ? $config['show_console_on_empty_activation']
        : $GLOBALS['config_static']['show_console_on_empty_activation'];

    $output .= "Mostra console su attivazione vuota: " . ($show_console_value ? "Abilitato" : "Disabilitato") . "\n";

    $output .= "\n";

    // Whitelist
    $output .= "=== WHITELIST (" . count($config['whitelist']) . ") ===\n";
    if (!empty($config['whitelist'])) {
        $output .= implode(", ", $config['whitelist']) . "\n";
    } else {
        $output .= "Nessun IP in whitelist\n";
    }

    // Blacklist
    $output .= "\n=== BLACKLIST (" . count($config['blacklist']) . ") ===\n";
    if (!empty($config['blacklist'])) {
        $output .= implode(", ", $config['blacklist']) . "\n";
    } else {
        $output .= "Nessun IP in blacklist\n";
    }

    // Operatori
    $output .= "\n=== OPERATORI (" . count($config['operators']) . ") ===\n";
    if (!empty($config['operators'])) {
        foreach ($config['operators'] as $ip => $data) {
            $remaining = $data['expires'] - $now;
            if ($remaining > 0) {
                $output .= $ip . " (scade tra " . floor($remaining / 60) . " minuti)\n";
            } else {
                $output .= $ip . " (SCADUTO)\n";
            }
        }
    } else {
        $output .= "Nessun operatore attivo\n";
    }

    // Bypass patterns
    $output .= "\n=== BYPASS PATTERNS (" . count($config['bypass_patterns']) . ") ===\n";
    if (!empty($config['bypass_patterns'])) {
        $output .= implode("\n", $config['bypass_patterns']) . "\n";
    } else {
        $output .= "Nessun pattern di bypass configurato\n";
    }

    // Block patterns
    $output .= "\n=== BLOCK PATTERNS (" . count($config['block_patterns']) . ") ===\n";
    if (!empty($config['block_patterns'])) {
        $output .= implode("\n", $config['block_patterns']) . "\n";
    } else {
        $output .= "Nessun pattern di blocco configurato\n";
    }

    // Visitatori registrati
    $output .= "\n=== VISITATORI REGISTRATI (" . count($config['visitors']) . ") ===\n";
    if (!empty($config['visitors'])) {
        foreach ($config['visitors'] as $ip => $data) {
            $output .= $ip . " - " . $data['name'] . " (" . format_timestamp($data['timestamp']) . ")\n";
        }
    } else {
        $output .= "Nessun visitatore registrato\n";
    }

    // Aggiungi lo stato dell'autoshow
    $show_console_value = isset($config['show_console_on_empty_activation'])
        ? $config['show_console_on_empty_activation']
        : $GLOBALS['config_static']['show_console_on_empty_activation'];

    $output .= "Mostra console su attivazione vuota: " . ($show_console_value ? "Abilitato" : "Disabilitato") . "\n";

    // Log (se attivo)
    if ($config['logging']) {
        $output .= "\n=== LOG (ultimi " . min(5, count($config['log'])) . ") ===\n";
        $log_slice = array_slice($config['log'], 0, 5);
        if (!empty($log_slice)) {
            foreach ($log_slice as $entry) {
                $output .= format_timestamp($entry['timestamp']) . " - " . $entry['ip'] . " - " . $entry['action'] . "\n";
            }
        } else {
            $output .= "Nessun evento nel log\n";
        }
    }

    return $output;
}

// -------------------------------------------------------------------------
// DEFINIZIONE COMANDI
// -------------------------------------------------------------------------

// Definizione dei comandi disponibili
$commands = [
    // Comando di help
    'help' => [
        'aliases' => ['?', 'aiuto'],
        'description' => 'Mostra la lista dei comandi disponibili',
        'requires_value' => false,
        'visible_in_help' => true,
        'menu_order' => 1
    ],

    // Comando di status
    'status' => [
        'aliases' => ['stato', 'st'],
        'description' => 'Mostra lo stato attuale del sistema',
        'requires_value' => false,
        'visible_in_help' => true,
        'menu_order' => 2
    ],

     // Attiva/disattiva apertura console se tool attivato
    'setautoshow' => [
        'aliases' => ['autoshow', 'showempty'],
        'description' => 'Imposta se mostrare la console quando attivata senza comandi [on/off]',
        'requires_value' => true,
        'visible_in_help' => true,
        'menu_order' => 3
    ],

    // Modalità manutenzione
    'manut' => [
        'aliases' => ['maintenance', 'manutenzione'],
        'description' => 'Imposta il sito in modalità manutenzione [durata opzionale: 1h, 30m, 2d]',
        'requires_value' => 'optional',
        'visible_in_help' => true,
        'menu_order' => 10
    ],

    // Modalità messaggio
    'msgset' => [
        'aliases' => ['message', 'messaggio'],
        'description' => 'Imposta un messaggio personalizzato [testo messaggio]',
        'requires_value' => true,
        'visible_in_help' => true,
        'menu_order' => 11
    ],

    // Cancella messaggio
    'msgclear' => [
        'aliases' => ['nomsg', 'nomessage'],
        'description' => 'Cancella il messaggio personalizzato',
        'requires_value' => false,
        'visible_in_help' => true,
        'menu_order' => 13
    ],

    // Modalità redirect
    'redirect' => [
        'aliases' => ['redir', 'url'],
        'description' => 'Reindirizza a un URL specifico [url]',
        'requires_value' => true,
        'visible_in_help' => true,
        'menu_order' => 14
    ],

    // Apri tutto
    'open' => [
        'aliases' => ['apri', 'aperto'],
        'description' => 'Imposta il sito in modalità aperta [durata opzionale: 1h, 30m, 2d]',
        'requires_value' => 'optional',
        'visible_in_help' => true,
        'menu_order' => 15
    ],

    // Apri solo frontend
    'openfront' => [
        'aliases' => ['frontonly'],
        'description' => 'Apri solo il frontend del sito [durata opzionale: 1h, 30m, 2d]',
        'requires_value' => 'optional',
        'visible_in_help' => true,
        'menu_order' => 16
    ],

    // Apri solo backend
    'openback' => [
        'aliases' => ['backonly'],
        'description' => 'Apri solo il backend del sito [durata opzionale: 1h, 30m, 2d]',
        'requires_value' => 'optional',
        'visible_in_help' => true,
        'menu_order' => 17
    ],

    // Chiudi tutto
    'closed' => [
        'aliases' => ['chiudi', 'chiuso'],
        'description' => 'Imposta il sito in modalità chiusa [durata opzionale: 1h, 30m, 2d]',
        'requires_value' => 'optional',
        'visible_in_help' => true,
        'menu_order' => 18
    ],

    '--- whitelist & blacklist ---' => [
        'visible_in_help' => true,
        'menu_order' => 19
    ],

    // Aggiungi IP a whitelist
    'wladd' => [
        'aliases' => ['whitelist'],
        'description' => 'Aggiungi IP alla whitelist [ip o vuoto per IP corrente]',
        'requires_value' => 'optional',
        'visible_in_help' => true,
        'menu_order' => 20
    ],

    // Rimuovi IP da whitelist
    'wlremove' => [
        'aliases' => ['unwl', 'nowhitelist'],
        'description' => 'Rimuovi IP dalla whitelist [ip o vuoto per IP corrente]',
        'requires_value' => 'optional',
        'visible_in_help' => true,
        'menu_order' => 21
    ],

    // Aggiungi IP a blacklist
    'bladd' => [
        'aliases' => ['blacklist', 'ipblock'],
        'description' => 'Aggiungi IP alla blacklist [ip o vuoto per IP corrente]',
        'requires_value' => 'optional',
        'visible_in_help' => true,
        'menu_order' => 22
    ],

    // Rimuovi IP da blacklist
    'blremove' => [
        'aliases' => ['unbl', 'noblacklist', 'ipunblock'],
        'description' => 'Rimuovi IP dalla blacklist [ip o vuoto per IP corrente]',
        'requires_value' => 'optional',
        'visible_in_help' => true,
        'menu_order' => 23
    ],

    // Cancella whitelist
    'clearwl' => [
        'aliases' => ['wlclear'],
        'description' => 'Svuota la whitelist',
        'requires_value' => false,
        'visible_in_help' => true,
        'menu_order' => 24
    ],

    // Cancella blacklist
    'clearbl' => [
        'aliases' => ['blclear'],
        'description' => 'Svuota la blacklist',
        'requires_value' => false,
        'visible_in_help' => true,
        'menu_order' => 25
    ],

    '--- bypass & block ---' => [
        'visible_in_help' => true,
        'menu_order' => 29
    ],

    // Aggiunge pattern bypass
    'bypassadd' => [
        'aliases' => ['addbypass'],
        'description' => 'Aggiungi un pattern URL da bypassare [pattern]',
        'requires_value' => true,
        'visible_in_help' => true,
        'menu_order' => 30
    ],

    // Rimuove pattern bypass
    'bypassremove' => [
        'aliases' => ['rmbypass'],
        'description' => 'Rimuovi un pattern URL da bypassare [pattern]',
        'requires_value' => true,
        'visible_in_help' => true,
        'menu_order' => 31
    ],

    // Aggiunge pattern block
    'blockadd' => [
        'aliases' => ['addblock'],
        'description' => 'Aggiungi un pattern URL da bloccare [pattern]',
        'requires_value' => true,
        'visible_in_help' => true,
        'menu_order' => 32
    ],

    // Rimuove pattern block
    'blockremove' => [
        'aliases' => ['rmblock'],
        'description' => 'Rimuovi un pattern URL da bloccare [pattern]',
        'requires_value' => true,
        'visible_in_help' => true,
        'menu_order' => 33
    ],

    // Imposta URL di redirect per blacklist
    'blredirect' => [
        'aliases' => ['blredir'],
        'description' => 'Imposta URL di redirect per IP in blacklist [url o vuoto per disabilitare]',
        'requires_value' => 'optional',
        'visible_in_help' => true,
        'menu_order' => 34
    ],

    '--- users ---' => [
        'visible_in_help' => true,
        'menu_order' => 39
    ],

    // Pulisci operatori
    'clearop' => [
        'aliases' => ['opclear'],
        'description' => 'Rimuovi tutti gli operatori attivi',
        'requires_value' => false,
        'visible_in_help' => true,
        'menu_order' => 40
    ],

    // Pulisci visitatori
    'clearvisitors' => [
        'aliases' => ['clearvs'],
        'description' => 'Cancella la lista dei visitatori registrati',
        'requires_value' => false,
        'visible_in_help' => true,
        'menu_order' => 41
    ],

    '--- others ---' => [
        'visible_in_help' => true,
        'menu_order' => 49
    ],

    // Backup configurazione
    'backup' => [
        'aliases' => ['export'],
        'description' => 'Esporta la configurazione corrente',
        'requires_value' => false,
        'visible_in_help' => true,
        'menu_order' => 50
    ],

    // Ripristina configurazione
    'restore' => [
        'aliases' => ['import'],
        'description' => 'Importa una configurazione salvata [filename]',
        'requires_value' => true,
        'visible_in_help' => true,
        'menu_order' => 51
    ],

    // Reset configurazione
    'reset' => [
        'aliases' => ['default'],
        'description' => 'Ripristina la configurazione predefinita',
        'requires_value' => false,
        'visible_in_help' => true,
        'menu_order' => 52
    ],

    // Attiva/disattiva logging
    'log' => [
        'aliases' => ['logging'],
        'description' => 'Attiva/disattiva logging [on/off]',
        'requires_value' => true,
        'visible_in_help' => true,
        'menu_order' => 60
    ],

    // Carica WordPress
    'wp' => [
        'aliases' => ['wordpress', 'site'],
        'description' => 'Carica WordPress (conservando sessione operatore)',
        'requires_value' => false,
        'visible_in_help' => true,
        'menu_order' => 90
    ],

    // Esci e termina sessione
    'quit' => [
        'aliases' => ['exit', 'esci'],
        'description' => 'Esci dalla console e termina sessione operatore',
        'requires_value' => false,
        'visible_in_help' => true,
        'menu_order' => 91
    ],

    // Imposta password (nascosto dall'help)
    'setpassword' => [
        'aliases' => ['setpw', 'passwd'],
        'description' => 'Imposta la password dinamica [password o vuoto per disabilitare]',
        'requires_value' => 'optional',
        'visible_in_help' => false,
        'menu_order' => 999
    ],

    // Comando speciale register (non richiede attivazione)
    'register' => [
        'aliases' => ['reg'],
        'description' => 'Registra un visitatore [nome]',
        'requires_value' => true,
        'visible_in_help' => false,
        'menu_order' => 1000,
        'special' => true  // Non richiede attivazione
    ]
];

// Costruisci mappa degli alias
$command_aliases = [];
foreach ($commands as $cmd => $cmd_config) {
    if ( isset($cmd_config['aliases'])) {
        foreach ($cmd_config['aliases'] as $alias) {
            $command_aliases[$alias] = $cmd;
        }
    }
}

// -------------------------------------------------------------------------
// PARSING DEI PARAMETRI
// -------------------------------------------------------------------------

// Ottieni la query string originale per preservare l'ordine
$query_string = $_SERVER['QUERY_STRING'];

// Gestione del comando register (caso speciale)
if (preg_match('/(\?|&)register=([^&]+)/', $query_string, $matches)) {
    $name = urldecode($matches[2]);

    // Registra il visitatore
    $config['visitors'][$current_ip] = [
        'name' => $name,
        'timestamp' => $now
    ];

    // Limita il numero di visitatori
    if (count($config['visitors']) > $config_static['max_visitors']) {
        // Rimuovi il più vecchio
        $oldest_ip = null;
        $oldest_time = PHP_INT_MAX;

        foreach ($config['visitors'] as $ip => $data) {
            if ($data['timestamp'] < $oldest_time) {
                $oldest_time = $data['timestamp'];
                $oldest_ip = $ip;
            }
        }

        if ($oldest_ip !== null) {
            unset($config['visitors'][$oldest_ip]);
        }
    }

    // Salva configurazione
    save_config($config, $config_static['config_file']);

    // Aggiungi al log
    add_log_entry($config, "Registrazione visitatore: $name", $current_ip);

    // Continua con il normale controllo di accesso
}

// Controlla se l'IP è un operatore attivo
if (isset($config['operators'][$current_ip])) {
    if ($config['operators'][$current_ip]['expires'] > $now) {
        $is_operator = true;
        $bypass_blacklist = true;
    } else {
        // Operatore scaduto, rimuovi
        unset($config['operators'][$current_ip]);
        $save_config_needed = true;
    }
}

// Pulisci gli operatori scaduti
if (cleanup_expired_operators($config, $now)) {
    $save_config_needed = true;
}

// $query_string ← es. "wpmanage=pin&status&help"
if (! empty($query_string)) {
    // rimuoviamo eventuale "?" iniziale
    $qs = ltrim($query_string, '?');

    // spezzettiamo sulle "&"
    $segments = explode('&', $qs);
    if (count($segments) === 0) {
        return; // niente da fare
    }

    // ===== 1) Primo parametro (attivazione) =====
    $first_segment = array_shift($segments);
    if (strpos($first_segment, '=') !== false) {
        list($first_param, $first_value) = explode('=', $first_segment, 2);
        $first_param = urldecode($first_param);
        $first_value = urldecode($first_value);
    } else {
        $first_param = urldecode($first_segment);
        $first_value = '';
    }

    // ===== 2) Se è il comando di attivazione =====
    if ($first_param === $config_static['activation_command']) {
        // [qui la tua logica di verifica password]
        if ($is_superadmin) {
            // IP fidato (non garantisce che l'utente sia lo stesso)
            $tool_activated = true;
            $bypass_blacklist = true;
        } else {

            if ("" == $config_static['superadmin_password']?? "" || "" == $config['password']?? "")
            $tool_activated = true;
            $bypass_blacklist = true;
        }
        // aggiungi operatore, salva config, log...

        // ===== 3) Gli altri parametri come comandi =====
        foreach ($segments as $seg) {
            if (strpos($seg, '=') !== false) {
                list($cmd, $value) = explode('=', $seg, 2);
                $cmd   = urldecode($cmd);
                $value = urldecode($value);
            } else {
                $cmd   = urldecode($seg);
                $value = '';            // parametro “flag”
            }
            // alias se serve
            if (isset($command_aliases[$cmd])) {
                $cmd = $command_aliases[$cmd];
            }
            $commands_to_execute[] = ['cmd' => $cmd, 'value' => $value];
        }

    } elseif ($is_operator) {
        // Se l’utente è già operatore, facciamo parsing identico per TUTTI i segmenti (incluso il primo)
        // (oppure riprendi $segments e ricomincia da zero, a seconda di come vuoi gestire)
        $all = explode('&', $qs);
        foreach ($all as $seg) {
            if (strpos($seg, '=') !== false) {
                list($cmd, $value) = explode('=', $seg, 2);
                $cmd   = urldecode($cmd);
                $value = urldecode($value);
            } else {
                $cmd   = urldecode($seg);
                $value = '';
            }
            if (isset($command_aliases[$cmd])) {
                $cmd = $command_aliases[$cmd];
            }
            $commands_to_execute[] = ['cmd' => $cmd, 'value' => $value];
        }
    }
}

// Controlla se c'è un comando POST dalla console
if ($tool_activated && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['command'])) {
    $commands_to_execute = []; // Cancella comandi da GET se c'è un comando POST

    $command_line = trim($_POST['command']);

    // Separa comando e valore
    $parts = explode(' ', $command_line, 2);
    $cmd = $parts[0];
    $value = isset($parts[1]) ? $parts[1] : '';

    // Normalizza il comando (alias -> nome principale)
    if (isset($command_aliases[$cmd])) {
        $cmd = $command_aliases[$cmd];
    }

    // Aggiungi alla lista dei comandi da eseguire
    $commands_to_execute[] = ['cmd' => $cmd, 'value' => $value];
}

// -------------------------------------------------------------------------
// ESECUZIONE COMANDI
// -------------------------------------------------------------------------

// Esegue i comandi se il tool è attivato
if ($tool_activated && !empty($commands_to_execute)) {
    $output = '';
    $show_status_after = true;
    // Base URL dinamica della directory corrente, con slash finale
    $base_url = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\') . '/';

    foreach ($commands_to_execute as $command) {
        $cmd = $command['cmd'];
        $value = $command['value'];

        // Esegui il comando
        switch ($cmd) {
            case 'help':
                $output .= "=== COMANDI DISPONIBILI ===\n";

                // Ordina i comandi per menu_order
                $sorted_commands = $commands;
                uasort($sorted_commands, function($a, $b) {
                    return $a['menu_order'] - $b['menu_order'];
                });

                foreach ($sorted_commands as $cmd_name => $cmd_config) {
                    // Mostra solo se visibile in help
                    if ($cmd_config['visible_in_help']) {
                        $output .= $cmd_name;

                        // Mostra alias
                        if (!empty($cmd_config['aliases'])) {
                            $output .= " (" . implode(", ", $cmd_config['aliases']) . ")";
                        }

                        // Mostra se richiede valore
                        if (isset($cmd_config['requires_value']) && $cmd_config['requires_value'] === true) {
                            $output .= " [valore richiesto]";
                        } elseif (isset($cmd_config['requires_value']) && $cmd_config['requires_value'] === 'optional') {
                            $output .= " [valore opzionale]";
                        }

                        if ( isset($cmd_config['description'])) {
                            $output .= ": " . $cmd_config['description'];
                        }
                        $output .= "\n";
                    }
                }

                // Non mostrare lo stato dopo help
                $show_status_after = false;
                break;

            case 'status':
                // Gestito alla fine, niente da fare qui
                $show_status_after = true;
                break;

            case 'setautoshow':
                if (strtolower($value) === 'on') {
                    $config['show_console_on_empty_activation'] = true;
                    $output .= "Visualizzazione console su attivazione vuota: abilitata\n";
                } elseif (strtolower($value) === 'off') {
                    $config['show_console_on_empty_activation'] = false;
                    $output .= "Visualizzazione console su attivazione vuota: disabilitata\n";
                } else {
                    $output .= "Errore: specificare 'on' o 'off'\n";
                }
                $save_config_needed = true;
                add_log_entry($config, "Impostata visualizzazione console su attivazione vuota: " . ($config['show_console_on_empty_activation'] ? "on" : "off"), $current_ip);
                break;

            case 'manut':
                $config['mode'] = 'maintenance';

                if (!empty($value)) {
                    $duration = duration_to_seconds($value);
                    if ($duration > 0) {
                        $config['expires'] = $now + $duration;
                        $output .= "Sito impostato in manutenzione per " . floor($duration / 60) . " minuti\n";
                    } else {
                        $config['expires'] = 0;
                        $output .= "Sito impostato in manutenzione (permanente)\n";
                    }
                } else {
                    $config['expires'] = 0;
                    $output .= "Sito impostato in manutenzione (permanente)\n";
                }

                $save_config_needed = true;
                add_log_entry($config, "Modalità manutenzione attivata" . (!empty($value) ? " per $value" : ""), $current_ip);
                break;

            case 'msgset':
                if (empty($value)) {
                    $output .= "Errore: è necessario specificare un messaggio\n";
                } else {
                    $config['mode'] = 'message';
                    $config['message'] = $value;
                    $config['expires'] = 0;
                    $output .= "Messaggio personalizzato impostato\n";
                    $save_config_needed = true;
                    add_log_entry($config, "Modalità messaggio: " . substr($value, 0, 30) . (strlen($value) > 30 ? "..." : ""), $current_ip);
                }
                break;

            case 'msgclear':
                $config['message'] = '';
                $config['mode'] = 'closed';
                $output .= "Messaggio personalizzato cancellato\n";
                $save_config_needed = true;
                add_log_entry($config, "Messaggio cancellato", $current_ip);
                break;

            case 'redirect':
                if (empty($value)) {
                    $output .= "Errore: è necessario specificare un URL\n";
                } else {
                    $config['mode'] = 'redirect';
                    $config['redirect_url'] = $value;
                    $config['expires'] = 0;
                    $output .= "Reindirizzamento impostato a: $value\n";
                    $save_config_needed = true;
                    add_log_entry($config, "Modalità redirect: $value", $current_ip);
                }
                break;

            case 'closed':
                if (!empty($value)) {
                    // Modalità temporanea
                    $config['mode'] = 'closed';
                    $duration = duration_to_seconds($value);
                    if ($duration > 0) {
                        $config['expires'] = $now + $duration;
                        $output .= "Sito impostato in modalità chiusa per " . floor($duration / 60) . " minuti\n";
                    }
                } else {
                    // Modalità predefinita
                    $config['mode'] = 'closed';
                    $config['default_mode'] = 'closed';
                    $config['expires'] = 0;
                    $output .= "Sito impostato in modalità chiusa (default)\n";
                }

                $save_config_needed = true;
                add_log_entry($config, "Modalità chiusa attivata" . (!empty($value) ? " per $value" : ""), $current_ip);
                break;

            case 'open':
                if (!empty($value)) {
                    // Modalità temporanea
                    $config['mode'] = 'open';
                    $duration = duration_to_seconds($value);
                    if ($duration > 0) {
                        $config['expires'] = $now + $duration;
                        $output .= "Sito impostato in modalità aperta per " . floor($duration / 60) . " minuti\n";
                    }
                } else {
                    // Modalità predefinita
                    $config['mode'] = 'open';
                    $config['default_mode'] = 'open';
                    $config['expires'] = 0;
                    $output .= "Sito impostato in modalità aperta (default)\n";
                }

                $save_config_needed = true;
                add_log_entry($config, "Modalità aperta " . (!empty($value) ? "temporanea per $value" : "impostata come default"), $current_ip);
                break;

            case 'openfront':
                if (!empty($value)) {
                    // Modalità temporanea
                    $config['mode'] = 'openfront';
                    $duration = duration_to_seconds($value);
                    if ($duration > 0) {
                        $config['expires'] = $now + $duration;
                        $output .= "Frontend aperto per " . floor($duration / 60) . " minuti\n";
                    }
                } else {
                    // Modalità predefinita
                    $config['mode'] = 'openfront';
                    $config['default_mode'] = 'openfront';
                    $config['expires'] = 0;
                    $output .= "Frontend aperto (permanente)\n";
                }

                $save_config_needed = true;
                add_log_entry($config, "Modalità frontend aperto" . (!empty($value) ? " per $value" : ""), $current_ip);
                break;

            case 'openback':
                if (!empty($value)) {
                    // Modalità temporanea
                    $config['mode'] = 'openback';
                    $duration = duration_to_seconds($value);
                    if ($duration > 0) {
                        $config['expires'] = $now + $duration;
                        $output .= "Backend aperto per " . floor($duration / 60) . " minuti\n";
                    }
                } else {
                    // Modalità predefinita
                    $config['mode'] = 'openback';
                    $config['default_mode'] = 'openback';
                    $config['expires'] = 0;
                    $output .= "Backeend aperto (permanente)\n";
                }

                $save_config_needed = true;
                add_log_entry($config, "Modalità backend aperto" . (!empty($value) ? " per $value" : ""), $current_ip);
                break;

            case 'wladd':
                $ip_to_add = !empty($value) ? $value : $current_ip;

                if (!is_valid_ip($ip_to_add)) {
                    $output .= "Errore: IP non valido: $ip_to_add\n";
                } elseif (in_array($ip_to_add, $config['whitelist'])) {
                    $output .= "IP $ip_to_add già presente in whitelist\n";
                } else {
                    $config['whitelist'][] = $ip_to_add;
                    $output .= "IP $ip_to_add aggiunto alla whitelist\n";

                    // Rimuovi dalla blacklist se presente
                    if (in_array($ip_to_add, $config['blacklist'])) {
                        $config['blacklist'] = array_diff($config['blacklist'], [$ip_to_add]);
                        $output .= "IP $ip_to_add rimosso dalla blacklist\n";
                    }

                    $save_config_needed = true;
                    add_log_entry($config, "Aggiunto IP a whitelist: $ip_to_add", $current_ip);
                }
                break;

            case 'wlremove':
                $ip_to_remove = !empty($value) ? $value : $current_ip;

                if (!is_valid_ip($ip_to_remove)) {
                    $output .= "Errore: IP non valido: $ip_to_remove\n";
                } elseif (!in_array($ip_to_remove, $config['whitelist'])) {
                    $output .= "IP $ip_to_remove non presente in whitelist\n";
                } else {
                    $config['whitelist'] = array_diff($config['whitelist'], [$ip_to_remove]);
                    $output .= "IP $ip_to_remove rimosso dalla whitelist\n";
                    $save_config_needed = true;
                    add_log_entry($config, "Rimosso IP da whitelist: $ip_to_remove", $current_ip);
                }
                break;

            case 'bladd':
                $ip_to_add = !empty($value) ? $value : $current_ip;

                if (!is_valid_ip($ip_to_add)) {
                    $output .= "Errore: IP non valido: $ip_to_add\n";
                } elseif (in_array($ip_to_add, $config['blacklist'])) {
                    $output .= "IP $ip_to_add già presente in blacklist\n";
                } else {
                    $config['blacklist'][] = $ip_to_add;
                    $output .= "IP $ip_to_add aggiunto alla blacklist\n";

                    // Rimuovi dalla whitelist se presente
                    if (in_array($ip_to_add, $config['whitelist'])) {
                        $config['whitelist'] = array_diff($config['whitelist'], [$ip_to_add]);
                        $output .= "IP $ip_to_add rimosso dalla whitelist\n";
                    }

                    $save_config_needed = true;
                    add_log_entry($config, "Aggiunto IP a blacklist: $ip_to_add", $current_ip);
                }
                break;

            case 'blremove':
                $ip_to_remove = !empty($value) ? $value : $current_ip;

                if (!is_valid_ip($ip_to_remove)) {
                    $output .= "Errore: IP non valido: $ip_to_remove\n";
                } elseif (!in_array($ip_to_remove, $config['blacklist'])) {
                    $output .= "IP $ip_to_remove non presente in blacklist\n";
                } else {
                    $config['blacklist'] = array_diff($config['blacklist'], [$ip_to_remove]);
                    $output .= "IP $ip_to_remove rimosso dalla blacklist\n";
                    $save_config_needed = true;
                    add_log_entry($config, "Rimosso IP da blacklist: $ip_to_remove", $current_ip);
                }
                break;

            case 'clearwl':
                $config['whitelist'] = [];
                $output .= "Whitelist svuotata\n";
                $save_config_needed = true;
                add_log_entry($config, "Whitelist svuotata", $current_ip);
                break;

            case 'clearbl':
                $config['blacklist'] = [];
                $output .= "Blacklist svuotata\n";
                $save_config_needed = true;
                add_log_entry($config, "Blacklist svuotata", $current_ip);
                break;

            case 'bypassadd':
                if (empty($value)) {
                    $output .= "Errore: è necessario specificare un pattern\n";
                } else {
                    if (!in_array($value, $config['bypass_patterns'])) {
                        $config['bypass_patterns'][] = $value;
                        $output .= "Pattern di bypass aggiunto: $value\n";
                        $save_config_needed = true;
                        add_log_entry($config, "Aggiunto pattern bypass: $value", $current_ip);
                    } else {
                        $output .= "Pattern già presente\n";
                    }
                }
                break;

            case 'bypassremove':
                if (empty($value)) {
                    $output .= "Errore: è necessario specificare un pattern\n";
                } else {
                    if (in_array($value, $config['bypass_patterns'])) {
                        $config['bypass_patterns'] = array_diff($config['bypass_patterns'], [$value]);
                        $output .= "Pattern di bypass rimosso: $value\n";
                        $save_config_needed = true;
                        add_log_entry($config, "Rimosso pattern bypass: $value", $current_ip);
                    } else {
                        $output .= "Pattern non trovato\n";
                    }
                }
                break;

            case 'blockadd':
                if (empty($value)) {
                    $output .= "Errore: è necessario specificare un pattern\n";
                } else {
                    if (!in_array($value, $config['block_patterns'])) {
                        $config['block_patterns'][] = $value;
                        $output .= "Pattern di blocco aggiunto: $value\n";
                        $save_config_needed = true;
                        add_log_entry($config, "Aggiunto pattern blocco: $value", $current_ip);
                    } else {
                        $output .= "Pattern già presente\n";
                    }
                }
                break;

            case 'blockremove':
                // se passo "*" rimuovo TUTTI i pattern
                if ($value === '*') {
                    if (! empty($config['block_patterns'])) {
                        $config['block_patterns'] = [];
                        $output .= "Tutti i pattern di blocco sono stati rimossi\n";
                        $save_config_needed = true;
                        add_log_entry($config, "Rimossi tutti i pattern di blocco", $current_ip);
                    } else {
                        $output .= "Non ci sono pattern di blocco da rimuovere\n";
                    }
                }
                // se non ho passato nessun valore
                elseif (empty($value)) {
                    $output .= "Errore: è necessario specificare un pattern (o '*' per 'tutti'\n";
                }
                // altrimenti rimuovo solo quello specificato
                else {
                    if (in_array($value, $config['block_patterns'], true)) {
                        $config['block_patterns'] = array_diff($config['block_patterns'], [$value]);
                        $output .= "Pattern di blocco rimosso: $value\n";
                        $save_config_needed = true;
                        add_log_entry($config, "Rimosso pattern blocco: $value", $current_ip);
                    } else {
                        $output .= "Pattern non trovato\n";
                    }
                }
                break;

            case 'blredirect':
                $config['blacklist_redirect'] = $value;
                if (!empty($value)) {
                    $output .= "URL di redirect per blacklist impostato a: $value\n";
                } else {
                    $output .= "URL di redirect per blacklist disabilitato\n";
                }
                $save_config_needed = true;
                add_log_entry($config, "Impostato redirect blacklist: " . (!empty($value) ? $value : "disabilitato"), $current_ip);
                break;

            case 'clearop':
                $config['operators'] = [];
                $output .= "Tutti gli operatori rimossi\n";
                $save_config_needed = true;
                add_log_entry($config, "Operatori rimossi", $current_ip);
                break;

            case 'clearvisitors':
                $config['visitors'] = [];
                $output .= "Lista visitatori svuotata\n";
                $save_config_needed = true;
                add_log_entry($config, "Visitatori rimossi", $current_ip);
                break;

            case 'backup':
                $timestamp = date('YmdHis');
                $backup_filename = "pin-access-backup-$timestamp.json";
                $backup_path = dirname($config_static['config_file']) . '/' . $backup_filename;

                if (save_config($config, $backup_path)) {
                    $output .= "Backup salvato in: $backup_path\n";
                    add_log_entry($config, "Backup creato: $backup_filename", $current_ip);
                } else {
                    $output .= "Errore nel salvare il backup\n";
                }
                break;

            case 'restore':
                if (empty($value)) {
                    $output .= "Errore: specificare il nome del file di backup\n";
                } else {
                    $backup_path = dirname($config_static['config_file']) . '/' . $value;

                    if (!file_exists($backup_path)) {
                        $output .= "Errore: file di backup non trovato: $backup_path\n";
                    } else {
                        $json_content = @file_get_contents($backup_path);
                        if ($json_content === false) {
                            $output .= "Errore nella lettura del file di backup\n";
                        } else {
                            $loaded_config = @json_decode($json_content, true);
                            if ($loaded_config === null) {
                                $output .= "Errore nel parsing del file di backup\n";
                            } else {
                                $config = $loaded_config;
                                $save_config_needed = true;
                                $output .= "Configurazione ripristinata da: $value\n";
                                add_log_entry($config, "Configurazione ripristinata da: $value", $current_ip);
                            }
                        }
                    }
                }
                break;

            case 'reset':
                $config = $config_static['default_config'];

                // Mantieni l'operatore corrente
                if ($is_operator || $is_superadmin) {
                    $config['operators'][$current_ip] = [
                        'expires' => $now + $config_static['operator_timeout']
                    ];
                }

                $output .= "Configurazione ripristinata ai valori predefiniti\n";
                $save_config_needed = true;
                add_log_entry($config, "Configurazione resettata", $current_ip);
                break;

            case 'log':
                if (strtolower($value) === 'on') {
                    $config['logging'] = true;
                    $output .= "Logging attivato\n";
                    add_log_entry($config, "Logging attivato", $current_ip);
                } elseif (strtolower($value) === 'off') {
                    $config['logging'] = false;
                    $output .= "Logging disattivato\n";
                    add_log_entry($config, "Logging disattivato", $current_ip);
                } else {
                    $output .= "Errore: specificare 'on' o 'off'\n";
                }
                $save_config_needed = true;
                break;

            case 'setpassword':
                $config['password'] = $value;
                if (!empty($value)) {
                    $output .= "Password dinamica impostata\n";
                } else {
                    $output .= "Password dinamica disabilitata\n";
                }
                $save_config_needed = true;
                add_log_entry($config, "Password dinamica " . (!empty($value) ? "modificata" : "disabilitata"), $current_ip);
                break;

            case 'wp':
                // Salva configurazione prima di reindirizzare
                if ($save_config_needed) {
                    save_config($config, $config_static['config_file']);
                }

                // Aggiorna timeout operatore
                if ($is_operator || $is_superadmin) {
                    $config['operators'][$current_ip] = [
                        'expires' => $now + $config_static['operator_timeout']
                    ];
                    save_config($config, $config_static['config_file']);
                }

                // Reindirizza alla home di WordPress
                header('Location: ' . $base_url);
                exit;

            case 'quit':
                // Rimuovi lo stato di operatore
                if (isset($config['operators'][$current_ip])) {
                    unset($config['operators'][$current_ip]);
                    $save_config_needed = true;
                }

                // Salva configurazione prima di reindirizzare
                if ($save_config_needed) {
                    save_config($config, $config_static['config_file']);
                }

                add_log_entry($config, "Logout operatore", $current_ip);

                // Reindirizza alla home di WordPress
                header('Location: ' . $base_url);
                exit;

            default:
                $output .= "Comando sconosciuto: $cmd\n";
                break;
        }

        // Aggiorna timeout operatore per ogni comando
        if ($cmd !== 'wp' && $cmd !== 'quit') {
            $config['operators'][$current_ip] = [
                'expires' => $now + $config_static['operator_timeout']
            ];
        }
    }

    // Salva configurazione se necessario
    if ($save_config_needed) {
        save_config($config, $config_static['config_file']);
    }

    // Mostra lo stato dopo l'esecuzione dei comandi
    if ($show_status_after) {
        $output .= "\n" . format_status_output($config, $now, $is_superadmin, $current_ip);
    }

    // Mostra la console con l'output
    show_console($output, $config_static, $config, $now, $is_superadmin, $current_ip);
    exit;
}

if ($tool_activated === true) {
    if  (($config['show_console_on_empty_activation'] ?? $config_static['show_console_on_empty_activation'])) {
        $output .= "\n" . format_status_output($config, $now, $is_superadmin, $current_ip);

        // Mostra la console con l'output
        show_console($output, $config_static, $config, $now, $is_superadmin, $current_ip);
        exit;
    }
}

// -------------------------------------------------------------------------
// VERIFICA ACCESSO E ROUTING
// -------------------------------------------------------------------------

// Ottieni URI corrente
$current_uri = $_SERVER['REQUEST_URI'];

// Verifica blacklist (solo se non è un superadmin o operatore)
if (!$bypass_blacklist && in_array($current_ip, $config['blacklist'])) {
    if (!empty($config['blacklist_redirect'])) {
        // Reindirizza se configurato
        header('Location: ' . $config['blacklist_redirect']);
    } else {
        // Mostra pagina di blocco
        show_blocked_page();
    }
    exit;
}

// Verifica whitelist (accesso totale)
if (in_array($current_ip, $config['whitelist'])) {
    // Carica WordPress
    include 'wp-index.php';
    exit;
}

// Verifica bypass pattern (priorità dopo whitelist)
foreach ($config['bypass_patterns'] as $pattern) {
    if (uri_matches_pattern($pattern, $current_uri)) {
        // Carica WordPress per questo pattern
        include 'wp-index.php';
        exit;
    }
}

// Verifica block pattern
foreach ($config['block_patterns'] as $pattern) {
    if (uri_matches_pattern($pattern, $current_uri)) {
        // Mostra pagina di blocco per questo pattern
        show_blocked_area_page();
        exit;
    }
}

// Controlla scadenza modalità
if ($config['expires'] > 0 && $config['expires'] < $now) {
    // Modalità temporanea scaduta, ripristina modalità predefinita
    $config['mode'] = $config['default_mode'];
    $config['expires'] = 0;
    $save_config_needed = true;
    add_log_entry($config, "Ripristinata modalità predefinita: " . $config['default_mode'], 'system');
}

// Verifica accesso backend
$is_backend_request = preg_match('/(\/wp-admin|\/wp-login\.php)/', $current_uri);

// Gestisci accesso in base alla modalità
switch ($config['mode']) {
    case 'open':
        // Accesso totale
        include 'wp-index.php';
        break;

    case 'openfront':
        // Solo frontend
        if ($is_backend_request) {
            show_maintenance_page();
        } else {
            include 'wp-index.php';
        }
        break;

    case 'openback':
        // Solo backend
        if ($is_backend_request) {
            include 'wp-index.php';
        } else {
            show_maintenance_page();
        }
        break;

    case 'closed':
        // Tutto chiuso
        show_closed_page();
        break;

    case 'maintenance':
        // Manutenzione
        show_maintenance_page();
        break;

    case 'message':
        // Messaggio personalizzato
        show_message_page($config['message']);
        break;

    case 'redirect':
        // Reindirizzamento
        header('Location: ' . $config['redirect_url']);
        exit;

    default:
        // Default: comportamento aperto
        include 'wp-index.php';
        break;
}

// Termina l'esecuzione
exit;

// -------------------------------------------------------------------------
// FUNZIONI DI RENDERING PAGINE
// -------------------------------------------------------------------------
function show_blocked_page() {
    show_status_page(
        'Accesso bloccato',
        'Accesso bloccato',
        'Il tuo indirizzo IP è stato bloccato.',
        '#d9534f',  // Rosso
        403,
        '<p><a href="/">Torna alla home</a></p>'
    );
}

function show_blocked_area_page() {
    show_status_page(
        'Area non autorizzata',
        'Area non autorizzata',
        'Area del sito non accessibile.',
        '#d9534f',  // Rosso
        403,
        '<p><a href="/">Torna alla home</a></p>'
    );
}
function show_unauthorized_page() {
    show_status_page(
        'Accesso non autorizzato',
        'Accesso non autorizzato',
        'La password fornita non è valida.',
        '#d9534f',  // Rosso
        401,
        '<p><a href="/">Torna alla home</a></p>'
    );
}

function show_closed_page() {
    show_status_page(
        'Sito non disponibile',
        'Sito non disponibile',
        'Il sito è attualmente chiuso. Si prega di riprovare più tardi.',
        '#f0ad4e'  // Arancione
    );
}

function show_maintenance_page() {
    show_status_page(
        'Sito in manutenzione',
        'Sito in manutenzione',
        'Stiamo effettuando lavori di manutenzione. Torneremo presto online.',
        '#5bc0de'  // Azzurro
    );
}

function show_message_page($message) {
    show_status_page(
        'Messaggio',
        'Comunicazione',
        $message,
        '#5cb85c'  // Verde
    );
}
/**
 * Mostra una pagina di stato utilizzando un template unico
 *
 * @param string $title Titolo della pagina
 * @param string $heading Intestazione principale
 * @param string $message Messaggio da mostrare
 * @param string $heading_color Colore dell'intestazione (hex o nome CSS)
 * @param int $status_code Codice HTTP da inviare
 * @param string $extra_html HTML aggiuntivo da mostrare dopo il messaggio (opzionale)
 */
function show_status_page($title, $heading, $message, $heading_color = '#5bc0de', $status_code = 503, $extra_html = '') {
    // Imposta l'header HTTP appropriato
    $status_messages = [
        401 => 'Unauthorized',
        403 => 'Forbidden',
        503 => 'Service Unavailable'
    ];

    $status_text = isset($status_messages[$status_code]) ? $status_messages[$status_code] : 'Service Unavailable';
    header("HTTP/1.1 $status_code $status_text");

    echo <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>$title</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f5f5f5;
            margin: 0;
            padding: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
        }
        .container {
            text-align: center;
            background-color: white;
            padding: 2em;
            border-radius: 5px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            max-width: 500px;
        }
        h1 {
            color: $heading_color;
        }
        p {
            color: #333;
            margin: 1em 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>$heading</h1>
        <p>$message</p>
        $extra_html
    </div>
</body>
</html>
HTML;
}

/**
 * Mostra la console di amministrazione
 */
function show_console($output, $config_static, $config, $now, $is_superadmin, $current_ip) {
    $output = isset($output)? $output : "";
    $is_operator = isset($config['operators'][$current_ip]);
    $operator_expires = $is_operator ? $config['operators'][$current_ip]['expires'] : 0;
    $time_remaining = $operator_expires > $now ? floor(($operator_expires - $now) / 60) : 0;
    $command_history_size = $config_static['command_history_size'];
    // Prepara i link veloci per i comandi
    $quick_links = [
        'help' => 'Aiuto',
        'status' => 'Stato',
        'manut' => 'Manutenzione',
        'open' => 'Apri',
        'openfront' => 'Solo front',
        'openback' => 'Solo back',
        'closed' => 'Chiudi',
        'wp' => 'WordPress',
        'quit' => 'Esci'
    ];

    $quick_link_html = '';
    foreach ($quick_links as $cmd => $label) {
        $quick_link_html .= "<a href=\"#\" onclick=\"setCommand('$cmd'); return false;\">$label</a> | ";
    }
    $quick_link_html = rtrim($quick_link_html, ' | ');

    // Crea array per comando IP dinamico
    $ip_commands = [
        'wladd' => 'Aggiungi a whitelist',
        'wlremove' => 'Rimuovi da whitelist',
        'bladd' => 'Aggiungi a blacklist',
        'blremove' => 'Rimuovi da blacklist'
    ];

    echo <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Console di Amministrazione</title>
    <style>
        body {
            font-family: monospace;
            background-color: #000;
            color: #0f0;
            margin: 0;
            padding: 1em;
        }
        .header {
            background-color: #222;
            padding: 10px;
            margin-bottom: 10px;
            border-radius: 5px;
        }
        .console {
            background-color: #111;
            padding: 10px;
            border-radius: 5px;
            height: 70vh;
            overflow-y: auto;
            white-space: pre-wrap;
            margin-bottom: 10px;
        }
        .input-area {
            display: flex;
            margin-bottom: 10px;
        }
        .input-area input[type="text"] {
            flex: 1;
            background-color: #222;
            color: #0f0;
            border: 1px solid #0f0;
            padding: 8px;
            font-family: monospace;
            font-size: 14px;
        }
        .input-area button {
            background-color: #0f0;
            color: #000;
            border: none;
            padding: 8px 15px;
            margin-left: 5px;
            cursor: pointer;
            font-weight: bold;
        }
        .quick-links {
            background-color: #222;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 10px;
        }
        .quick-links a {
            color: #0f0;
            text-decoration: none;
            margin-right: 10px;
        }
        .quick-links a:hover {
            text-decoration: underline;
        }
        .status-info {
            margin-bottom: 5px;
        }
        .ip-clickable {
            cursor: pointer;
            text-decoration: underline;
            color: #5bc0de;
        }
        a:visited {
            color: #0f0;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="status-info">
            <strong>Pin Access Manager</strong> | 
            <span id="current-time">Ora: </span> | 
            IP: $current_ip
HTML;

    if ($is_superadmin) {
        echo " <span style='color: #ff0;'>(SUPERADMIN)</span>";
    }

    if ($is_operator) {
        echo " | <span id='session-timer'>Sessione: <span id='minutes-remaining'>$time_remaining</span> minuti</span>";
    }


    echo <<<HTML
        </div>
    </div>
    
    <div class="quick-links">
        {$quick_link_html}
    </div>
    
    <div class="console" id="console-output">{$output}</div>
    
    <form method="post" action="">
        <div class="input-area">
            <input type="text" id="command" name="command" autocomplete="off" placeholder="Inserisci un comando...">
            <button type="submit">Invia</button>
        </div>
    </form>
    
    <script>
        // Aggiorna ora corrente
        function updateCurrentTime() {
            const now = new Date();
            const timeString = now.toLocaleTimeString();
            document.getElementById('current-time').textContent = 'Ora: ' + timeString;
        }
        
        // Aggiorna timer sessione
        function updateSessionTimer() {
            const minutesElement = document.getElementById('minutes-remaining');
            if (minutesElement) {
                let minutes = parseInt(minutesElement.textContent);
                if (minutes > 0) {
                    minutes--;
                    minutesElement.textContent = minutes;
                    
                    if (minutes <= 5) {
                        document.getElementById('session-timer').style.color = '#ff0000';
                    }
                }
            }
        }
        
        // Imposta comando nel campo input
        function setCommand(cmd) {
            document.getElementById('command').value = cmd;
            document.getElementById('command').focus();
        }
        
        // Imposta comando IP
        function setIpCommand(cmd, ip) {
            document.getElementById('command').value = cmd + ' ' + ip;
            document.getElementById('command').focus();
        }
        
        // Rendi IP cliccabili
        function makeIpClickable() {
            const consoleOutput = document.getElementById('console-output');
            const ipRegex = /\b(?:\d{1,3}\.){3}\d{1,3}\b/g;
            
            consoleOutput.innerHTML = consoleOutput.innerHTML.replace(ipRegex, function(ip) {
                const ipMenu = document.createElement('div');
                ipMenu.className = 'ip-menu';
                ipMenu.style.display = 'none';
                
                let menuHtml = '';
                
                for (const cmd in ipCommands) {
                    menuHtml += `<a href="#" onclick="setIpCommand('\${cmd}', '\${ip}'); return false;">\${ipCommands[cmd]}</a><br>`;
                }
                
                return `<span class="ip-clickable" onclick="setIpCommand('wladd', '\${ip}'); return false;">\${ip}</span>`;
            });
        }
        
        // Cronologia comandi
        let commandHistory = [];
        let currentHistoryIndex = -1;
        
        // Carica cronologia dal localStorage
        if (localStorage.getItem('commandHistory')) {
            try {
                commandHistory = JSON.parse(localStorage.getItem('commandHistory'));
            } catch (e) {
                commandHistory = [];
            }
        }
        
        // Gestisci invio form e salva cronologia
        document.querySelector('form').addEventListener('submit', function(e) {
            const commandInput = document.getElementById('command');
            const command = commandInput.value.trim();
            
            if (command) {
                // Aggiungi alla cronologia solo se è diverso dall'ultimo comando
                if (commandHistory.length === 0 || commandHistory[0] !== command) {
                    commandHistory.unshift(command);
                    
                    // Limita la dimensione della cronologia
                    if (commandHistory.length > {$command_history_size}) {
                        commandHistory.pop();
                    }
                    
                    // Salva nel localStorage
                    localStorage.setItem('commandHistory', JSON.stringify(commandHistory));
                }
                
                currentHistoryIndex = -1;
            }
        });
        
        // Gestisci navigazione cronologia con frecce su/giù
        document.getElementById('command').addEventListener('keydown', function(e) {
            if (e.key === 'ArrowUp') {
                e.preventDefault();
                
                if (commandHistory.length > 0) {
                    currentHistoryIndex = Math.min(currentHistoryIndex + 1, commandHistory.length - 1);
                    this.value = commandHistory[currentHistoryIndex];
                }
            } else if (e.key === 'ArrowDown') {
                e.preventDefault();
                
                if (currentHistoryIndex > 0) {
                    currentHistoryIndex--;
                    this.value = commandHistory[currentHistoryIndex];
                } else if (currentHistoryIndex === 0) {
                    currentHistoryIndex = -1;
                    this.value = '';
                }
            }
        });
        
        // Definisci comandi IP per il menu contestuale
        const ipCommands = {
HTML;

    foreach ($ip_commands as $cmd => $label) {
        echo "            '$cmd': '$label',\n";
    }

    echo <<<HTML
        };
        
        // Inizializza
        document.addEventListener('DOMContentLoaded', function() {
            // Aggiorna ora
            updateCurrentTime();
            setInterval(updateCurrentTime, 1000);
            
            // Aggiorna timer sessione
            setInterval(updateSessionTimer, 60000);
            
            // Rendi IP cliccabili
            makeIpClickable();
            
            // Scroll console alla fine
            const consoleOutput = document.getElementById('console-output');
            consoleOutput.scrollTop = consoleOutput.scrollHeight;
            
            // Focus sul campo comando
            document.getElementById('command').focus();
        });
    </script>
</body>
</html>
HTML;
}
