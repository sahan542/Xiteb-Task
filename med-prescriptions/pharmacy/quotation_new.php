<?php
require_once __DIR__ . '/../inc/config.php';
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/auth.php';

if (session_status() === PHP_SESSION_NONE) session_start();

if (empty($_SESSION['user'])) {
  $next = urlencode($_SERVER['REQUEST_URI'] ?? BASE_URL);
  header('Location: ' . BASE_URL . 'login.php?next=' . $next);
  exit;
}
if (($_SESSION['user']['role'] ?? '') !== 'pharmacy') {
  header('Location: ' . BASE_URL . 'user/quotations.php');
  exit;
}

function h($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

function rx_img_url(?string $fname): string {
  if (!$fname) return '';


  if (preg_match('~^https?://~i', $fname)) {
    return $fname;
  }


  $f = ltrim(str_replace('\\', '/', (string)$fname), '/');


  $segments = array_map('rawurlencode', array_filter(explode('/', $f), 'strlen'));
  $encodedPath = implode('/', $segments);

  $baseHttp = rtrim(BASE_URL, '/');                 
  $uploadsHttp = $baseHttp . '/public/uploads/';  
  $uploadsFs   = realpath(__DIR__ . '/../public/uploads');

 
  $candidateFs = $uploadsFs ? $uploadsFs . '/' . $f : null;
  if ($candidateFs && is_file($candidateFs)) {
    return $uploadsHttp . $encodedPath;
  }

  $encodedBasename = rawurlencode(basename($f));
  return $uploadsHttp . $encodedBasename;
}


$con = db();

$pid = (int)($_GET['pid'] ?? 0);
if ($pid <= 0) { echo "Prescription not found"; exit; }


$sql = "SELECT p.*, u.email 
        FROM prescriptions p 
        JOIN users u ON u.id = p.user_id 
        WHERE p.id = ?";
$stmt = $con->prepare($sql);
$stmt->bind_param('i', $pid);
$stmt->execute();
$p = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$p) { echo "Prescription not found"; exit; }

$sql = "SELECT * FROM quotations 
        WHERE prescription_id = ? AND pharmacy_id = ? AND status = 'draft'";
$stmt = $con->prepare($sql);
$stmt->bind_param('ii', $pid, $_SESSION['user']['id']);
$stmt->execute();
$quote = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$quote) {
  $sql = "INSERT INTO quotations (prescription_id, pharmacy_id) VALUES (?, ?)";
  $stmt = $con->prepare($sql);
  $stmt->bind_param('ii', $pid, $_SESSION['user']['id']);
  $stmt->execute();
  $qid = (int)$con->insert_id;
  $stmt->close();
} else {
  $qid = (int)$quote['id'];
}


$sql = "SELECT * FROM quotation_items WHERE quotation_id = ?";
$stmt = $con->prepare($sql);
$stmt->bind_param('i', $qid);
$stmt->execute();
$res = $stmt->get_result();
$items = $res->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$subtotal = 0.0;
foreach ($items as $i) $subtotal += (float)$i['line_total'];

$sql = "SELECT image_path FROM prescription_images WHERE prescription_id = ? ORDER BY id ASC";
$stmt = $con->prepare($sql);
$stmt->bind_param('i', $pid);
$stmt->execute();
$imgsRes = $stmt->get_result();
$imgs = $imgsRes->fetch_all(MYSQLI_ASSOC);
$stmt->close();


$imgUrls = [];
foreach ($imgs as $im) {
  $url = rx_img_url($im['image_path'] ?? '');
  if ($url) $imgUrls[] = $url;
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Prepare Quotation</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">


  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="<?= BASE_URL ?>style.css">

  <style>

    .grid-table thead th,
    .grid-table tbody td,
    .grid-table tfoot td {
      border: 1px solid #dee2e6;
      text-align: center;
      vertical-align: middle;
    }

    .rx-main {
      position: relative;
      border: 1px solid #e0e0e0;
      border-radius: .5rem;
      background: #fff;
      overflow: hidden;
    }

    .rx-main::before { content: ""; display: block; padding-top: 100%; } 
    .rx-main img {
      position: absolute; inset: 0;
      width: 100%; height: 100%;
      object-fit: contain;
      background: #f8f9fa;
    }
    .rx-full-btn {
      position: absolute; right: .5rem; bottom: .5rem;
      z-index: 2;
      opacity: .85;
    }
    .rx-thumbs { gap: .5rem; }
    .rx-thumb {
      width: 68px; height: 68px;
      border: 1px solid #e0e0e0; border-radius: .35rem;
      overflow: hidden; background: #fff; padding: 0;
    }
    .rx-thumb img {
      width: 100%; height: 100%; object-fit: cover;
    }
    .rx-thumb.active { outline: 2px solid #0d6efd; outline-offset: 1px; }
  </style>
</head>
<body class="bg-light">

<div class="container my-5">
  <div class="card shadow rounded-3">
    <div class="card-body">
      <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-4">
        <h3 class="card-title mb-0">Prepare Quotation</h3>
        <div class="text-muted small">
          Rx ID: <b><?= (int)$pid ?></b> &nbsp;|&nbsp; Patient: <b><?= h($p['email']) ?></b>
        </div>
      </div>

      <div class="row g-4">
        <div class="col-12 col-lg-4">
          <?php if (!empty($imgUrls)): ?>
            <div id="rxViewer">
              <div class="rx-main mb-2" id="rxMainFrame">
                <img id="rxMain" src="<?= h($imgUrls[0]) ?>" alt="Prescription image">
                <button type="button" class="btn btn-dark btn-sm rx-full-btn" id="rxFull">⤢</button>
              </div>
              <div class="rx-thumbs d-flex flex-wrap">
                <?php foreach ($imgUrls as $idx => $url): ?>
                  <button type="button"
                          class="rx-thumb <?= $idx===0 ? 'active' : '' ?>"
                          data-src="<?= h($url) ?>"
                          aria-label="Thumbnail <?= $idx+1 ?>">
                    <img src="<?= h($url) ?>" alt="Thumbnail <?= $idx+1 ?>">
                  </button>
                <?php endforeach; ?>
              </div>
            </div>
          <?php else: ?>
            <div class="border rounded p-3 text-center text-muted">No images uploaded.</div>
          <?php endif; ?>
        </div>

        <div class="col-12 col-lg-8">
          <div class="table-responsive mb-3">
            <form method="post" action="<?= BASE_URL ?>pharmacy/quotation_store.php">
              <input type="hidden" name="qid" value="<?= (int)$qid ?>">

              <table class="table table-hover align-middle grid-table">
                <thead class="table-dark">
                  <tr>
                    <th>Drug</th>
                    <th>Qty</th>
                    <th>Unit</th>
                    <th>Amount</th>
                    <th>Remove</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if (!$items): ?>
                    <tr>
                      <td colspan="5" class="text-center text-muted py-4">No items yet. Add below.</td>
                    </tr>
                  <?php else: ?>
                    <?php foreach ($items as $i): ?>
                      <tr>
                        <td><?= h($i['drug']) ?></td>
                        <td><?= (int)$i['quantity'] ?></td>
                        <td><?= number_format((float)$i['unit_price'], 2) ?></td>
                        <td><?= number_format((float)$i['line_total'], 2) ?></td>
                        <td>
                          <a class="btn btn-sm btn-outline-danger"
                             href="<?= BASE_URL . 'pharmacy/quotation_store.php?del=' . (int)$i['id'] . '&qid=' . (int)$qid ?>"
                             title="Remove"
                             onclick="return confirm('Remove this item?');">✕</a>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  <?php endif; ?>

                  <tr>
                    <td class="text-start">
                      <input name="drug" class="form-control form-control-sm" placeholder="Drug name" required>
                    </td>
                    <td style="max-width:120px;">
                      <input name="qty" type="number" min="1" value="1" class="form-control form-control-sm text-center" required>
                    </td>
                    <td style="max-width:160px;">
                      <div class="input-group input-group-sm">
                        <span class="input-group-text">LKR</span>
                        <input name="unit" type="number" step="0.01" min="0" class="form-control text-end" placeholder="0.00" required>
                      </div>
                    </td>
                    <td colspan="2" class="text-start">
                      <button type="submit" class="btn btn-sm btn-primary">Add</button>
                    </td>
                  </tr>
                </tbody>
                <tfoot>
                  <tr>
                    <td colspan="3" class="text-end"><b>Total:</b></td>
                    <td colspan="2"><b><?= number_format($subtotal, 2) ?></b></td>
                  </tr>
                </tfoot>
              </table>
            </form>
          </div>

          <form method="post" action="<?= BASE_URL ?>pharmacy/quotation_send.php"
                class="d-flex align-items-center gap-2 flex-wrap"
                onsubmit="return confirm('Send quotation to user?');">
            <input type="hidden" name="qid" value="<?= (int)$qid ?>">
            <label class="form-label mb-0">Expires in (days):</label>
            <input type="number" name="days" value="7" min="1" max="30" class="form-control form-control-sm" style="width: 100px;">
            <button type="submit" class="btn btn-success btn-sm">Send Quotation</button>
          </form>

        </div>
      </div>

    </div>
  </div>
</div>

<script>
(function () {
  const mainImg = document.getElementById('rxMain');
  const frame   = document.getElementById('rxMainFrame');
  const fullBtn = document.getElementById('rxFull');
  const thumbs  = Array.from(document.querySelectorAll('.rx-thumb')); 
  if (!mainImg || !frame || thumbs.length === 0) return;

  let current = Math.max(0, thumbs.findIndex(t => t.classList.contains('active')));
  if (current === -1) current = 0;

  function setActive(idx) {
    if (!thumbs.length) return;
    const n = thumbs.length;
    current = ((idx % n) + n) % n; 
    const btn = thumbs[current];
    const src = btn.getAttribute('data-src');
    if (src) mainImg.src = src;


    thumbs.forEach(t => t.classList.remove('active'));
    btn.classList.add('active');


    btn.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'center' });


    const next = thumbs[(current + 1) % n]?.getAttribute('data-src');
    const prev = thumbs[(current - 1 + n) % n]?.getAttribute('data-src');
    [next, prev].forEach(u => { if (u) { const img = new Image(); img.src = u; } });
  }


  thumbs.forEach((btn, i) => {
    btn.addEventListener('click', () => setActive(i));
  });

  function openFullscreen(el) {
    if (el.requestFullscreen) return el.requestFullscreen();
    if (el.webkitRequestFullscreen) return el.webkitRequestFullscreen();
    if (el.msRequestFullscreen) return el.msRequestFullscreen();
  }
  function exitFullscreen() {
    if (document.exitFullscreen) return document.exitFullscreen();
    if (document.webkitExitFullscreen) return document.webkitExitFullscreen();
    if (document.msExitFullscreen) return document.msExitFullscreen();
  }

  fullBtn && fullBtn.addEventListener('click', () => openFullscreen(frame));
  frame.addEventListener('click', (e) => {
    if (e.target === mainImg) openFullscreen(frame);
  });


  document.addEventListener('keydown', (e) => {
    if (e.key === 'ArrowRight') { setActive(current + 1); }
    else if (e.key === 'ArrowLeft') { setActive(current - 1); }
    else if (e.key === 'Escape' && document.fullscreenElement) { exitFullscreen(); }
  });

  let touchX = null;
  mainImg.addEventListener('touchstart', (e) => {
    if (e.touches && e.touches[0]) touchX = e.touches[0].clientX;
  }, { passive: true });
  mainImg.addEventListener('touchend', (e) => {
    if (touchX === null) return;
    const endX = (e.changedTouches && e.changedTouches[0]) ? e.changedTouches[0].clientX : touchX;
    const dx = endX - touchX;
    const threshold = 30;
    if (Math.abs(dx) > threshold) {
      if (dx < 0) setActive(current + 1); else setActive(current - 1);
    }
    touchX = null;
  }, { passive: true });


  setActive(current);
})();
</script>


</body>
</html>
