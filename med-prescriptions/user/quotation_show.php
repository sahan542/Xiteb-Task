<?php
require_once __DIR__ . '/../inc/config.php';
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/auth.php';

$qid = (int)($_GET['id'] ?? 0);
$con = db();

$sql = "SELECT q.*, p.user_id 
        FROM quotations q 
        JOIN prescriptions p ON p.id=q.prescription_id
        WHERE q.id=?";
$stmt = $con->prepare($sql);
$stmt->bind_param('i', $qid);
$stmt->execute();
$quote = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$quote || (int)$quote['user_id'] !== (int)$_SESSION['user']['id']) {
  http_response_code(404); exit('Not found');
}

$stmt = $con->prepare("SELECT * FROM quotation_items WHERE quotation_id=?");
$stmt->bind_param('i', $qid);
$stmt->execute();
$items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$open = ($quote['status']==='sent' && (empty($quote['expires_at']) || strtotime($quote['expires_at'])>time()));
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Quotation #<?= (int)$qid ?></title>
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
  </style>
</head>
<body class="bg-light">

<div class="container my-5">
  <div class="card shadow rounded-3">
    <div class="card-body">
      <h3 class="card-title mb-4">
        Quotation #<?= (int)$qid ?> —
        <span class="mb-font">
          Status: <?= htmlspecialchars($quote['status']) ?>
          <?php if (!empty($quote['expires_at'])): ?>
            <small class="text-muted">(Expires: <?= htmlspecialchars(date('Y-m-d', strtotime($quote['expires_at']))) ?>)</small>
          <?php endif; ?>
        </span>
      </h3>

      <div class="table-responsive mb-4">
        <table class="table table-hover align-middle grid-table">
          <thead class="table-dark">
            <tr>
              <th>#</th>
              <th>Drug</th>
              <th>Qty</th>
              <th>Unit</th>
              <th>Amount</th>
            </tr>
          </thead>
          <tbody>
            <?php $i=1; foreach($items as $it): ?>
              <tr>
                <td><?= $i++ ?></td>
                <td><?= htmlspecialchars($it['drug']) ?></td>
                <td><?= (int)$it['quantity'] ?></td>
                <td><?= number_format((float)$it['unit_price'], 2) ?></td>
                <td><?= number_format((float)$it['line_total'], 2) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
          <tfoot>
            <tr>
              <td colspan="4" class="text-end"><b>Total</b></td>
              <td><b><?= number_format((float)$quote['total'], 2) ?></b></td>
            </tr>
          </tfoot>
        </table>
      </div>


      <?php if ($open): ?>
        <form method="post" action="<?= BASE_URL ?>user/quotation_decide.php" class="mt-3 d-flex gap-2">
          <input type="hidden" name="qid" value="<?= (int)$qid ?>">
          <button class="btn btn-success" name="decision" value="accept" type="submit">Accept</button>
          <button class="btn btn-danger" name="decision" value="reject" type="submit" onclick="return confirm('Reject this quotation?')">Reject</button>
        </form>
      <?php else: ?>
        <p class="mt-3 text-muted"><i>This quotation can no longer be acted on.</i></p>
      <?php endif; ?>

    </div>
  </div>
</div>

</body>
</html>
