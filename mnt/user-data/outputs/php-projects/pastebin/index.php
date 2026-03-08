<?php
require_once 'config.php';

$error = '';
$success_url = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $content  = $_POST['content'] ?? '';
    $title    = trim(substr($_POST['title'] ?? '', 0, 200));
    $language = $_POST['language'] ?? 'plaintext';
    $expiry   = $_POST['expiry'] ?? 'never';

    $allowed_langs = ['plaintext','php','javascript','python','html','css','sql','bash','json','xml','java','c','cpp','rust','go'];
    if (!in_array($language, $allowed_langs)) $language = 'plaintext';

    if (!trim($content)) {
        $error = 'Paste content cannot be empty.';
    } else {
        $expires_at = null;
        if ($expiry === '10min') $expires_at = date('Y-m-d H:i:s', time() + 600);
        elseif ($expiry === '1hr')  $expires_at = date('Y-m-d H:i:s', time() + 3600);
        elseif ($expiry === '1day') $expires_at = date('Y-m-d H:i:s', time() + 86400);
        elseif ($expiry === '1wk')  $expires_at = date('Y-m-d H:i:s', time() + 604800);

        $pdo = getDB();
        do {
            $slug = randomSlug();
            $chk  = $pdo->prepare("SELECT id FROM pastes WHERE slug = ?");
            $chk->execute([$slug]);
        } while ($chk->fetch());

        $stmt = $pdo->prepare("INSERT INTO pastes (slug, title, content, language, expires_at) VALUES (?,?,?,?,?)");
        $stmt->execute([$slug, $title, $content, $language, $expires_at]);
        header('Location: view.php?s=' . $slug);
        exit;
    }
}

$err_msg = match($_GET['err'] ?? '') {
    'notfound' => 'Paste not found.',
    'expired'  => 'This paste has expired and been deleted.',
    default    => ''
};
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>PasteVault — Share Code & Text</title>
<link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@400;600&family=DM+Sans:wght@400;500;700&display=swap" rel="stylesheet">
<style>
  *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
  :root{
    --bg:#0d1117;--surface:#161b22;--card:#1f2937;--border:#30363d;
    --amber:#f0b429;--green:#3fb950;--blue:#58a6ff;
    --text:#e6edf3;--muted:#8b949e;
    --mono:'IBM Plex Mono',monospace;--sans:'DM Sans',sans-serif;
  }
  body{background:var(--bg);color:var(--text);font-family:var(--sans);min-height:100vh;}

  nav{background:var(--surface);border-bottom:1px solid var(--border);padding:0 24px;display:flex;align-items:center;height:52px;}
  .nav-brand{color:var(--amber);font-family:var(--mono);font-weight:600;font-size:15px;}

  .wrap{max-width:860px;margin:0 auto;padding:48px 20px 80px;}

  .hero{text-align:center;margin-bottom:48px;}
  .hero-icon{font-size:48px;margin-bottom:12px;}
  h1{font-size:38px;font-weight:700;margin-bottom:8px;}
  h1 span{color:var(--amber);}
  .sub{color:var(--muted);font-size:15px;}

  .flash{padding:12px 18px;border-radius:8px;font-family:var(--mono);font-size:13px;margin-bottom:24px;}
  .flash.error{background:rgba(255,85,85,.08);border:1px solid rgba(255,85,85,.2);color:#ff8585;}
  .flash.info{background:rgba(88,166,255,.08);border:1px solid rgba(88,166,255,.2);color:var(--blue);}

  .form-card{background:var(--surface);border:1px solid var(--border);border-radius:14px;overflow:hidden;}

  .form-topbar{padding:14px 20px;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:8px;flex-wrap:wrap;}

  .form-group{padding:0;}

  textarea{
    width:100%;background:var(--bg);color:var(--text);
    font-family:var(--mono);font-size:13.5px;line-height:1.7;
    padding:20px 24px;border:none;outline:none;resize:vertical;
    min-height:360px;tab-size:2;
  }
  textarea::placeholder{color:var(--muted);}

  .form-footer{padding:16px 20px;border-top:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;background:var(--surface);}

  .footer-left{display:flex;gap:10px;flex-wrap:wrap;align-items:center;}

  input[type="text"]{
    background:var(--card);border:1px solid var(--border);color:var(--text);
    font-family:var(--sans);font-size:13px;padding:8px 14px;border-radius:7px;
    outline:none;width:200px;transition:border-color .15s;
  }
  input[type="text"]:focus{border-color:var(--amber);}
  input[type="text"]::placeholder{color:var(--muted);}

  select{
    background:var(--card);border:1px solid var(--border);color:var(--text);
    font-family:var(--mono);font-size:12px;padding:8px 12px;border-radius:7px;
    outline:none;cursor:pointer;
  }

  .submit-btn{
    background:var(--amber);color:#0d1117;font-family:var(--sans);
    font-weight:700;font-size:14px;padding:10px 28px;border:none;
    border-radius:8px;cursor:pointer;transition:opacity .15s;
    white-space:nowrap;
  }
  .submit-btn:hover{opacity:.85;}
</style>
</head>
<body>
<nav><span class="nav-brand">📋 PasteVault</span></nav>
<div class="wrap">

  <div class="hero">
    <div class="hero-icon">📋</div>
    <h1>Paste. Share. <span>Done.</span></h1>
    <p class="sub">Share code snippets, notes, and text with a single link.</p>
  </div>

  <?php if ($error): ?>
    <div class="flash error">⚠ <?= htmlspecialchars($error) ?></div>
  <?php endif; ?>
  <?php if ($err_msg): ?>
    <div class="flash info">ℹ <?= htmlspecialchars($err_msg) ?></div>
  <?php endif; ?>

  <form method="POST" action="">
    <div class="form-card">
      <div class="form-topbar">
        <input type="text" name="title" placeholder="Title (optional)" maxlength="200">
      </div>
      <div class="form-group">
        <textarea name="content" placeholder="Paste your code or text here..."><?= htmlspecialchars($_POST['content'] ?? '') ?></textarea>
      </div>
      <div class="form-footer">
        <div class="footer-left">
          <select name="language">
            <?php
            $langs = ['plaintext','php','javascript','python','html','css','sql','bash','json','xml','java','c','cpp','rust','go'];
            $sel = $_POST['language'] ?? 'plaintext';
            foreach ($langs as $l) echo "<option value=\"$l\"" . ($sel===$l?' selected':'') . ">$l</option>";
            ?>
          </select>
          <select name="expiry">
            <option value="never">Never expire</option>
            <option value="10min">10 minutes</option>
            <option value="1hr">1 hour</option>
            <option value="1day">1 day</option>
            <option value="1wk">1 week</option>
          </select>
        </div>
        <button type="submit" class="submit-btn">Create Paste →</button>
      </div>
    </div>
  </form>

</div>
</body>
</html>
