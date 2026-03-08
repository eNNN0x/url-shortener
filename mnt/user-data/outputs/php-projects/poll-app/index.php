<?php
require_once 'config.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $question = trim(substr($_POST['question'] ?? '', 0, 500));
    $rawOpts  = $_POST['options'] ?? [];
    $options  = array_values(array_filter(array_map('trim', $rawOpts)));

    if (!$question) {
        $error = 'Please enter a poll question.';
    } elseif (count($options) < 2) {
        $error = 'Please enter at least 2 options.';
    } elseif (count($options) > 8) {
        $error = 'Maximum 8 options allowed.';
    } else {
        $pdo = getDB();
        do {
            $slug = randomSlug();
            $chk  = $pdo->prepare("SELECT id FROM polls WHERE slug = ?");
            $chk->execute([$slug]);
        } while ($chk->fetch());

        $pdo->prepare("INSERT INTO polls (slug, question) VALUES (?,?)")->execute([$slug, $question]);
        $pollId = $pdo->lastInsertId();

        $ins = $pdo->prepare("INSERT INTO options (poll_id, text) VALUES (?,?)");
        foreach ($options as $opt) $ins->execute([$pollId, substr($opt, 0, 300)]);

        header('Location: vote.php?s=' . $slug);
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>QuickPoll — Create a Poll</title>
<link href="https://fonts.googleapis.com/css2?family=Clash+Display:wght@400;600;700&family=Plus+Jakarta+Sans:wght@400;500;600&display=swap" rel="stylesheet">
<style>
  *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
  :root{
    --bg:#faf9f7;--card:#ffffff;--border:#e5e2dc;
    --ink:#1a1814;--soft:#6b6760;
    --purple:#6c47ff;--purple-light:#ede9ff;
    --sans:'Plus Jakarta Sans',sans-serif;
    --display:'Clash Display',sans-serif;
  }
  body{background:var(--bg);color:var(--ink);font-family:var(--sans);min-height:100vh;padding:40px 20px;}
  .page{max-width:580px;margin:0 auto;}

  .hero{text-align:center;margin-bottom:40px;}
  .logo{font-family:var(--display);font-size:48px;font-weight:700;color:var(--purple);line-height:1;}
  .tagline{font-size:15px;color:var(--soft);margin-top:8px;}

  .card{background:var(--card);border:1px solid var(--border);border-radius:20px;padding:36px;box-shadow:0 2px 20px rgba(0,0,0,.06);}

  .error{padding:12px 16px;background:#fff1f2;border:1px solid #fecdd3;border-radius:10px;color:#e11d48;font-size:13px;margin-bottom:24px;}

  .field{margin-bottom:24px;}
  label{display:block;font-weight:600;font-size:13px;margin-bottom:8px;color:var(--ink);}
  label span{color:var(--soft);font-weight:400;}

  textarea,input[type="text"]{
    width:100%;background:var(--bg);border:1.5px solid var(--border);
    color:var(--ink);font-family:var(--sans);font-size:14px;
    padding:12px 16px;border-radius:10px;outline:none;
    transition:border-color .15s;
  }
  textarea{resize:vertical;min-height:80px;line-height:1.5;}
  textarea:focus,input[type="text"]:focus{border-color:var(--purple);}
  textarea::placeholder,input[type="text"]::placeholder{color:#b5b0aa;}

  .options-list{display:flex;flex-direction:column;gap:8px;}
  .option-row{display:flex;align-items:center;gap:8px;}
  .option-row input{flex:1;}
  .remove-btn{background:none;border:none;cursor:pointer;color:#ccc;font-size:20px;padding:2px 6px;line-height:1;transition:color .15s;}
  .remove-btn:hover{color:#e11d48;}

  .add-btn{
    margin-top:10px;background:transparent;border:1.5px dashed var(--border);
    color:var(--soft);font-family:var(--sans);font-size:13px;font-weight:600;
    padding:10px;width:100%;border-radius:10px;cursor:pointer;
    transition:border-color .15s,color .15s;
  }
  .add-btn:hover{border-color:var(--purple);color:var(--purple);}

  .submit-btn{
    width:100%;background:var(--purple);color:#fff;font-family:var(--display);
    font-weight:700;font-size:16px;padding:16px;border:none;
    border-radius:12px;cursor:pointer;transition:opacity .15s;margin-top:8px;
    letter-spacing:.02em;
  }
  .submit-btn:hover{opacity:.88;}
</style>
</head>
<body>
<div class="page">
  <div class="hero">
    <div class="logo">⚡ QuickPoll</div>
    <p class="tagline">Create a poll. Share the link. See results instantly.</p>
  </div>

  <div class="card">
    <?php if ($error): ?>
      <div class="error">⚠ <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" action="" id="poll-form">
      <div class="field">
        <label>Your Question</label>
        <textarea name="question" placeholder="e.g. What's your favourite programming language?" maxlength="500"><?= htmlspecialchars($_POST['question'] ?? '') ?></textarea>
      </div>

      <div class="field">
        <label>Options <span>(2–8)</span></label>
        <div class="options-list" id="opts-list">
          <div class="option-row">
            <input type="text" name="options[]" placeholder="Option 1" maxlength="300" required>
            <button type="button" class="remove-btn" onclick="removeOpt(this)" title="Remove">×</button>
          </div>
          <div class="option-row">
            <input type="text" name="options[]" placeholder="Option 2" maxlength="300" required>
            <button type="button" class="remove-btn" onclick="removeOpt(this)" title="Remove">×</button>
          </div>
          <div class="option-row">
            <input type="text" name="options[]" placeholder="Option 3 (optional)" maxlength="300">
            <button type="button" class="remove-btn" onclick="removeOpt(this)" title="Remove">×</button>
          </div>
        </div>
        <button type="button" class="add-btn" onclick="addOpt()" id="add-btn">+ Add option</button>
      </div>

      <button type="submit" class="submit-btn">Create Poll & Get Link →</button>
    </form>
  </div>
</div>

<script>
function addOpt() {
  const list = document.getElementById('opts-list');
  const count = list.children.length;
  if (count >= 8) { document.getElementById('add-btn').style.display = 'none'; return; }
  const div = document.createElement('div');
  div.className = 'option-row';
  div.innerHTML = `<input type="text" name="options[]" placeholder="Option ${count + 1}" maxlength="300">
    <button type="button" class="remove-btn" onclick="removeOpt(this)" title="Remove">×</button>`;
  list.appendChild(div);
  div.querySelector('input').focus();
  if (list.children.length >= 8) document.getElementById('add-btn').style.display = 'none';
}

function removeOpt(btn) {
  const list = document.getElementById('opts-list');
  if (list.children.length <= 2) return;
  btn.closest('.option-row').remove();
  document.getElementById('add-btn').style.display = '';
  // Re-label placeholders
  Array.from(list.querySelectorAll('input')).forEach((inp, i) => {
    const req = i < 2;
    inp.placeholder = `Option ${i + 1}${req ? '' : ' (optional)'}`;
    inp.required = req;
  });
}
</script>
</body>
</html>
