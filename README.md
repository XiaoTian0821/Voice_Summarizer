# AI Voice Summarizer

A beginner-friendly PHP 8.2 web application that lets users upload or record audio,
transcribe it to text using a speech-to-text API, and generate AI summaries.

## Tech Stack

| Layer | Technology |
|---|---|
| Backend | PHP 8.2+, PDO |
| Database | MySQL 8.0 / MariaDB 10.4+ |
| Frontend | Bootstrap 5, Font Awesome 6, vanilla JS |
| UX | SweetAlert2, Bootstrap toasts |
| AI | OpenAI / Groq (configurable) |

## Project Structure

```
Voice_Summarizer/
├── index.php                  → redirects to login or dashboard
├── register.php               → user registration
├── login.php                  → user login
├── logout.php                 → session destroy
├── dashboard.php              → stats, quick actions, recent summaries
├── upload.php                 → drag-and-drop audio file upload
├── record.php                 → browser MediaRecorder voice capture
├── process_audio.php          → API: save file + call STT
├── summarize.php              → select type, generate AI summary
├── history.php                → paginated, searchable summary list
├── view_summary.php           → full record view, edit title, copy, download
├── delete_summary.php         → API: soft-delete + remove audio file
├── download.php              → serve transcription/summary as .txt
│
├── config/
│   ├── database.php           → PDO connection (edit credentials here)
│   └── ai_config.php         → AI provider + API key (edit key here)
│
├── includes/
│   ├── auth.php              → session guard + helpers
│   ├── csrf.php              → CSRF token generation & verification
│   ├── functions.php         → shared PHP helper functions
│   ├── header.php            → shared HTML head + navbar
│   └── footer.php            → shared HTML closing tags
│
├── api/
│   ├── transcribe.php        → AJAX: re-transcribe a stored file
│   └── generate_summary.php  → AJAX: call LLM, save summary
│
├── assets/
│   ├── css/style.css         → custom app styles
│   └── js/app.js             → shared JS utilities
│
├── storage/
│   ├── audio/                → uploaded files (per-user subdirectories)
│   └── logs/                 → AI API error logs
│
└── database/
    └── ai_voice_summarizer.sql
```

## Installation (XAMPP)

### 1. Start Apache and MySQL

Open the XAMPP Control Panel and start both services.

### 2. Place the project

Copy or clone this folder into `C:\xampp\htdocs\`:

```
C:\xampp\htdocs\Voice_Summarizer\
```

### 3. Create the database

1. Open `http://localhost/phpmyadmin`
2. Click **New** → name it **ai_voice_summarizer** → character set `utf8mb4` → Go
3. Click the **Import** tab → choose `database/ai_voice_summarizer.sql` → **Go**
   (Or use the SQL tab and paste the file contents.)

### 4. Configure the database connection

Open `config/database.php` and verify the credentials match your setup:

```php
define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'ai_voice_summarizer');
define('DB_USER', 'root');
define('DB_PASS', '');          // XAMPP default is empty
```

### 5. Configure the AI API key

Open `config/ai_config.php` and set your API key:

```php
define('AI_API_KEY', 'sk-your-real-key-here');
```

**OpenAI** — sign up at https://platform.openai.com → API Keys → Create a new key.
**Groq** — sign up at https://console.groq.com/keys.

> ⚠️ These calls require an **internet connection**. Without a valid key
> the transcription and summarisation steps will fail with a clear error message.

### 6. PHP upload limits

The default `upload_max_filesize` is 2 MB. For large audio files edit
`C:\xampp\php\php.ini`:

```ini
upload_max_filesize = 30M
post_max_size       = 30M
max_execution_time  = 300
```

Restart Apache from XAMPP after saving.

### 7. Enable required PHP extensions

Open `C:\xampp\php\php.ini` and make sure these lines are **not** commented out:

```ini
extension=curl
extension=pdo_mysql
```

Restart Apache.

### 8. Create the storage directories

```bash
mkdir C:\xampp\htdocs\Voice_Summarizer\storage\audio
mkdir C:\xampp\htdocs\Voice_Summarizer\storage\logs
```

The app will also create per-user subdirectories automatically.

### 9. Open in a browser

Navigate to: **http://localhost/Voice_Summarizer/**

You will be redirected to the login page. Register a new account to get started.

## Testing Checklist

| # | Test | Expected Result |
|---|------|-----------------|
| 1 | Open `http://localhost/Voice_Summarizer/` | Redirects to `login.php` |
| 2 | Register a new account with a valid email | Redirects to `dashboard.php`, logged in |
| 3 | Try registering the same email again | Error: "An account with this email already exists." |
| 4 | Log in with correct credentials | Dashboard loads with stat cards |
| 5 | Log in with wrong password | Error message shown |
| 6 | Navigate directly to `dashboard.php` without login | Redirects to `login.php` |
| 7 | Upload a valid MP3/WAV/WebM file | File uploads, progress bar animates, transcription completes |
| 8 | Upload a .txt or .exe file | Error: unsupported format |
| 9 | Upload a file larger than the limit | Error: file too large |
| 10 | Record a voice clip in the browser | Timer runs, audio preview plays, submit transcribes |
| 11 | Deny microphone permission | Helpful error, link to upload page appears |
| 12 | Generate a "Short Summary" | Summary appears in right panel, "New Summary" card visible |
| 13 | Generate "Meeting Notes" with a custom instruction | Summary reflects the instruction |
| 14 | Copy transcription / copy summary buttons | Text appears in clipboard |
| 15 | Download summary as TXT | Browser downloads a .txt file |
| 16 | Download transcription as TXT | Browser downloads a .txt file |
| 17 | Edit summary title on view page | Title updates after page reload |
| 18 | Delete a summary from history | Confirmation dialog, record disappears, audio file removed from storage |
| 19 | Search history by title keyword | Filtered results shown |
| 20 | Filter by summary type | Only matching types listed |
| 21 | Paginate through 20+ records | Page 2 accessible, filter params preserved |
| 22 | Try to access another user's summary via direct URL | Redirects to login or 404 |
| 23 | Log out then open a private page | Redirects to login page |
| 24 | Set AI key to placeholder, then transcribe | Clear "API key not configured" error |
| 25 | Internet connection off, attempt transcription | Network error shown, no crash |

## Deployment to PHP Hosting

1. **Database** — create a MySQL database and user on your host.
2. **Upload** — copy the project files into your `public_html` directory.
3. **Configuration** — edit `config/database.php` with your host's DB credentials.
4. **AI key** — set your real API key in `config/ai_config.php`.
5. **Storage** — make sure `storage/` is writable by the web server user
   (`chmod 755 storage` / `chown -R www-data:www-data storage` on Linux).
6. **PHP version** — the host must run PHP 8.2 or newer with `pdo_mysql` and `curl`.
7. **HTTPS** — configure SSL on your host; the app uses session cookies that
   should be marked `secure` in production.

## Troubleshooting

| Symptom | Fix |
|---------|-----|
| Blank page | Check `C:\xampp\logs\php_error_log` or enable `display_errors` in `php.ini` |
| "Database connection failed" | Verify `config/database.php` credentials and that MySQL is running |
| "API key not configured" | Put your real key in `config/ai_config.php` |
| "cURL error: 6" | Host cannot resolve the API domain – check DNS / firewall |
| "cURL error: 60" | SSL certificate issue – add `curl -k` or fix CA bundle on the host |
| Audio not transcribed | Check `storage/logs/ai.log` for the API error code |
| Page looks broken | Clear browser cache; verify Bootstrap/FA/SweetAlert CDNs are loading |
| Permission denied on upload | `chmod 775 storage/audio` (Linux) or run XAMPP as admin (Windows) |

## Security Notes

- Passwords are hashed with `password_hash()` (bcrypt, cost 12).
- All SQL uses PDO prepared statements.
- CSRF tokens are required on every state-changing form and AJAX call.
- Uploaded audio files are stored outside the public web root when deployed
  to production; on XAMPP the `storage/` directory lives inside the project
  but can be protected with an `.htaccess` file if needed:

```apache
# storage/.htaccess
Deny from all
```

- API keys are never sent to the browser.

---

*Built for PHP 8.2 · MySQL · Bootstrap 5 · No frameworks*
