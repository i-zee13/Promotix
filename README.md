# Digital Promotix — Laravel 11 Admin Dashboard

Clean, production-ready Laravel 11 admin dashboard starter with dark theme, Tailwind CSS, Vite, and Chart.js.

---

## Quick start (run the project)

From the project directory, run these in order. You need **PHP 8.2**, **Composer**, and **Node 18+** installed.

```bash
# 1. Install PHP dependencies
composer install

# 2. Environment & app key (if you don't have .env yet)
cp .env.example .env
php artisan key:generate

# 3. Install frontend dependencies
npm install
```

Then start the app with **two terminals**:

| Terminal 1 | Terminal 2 |
|------------|------------|
| `php artisan serve` | `npm run dev` |

Open **http://localhost:8000** in your browser. Leave both commands running.

That’s it. For more detail (PHP version, troubleshooting, Docker), see the sections below.

---

## Requirements

Before you begin, ensure you have (or are prepared to install):

| Requirement | Version |
|-------------|---------|
| **PHP**     | 8.2 or higher |
| **Composer**| Latest stable |
| **Node.js** | 18 or higher |
| **npm**     | Comes with Node (or use yarn) |

Laravel 11 requires **PHP 8.2+**. Do not assume your current environment meets this.

This project pins **PHP 8.2** via a `.php-version` file. Use PHP 8.2 for both the CLI and the web server when working on this repo.

#### macOS: PHP 8.2 with Homebrew

```bash
# Install PHP 8.2
brew install php@8.2

# Use PHP 8.2 (add to ~/.zshrc to make it default in every terminal)
export PATH="$(brew --prefix php@8.2)/bin:$(brew --prefix php@8.2)/sbin:$PATH"
php -v   # should show 8.2.x
```

To remove an older PHP (e.g. 8.1):

```bash
brew unlink php@8.1
brew uninstall php@8.1
```

If `php -v` still shows an older version after opening a new terminal, another PHP (e.g. Herd) may be earlier in your `PATH`. Ensure only Homebrew’s php@8.2 path is in `~/.zshrc` so it wins.

**This project:** A `.envrc` is included so that when you use [direnv](https://direnv.net/) and run `direnv allow` in this directory, PHP 8.2 is forced and Herd is removed from `PATH` here only.

**Fully uninstall Herd (optional):** To remove Herd from your Mac: quit Herd, drag **Applications → Herd.app** to Trash, then delete `~/Library/Application Support/Herd`. Your shell config (`.zshrc`) already ignores Herd in `PATH` and uses Homebrew PHP 8.2.

---

## Next Steps — Local Development Setup

Follow these steps in order. Do not skip the PHP check.

### 1. Check your PHP version

Run:

```bash
php -v
```

You should see something like `PHP 8.2.x` or `PHP 8.3.x`.  
**Laravel 11 requires PHP 8.2 or higher.** If your version is below 8.2, upgrade PHP before continuing.

#### If your PHP version is below 8.2 — upgrade by system

**macOS (Homebrew)**

```bash
brew install php@8.2
export PATH="$(brew --prefix php@8.2)/bin:$(brew --prefix php@8.2)/sbin:$PATH"
php -v   # confirm 8.2+
```

Add the `export PATH=...` line to `~/.zshrc` to make PHP 8.2 the default in new terminals.

**Ubuntu (apt)**

```bash
sudo add-apt-repository ppa:ondrej/php
sudo apt update
sudo apt install php8.2 php8.2-cli php8.2-common php8.2-mbstring php8.2-xml php8.2-curl php8.2-zip
php -v   # confirm 8.2+
```

**Windows**

- **XAMPP:** Download a XAMPP build that includes PHP 8.2+ from [Apache Friends](https://www.apachefriends.org/), install it, and use the `php.exe` from that installation (e.g. add its folder to `PATH`).
- **Manual:** Download PHP 8.2+ Windows binaries from [windows.php.net](https://windows.php.net/download/), extract to a folder (e.g. `C:\php`), add that folder to your system `PATH`, and run `php -v` in a new terminal.

After upgrading, run `php -v` again and confirm 8.2+ before the next step.

---

### 2. Install PHP dependencies

From the project root:

```bash
composer install
```

If this fails with a PHP version error, your CLI is still using an older PHP. Fix the version (see step 1) and run `composer install` again.

---

### 3. Environment and app key

```bash
cp .env.example .env
php artisan key:generate
```

This creates your local `.env` and sets `APP_KEY`. Leave other `.env` values as-is for basic local use unless you need DB or other services.

---

### 4. Install frontend dependencies

```bash
npm install
```

If you see “command not found: npm”, install Node.js 18+ first ([nodejs.org](https://nodejs.org/) or your system package manager), then run `npm install` again.

---

### 5. Run the application

**Terminal 1 — Laravel:**

```bash
php artisan serve
```

Leave this running. The app will be at **http://localhost:8000** (or the URL shown).

**Terminal 2 — Vite (for CSS/JS hot reload):**

```bash
npm run dev
```

Leave both terminals running. Open **http://localhost:8000** in your browser to see the dashboard.

---

### 6. Optional: build assets for production-style testing

If you prefer built assets instead of Vite dev server:

```bash
npm run build
```

Then run only `php artisan serve`. The app will use the files in `public/build/` (no need for `npm run dev`).

---

## Troubleshooting

**Composer fails: “your php version does not satisfy that requirement”**

- Your CLI PHP is below 8.2. Run `php -v` and upgrade PHP (see step 1), then run `composer install` again.
- On some systems you may need to run the correct PHP explicitly, e.g. `php8.2 /usr/bin/composer install` or use your version manager (e.g. phpenv, asdf).

**“npm: command not found”**

- Node.js (and npm) are not installed or not on your `PATH`. Install Node 18+ from [nodejs.org](https://nodejs.org/) or your package manager (e.g. `brew install node` on macOS), open a new terminal, and run `npm install` again.

**Vite assets not loading (blank or unstyled page)**

- Make sure `npm run dev` is running in a second terminal so Vite can serve and hot-reload assets.  
- If you are not using the dev server, run `npm run build` and refresh the page so Laravel serves the built files from `public/build/`.  
- Confirm `APP_ENV=local` (or that you’re not forcing production asset URLs) and that no firewall or proxy is blocking the Vite dev server.

---

## Docker development

A simple Docker setup is included for local development without installing PHP 8.2 or Node locally.

**Requirements:** Docker and Docker Compose.

1. **Create `.env`** (if you don’t have one):

   ```bash
   cp .env.example .env
   ```

   Set these for Docker (or keep defaults):

   - `DB_HOST=mysql`
   - `DB_DATABASE=promotix`
   - `DB_USERNAME=promotix`
   - `DB_PASSWORD=secret`

2. **Install PHP dependencies** (run once, or whenever `composer.json` changes):

   ```bash
   docker compose run --rm app composer install
   ```

3. **Generate app key** (if `.env` is new):

   ```bash
   docker compose run --rm app php artisan key:generate
   ```

4. **Start the stack** (app, Nginx, MySQL, Node with Vite):

   ```bash
   docker compose up -d
   ```

   The app is at **http://localhost:8000**. The `node` service runs `npm install` and `npm run dev` so Vite HMR works through port 8000.

5. **Run Artisan** (migrations, tinker, etc.):

   ```bash
   docker compose run --rm app php artisan migrate
   docker compose run --rm app php artisan tinker
   ```

6. **Stop the stack:**

   ```bash
   docker compose down
   ```

**Containers:** `app` (PHP 8.2 FPM), `nginx` (port 8000), `mysql` (8, port 3306), `node` (18, runs Vite dev server).

---

## Project structure (reference)

| Path | Purpose |
|------|---------|
| `resources/views/layouts/admin.blade.php` | Admin layout (sidebar, header) |
| `resources/views/dashboard.blade.php`     | Dashboard page |
| `resources/views/components/stat-card.blade.php` | Reusable stat card |
| `resources/js/app.js`   | Chart.js (revenue + user growth charts) |
| `resources/css/app.css`| Tailwind directives |
| `routes/web.php`       | Web routes (dashboard at `/`) |

---

## Stack

- **Laravel 11** · **Tailwind CSS** (utility-only) · **Vite** · **Chart.js**  
- Dark theme (`#0D0D0D`), 20px rounded containers, responsive sidebar (hidden on mobile).
