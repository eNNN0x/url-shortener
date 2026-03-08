<?php
require_once 'config.php';

$slug = preg_replace('/[^a-z0-9]/', '', strtolower($_GET['s'] ?? ''));
if (!$slug) { header('Location: index.php'); exit; }

$pdo  = getDB();
$stmt = $pdo->prepare("SELECT * FROM pastes WHERE slug = ? LIMIT 1");
$stmt->execute([$slug]);
$paste = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$paste) { header('Location: index.php?err=notfound'); exit; }

// Check expiry
if ($paste['expires_at'] && strtotime($paste['expires_at']) < time()) {
    $pdo->prepare("DELETE FROM pastes WHERE slug = ?")->execute([$slug]);
    header('Location: index.php?err=expired'); exit;
}

// Increment views
$pdo->prepare("UPDATE pastes SET views = views + 1 WHERE slug = ?")->execute([$slug]);

$langs = ['plaintext','php','javascript','python','html','css','sql','bash','json','xml','java','c','cpp','rust','go'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= htmlspecialchars($paste['title'] ?: 'Untitled Paste') ?> — PasteVault</title>
<link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@400;600&family=DM+Sans:wght@400;500;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/styles/atom-one-dark.min.css">
<style>
  *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
  :root{
    --bg:#0d1117;--surface:#161b22;--card:#1f2937;--border:#30363d;
    --amber:#f0b429;--green:#3fb950;--text:#e6edf3;--muted:#8b949e;
    --mono:'IBM Plex Mono',monospace;--sans:'DM Sans',sans-serif;
  }
  body{background:var(--bg);color:var(--text);font-family:var(--sans);min-height:100vh;}

  nav{background:var(--surface);border-bottom:1px solid var(--border);padding:0 24px;display:flex;align-items:center;justify-content:space-between;height:52px;}
  .nav-brand{color:var(--amber);font-family:var(--mono);font-weight:600;font-size:15px;text-decoration:none;}
  .nav-link{color:var(--muted);font-size:13px;text-decoration:none;padding:6px 14px;border:1px solid var(--border);border-radius:6px;transition:color .15s,border-color .15s;}
  .nav-link:hover{color:var(--text);border-color:var(--muted);}

  .wrap{max-width:960px;margin:0 auto;padding:32px 20px;}

  .paste-header{display:flex;align-items:flex-start;justify-content:space-between;gap:16px;flex-wrap:wrap;margin-bottom:24px;}
  h1{font-size:22px;font-weight:700;line-height:1.2;}
  .meta{display:flex;gap:12px;flex-wrap:wrap;margin-top:6px;}
  .badge{font-family:var(--mono);font-size:11px;padding:3px 10px;border-radius:4px;background:rgba(240,180,41,.1);color:var(--amber);border:1px solid rgba(240,180,41,.2);}
  .badge.green{background:rgba(63,185,80,.1);color:var(--green);border-color:rgba(63,185,80,.2);}

  .toolbar{display:flex;gap:8px;}
  .btn{font-family:var(--mono);font-size:12px;padding:7px 14px;border-radius:6px;border:1px solid var(--border);background:transparent;color:var(--muted);cursor:pointer;transition:all .15s;}
  .btn:hover{color:var(--text);border-color:var(--muted);}
  .btn.primary{background:var(--amber);color:#0d1117;border-color:var(--amber);font-weight:600;}
  .btn.primary:hover{opacity:.85;}

  .code-wrap{border:1px solid var(--border);border-radius:10px;overflow:hidden;}
  .code-topbar{background:var(--surface);padding:10px 16px;display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid var(--border);}
  .lang-tag{font-family:var(--mono);font-size:11px;color:var(--muted);text-transform:uppercase;letter-spacing:.08em;}
  pre{margin:0;}
  pre code{font-family:var(--mono)!important;font-size:13.5px!important;line-height:1.65!important;padding:20px 24px!important;background:var(--card)!important;display:block;overflow-x:auto;}
  .hljs{background:var(--card)!important;}
  .expiry-warn{margin-top:16px;padding:10px 16px;background:rgba(255,85,85,.08);border:1px solid rgba(255,85,85,.2);border-radius:8px;font-family:var(--mono);font-size:12px;color:#ff8585;}
</style>
</head>
<body>
<nav>
  <a class="nav-brand" href="index.php">📋 PasteVault</a>
  <a class="nav-link" href="index.php">+ New Paste</a>
</nav>
<div class="wrap">
  <div class="paste-header">
    <div>
      <h1><?= htmlspecialchars($paste['title'] ?: 'Untitled Paste') ?></h1>
      <div class="meta">
        <span class="badge"><?= htmlspecialchars($paste['language']) ?></span>
        <span class="badge green"><?= (int)$paste['views'] ?> views</span>
        <span class="badge">Created <?= date('M j, Y', strtotime($paste['created_at'])) ?></span>
        <?php if ($paste['expires_at']): ?>
          <span class="badge">Expires <?= date('M j, Y H:i', strtotime($paste['expires_at'])) ?></span>
        <?php endif; ?>
      </div>
    </div>
    <div class="toolbar">
      <button class="btn" onclick="copyRaw()">Copy</button>
      <a class="btn primary" href="index.php">New</a>
    </div>
  </div>

  <?php
  $remaining = $paste['expires_at'] ? strtotime($paste['expires_at']) - time() : null;
  if ($remaining && $remaining < 3600):
  ?>
  <div class="expiry-warn">⚠ This paste expires in less than 1 hour.</div>
  <?php endif; ?>

  <div class="code-wrap">
    <div class="code-topbar">
      <span class="lang-tag"><?= htmlspecialchars($paste['language']) ?></span>
      <span style="font-family:var(--mono);font-size:11px;color:var(--muted);"><?= number_format(strlen($paste['content'])) ?> chars</span>
    </div>
    <pre><code class="language-<?= htmlspecialchars($paste['language']) ?>" id="code-block"><?= htmlspecialchars($paste['content']) ?></code></pre>
  </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/highlight.min.js"></script>
<script>hljs.highlightAll();</script>
<script>
function copyRaw() {
  const text = document.getElementById('code-block').textContent;
  navigator.clipboard.writeText(text).then(() => {
    const btn = event.target;
    btn.textContent = 'Copied!';
    setTimeout(() => btn.textContent = 'Copy', 2000);
  });
}
</script>
</body>
</html>
