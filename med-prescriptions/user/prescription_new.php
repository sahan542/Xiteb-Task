<?php
require_once __DIR__ . '/../inc/config.php';
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/helpers.php';

require_user();

$me = current_user();
$slots = time_slots_2h();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>New Prescription</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

  <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container-fluid">

      <div class="d-flex align-items-center">
        <a href="<?= e(BASE_URL) ?>/user/dashboard.php" class="navbar-brand">&larr; Back</a>
        <span class="navbar-brand fw-bold mb-0">Xiteb Prescription Mangement System</span>
      </div>

      <div class="d-flex align-items-center ms-auto">
                <span class="text-white me-3">Welcome, <?= e($me['name']) ?></span>

        <a href="<?= e(BASE_URL) ?>/user/logout.php" class="btn btn-outline-light btn-sm">Logout</a>
      </div>

    </div>
  </nav>

  <div class="container my-5">
    <div class="card shadow rounded-3">
      <div class="card-body">
        <h3 class="card-title mb-3">Upload Prescription</h3>
        <p class="text-muted">Max 5 images. Allowed: JPG / PNG / WebP (each ≤ 5MB).</p>

        <form method="post" action="<?= e(BASE_URL) ?>/user/prescription_store.php" enctype="multipart/form-data" id="rxForm">

          <div class="mb-3">
            <label for="note" class="form-label">Note</label>
            <textarea name="note" id="note" rows="3" class="form-control"></textarea>
          </div>

          <div class="mb-3">
            <label for="delivery_address" class="form-label">Delivery Address</label>
            <textarea name="delivery_address" id="delivery_address" rows="3" class="form-control" required></textarea>
          </div>

          <div class="mb-3">
            <label for="delivery_time_slot" class="form-label">Delivery Time Slot</label>
            <select name="delivery_time_slot" id="delivery_time_slot" class="form-select" required>
              <option value="">-- Select --</option>
              <?php foreach ($slots as $s): ?>
                <option value="<?= e($s) ?>"><?= e($s) ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="mb-3">
            <label for="images" class="form-label">Prescription Images</label>
            <input
              type="file"
              name="images[]"
              id="images"
              accept="image/jpeg,image/png,image/webp"
              class="form-control"
              multiple
            >
            <div id="imgSizeError" class="text-danger small mt-2" style="display:none;">
              Max 5 images. Allowed: JPG / PNG / WebP (each ≤ 5MB).
            </div>
            <div id="imgError" class="text-danger small mt-2" style="display:none;"></div>
          </div>

          <div id="previewWrap" class="mb-3" style="display:none;">
            <label class="form-label">Preview</label>
            <div id="imgPreview" class="d-flex flex-wrap gap-2"></div>
            <div id="imgCount" class="form-text"></div>
          </div>


          <button type="submit" class="btn btn-primary fw-bold">Submit</button>
          <a href="<?= e(BASE_URL) ?>/user/dashboard.php" class="btn btn-secondary ms-2">Cancel</a>
        </form>
      </div>
    </div>
  </div>

  <script>
    (function () {
      const MAX_IMAGES = 5;
      const MAX_SIZE = 5 * 1024 * 1024; 
      const allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];

      const form = document.getElementById('rxForm');
      const input = document.getElementById('images');
      const previewWrap = document.getElementById('previewWrap');
      const preview = document.getElementById('imgPreview');
      const imgCount = document.getElementById('imgCount');
      const imgError = document.getElementById('imgError');      
      const imgSizeError = document.getElementById('imgSizeError');

      function clearPreviewAndErrors() {
        preview.innerHTML = '';
        imgCount.textContent = '';
        previewWrap.style.display = 'none';
        imgError.style.display = 'none';
        imgError.textContent = '';
        imgSizeError.style.display = 'none';
        input.classList.remove('is-invalid');
      }

      function addThumb(file, url) {
        const fig = document.createElement('figure');
        fig.className = 'm-0';
        fig.style.width = '120px';

        const img = document.createElement('img');
        img.src = url;
        img.alt = file.name;
        img.className = 'img-thumbnail';
        img.style.width = '120px';
        img.style.height = '120px';
        img.style.objectFit = 'cover';

        const cap = document.createElement('figcaption');
        cap.className = 'small text-muted mt-1 text-truncate';
        cap.title = file.name;
        cap.textContent = file.name;

        fig.appendChild(img);
        fig.appendChild(cap);
        preview.appendChild(fig);
      }

      input.addEventListener('change', () => {
        clearPreviewAndErrors();

        const files = Array.from(input.files || []);
        if (!files.length) return;

        let blockSubmit = false;
        const messages = [];

        if (files.length > MAX_IMAGES) {
          messages.push(`You selected ${files.length} files. Maximum allowed is ${MAX_IMAGES}.`);
          blockSubmit = true;
        }

        const validForPreview = [];
        let anyTooLarge = false;

        files.forEach((file, idx) => {
          if (!allowedTypes.includes(file.type)) {
            messages.push(`File ${idx + 1} (${file.name}): Unsupported type.`);
            blockSubmit = true;
            return;
          }
          if (file.size > MAX_SIZE) {
            anyTooLarge = true;         
            blockSubmit = true;
            return;
          }
          validForPreview.push(file);
        });


        if (anyTooLarge) {
          imgSizeError.style.display = 'block';
        }


        if (messages.length) {
          imgError.textContent = messages.join(' ');
          imgError.style.display = 'block';
        }

        if (validForPreview.length) {
          previewWrap.style.display = 'block';
          validForPreview.forEach((file) => {
            const url = URL.createObjectURL(file);
            addThumb(file, url);
          });
          imgCount.textContent = `${validForPreview.length} file(s) selected`;
        }

        if (blockSubmit) {
          input.classList.add('is-invalid');
        }
      });


      form.addEventListener('submit', (e) => {
        const files = Array.from(input.files || []);
        let invalid = false;

        if (files.length > MAX_IMAGES) invalid = true;

        for (const f of files) {
          if (!allowedTypes.includes(f.type)) { invalid = true; break; }
          if (f.size > MAX_SIZE) { invalid = true; break; }
        }

        if (invalid) {
          e.preventDefault();
          input.dispatchEvent(new Event('change'));
        }
      });
    })();
  </script>
</body>
</html>
