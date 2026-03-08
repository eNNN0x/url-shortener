<?php
require_once 'config.php';

// ─── Helpers ──────────────────────────────────────────────────────────────
function generateCode(int $len = SHORT_CODE_LENGTH): string {
    return substr(str_shuffle('abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, $len);
}

function isValidUrl(string $url): bool {
    return filter_var($url, FILTER_VALIDATE_URL) !== false;
}

// ─── Handle POST (shorten) ────────────────────────────────────────────────
$short  = '';
$error  = '';
$clicks = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $longUrl = trim($_POST['url'] ?? '');
    if (!$longUrl) {
        $error = 'Please enter a URL.';
    } elseif (!isValidUrl($longUrl)) {
        $error = 'That doesn\'t look like a valid URL. Include http:// or https://';
    } else {
        $pdo = getDB();

        // Check if already shortened
        $stmt = $pdo->prepare("SELECT code FROM urls WHERE long_url = ? LIMIT 1");
        $stmt->execute([$longUrl]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            $short = BASE_URL . '/redirect.php?c=' . $existing['code'];
        } else {
            // Generate unique code
            do {
                $code = generateCode();
                $check = $pdo->prepare("SELECT id FROM urls WHERE code = ?");
                $check->execute([$code]);
            } while ($check->fetch());

            $pdo->prepare("INSERT INTO urls (code, long_url) VALUES (?, ?)")->execute([$code, $longUrl]);
            $short = BASE_URL . '/redirect.php?c=' . $code;
        }
    }
}

// ─── Recent links ─────────────────────────────────────────────────────────
$pdo = getDB();
$recent = $pdo->query("SELECT code, long_url, clicks, created_at FROM urls ORDER BY created_at DESC LIMIT 8")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>⚡ Snip — URL Shortener</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Space+Mono:wght@400;700&family=Syne:wght@400;700;800&display=swap" rel="stylesheet">
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

  :root {
    --bg: #0a0a0f;
    --surface: #13131a;
    --card: #1a1a24;
    --border: #2a2a3a;
    --accent: #7fff6e;
    --accent2: #ff6eaa;
    --text: #e8e8f0;
    --muted: #6b6b80;
    --mono: 'Space Mono', monospace;
    --sans: 'Syne', sans-serif;
  }

  body {
    background: var(--bg);
    color: var(--text);
    font-family: var(--sans);
    min-height: 100vh;
    padding: 0;
    overflow-x: hidden;
  }

  /* Grid bg */
  body::before {
    content: '';
    position: fixed;
    inset: 0;
    background-image:
      linear-gradient(rgba(127,255,110,0.03) 1px, transparent 1px),
      linear-gradient(90deg, rgba(127,255,110,0.03) 1px, transparent 1px);
    background-size: 40px 40px;
    pointer-events: none;
    z-index: 0;
  }

  .wrap {
    position: relative;
    z-index: 1;
    max-width: 720px;
    margin: 0 auto;
    padding: 60px 20px 80px;
  }

  header {
    text-align: center;
    margin-bottom: 56px;
  }

  .logo {
    font-size: 13px;
    letter-spacing: 0.3em;
    text-transform: uppercase;
    color: var(--accent);
    font-family: var(--mono);
    margin-bottom: 16px;
  }

  h1 {
    font-size: clamp(42px, 8vw, 72px);
    font-weight: 800;
    line-height: 1;
    background: linear-gradient(135deg, var(--text) 30%, var(--accent) 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    margin-bottom: 12px;
  }

  .tagline {
    color: var(--muted);
    font-size: 15px;
    font-family: var(--mono);
  }

  /* Input card */
  .card {
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: 16px;
    padding: 32px;
    margin-bottom: 32px;
  }

  .input-row {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
  }

  input[type="url"] {
    flex: 1;
    min-width: 0;
    background: var(--surface);
    border: 1px solid var(--border);
    color: var(--text);
    font-family: var(--mono);
    font-size: 14px;
    padding: 14px 18px;
    border-radius: 10px;
    outline: none;
    transition: border-color .2s;
  }

  input[type="url"]:focus {
    border-color: var(--accent);
  }

  input[type="url"]::placeholder { color: var(--muted); }

  button {
    background: var(--accent);
    color: #0a0a0f;
    font-family: var(--sans);
    font-weight: 700;
    font-size: 14px;
    letter-spacing: 0.05em;
    padding: 14px 28px;
    border: none;
    border-radius: 10px;
    cursor: pointer;
    transition: transform .15s, opacity .15s;
    white-space: nowrap;
  }

  button:hover { opacity: .85; transform: translateY(-1px); }
  button:active { transform: translateY(0); }

  .error {
    margin-top: 16px;
    color: var(--accent2);
    font-family: var(--mono);
    font-size: 13px;
    padding: 12px 16px;
    background: rgba(255,110,170,.08);
    border: 1px solid rgba(255,110,170,.2);
    border-radius: 8px;
  }

  /* Result */
  .result {
    margin-top: 20px;
    background: rgba(127,255,110,.07);
    border: 1px solid rgba(127,255,110,.25);
    border-radius: 10px;
    padding: 16px 20px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    flex-wrap: wrap;
  }

  .result a {
    color: var(--accent);
    font-family: var(--mono);
    font-size: 15px;
    font-weight: 700;
    text-decoration: none;
  }

  .copy-btn {
    background: transparent;
    border: 1px solid var(--accent);
    color: var(--accent);
    font-family: var(--mono);
    font-size: 12px;
    padding: 6px 14px;
    border-radius: 6px;
    cursor: pointer;
    transition: background .15s;
  }

  .copy-btn:hover { background: rgba(127,255,110,.1); }

  /* Table */
  .section-title {
    font-family: var(--mono);
    font-size: 11px;
    letter-spacing: 0.2em;
    text-transform: uppercase;
    color: var(--muted);
    margin-bottom: 16px;
  }

  table {
    width: 100%;
    border-collapse: collapse;
  }

  th {
    text-align: left;
    font-family: var(--mono);
    font-size: 11px;
    letter-spacing: 0.1em;
    text-transform: uppercase;
    color: var(--muted);
    padding: 0 12px 12px;
  }

  td {
    padding: 12px;
    font-size: 13px;
    border-top: 1px solid var(--border);
    font-family: var(--mono);
    vertical-align: middle;
  }

  .td-url {
    max-width: 280px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    color: var(--muted);
  }

  .td-short a {
    color: var(--accent);
    text-decoration: none;
  }

  .clicks-badge {
    display: inline-block;
    background: rgba(127,255,110,.1);
    color: var(--accent);
    border-radius: 4px;
    padding: 2px 8px;
    font-size: 11px;
  }

  tr:hover td { background: rgba(255,255,255,.02); }
</style>
</head>
<body>
<div class="wrap">

  <header>
    <div class="logo">⚡ URL Tools</div>
    <h1>SNIP</h1>
    <p class="tagline">// paste long. get short. done.</p>
  </header>

  <div class="card">
    <form method="POST" action="">
      <div class="input-row">
        <input type="url" name="url" placeholder="https://your-very-long-url.com/goes/here"
               value="<?= htmlspecialchars($_POST['url'] ?? '') ?>" autofocus required>
        <button type="submit">Snip it →</button>
      </div>

      <?php if ($error): ?>
        <div class="error">⚠ <?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <?php if ($short): ?>
        <div class="result">
          <a href="<?= htmlspecialchars($short) ?>" target="_blank"><?= htmlspecialchars($short) ?></a>
          <button type="button" class="copy-btn" onclick="copyShort(this, '<?= htmlspecialchars($short) ?>')">Copy</button>
        </div>
      <?php endif; ?>
    </form>
  </div>

  <?php if ($recent): ?>
  <div class="card">
    <div class="section-title">Recent Links</div>
    <table>
      <thead>
        <tr>
          <th>Short URL</th>
          <th>Original</th>
          <th>Clicks</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($recent as $row): ?>
        <tr>
          <td class="td-short">
            <a href="<?= BASE_URL ?>/redirect.php?c=<?= htmlspecialchars($row['code']) ?>" target="_blank">
              /<?= htmlspecialchars($row['code']) ?>
            </a>
          </td>
          <td class="td-url" title="<?= htmlspecialchars($row['long_url']) ?>">
            <?= htmlspecialchars($row['long_url']) ?>
          </td>
          <td><span class="clicks-badge"><?= (int)$row['clicks'] ?></span></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>

</div>

<script>
function copyShort(btn, url) {
  navigator.clipboard.writeText(url).then(() => {
    btn.textContent = 'Copied!';
    setTimeout(() => btn.textContent = 'Copy', 2000);
  });
}
</script>
</body>
</html>
