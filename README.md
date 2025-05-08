# WP-Access-Control-type-G
Control access to WordPress - Maintenance mode, message mode; IP whitelist and blacklist; redirect; open, close, front or back; time.
# Pin Access Manager per WordPress
## Documentazione completa

## Indice
1. [Introduzione e scopo](#introduzione-e-scopo)
2. [Installazione](#installazione)
3. [Accesso e autenticazione](#accesso-e-autenticazione)
4. [Funzionalità principali](#funzionalità-principali)
5. [Modalità di accesso](#modalità-di-accesso)
6. [Comandi disponibili](#comandi-disponibili)
7. [Interfaccia console](#interfaccia-console)
8. [Gestione degli IP](#gestione-degli-ip)
9. [Gestione dei pattern URL](#gestione-dei-pattern-url)
10. [Funzionalità avanzate](#funzionalità-avanzate)
11. [Struttura del codice](#struttura-del-codice)
12. [Come aggiungere nuovi comandi](#come-aggiungere-nuovi-comandi)
13. [Personalizzazione](#personalizzazione)
14. [Risoluzione problemi](#risoluzione-problemi)
15. [Considerazioni sulla sicurezza](#considerazioni-sulla-sicurezza)

## Introduzione e scopo

Pin Access Manager è uno strumento di gestione degli accessi per WordPress che funziona come un "gatekeeper" posizionato davanti al sito. Con il minimo sforzo, consente di:

- Mettere rapidamente il sito in modalità manutenzione
- Bloccare completamente l'accesso al sito
- Controllare l'accesso separatamente per frontend e backend
- Mostrare messaggi personalizzati ai visitatori
- Reindirizzare a URL specifici
- Gestire whitelist e blacklist di indirizzi IP
- Controllare gli accessi tramite pattern URL

Lo strumento è progettato per essere:
- Leggero e veloce
- Facilmente controllabile tramite parametri URL
- Accessibile tramite un'interfaccia a console
- Modulare e facilmente estendibile

## Installazione

1. **Backup**: Crea sempre un backup del tuo sito WordPress prima di procedere
2. **Rinomina l'index.php originale**:
   ```bash
   mv index.php wp-index.php
   ```
3. **Carica il nuovo index.php**: Carica il file Pin Access Manager come nuovo `index.php`
4. **Configura le opzioni di sicurezza**:
   - Modifica la `superadmin_password` nell'array `$config_static`
   - Aggiungi i tuoi IP fidati all'array `$superadmin_ips`

## Accesso e autenticazione

### Metodi di accesso

Esistono tre modi per autenticarsi e utilizzare Pin Access Manager:

1. **IP superadmin**:
   - Gli IP elencati in `$superadmin_ips` hanno sempre accesso completo
   - Ricevono automaticamente privilegi speciali

2. **Password statica**:
   - Accesso con `?managewp=superadmin_password`
   - Password definita in `$config_static['superadmin_password']`
   - Sempre valida (di emergenza)

3. **Password dinamica**:
   - Accesso con `?managewp=password_dinamica`
   - Configurabile tramite comando `setpassword`
   - Salvata nel file di configurazione

### Stati utente

- **Superadmin**: IP elencati nell'array statico (accesso completo)
- **Operatore**: Utente autenticato con password valida (timeout configurabile)
- **Visitatore**: Utente normale soggetto alle regole di accesso

## Funzionalità principali

### Controllo stato del sito

- **Modalità aperta**: Accesso completo al sito
- **Modalità chiusa**: Nessun accesso al sito
- **Manutenzione**: Pagina di manutenzione per tutti i visitatori
- **Messaggio**: Visualizza un messaggio personalizzato
- **Reindirizzamento**: Reindirizza i visitatori a un URL specifico
- **Accesso parziale**: Possibilità di aprire solo frontend o backend

### Gestione delle liste IP

- **Whitelist**: IP con accesso completo in qualsiasi modalità
- **Blacklist**: IP sempre bloccati (con opzione di reindirizzamento)
- **Registrazione visitatori**: Possibilità per i visitatori di registrarsi per essere identificati

### Bypass e blocchi URL

- **Pattern di bypass**: URL che bypassano le restrizioni di accesso
- **Pattern di blocco**: URL sempre bloccati
- **Accesso backend**: Gestione specifica per aree admin

### Funzionalità amministrative

- **Backup e ripristino**: Salvataggio e importazione configurazioni
- **Logging**: Registrazione eventi con opzione di attivazione/disattivazione
- **Reset**: Ripristino configurazione predefinita

## Modalità di accesso

### Modalità standard

- **open**: Accesso completo al sito (predefinito)
- **closed**: Sito completamente chiuso, mostra pagina di "Sito non disponibile"
- **maintenance**: Sito in manutenzione, mostra pagina di "Sito in manutenzione"
- **message**: Mostra un messaggio personalizzato
- **redirect**: Reindirizza a un URL specifico

### Modalità specializzate

- **openfront**: Solo il frontend è accessibile, il backend è bloccato
- **openback**: Solo il backend è accessibile, il frontend è bloccato

### Impostazione durata

Tutte le modalità possono essere impostate con una durata specifica:
- Senza durata: `?managewp&closed` - permanente
- Con durata: `?managewp&closed=1h` - temporanea (1 ora)

Formati durata supportati:
- `m`: minuti (es. 30m)
- `h`: ore (es. 2h)
- `d`: giorni (es. 1d)
- `w`: settimane (es. 1w)

## Comandi disponibili

### Comandi di sistema
- `help` (alias: `?`, `aiuto`): Mostra la lista dei comandi disponibili
- `status` (alias: `stato`, `st`): Mostra lo stato attuale del sistema
- `wp` (alias: `wordpress`, `site`): Carica WordPress conservando la sessione
- `quit` (alias: `exit`, `esci`): Esce dalla console e termina la sessione

### Modalità sito
- `open` (alias: `apri`, `aperto`): Imposta il sito in modalità aperta
- `openfront` (alias: `frontonly`): Apre solo il frontend
- `openback` (alias: `backonly`): Apre solo il backend
- `closed` (alias: `chiudi`, `chiuso`): Imposta il sito in modalità chiusa
- `manut` (alias: `maintenance`, `manutenzione`): Imposta modalità manutenzione
- `msg` (alias: `message`, `messaggio`): Imposta un messaggio personalizzato
- `clearmsg` (alias: `nomsg`, `nomessage`): Cancella il messaggio personalizzato
- `redirect` (alias: `redir`, `url`): Reindirizza a un URL specifico

### Gestione IP
- `wladd` (alias: `whitelist`): Aggiungi IP alla whitelist
- `wlremove` (alias: `unwl`, `nowhitelist`): Rimuovi IP dalla whitelist
- `bladd` (alias: `blacklist`, `ipblock`): Aggiungi IP alla blacklist
- `blremove` (alias: `unbl`, `noblacklist`, `ipunblock`): Rimuovi IP dalla blacklist
- `clearwl` (alias: `wlclear`): Svuota la whitelist
- `clearbl` (alias: `blclear`): Svuota la blacklist
- `blredirect` (alias: `blredir`): Imposta URL di redirect per IP in blacklist

### Gestione pattern URL
- `bypassadd` (alias: `addbypass`): Aggiungi pattern URL da bypassare
- `bypassremove` (alias: `rmbypass`): Rimuovi pattern URL da bypassare
- `blockadd` (alias: `addblock`): Aggiungi pattern URL da bloccare
- `blockremove` (alias: `rmblock`): Rimuovi pattern URL da bloccare

### Gestione operatori e visitatori
- `clearop` (alias: `opclear`): Rimuovi tutti gli operatori attivi
- `clearvisitors` (alias: `clearvs`): Cancella la lista dei visitatori registrati
- `setpassword` (alias: `setpw`, `passwd`): Imposta la password dinamica
- `register` (alias: `reg`): Registra un visitatore [comando speciale]

### Gestione configurazione
- `backup` (alias: `export`): Esporta la configurazione corrente
- `restore` (alias: `import`): Importa una configurazione salvata
- `reset` (alias: `default`): Ripristina la configurazione predefinita
- `log` (alias: `logging`): Attiva/disattiva logging [on/off]
- `setautoshow` (alias: `autoshow`, `showempty`): Imposta se mostrare la console quando attivata senza comandi

## Interfaccia console

### Componenti dell'interfaccia

- **Header**: Informazioni di stato, IP e timer sessione
- **Quick links**: Collegamenti rapidi ai comandi principali
- **Console output**: Area di visualizzazione output comandi e stato
- **Input area**: Campo per inserire comandi e pulsante di invio

### Funzionalità JavaScript

- **Cronologia comandi**: Navigabile con frecce su/giù
- **IP cliccabili**: Gli indirizzi IP nell'output sono cliccabili per azioni rapide
- **Timer sessione**: Countdown tempo rimanente per la sessione operatore
- **Orologio**: Visualizzazione ora corrente

### Utilizzo delle scorciatoie

- **Quick links**: Cliccabili per inserire il comando corrispondente
- **IP links**: Cliccabili per inserire comandi relativi a quell'IP
- **Cronologia**: Accessibile con i tasti freccia su/giù

## Gestione degli IP

### Whitelist

La whitelist consente accesso completo al sito, indipendentemente dalla modalità configurata:

```
?managewp&wladd=192.168.1.10     # Aggiungi IP specifico
?managewp&wladd                  # Aggiungi IP corrente
?managewp&wlremove=192.168.1.10  # Rimuovi IP specifico
?managewp&clearwl                # Svuota whitelist
```

Gli IP in whitelist hanno priorità sulla blacklist.

### Blacklist

La blacklist blocca completamente gli IP, con opzione di reindirizzamento:

```
?managewp&bladd=192.168.1.20     # Aggiungi IP alla blacklist
?managewp&bladd                  # Aggiungi IP corrente
?managewp&blremove=192.168.1.20  # Rimuovi IP dalla blacklist
?managewp&clearbl                # Svuota blacklist
?managewp&blredirect=https://example.com  # Imposta URL di reindirizzamento
?managewp&blredirect=            # Disabilita reindirizzamento
```

La blacklist ha effetto solo su utenti non-operatori e non-superadmin.

### Registrazione visitatori

I visitatori possono registrarsi senza avere accesso alla console:

```
?register=NomeUtente
```

Questo registra l'IP e il nome nel sistema, permettendo agli amministratori di identificare e, se necessario, aggiungere l'IP alla whitelist.

## Gestione dei pattern URL

### Pattern di bypass

I pattern di bypass consentono di specificare URL che dovrebbero sempre essere accessibili, indipendentemente dalla modalità del sito:

```
?managewp&bypassadd=/api/        # Bypassa tutti gli URL che contengono "/api/"
?managewp&bypassadd=/feed.xml    # Bypassa feed.xml
?managewp&bypassadd=/webhook     # Bypassa webhook
?managewp&bypassremove=/api/     # Rimuovi pattern di bypass
```

### Pattern di blocco

I pattern di blocco specificano URL che devono sempre essere bloccati:

```
?managewp&blockadd=/wp-content/uploads/private/  # Blocca accesso a cartella privata
?managewp&blockadd=/riservato    # Blocca accesso a area riservata
?managewp&blockremove=/riservato # Rimuovi pattern di blocco
```

### Pattern regex

Per pattern più complessi, è possibile utilizzare espressioni regolari:

```
?managewp&bypassadd=/^\/api\/v[0-9]+\//  # Bypassa URL che iniziano con /api/v seguito da numeri
?managewp&blockadd=/\.(pdf|docx)$/     # Blocca accesso a file PDF e DOCX
```

I pattern regex devono essere racchiusi tra slash (`/pattern/`).

## Funzionalità avanzate

### Default e stato temporaneo

Il sistema supporta uno stato predefinito e uno stato temporaneo:

```
?managewp&open                  # Imposta "open" come stato predefinito
?managewp&closed=1d             # Imposta "closed" come stato temporaneo per 1 giorno
```

Alla scadenza dello stato temporaneo, il sistema tornerà automaticamente allo stato predefinito.

### Backup e ripristino

Permette di salvare e ripristinare configurazioni complete:

```
?managewp&backup                # Crea un backup della configurazione
?managewp&restore=pin-access-backup-20240510123045.json  # Ripristina da backup
```

I file di backup vengono salvati nella cartella `wp-content/` con un timestamp nel nome.

### Logging

Il sistema può registrare gli eventi chiave:

```
?managewp&log=on                # Attiva logging
?managewp&log=off               # Disattiva logging
```

Il log è visualizzabile nella console e contiene azioni, IP e timestamp.

### Opzioni avanzate di visualizzazione

Controlla se mostrare la console quando è attivata senza comandi:

```
?managewp&setautoshow=on        # Mostra console quando attivata senza comandi
?managewp&setautoshow=off       # Non mostrare console quando attivata senza comandi
```

## Struttura del codice

Il file `index.php` è organizzato in sezioni logiche:

```
1. Configurazione iniziale e definizioni
2. Caricamento e gestione configurazione
3. Funzioni di utilità
4. Definizione comandi
5. Parsing dei parametri
6. Esecuzione comandi
7. Verifica accesso e routing
8. Funzioni di rendering pagine
```

### Componenti principali

- **$config_static**: Configurazione statica (password, IP superadmin, timeout)
- **$config**: Configurazione dinamica (salvata su file JSON)
- **$commands**: Definizione di tutti i comandi disponibili
- **Funzioni di utilità**: Helper per la gestione di IP, durate, pattern URL
- **Funzioni di rendering**: Generazione pagine HTML per diverse modalità
- **Motore principale**: Logica di routing e controllo accessi

## Come aggiungere nuovi comandi

Per aggiungere un nuovo comando:

1. **Aggiungi la definizione nell'array $commands**:

```php
'miocomando' => [
    'aliases' => ['mc', 'mycommand'],
    'description' => 'Descrizione del mio comando [parametri]',
    'requires_value' => true,  // true/false/'optional'
    'visible_in_help' => true,
    'menu_order' => 70  // Posizione nel menu help
],
```

2. **Implementa la logica nel case switch per l'esecuzione comandi**:

```php
case 'miocomando':
    if (empty($value)) {
        $output .= "Errore: Valore richiesto per questo comando\n";
    } else {
        // Logica del comando
        $output .= "Mio comando eseguito con valore: $value\n";
        $save_config_needed = true;  // Se modifica la configurazione
        add_log_entry($config, "Mio comando eseguito: $value", $current_ip);
    }
    break;
```

3. **Se il comando gestisce nuovi dati**, aggiungi i campi necessari in `$config_static['default_config']`.

## Personalizzazione

### Template pagine

Per personalizzare l'aspetto delle pagine di stato (manutenzione, chiuso, ecc.), modifica le funzioni corrispondenti:

- `show_blocked_page()`
- `show_closed_page()`
- `show_maintenance_page()`
- `show_message_page()`

Oppure implementa un template unificato come discusso in precedenza.

### Stile console

Per modificare l'aspetto della console:

1. Localizza la funzione `show_console()`
2. Modifica il CSS interno per personalizzare colori, font, dimensioni

### Timeout e limiti

Questi valori sono configurabili nell'array `$config_static`:

- `operator_timeout`: Durata sessione operatore in secondi
- `max_visitors`: Numero massimo di visitatori registrati
- `command_history_size`: Dimensione della cronologia comandi
- `log_max_entries`: Numero massimo di eventi di log

## Risoluzione problemi

### Accesso di emergenza

Se non riesci ad accedere:

1. **IP superadmin**: Accedi da un IP elencato in `$superadmin_ips`
2. **Password superadmin**: Usa `?managewp=superadmin_password` per accedere

### Ripristino configurazione

Se la configurazione è danneggiata:

1. Elimina il file di configurazione in `wp-content/pin-access-manager-config.json`
2. Il sistema creerà una nuova configurazione predefinita
3. Oppure usa `?managewp&reset` per ripristinare le impostazioni predefinite

### Debug

Per risolvere problemi:

1. Verifica la console per messaggi di errore
2. Controlla i permessi dei file per assicurarti che PHP possa scrivere nella cartella `wp-content/`
3. Attiva il logging con `?managewp&log=on` per tracciare le azioni

## Considerazioni sulla sicurezza

### Password

- Cambia sempre la password superadmin predefinita
- Utilizza una password forte per l'accesso dinamico
- Considera l'uso di HTTPS per proteggere le credenziali

### IP superadmin

- Usa indirizzi IP statici quando possibile
- Verifica regolarmente la lista degli IP superadmin
- Limita l'accesso solo agli IP affidabili

### Blacklist

- Considera l'aggiunta di IP che tentano accessi non autorizzati
- Abilita il logging per tenere traccia dei tentativi di accesso

### Accesso backend

- Valuta la separazione degli accessi frontend e backend
- Usa `openback` durante la manutenzione per consentire l'amministrazione

### File di configurazione

- Verifica che il file di configurazione non sia accessibile pubblicamente
- Esegui backup regolari della configurazione
