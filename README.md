# OpenLitterMap

**Open-source platform for mapping litter and plastic pollution.**

Built in Cork, Ireland. Used in 110+ countries. 500,000+ geotagged observations. 850,000+ classified tags. Cited in [98+ peer-reviewed publications](/references) including Nature and the World Bank. Previously identified as a Digital Public Good by the Digital Public Goods Alliance.

**Code:** [GPL v3](https://www.gnu.org/licenses/gpl-3.0.en.html) | **Data:** [ODbL](https://opendatacommons.org/licenses/odbl/) | **Privacy:** [Anonymous by default](/privacy)

---

## Tech Stack

- **Backend:** PHP 8.2, Laravel 11
- **Frontend:** Vue 3 (Composition API), Pinia, Vue Router 4, Tailwind CSS 3, Vite 6
- **Database:** MySQL 5.7+, Redis 7+
- **Auth:** Laravel Passport + Sanctum
- **Real-time:** Laravel Reverb (WebSockets)
- **Storage:** AWS S3 (prod), MinIO (dev)
- **Payments:** Stripe via Laravel Cashier

---

## Getting Started

### Prerequisites

- PHP 8.2+, Composer
- Node v20.20.0, npm v10.8.2
- MySQL 5.7+, Redis 7+
- [Laravel Valet](https://laravel.com/docs/11.x/valet) (macOS)

### Install

```bash
git clone https://github.com/OpenLitterMap/openlittermap-web.git
cd openlittermap-web

composer install
npm install

cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan db:seed
```

### HTTPS with Valet

The app runs at `https://olm.test`. Set up Valet:

```bash
valet trust
valet secure olm
valet restart
```

Update `.env`:

```env
APP_URL=https://olm.test
```

See [readme/Setup.md](readme/Setup.md) for Reverb TLS configuration and troubleshooting.

### Start Everything

The app needs several processes running at once. Create a startup script:

**1. Create the script:**

```bash
mkdir -p ~/scripts
cat > ~/scripts/olm.sh << 'SCRIPT'
#!/bin/zsh

# Update this to your project directory
PROJECT_DIR="/Users/youruser/Code/openlittermap-web"

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
else
  open "https://olm.test"
fi
SCRIPT
chmod +x ~/scripts/olm.sh
```

**2. Add to your PATH** (in `~/.zshrc`):

```bash
export PATH=$PATH:~/scripts
```

**3. Run it:**

```bash
olm.sh
```

This opens a terminal tab for each process and launches the browser:

| Tab | Command | Purpose |
|-----|---------|---------|
| Vite | `npm run dev` | Frontend HMR |
| Reverb | `php artisan reverb:start` | WebSocket server |
| Queue | `php artisan queue:work` | Background jobs |
| Horizon | `php artisan horizon` | Queue dashboard |
| Tinker | `php artisan tinker` | PHP REPL for debugging |
| Serve | `php artisan serve --host=0.0.0.0 --port=8000` | HTTP server for mobile dev |

---

## Running Tests

```bash
php artisan test                                          # All tests (~1000+)
php artisan test tests/Feature/Teams/CreateTeamTest.php   # Single file
php artisan test --filter=test_method_name                # Single test
```

---

## Project Structure

```
app/
  Actions/           # Command-pattern action classes
  Http/Controllers/  # Thin controllers
  Http/Requests/     # Form request validation
  Http/Resources/    # API response transformers
  Models/            # Eloquent models
  Services/          # Business logic (MetricsService, ClassifyTagsService, etc.)
  Enums/             # VerificationStatus, CategoryKey, etc.

resources/js/
  views/             # Page components (by feature)
  components/        # Reusable components
  stores/            # Pinia stores
  router/index.js    # Vue Router config
  langs/             # Translation files (i18n)

routes/
  api.php            # API routes (v3)
  web.php            # SPA catch-all

tests/
  Feature/           # Integration tests
  Unit/              # Unit tests
```

---

## Documentation

Detailed docs live in `readme/`:

| Doc | What it covers |
|-----|---------------|
| [Setup.md](readme/Setup.md) | Full local dev setup, HTTPS, Reverb TLS, troubleshooting |
| [API.md](readme/API.md) | API endpoint reference (source of truth for all contracts) |
| [Tags.md](readme/Tags.md) | Tagging system, categories, litter objects |
| [Teams.md](readme/Teams.md) | Teams, permissions, safeguarding |
| [SchoolPipeline.md](readme/SchoolPipeline.md) | School approval pipeline |
| [Metrics.md](readme/Metrics.md) | Metrics pipeline and aggregation |
| [Clustering.md](readme/Clustering.md) | Map clustering system |
| [Leaderboards.md](readme/Leaderboards.md) | Leaderboard system |
| [XP.md](readme/XP.md) | XP scoring and levels |
| [Admin.md](readme/Admin.md) | Admin verification system |
| [Profile.md](readme/Profile.md) | User profile, privacy, account deletion |
| [Upload.md](readme/Upload.md) | Photo upload pipeline |
| [Locations.md](readme/Locations.md) | Location and geography system |
| [Terms.md](readme/Terms.md) | Terms & Conditions |
| [Privacy.md](readme/Privacy.md) | Privacy Policy (GDPR) |

---

## Contributing

1. Fork the repo
2. Create a branch: `git checkout -b feature/your-feature`
3. Make your changes, add tests
4. Submit a pull request

File issues and feature requests at [github.com/OpenLitterMap](https://github.com/OpenLitterMap).

---

## Links

- **Website:** [openlittermap.com](https://openlittermap.com)
- **Global Map:** [openlittermap.com/global](https://openlittermap.com/global)
- **Training:** [LitterWeek.org](https://litterweek.org)
- **Contact:** info@openlittermap.com
