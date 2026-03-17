# Local Development Setup

## Prerequisites

- PHP 8.2+, Composer
- Node v20.20.0, npm v10.8.2
- MySQL 5.7+, Redis 7+
- Laravel Valet (macOS)

## Quick Start

```bash
composer install && npm install
cp .env.example .env && php artisan key:generate
php artisan migrate
php artisan db:seed
```

This will run migrations and seed the database with 3 test users.

## Running the App

The app requires several processes running simultaneously. We recommend creating an `olm.sh` startup script.

### Startup Script

Create `~/scripts/olm.sh` and add `~/scripts` to your `PATH` in `.zshrc`:

```bash
export PATH=$PATH:~/scripts
```

The script opens a terminal tab for each process and launches the browser:

```bash
#!/bin/zsh

PROJECT_DIR="/Users/<you>/Code/openlittermap-web"

open_new_tab() {
  local command="$1"
  osascript <<EOF
  tell application "Terminal"
    activate
    tell application "System Events"
      tell process "Terminal"
        keystroke "t" using command down
        delay 0.2
      end tell
    end tell
    do script "cd $PROJECT_DIR && $command" in front window
  end tell
EOF
}

open_new_tab "npm run dev"
open_new_tab "php artisan reverb:start"
open_new_tab "php artisan queue:work"
open_new_tab "php artisan horizon"
open_new_tab "php artisan tinker"
open_new_tab "php artisan serve --host=0.0.0.0 --port=8000"

# Open browser
if [ -x "/Applications/Brave Browser.app/Contents/MacOS/Brave Browser" ]; then
  open -a "Brave Browser" "https://olm.test"
elif [ -x "/Applications/Google Chrome.app/Contents/MacOS/Google Chrome" ]; then
  open -a "Google Chrome" "https://olm.test"
fi
```

Then run `olm.sh` from any terminal to start everything.

### Processes

| Tab | Command | Purpose |
|-----|---------|---------|
| Vite | `npm run dev` | Frontend HMR at `https://olm.test` |
| Reverb | `php artisan reverb:start` | WebSocket server (wss://olm.test:8080) |
| Queue | `php artisan queue:work` | Background job processing |
| Horizon | `php artisan horizon` | Queue dashboard |
| Tinker | `php artisan tinker` | PHP REPL for debugging |
| Mobile | `php artisan serve --host=0.0.0.0 --port=8000` | HTTP server for React Native dev |

### Mobile Development

The browser uses `https://olm.test` (Valet + HTTPS), but React Native rejects self-signed certificates. The `php artisan serve` tab provides a plain HTTP endpoint for mobile:

- **iOS Simulator:** `http://localhost:8000`
- **Android Emulator:** `http://10.0.2.2:8000`
- **Physical device:** `http://<your-local-ip>:8000`

## HTTPS with Valet

Valet can serve the site over HTTPS with a self-signed certificate. This is required for WebSocket (wss://) connections.

### 1. Secure the site and trust the CA

```bash
valet trust          # Adds Valet CA to macOS Keychain as trusted root
valet secure olm     # Generates TLS cert for olm.test
valet restart
```

If the browser still shows "Not Secure", open **Keychain Access**, find **"Laravel Valet CA Self Signed CN"** in the **System** keychain, double-click it, expand **Trust**, and set **"When using this certificate"** to **"Always Trust"**. Then fully quit and reopen your browser (`Cmd+Q`).

### 2. Update `.env` for HTTPS

```env
APP_URL=https://olm.test
```

### 3. Configure Reverb for TLS

Reverb must serve over TLS so the browser can connect via `wss://`. Add these to `.env`:

```env
REVERB_HOST=olm.test
REVERB_PORT=8080
REVERB_SCHEME=https

# TLS for the Reverb WebSocket server (uses Valet's certs)
REVERB_SERVER_TLS_CERT=/Users/<you>/.config/valet/Certificates/olm.test.crt
REVERB_SERVER_TLS_KEY=/Users/<you>/.config/valet/Certificates/olm.test.key
REVERB_SERVER_SCHEME=https

# Frontend
VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"
VITE_REVERB_HOST="${REVERB_HOST}"
VITE_REVERB_PORT="${REVERB_PORT}"
VITE_REVERB_SCHEME="${REVERB_SCHEME}"
```

### 4. Clear config and restart all processes

After any `.env` or config change:

```bash
php artisan config:clear
php artisan queue:restart    # Queue workers cache config in memory
php artisan reverb:restart   # Or Ctrl+C and re-run reverb:start
```

## How Reverb TLS Works

There are three separate concerns:

| Concern | Config | Purpose |
|---------|--------|---------|
| **Reverb server binding** | `REVERB_SERVER_HOST` (default `0.0.0.0`), `REVERB_SERVER_PORT` (default `8080`) | IP/port the server process listens on |
| **Reverb server TLS** | `REVERB_SERVER_TLS_CERT`, `REVERB_SERVER_TLS_KEY` in `config/reverb.php` | Enables TLS on the WebSocket server |
| **Broadcasting client** | `REVERB_HOST`, `REVERB_SERVER_SCHEME` in `config/broadcasting.php` | How Laravel's backend connects to Reverb to push events |
| **Frontend client** | `VITE_REVERB_HOST`, `VITE_REVERB_SCHEME` in `resources/js/echo.js` | How the browser connects via Echo/Pusher.js |

Key detail: `REVERB_SERVER_HOST` must be an IP (e.g., `0.0.0.0`) for the server to bind, but the broadcasting client host (`REVERB_HOST` in `config/broadcasting.php`) must be a hostname matching the TLS cert (e.g., `olm.test`). These are intentionally different.

## Common Issues

### "cURL error 60: SSL: no alternative certificate subject name matches target ipv4 address '127.0.0.1'"
The broadcasting client is connecting to `127.0.0.1` but the cert is for `olm.test`. Ensure `config/broadcasting.php` uses `REVERB_HOST` (not `REVERB_SERVER_HOST`) for the client connection host.

### "InvalidArgumentException: Given URI does not contain a valid host IP"
`REVERB_SERVER_HOST` is set to a hostname instead of an IP. It must be an IP like `0.0.0.0` or `127.0.0.1`.

### "Received HTTP/0.9 when not allowed"
The broadcasting client is connecting over HTTP to a TLS-enabled server. Set `REVERB_SERVER_SCHEME=https`.

### Config changes not taking effect
Long-running processes (queue workers, Reverb) cache config in memory. After `.env` changes, run `php artisan config:clear`, then restart all processes.

### Browser still shows "Not Secure"
1. Run `valet trust` then regenerate: `valet unsecure olm && valet secure olm && valet restart`
2. Trust the CA in Keychain Access (System keychain → Always Trust)
3. Fully quit browser (`Cmd+Q`) — closing tabs is not enough

## Test Database

See CLAUDE.md for test database setup (`olm_test` with `GenerateTagsSeeder`).
