# PHP Web Projects

Three self-contained PHP/MySQL mini-apps: a URL shortener that turns long links into short ones and tracks clicks, a Pastebin clone for sharing code snippets with syntax highlighting and optional expiry, and a poll creator where you write a question, share the link, and watch votes roll in live.

---

## Projects

### URL Shortener
Paste a long URL and get a short shareable link stored in MySQL. Tracks click counts per link and shows a recent links table on the homepage.

**Files:** `index.php`, `config.php`, `redirect.php`

### 📋 Pastebin Clone
Share code or text with a unique link. Supports syntax highlighting for 15 languages, optional expiry times (10 min / 1 hr / 1 day / 1 week), and view counts.

**Files:** `index.php`, `config.php`, `view.php`

### 🗳️ Poll App
Create a question with up to 8 options and share the link. Voters see a live animated bar chart after voting. One vote per device enforced via IP + user agent hashing.

**Files:** `index.php`, `config.php`, `vote.php`

---

## Requirements

- PHP 8.0+
- MySQL 5.7+ or MariaDB 10+
- Apache or Nginx (or PHP's built-in server for local dev)

---

## Setup

1. Clone or download this repo into your web server's document root
```bash
git clone https://github.com/your-username/php-web-projects.git
```

2. Open `config.php` in each project folder and set your database credentials:
```php
define('DB_HOST', 'localhost');
define('DB_USER', 'your_username');
define('DB_PASS', 'your_password');
define('BASE_URL', 'http://localhost/url-shortener'); // adjust per project
```

3. Visit each project in your browser — databases and tables are created automatically on first load.

---

## Project URLs

| Project | URL |
|---|---|
| URL Shortener | `http://localhost/url-shortener/` |
| Pastebin | `http://localhost/pastebin/` |
| Poll App | `http://localhost/poll-app/` |

---

## Project Structure

```
php-web-projects/
├── url-shortener/
│   ├── index.php       # Main page + shorten form
│   ├── config.php      # DB config + auto table creation
│   └── redirect.php    # Handles short link redirects
├── pastebin/
│   ├── index.php       # Create paste form
│   ├── config.php      # DB config + auto table creation
│   └── view.php        # View paste with syntax highlighting
└── poll-app/
    ├── index.php       # Create poll form
    ├── config.php      # DB config + auto table creation
    └── vote.php        # Vote + view live results
```

---

## License

MIT — free to use, modify, and deploy.
