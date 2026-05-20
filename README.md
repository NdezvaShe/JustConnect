# JustConnect — Smarter Legal Decisions Powered by NLP

A full-stack Laravel application for Zimbabwean legal document analysis, powered by an in-house PHP NLP engine with optional GPT-4o / Gemini integration.

---

## Tech Stack

| Layer       | Technology                               |
|-------------|------------------------------------------|
| Backend     | PHP 8.2 · Laravel 11                     |
| Database    | MySQL 8 / MariaDB 10.3 (via XAMPP)       |
| NLP Engine  | Custom PHP NLP (TF-IDF, NER, readability)|
| AI (optional)| OpenAI GPT-4o · Google Gemini 1.5 Flash |
| Frontend    | Blade · Vanilla JS · PDF.js · jsPDF      |
| Auth        | Laravel Session Auth · OTP Email Verify  |

---

## Free Public Deployment: Koyeb

This repo is ready to deploy as a public Docker web service on Koyeb's free instance. Koyeb gives the app a public `https://<service>.koyeb.app` address; anyone with that address can open it.

### What was added for hosting

- `Dockerfile` builds the Laravel app on PHP 8.4 + Apache.
- `docker/koyeb-entrypoint.sh` prepares storage, runs migrations, seeds the demo user, caches config/routes, and starts Apache on Koyeb's assigned `PORT`.
- `.env.koyeb.example` lists the environment variables to set in Koyeb.
- Laravel config files and a baseline migration allow a fresh hosted install to boot without importing the XAMPP MySQL dump manually.

### Deploy Steps

1. Push this project to a GitHub repository. Do not commit your local `.env`.
2. In Koyeb, choose **Create App** -> **GitHub** -> select the repository.
3. Use **Dockerfile** deployment from the repository root.
4. Choose the **Free** instance.
5. Add these environment variables, using `.env.koyeb.example` as the template:

```env
APP_NAME="JustConnect"
APP_ENV=production
APP_DEBUG=false
APP_KEY=base64:REPLACE_WITH_A_STABLE_KEY
APP_URL=https://your-service-name.koyeb.app

DB_CONNECTION=sqlite
DB_DATABASE=/var/www/html/database/database.sqlite
SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=sync
PREFERRED_AI=nlp_local
MAIL_MAILER=log
LOG_CHANNEL=stderr
SEED_DEMO_USER=true
DEMO_USER_PASSWORD=change-this-public-demo-password
```

Generate `APP_KEY` locally before deploying:

```bash
php artisan key:generate --show
```

After deployment, open the Koyeb URL. The seeded demo login is `demo@justconnect.zw`; the password is whatever you set in `DEMO_USER_PASSWORD`.

### Free Tier Notes

The free Koyeb instance uses local ephemeral storage. The app is public and usable, but uploads and SQLite data can reset after redeploys or platform restarts. For real production data, attach a paid persistent database/storage service later and switch `DB_CONNECTION` to `mysql` or another managed database.

---

## XAMPP Setup (Step-by-Step)

### 1. Prerequisites

- XAMPP with **PHP 8.2+** and **MySQL/MariaDB**
- [Composer](https://getcomposer.org/) installed globally
- Git (optional)

### 2. Copy Project to XAMPP

```bash
# Windows
xcopy /E /I justconnect C:\xampp\htdocs\justconnect

# macOS / Linux
cp -r justconnect /opt/lampp/htdocs/justconnect
```

### 3. Install PHP Dependencies

```bash
cd C:\xampp\htdocs\justconnect    # Windows
# or
cd /opt/lampp/htdocs/justconnect  # macOS/Linux

composer install
```

### 4. Create the Database

1. Start Apache + MySQL in XAMPP Control Panel
2. Open **phpMyAdmin**: `http://localhost:8501/phpmyadmin`
3. Click **Import** → select `database/justconnect.sql`
4. Click **Go**

Or via command line:
```bash
mysql -u root -p < database/justconnect.sql
```

### 5. Configure Environment

```bash
cp .env.example .env
php artisan key:generate
```

Edit `.env`:
```env
APP_URL=http://localhost:8501/justconnect/public

DB_HOST=127.0.0.1
DB_PORT=4306
DB_DATABASE=justconnect
DB_USERNAME=root
DB_PASSWORD=          # blank for default XAMPP

# Choose your AI provider (or leave as nlp_local for no API key)
PREFERRED_AI=gemini         # nlp_local | openai | gemini
OPENAI_API_KEY=sk-...       # only if PREFERRED_AI=openai
GEMINI_API_KEY=...          # only if PREFERRED_AI=gemini
GEMINI_MODEL=gemini-2.5-flash
```

### 6. Create Storage Symlink & Cache

```bash
php artisan storage:link
php artisan config:cache
php artisan route:cache
```

### 6b. Enable Background Analysis Queue

Import the `jobs` and `failed_jobs` tables from `database/justconnect.sql`, then run:

```bash
php artisan queue:work --tries=2
```

Set in `.env`:

```env
QUEUE_CONNECTION=database
QUEUE_FAILED_DRIVER=database-uuids
```

### 7. Set Permissions (macOS/Linux)

```bash
chmod -R 775 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache
```

### 8. Access the App

Open: **http://localhost:8501/justconnect/public**

Demo login (from seed):
- Email: `demo@justconnect.zw`
- Password: `Admin@2024!`

---

## NLP Engine Features

The built-in PHP NLP engine (`app/Services/NlpService.php`) provides:

| Feature                  | Description                                               |
|--------------------------|-----------------------------------------------------------|
| **Named Entity Recognition** | Persons, organisations, courts, dates, monetary amounts |
| **TF-IDF Keywords**      | Top-20 legal keywords per document                        |
| **Document Classification** | 10+ Zimbabwe-specific legal document types             |
| **Extractive Summary**   | Sentence scoring for executive summary & key findings     |
| **Obligation Detection** | Extracts shall/must/agreed-to provisions                  |
| **Evidence Trail**       | Quoted snippets, page references, and extraction reasons  |
| **Legal Principles**     | Identifies latin maxims and ZW legal principles           |
| **Readability Score**    | Flesch-Kincaid reading ease (0–100)                       |
| **Sentiment Analysis**   | Positive / Neutral / Negative heuristic                   |
| **Language Detection**   | English / Shona / Ndebele                                 |
| **Legal Categories**     | Constitutional, Commercial, Criminal, Labour, etc.        |
| **Risk & Action Panel**  | Urgency, deadlines, risky clauses, and next actions       |

---

## AI Provider Configuration

| `PREFERRED_AI` | Behaviour                                                     |
|----------------|---------------------------------------------------------------|
| `nlp_local`    | Pure PHP NLP (no API key, always works, instant)              |
| `openai`       | GPT-4o analysis + NLP metadata merged together                |
| `gemini`       | Gemini 2.5 Flash analysis + NLP metadata merged together      |

The NLP engine **always runs** regardless of provider — AI output is merged with NLP metadata (entities, keywords, sentiment, readability).

---

## Project Structure

```
justconnect/
├── app/
│   ├── Http/Controllers/
│   │   ├── AuthController.php       # Login, register, OTP verify
│   │   └── DocumentController.php   # Upload, NLP analyse, records, downloads
│   ├── Models/
│   │   ├── User.php
│   │   ├── Document.php
│   │   ├── Summary.php
│   │   └── Download.php
│   ├── Services/
│   │   ├── NlpService.php           # Pure PHP NLP pipeline
│   │   └── AiSummaryService.php     # GPT-4o / Gemini / NLP fallback
│   └── helpers.php
├── database/
│   └── justconnect.sql              # Full MySQL schema + seed
├── public/
│   └── css/app.css                  # Mobile-responsive stylesheet
├── resources/views/
│   ├── landing.blade.php
│   ├── auth/
│   │   ├── login.blade.php
│   │   └── register.blade.php       # With OTP verification
│   └── dashboard/
│       └── index.blade.php          # Full SPA-style dashboard
├── routes/web.php
├── config/services.php
├── .env.example
└── composer.json
```

---

## Supported Document Types

- Court Judgments (High Court, Supreme Court, Constitutional Court)
- Acts & Statutory Instruments
- Lease Agreements
- Employment Contracts
- Sale Agreements
- Loan Agreements
- Powers of Attorney
- Wills & Testaments
- Shareholder Agreements
- General Contracts

---

## Troubleshooting

**500 error on XAMPP?**
```bash
php artisan config:clear
php artisan cache:clear
# Check storage/logs/laravel.log
```

**Database connection refused?**
- Ensure MySQL is running in XAMPP Control Panel
- Check `DB_PASSWORD` — default XAMPP has no root password

**OTP email not arriving?**
- Check that `MAIL_MAILER` is not set to `log` or `array`
- Confirm your SMTP host, port, username, and password are correct in `.env`
- Retry signup after updating mail settings and clearing config with `php artisan config:clear`

**File upload fails?**
```bash
# Increase PHP upload limit in php.ini:
upload_max_filesize = 20M
post_max_size = 25M
```

---

## Security Notes for Production

1. Set `APP_DEBUG=false` and `APP_ENV=production`
2. Change `DB_PASSWORD` from blank to a strong password
3. Configure real email (SMTP/Mailgun) for OTP delivery
4. Set `SESSION_DRIVER=database` (already configured)
5. Use HTTPS — update `APP_URL` accordingly
6. Set `FILESYSTEM_DISK=local` and ensure `storage/` is not web-accessible

---

*JustConnect © 2024 — Smarter Legal Decisions Powered by NLP · Zimbabwe*
