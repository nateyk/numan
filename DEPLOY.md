# Deploy — cPanel + GitHub Actions (NumanFreight)

> Quick setup for `https://github.com/nateyk/numan` → cPanel live testing.

## 0. Push existing repo to your GitHub (one-time)

Your local already has 85 commits ahead of `XCO-Agency`. Push to **your** repo:

```bash
# check current origin (XCO)
git remote -v

# add your repo as new origin (keep XCO as upstream if you want)
git remote rename origin upstream
git remote add origin https://github.com/nateyk/numan.git

# or if origin already is nateyk/numan, just push:
git branch -M main
git push -u origin main
```

If repo is empty, GitHub shows the same commands — use the “push an existing repository” block.

> If `git push` asks for auth: create a **Personal Access Token (Classic)** at `github.com/settings/tokens` → `repo` scope, then use it as password, or use `gh auth login`.

---

## 1. cPanel — prepare live server

1. **cPanel → File Manager** — note the document root:
   - Usually `~/public_html` = `https://yourdomain.com`
   - For Laravel you need `public_html` to point to Laravel's `public/` folder. Two options:
     - **Option A (recommended):** set **Application root** `~/numan` and **Document root** `~/numan/public` via *Setup Node.js App* or *Application Manager* if available.
     - **Option B:** keep `~/public_html` as document root and upload Laravel's `public/` contents there, or symlink:
       ```bash
       mv public_html public_html.bak
       ln -s numan/public public_html
       ```

2. **cPanel → MySQL® Databases**
   - Create DB: `nateyk_numan`  (or any)
   - Create user: `nateyk_numan` / strong password
   - Add user to DB (ALL PRIVILEGES)

3. **cPanel → Terminal** (or SSH) — or use *Git Version Control*:
   ```bash
   cd ~
   git clone https://github.com/nateyk/numan.git
   cd numan
   cp .env.example .env
   # edit .env: APP_URL, DB_*, MAIL_*, STRIPE_*, etc.
   nano .env
   ```

   Minimal `.env` for live:
   ```
   APP_NAME="NumanFreight"
   APP_ENV=production
   APP_DEBUG=false
   APP_URL=https://yourdomain.com
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=nateyk_numan
   DB_USERNAME=nateyk_numan
   DB_PASSWORD=yourStrongPassword
   FILESYSTEM_DISK=public
   ```

4. **On server (SSH/Terminal):**
   ```bash
   cd ~/numan
   composer install --no-dev --optimize-autoloader
   php artisan key:generate
   php artisan storage:link
   php artisan migrate --force
   php artisan config:cache
   php artisan route:cache
   php artisan view:cache
   npm ci   # or pnpm install, but cPanel Node may need `npm`
   npm run build
   chmod -R 775 storage bootstrap/cache
   ```

   If `pnpm` not available on cPanel, change `package.json` engine or use `npm`:
   ```bash
   npm install --frozen-lockfile  # or npm ci
   ```

5. **Cron (optional) for queue/scheduler:**
   cPanel → Cron Jobs:
   ```
   * * * * * /usr/local/bin/php /home/nateyk/numan/artisan schedule:run >> /dev/null 2>&1
   ```

---

## 2. GitHub → Secrets (for auto-deploy)

Repo → **Settings → Secrets and variables → Actions → New repository secret**

Pick **one** deploy method:

### A. FTP (works on every cPanel)
| Secret | Value | Where to find |
|--------|-------|---------------|
| `CPANEL_FTP_SERVER` | `ftp.yourdomain.com` or `yourdomain.com` | cPanel → FTP Accounts → server |
| `CPANEL_FTP_USERNAME` | `nateyk@yourdomain.com` or `nateyk` | FTP Accounts |
| `CPANEL_FTP_PASSWORD` | your FTP password | FTP Accounts |
| `CPANEL_FTP_PORT` | `21` | default |
| `CPANEL_FTP_PROTOCOL` | `ftp` or `ftps` | try `ftps` if `ftp` fails |
| `CPANEL_REMOTE_DIR` | `public_html/` or `~/numan/` | where you cloned |

### B. SSH (faster, runs composer/migrate automatically)
Enable **SSH Access** in cPanel → **Terminal** or **SSH Access** → generate key or add your own.

| Secret | Value |
|--------|-------|
| `CPANEL_SSH_HOST` | `yourdomain.com` or server IP |
| `CPANEL_SSH_USER` | `nateyk` |
| `CPANEL_SSH_KEY` | *content* of `~/.ssh/id_rsa` (private key, no passphrase) |
| `CPANEL_SSH_PORT` | `22` or `21098` (cPanel often 21098) |
| `CPANEL_REMOTE_DIR` | `~/numan/` (where repo lives) |

> Generate SSH key locally:
> ```bash
> ssh-keygen -t ed25519 -C "github-deploy" -f ~/.ssh/cpanel_deploy
> cat ~/.ssh/cpanel_deploy      # → paste to CPANEL_SSH_KEY
> cat ~/.ssh/cpanel_deploy.pub   # → paste to cPanel → SSH Access → Manage SSH Keys → Import → Authorize
> ```

---

## 3. CI/CD — what runs automatically

- **`.github/workflows/ci.yml`** — on every `push` to `main`/`develop` + PRs:
  1. PHP 8.4 + `composer install`
  2. Node 22 + `pnpm install`
  3. `vendor/bin/pint --test` (lint)
  4. `pnpm run build`
  5. `cp .env.example .env && php artisan key:generate`
  6. `php artisan test --compact`

- **`.github/workflows/deploy-cpanel.yml`** — on `push` to `main` (after CI passes):
  1. Re-runs CI (tests must pass)
  2. Builds production artifact (`composer install --no-dev`, `pnpm run build`)
  3. Deploys:
     - If `CPANEL_FTP_*` set → FTP deploy
     - If `CPANEL_SSH_*` set → `rsync` + SSH post-deploy (`migrate --force`, `storage:link`, `config:cache`, `chmod 775`)
  4. If no secrets → CI passes, deploy skipped (warning)

Existing manual workflows (`tests.yml`, `lint.yml`, etc.) kept as `workflow_dispatch` for local `act` testing.

---

## 4. Test live

1. Push to `main`:
   ```bash
   git add .
   git commit -m "feat: freight fixes + deploy"
   git push origin main
   ```
2. GitHub → **Actions** → watch `CI` then `Deploy to cPanel`.
3. On cPanel, verify:
   ```bash
   tail -f ~/numan/storage/logs/laravel.log
   php artisan about
   ```

---

## 5. cPanel Git Pull (alternative, no FTP/SSH action)

If you prefer cPanel's **Git Version Control** (pull instead of push):

1. cPanel → **Git Version Control** → **Clone** `https://github.com/nateyk/numan.git` → `~/numan`
2. cPanel → **Git Version Control** → **Manage** → **Pull or Deploy** (or set webhook: `https://yourdomain.com:2083/cpsess.../execute/VersionControl/deployment`).

Add GitHub webhook: Repo → Settings → Webhooks → `https://yourdomain.com:2083/cpsess...` (from cPanel).

Then CI still runs tests, but deploy is manual pull — or add a GitHub Action that hits the cPanel deploy URL via `curl`.

---

## 6. Troubleshooting

- **500 error:** `chmod -R 775 storage bootstrap/cache`, `php artisan config:clear`, check `.env` `APP_KEY`.
- **Vite manifest not found:** `npm run build` not run on server — ensure deploy runs it, or pre-build locally and FTP `public/build`.
- **storage 403:** `php artisan storage:link` must run; `public/storage` should be symlink `../storage/app/public`.
- **DB errors:** re-run `php artisan migrate --force`, check `DB_*` in `.env`.
- **FTP 530:** use `CPANEL_FTP_PROTOCOL=ftps`, check `CPANEL_REMOTE_DIR` has trailing `/`.

---

## 7. Quick commands reference

```bash
# first time push
git remote add origin https://github.com/nateyk/numan.git
git branch -M main
git push -u origin main

# ongoing dev
git checkout -b feat/my-freight-fix
git commit -am "fix: ..."
git push origin feat/my-freight-fix
# → open PR → CI runs → merge to main → auto-deploy

# local CI with act
act push -W .github/workflows/ci.yml
act push -W .github/workflows/deploy-cpanel.yml
```
