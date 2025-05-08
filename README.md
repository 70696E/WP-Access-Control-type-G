# WP-Access-Control-type-G (aka Pin Access Manager)
Control access to WordPress - Maintenance mode, message mode; IP whitelist and blacklist; redirect; open, close, front or back; time.
![250508-110923_opera_Console_di_Amministrazione_-_Opera](https://github.com/user-attachments/assets/9363da24-0d7b-451e-9c78-bee6839e112e)

# Pin Access Manager for WordPress
## Complete Documentation

## Table of Contents
1. [Introduction and Purpose](#introduction-and-purpose)
2. [Installation](#installation)
3. [Access and Authentication](#access-and-authentication)
4. [Main Features](#main-features)
5. [Access Modes](#access-modes)
6. [Available Commands](#available-commands)
7. [Console Interface](#console-interface)
8. [IP Management](#ip-management)
9. [URL Pattern Management](#url-pattern-management)
10. [Advanced Features](#advanced-features)
11. [Code Structure](#code-structure)
12. [How to Add New Commands](#how-to-add-new-commands)
13. [Customization](#customization)
14. [Troubleshooting](#troubleshooting)
15. [Security Considerations](#security-considerations)

## Introduction and Purpose

Pin Access Manager is an access management tool for WordPress that works as a "gatekeeper" positioned in front of your site. With minimal effort, it allows you to:

- Quickly put the site in maintenance mode
- Completely block access to the site
- Control access separately for frontend and backend
- Display custom messages to visitors
- Redirect to specific URLs
- Manage whitelists and blacklists of IP addresses
- Control access via URL patterns

The tool is designed to be:
- Lightweight and fast
- Easily controllable via URL parameters
- Accessible through a console interface
- Modular and easily extensible

## Installation

1. **Backup**: Always create a backup of your WordPress site before proceeding
2. **Rename the original index.php**:
   ```bash
   mv index.php wp-index.php
   ```
3. **Upload the new index.php**: Upload the Pin Access Manager file as the new `index.php`
4. **Configure security options**:
   - Modify the `superadmin_password` in the `$config_static` array
   - Add your trusted IPs to the `$superadmin_ips` array

## Access and Authentication

### Access Methods

There are three ways to authenticate and use Pin Access Manager:

1. **Superadmin IP**:
   - IPs listed in `$superadmin_ips` always have full access
   - They automatically receive special privileges

2. **Static Password**:
   - Access with `?managewp=superadmin_password`
   - Password defined in `$config_static['superadmin_password']`
   - Always valid (emergency access)

3. **Dynamic Password**:
   - Access with `?managewp=dynamic_password`
   - Configurable via the `setpassword` command
   - Saved in the configuration file

### User States

- **Superadmin**: IPs listed in the static array (full access)
- **Operator**: User authenticated with a valid password (configurable timeout)
- **Visitor**: Normal user subject to access rules

## Main Features

### Site Status Control

- **Open Mode**: Full access to the site
- **Closed Mode**: No access to the site
- **Maintenance**: Maintenance page for all visitors
- **Message**: Displays a custom message
- **Redirect**: Redirects visitors to a specific URL
- **Partial Access**: Possibility to open only frontend or backend

### IP List Management

- **Whitelist**: IPs with full access in any mode
- **Blacklist**: Always blocked IPs (with redirect option)
- **Visitor Registration**: Possibility for visitors to register to be identified

### URL Bypass and Blocks

- **Bypass Patterns**: URLs that bypass access restrictions
- **Block Patterns**: Always blocked URLs
- **Backend Access**: Specific management for admin areas

### Administrative Features

- **Backup and Restore**: Save and import configurations
- **Logging**: Event recording with enable/disable option
- **Reset**: Restore default configuration

## Access Modes

### Standard Modes

- **open**: Full site access (default)
- **closed**: Site completely closed, shows "Site unavailable" page
- **maintenance**: Site under maintenance, shows "Site under maintenance" page
- **message**: Shows a custom message
- **redirect**: Redirects to a specific URL

### Specialized Modes

- **openfront**: Only frontend is accessible, backend is blocked
- **openback**: Only backend is accessible, frontend is blocked

### Duration Setting

All modes can be set with a specific duration:
- Without duration: `?managewp&closed` - permanent
- With duration: `?managewp&closed=1h` - temporary (1 hour)

Supported duration formats:
- `m`: minutes (e.g., 30m)
- `h`: hours (e.g., 2h)
- `d`: days (e.g., 1d)
- `w`: weeks (e.g., 1w)

## Available Commands

### System Commands
- `help` (aliases: `?`, `aiuto`): Shows the list of available commands
- `status` (aliases: `stato`, `st`): Shows the current system status
- `wp` (aliases: `wordpress`, `site`): Loads WordPress while maintaining the session
- `quit` (aliases: `exit`, `esci`): Exits the console and terminates the session

### Site Modes
- `open` (aliases: `apri`, `aperto`): Sets the site to open mode
- `openfront` (aliases: `frontonly`): Opens only the frontend
- `openback` (aliases: `backonly`): Opens only the backend
- `closed` (aliases: `chiudi`, `chiuso`): Sets the site to closed mode
- `manut` (aliases: `maintenance`, `manutenzione`): Sets maintenance mode
- `msg` (aliases: `message`, `messaggio`): Sets a custom message
- `clearmsg` (aliases: `nomsg`, `nomessage`): Clears the custom message
- `redirect` (aliases: `redir`, `url`): Redirects to a specific URL

### IP Management
- `wladd` (aliases: `whitelist`): Adds IP to whitelist
- `wlremove` (aliases: `unwl`, `nowhitelist`): Removes IP from whitelist
- `bladd` (aliases: `blacklist`, `ipblock`): Adds IP to blacklist
- `blremove` (aliases: `unbl`, `noblacklist`, `ipunblock`): Removes IP from blacklist
- `clearwl` (aliases: `wlclear`): Empties the whitelist
- `clearbl` (aliases: `blclear`): Empties the blacklist
- `blredirect` (aliases: `blredir`): Sets redirect URL for blacklisted IPs

### URL Pattern Management
- `bypassadd` (aliases: `addbypass`): Adds URL pattern to bypass
- `bypassremove` (aliases: `rmbypass`): Removes URL pattern from bypass
- `blockadd` (aliases: `addblock`): Adds URL pattern to block
- `blockremove` (aliases: `rmblock`): Removes URL pattern from block

### Operator and Visitor Management
- `clearop` (aliases: `opclear`): Removes all active operators
- `clearvisitors` (aliases: `clearvs`): Clears the registered visitors list
- `setpassword` (aliases: `setpw`, `passwd`): Sets the dynamic password
- `register` (aliases: `reg`): Registers a visitor [special command]

### Configuration Management
- `backup` (aliases: `export`): Exports the current configuration
- `restore` (aliases: `import`): Imports a saved configuration
- `reset` (aliases: `default`): Restores the default configuration
- `log` (aliases: `logging`): Enables/disables logging [on/off]
- `setautoshow` (aliases: `autoshow`, `showempty`): Sets whether to show the console when activated without commands

## Console Interface

### Interface Components

- **Header**: Status information, IP, and session timer
- **Quick links**: Quick links to main commands
- **Console output**: Command output and status display area
- **Input area**: Field for entering commands and submit button

### JavaScript Features

- **Command history**: Navigable with up/down arrows
- **Clickable IPs**: IP addresses in the output are clickable for quick actions
- **Session timer**: Countdown of remaining time for the operator session
- **Clock**: Display of current time

### Using Shortcuts

- **Quick links**: Clickable to enter the corresponding command
- **IP links**: Clickable to enter commands related to that IP
- **History**: Accessible with up/down arrow keys

## IP Management

### Whitelist

The whitelist allows full access to the site, regardless of the configured mode:

```
?managewp&wladd=192.168.1.10     # Add specific IP
?managewp&wladd                  # Add current IP
?managewp&wlremove=192.168.1.10  # Remove specific IP
?managewp&clearwl                # Empty whitelist
```

Whitelisted IPs have priority over the blacklist.

### Blacklist

The blacklist completely blocks IPs, with a redirect option:

```
?managewp&bladd=192.168.1.20     # Add IP to blacklist
?managewp&bladd                  # Add current IP
?managewp&blremove=192.168.1.20  # Remove IP from blacklist
?managewp&clearbl                # Empty blacklist
?managewp&blredirect=https://example.com  # Set redirect URL
?managewp&blredirect=            # Disable redirect
```

The blacklist only affects non-operator and non-superadmin users.

### Visitor Registration

Visitors can register without having access to the console:

```
?register=UserName
```

This registers the IP and name in the system, allowing administrators to identify and, if necessary, add the IP to the whitelist.

## URL Pattern Management

### Bypass Patterns

Bypass patterns allow you to specify URLs that should always be accessible, regardless of the site mode:

```
?managewp&bypassadd=/api/        # Bypass all URLs containing "/api/"
?managewp&bypassadd=/feed.xml    # Bypass feed.xml
?managewp&bypassadd=/webhook     # Bypass webhook
?managewp&bypassremove=/api/     # Remove bypass pattern
```

### Block Patterns

Block patterns specify URLs that must always be blocked:

```
?managewp&blockadd=/wp-content/uploads/private/  # Block access to private folder
?managewp&blockadd=/reserved     # Block access to reserved area
?managewp&blockremove=/reserved  # Remove block pattern
```

### Regex Patterns

For more complex patterns, you can use regular expressions:

```
?managewp&bypassadd=/^\/api\/v[0-9]+\//  # Bypass URLs starting with /api/v followed by numbers
?managewp&blockadd=/\.(pdf|docx)$/       # Block access to PDF and DOCX files
```

Regex patterns must be enclosed in slashes (`/pattern/`).

## Advanced Features

### Default and Temporary Status

The system supports a default state and a temporary state:

```
?managewp&open                  # Set "open" as default state
?managewp&closed=1d             # Set "closed" as temporary state for 1 day
```

When the temporary state expires, the system will automatically return to the default state.

### Backup and Restore

Allows saving and restoring complete configurations:

```
?managewp&backup                # Create a configuration backup
?managewp&restore=pin-access-backup-20240510123045.json  # Restore from backup
```

Backup files are saved in the `wp-content/` folder with a timestamp in the name.

### Logging

The system can record key events:

```
?managewp&log=on                # Enable logging
?managewp&log=off               # Disable logging
```

The log is viewable in the console and contains actions, IPs, and timestamps.

### Advanced Display Options

Controls whether to show the console when activated without commands:

```
?managewp&setautoshow=on        # Show console when activated without commands
?managewp&setautoshow=off       # Don't show console when activated without commands
```

## Code Structure

The `index.php` file is organized in logical sections:

```
1. Initial configuration and definitions
2. Loading and managing configuration
3. Utility functions
4. Command definition
5. Parameter parsing
6. Command execution
7. Access verification and routing
8. Page rendering functions
```

### Main Components

- **$config_static**: Static configuration (password, superadmin IPs, timeout)
- **$config**: Dynamic configuration (saved in JSON file)
- **$commands**: Definition of all available commands
- **Utility functions**: Helpers for managing IPs, durations, URL patterns
- **Rendering functions**: HTML page generation for different modes
- **Main engine**: Routing logic and access control

## How to Add New Commands

To add a new command:

1. **Add the definition in the $commands array**:

```php
'mycommand' => [
    'aliases' => ['mc', 'myalias'],
    'description' => 'Description of my command [parameters]',
    'requires_value' => true,  // true/false/'optional'
    'visible_in_help' => true,
    'menu_order' => 70  // Position in help menu
],
```

2. **Implement the logic in the case switch for command execution**:

```php
case 'mycommand':
    if (empty($value)) {
        $output .= "Error: Value required for this command\n";
    } else {
        // Command logic
        $output .= "My command executed with value: $value\n";
        $save_config_needed = true;  // If it modifies the configuration
        add_log_entry($config, "My command executed: $value", $current_ip);
    }
    break;
```

3. **If the command manages new data**, add the necessary fields in `$config_static['default_config']`.

## Customization

### Page Templates

To customize the appearance of status pages (maintenance, closed, etc.), modify the corresponding functions:

- `show_blocked_page()`
- `show_closed_page()`
- `show_maintenance_page()`
- `show_message_page()`

Or implement a unified template as previously discussed.

### Console Style

To change the console appearance:

1. Locate the `show_console()` function
2. Modify the internal CSS to customize colors, fonts, sizes

### Timeouts and Limits

These values are configurable in the `$config_static` array:

- `operator_timeout`: Operator session duration in seconds
- `max_visitors`: Maximum number of registered visitors
- `command_history_size`: Size of the command history
- `log_max_entries`: Maximum number of log events

## Troubleshooting

### Emergency Access

If you can't access:

1. **Superadmin IP**: Access from an IP listed in `$superadmin_ips`
2. **Superadmin password**: Use `?managewp=superadmin_password` to access

### Configuration Reset

If the configuration is corrupted:

1. Delete the configuration file in `wp-content/pin-access-manager-config.json`
2. The system will create a new default configuration
3. Or use `?managewp&reset` to restore default settings

### Debug

To solve problems:

1. Check the console for error messages
2. Check file permissions to ensure PHP can write to the `wp-content/` folder
3. Enable logging with `?managewp&log=on` to track actions

## Security Considerations

### Passwords

- Always change the default superadmin password
- Use a strong password for dynamic access
- Consider using HTTPS to protect credentials

### Superadmin IPs

- Use static IP addresses when possible
- Regularly verify the list of superadmin IPs
- Limit access only to trusted IPs

### Blacklist

- Consider adding IPs that attempt unauthorized access
- Enable logging to keep track of access attempts

### Backend Access

- Consider separating frontend and backend access
- Use `openback` during maintenance to allow administration

### Configuration File

- Verify that the configuration file is not publicly accessible
- Consider adding server-level protections (e.g., .htaccess rules) to prevent direct access to the JSON file
- Perform regular backups of the configuration
- Check file permissions to ensure only the web server can read and write to it
- Consider encrypting sensitive data in the configuration file for additional security

## Conclusion

Pin Access Manager provides a robust, flexible solution for controlling access to your WordPress site. Whether you need to temporarily close your site for maintenance, protect specific areas, or manage access based on IP addresses, this tool offers a comprehensive set of features with minimal overhead.

The modular design makes it easy to extend with new commands and functionalities, while the console interface provides a convenient way to manage your site's accessibility. By following the security considerations outlined in this documentation, you can ensure that the tool itself remains secure while protecting your WordPress installation.

For more advanced users, the tool can be further customized and extended to meet specific requirements, making it a valuable addition to any WordPress administrator's toolkit.

### Support and Contributions

For support issues or to contribute improvements to Pin Access Manager, please reach out to the developer or consider submitting pull requests if the code is hosted in a public repository.

Remember to always keep the tool updated with the latest security best practices to ensure your WordPress site remains protected.

---

*This documentation last updated: May 2025*
