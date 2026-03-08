<?php
require_once 'config.php';

$slug = preg_replace('/[^a-z0-9]/', '', strtolower($_GET['s'] ?? ''));
if (!$slug) { header('Location: index.php'); exit; }

$pdo  = getDB();
$stmt = $pdo->prepare("SELECT * FROM polls WHERE slug = ? LIMIT 1");
$stmt->execute([$slug]);
$poll = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$poll) { header('Location: index.php?err=notfound'); exit; }

$opts = $pdo->prepare("SELECT * FROM options WHERE poll_id = ? ORDER BY id");
$opts->execute([$poll['id']]);
$options = $opts->fetchAll(PDO::FETCH_ASSOC);

$voterHash   = voterHash($poll['id']);
$hasVoted    = false;
$voteMsg     = '';
$votedOption = null;

// Check if already voted
$cv = $pdo->prepare("SELECT option_id FROM votes WHERE poll_id = ? AND voter_hash = ?");
$cv->execute([$poll['id'], $voterHash]);
$existing = $cv->fetch(PDO::FETCH_ASSOC);
if ($existing) {
    $hasVoted    = true;
    $votedOption = $existing['option_id'];
}

// Handle vote submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$hasVoted) {
    $optionId = (int)($_POST['option'] ?? 0);
    // Validate option belongs to poll
    $valid = false;
    foreach ($options as $o) { if ($o['id'] === $optionId) { $valid = true; break; } }

    if ($valid) {
        try {
            $pdo->prepare("INSERT INTO votes (poll_id, option_id, voter_hash) VALUES (?,?,?)")
                ->execute([$poll['id'], $optionId, $voterHash]);
            $pdo->prepare("UPDATE options SET votes = votes + 1 WHERE id = ?")
                ->execute([$optionId]);
            $hasVoted    = true;
            $votedOption = $optionId;
            // Refresh options
            $opts->execute([$poll['id']]);
            $options = $opts->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $voteMsg = 'Already voted.';
            $hasVoted = true;
        }
    }
}

$totalVotes = array_sum(array_column($options, 'votes'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= htmlspecialchars($poll['question']) ?> — QuickPoll</title>
<link href="https://fonts.googleapis.com/css2?family=Clash+Display:wght@400;600;700&family=Plus+Jakarta+Sans:wght@400;500;600&display=swap" rel="stylesheet">
<style>
  *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
  :root{
    --bg:#faf9f7;--card:#ffffff;--border:#e5e2dc;
    --ink:#1a1814;--soft:#6b6760;
    --purple:#6c47ff;--purple-light:#ede9ff;
    --green:#16a34a;--green-light:#dcfce7;
    --sans:'Plus Jakarta Sans',sans-serif;
    --display:'Clash Display',sans-serif;
  }
  body{background:var(--bg);color:var(--ink);font-family:var(--sans);min-height:100vh;padding:40px 20px;}

  .page{max-width:580px;margin:0 auto;}

  .brand{text-align:center;margin-bottom:40px;}
  .brand-name{font-family:var(--display);font-size:18px;font-weight:700;color:var(--purple);}

  .poll-card{background:var(--card);border:1px solid var(--border);border-radius:20px;padding:36px;box-shadow:0 2px 20px rgba(0,0,0,.06);}

  .poll-meta{font-size:11px;letter-spacing:.1em;text-transform:uppercase;color:var(--soft);font-weight:600;margin-bottom:14px;}
  .question{font-family:var(--display);font-size:26px;font-weight:700;line-height:1.25;margin-bottom:32px;color:var(--ink);}

  /* Vote form */
  .options{display:flex;flex-direction:column;gap:10px;margin-bottom:24px;}
  .option-label{
    display:flex;align-items:center;gap:14px;
    padding:14px 18px;border:2px solid var(--border);border-radius:12px;
    cursor:pointer;transition:border-color .15s,background .15s;
    font-size:15px;font-weight:500;
  }
  .option-label:hover{border-color:var(--purple);background:var(--purple-light);}
  input[type="radio"]{accent-color:var(--purple);width:18px;height:18px;flex-shrink:0;}
  input[type="radio"]:checked + .option-label, .option-label:has(input[type="radio"]:checked){
    border-color:var(--purple);background:var(--purple-light);
  }

  .vote-btn{
    width:100%;background:var(--purple);color:#fff;font-family:var(--sans);
    font-weight:700;font-size:15px;padding:14px;border:none;border-radius:12px;
    cursor:pointer;transition:opacity .15s;
  }
  .vote-btn:hover{opacity:.88;}

  /* Results */
  .results{display:flex;flex-direction:column;gap:14px;margin-bottom:24px;}
  .result-item{}
  .result-header{display:flex;justify-content:space-between;align-items:baseline;margin-bottom:6px;}
  .result-text{font-size:14px;font-weight:600;}
  .result-count{font-size:12px;color:var(--soft);font-family:monospace;}
  .bar-track{background:#f0ede8;border-radius:100px;height:10px;overflow:hidden;}
  .bar-fill{height:100%;border-radius:100px;background:var(--purple);transition:width .6s cubic-bezier(.4,0,.2,1);}
  .bar-fill.winner{background:var(--green);}
  .bar-fill.voted{background:var(--purple);}

  .result-item.my-vote .result-text::after{content:' ✓';color:var(--purple);}
  .result-item.winner-item .result-text::after{content:' 🏆';}
  .result-item.my-vote.winner-item .result-text::after{content:' ✓ 🏆';}

  .total-line{text-align:center;font-size:13px;color:var(--soft);margin-bottom:24px;}

  .divider{border:none;border-top:1px solid var(--border);margin:24px 0;}

  .share-box{background:#f7f5ff;border-radius:12px;padding:16px 18px;}
  .share-label{font-size:11px;letter-spacing:.08em;text-transform:uppercase;color:var(--purple);font-weight:700;margin-bottom:8px;}
  .share-row{display:flex;gap:8px;}
  .share-url{flex:1;background:#fff;border:1px solid var(--border);border-radius:8px;padding:9px 13px;font-size:13px;font-family:monospace;color:var(--soft);overflow:hidden;white-space:nowrap;text-overflow:ellipsis;}
  .copy-btn{background:var(--purple);color:#fff;border:none;border-radius:8px;padding:9px 18px;font-size:13px;font-weight:600;cursor:pointer;white-space:nowrap;transition:opacity .15s;}
  .copy-btn:hover{opacity:.85;}

  .back-link{display:block;text-align:center;margin-top:20px;color:var(--soft);font-size:13px;text-decoration:none;}
  .back-link:hover{color:var(--purple);}
</style>
</head>
<body>
<div class="page">
  <div class="brand"><span class="brand-name">⚡ QuickPoll</span></div>

  <div class="poll-card">
    <div class="poll-meta">Poll · <?= $totalVotes ?> vote<?= $totalVotes !== 1 ? 's' : '' ?></div>
    <div class="question"><?= htmlspecialchars($poll['question']) ?></div>

    <?php if (!$hasVoted): ?>
    <form method="POST" action="">
      <div class="options">
        <?php foreach ($options as $opt): ?>
        <label class="option-label">
          <input type="radio" name="option" value="<?= $opt['id'] ?>" required>
          <?= htmlspecialchars($opt['text']) ?>
        </label>
        <?php endforeach; ?>
      </div>
      <button type="submit" class="vote-btn">Cast My Vote →</button>
    </form>

    <?php else: ?>
    <?php
      $maxVotes = max(array_column($options, 'votes') ?: [0]);
    ?>
    <div class="results">
      <?php foreach ($options as $opt):
        $pct = $totalVotes > 0 ? round($opt['votes'] / $totalVotes * 100) : 0;
        $isWinner = $opt['votes'] === $maxVotes && $maxVotes > 0;
        $isMyVote = $opt['id'] == $votedOption;
        $cls = ($isMyVote ? ' my-vote' : '') . ($isWinner ? ' winner-item' : '');
      ?>
      <div class="result-item<?= $cls ?>">
        <div class="result-header">
          <span class="result-text"><?= htmlspecialchars($opt['text']) ?></span>
          <span class="result-count"><?= $opt['votes'] ?> — <?= $pct ?>%</span>
        </div>
        <div class="bar-track">
          <div class="bar-fill<?= $isWinner ? ' winner' : '' ?>" style="width:<?= $pct ?>%"></div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <p class="total-line"><?= $totalVotes ?> total vote<?= $totalVotes !== 1 ? 's' : '' ?> cast</p>

    <?php if ($voteMsg): ?>
      <p style="text-align:center;color:var(--soft);font-size:13px;margin-bottom:16px;"><?= htmlspecialchars($voteMsg) ?></p>
    <?php endif; ?>
    <?php endif; ?>

    <hr class="divider">

    <div class="share-box">
      <div class="share-label">Share this poll</div>
      <div class="share-row">
        <div class="share-url" id="share-url"><?= BASE_URL ?>/vote.php?s=<?= htmlspecialchars($slug) ?></div>
        <button class="copy-btn" onclick="copyLink(this)">Copy Link</button>
      </div>
    </div>
  </div>

  <a class="back-link" href="index.php">← Create a new poll</a>
</div>
<script>
function copyLink(btn) {
  const url = document.getElementById('share-url').textContent;
  navigator.clipboard.writeText(url).then(() => {
    btn.textContent = 'Copied!';
    setTimeout(() => btn.textContent = 'Copy Link', 2000);
  });
}
</script>
</body>
</html>
