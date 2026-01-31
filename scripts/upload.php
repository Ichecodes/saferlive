<?php
declare(strict_types=1);
// Single-file uploader: serves HTML form and handles POST uploads to Cloudinary
 require_once dirname(__DIR__, 1) . '/vendor/autoload.php';
    use Cloudinary\Cloudinary;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');

    $incidentId = $_POST['incident_id'] ?? null;
    if (!$incidentId || !ctype_digit((string)$incidentId) || (int)$incidentId <= 0) {
        echo json_encode(['success' => false, 'error' => 'Invalid incident_id']);
        exit;
    }

    if (!isset($_FILES['photos'])) {
        echo json_encode(['success' => false, 'error' => 'No files uploaded']);
        exit;
    }

    // Cloudinary credentials copied from existing code
     $cloudinary = new Cloudinary([
    'cloud' => [
      'cloud_name' => 'dphdvbdwg',
      'api_key' => '578132374856611',
      'api_secret' => 'HwtzajSrqai4h5iV0jatHo0XyBI'
    ]
  ]);

    // Attempt to get DB connection for saving metadata (optional)
    $db = null;
    try {
      require_once dirname(__DIR__, 1) . '/config/database.php';
      $db = getDatabaseConnection();
    } catch (Throwable $e) {
      // DB not available — uploads will continue but metadata won't be saved
      $db = null;
    }

    // Optional CSV error logger
    if (file_exists(dirname(__DIR__, 1) . '/error_logger.php')) {
      require_once dirname(__DIR__, 1) . '/error_logger.php';
    }

    $files = $_FILES['photos'];
    $count = count($files['name']);
    $uploaded = [];
    $failed = [];

    for ($i = 0; $i < $count; $i++) {
        $name = $files['name'][$i];
        $tmp = $files['tmp_name'][$i];
        $err = $files['error'][$i];
        $size = $files['size'][$i];

        if ($err !== UPLOAD_ERR_OK) { $failed[] = ['name'=>$name,'error'=>'Upload error']; continue; }
        if (!is_uploaded_file($tmp) || !getimagesize($tmp)) { $failed[] = ['name'=>$name,'error'=>'Invalid image']; continue; }

        try {
          $res = $cloudinary->uploadApi()->upload($tmp, [
            'folder' => 'incidents/' . (int)$incidentId,
            'resource_type' => 'image'
          ]);

          $publicId = $res['public_id'] ?? null;
          $secureUrl = $res['secure_url'] ?? null;
          $width = isset($res['width']) ? (int)$res['width'] : null;
          $height = isset($res['height']) ? (int)$res['height'] : null;

          // Save metadata to DB if available
          $mediaId = null;
          if ($db) {
            try {
              $stmt = $db->prepare('INSERT INTO incident_media (incident_id, cloudinary_public_id, secure_url, width, height, media_type, created_at) VALUES (:incident_id, :public_id, :secure_url, :width, :height, :media_type, NOW())');
              $stmt->execute([
                ':incident_id' => (int)$incidentId,
                ':public_id' => $publicId,
                ':secure_url' => $secureUrl,
                ':width' => $width,
                ':height' => $height,
                ':media_type' => 'image'
              ]);
              $mediaId = (int)$db->lastInsertId();
            } catch (Throwable $e) {
              // If DB insert fails, record failure but continue
              error_log('incident_media insert failed: ' . $e->getMessage());
            }
          }

          $uploaded[] = [
            'name' => $name,
            'public_id' => $publicId,
            'secure_url' => $secureUrl,
            'width' => $width,
            'height' => $height,
            'media_id' => $mediaId
          ];

        } catch (Throwable $e) {
          if (function_exists('log_error_to_csv')) {
            try { log_error_to_csv(basename(__FILE__), $e->getMessage() . "\n" . $e->getTraceAsString()); } catch (Throwable $_) {}
          }
          $failed[] = ['name'=>$name,'error'=>$e->getMessage()];
        }
    }

    echo json_encode(['success'=>true,'uploaded'=>$uploaded,'failed'=>$failed]);
    exit;
}

// If not POST, render HTML form
?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Upload Photos to Cloudinary</title>
  <style>
    body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial;margin:24px;background:#f7f7f7}
    .card{max-width:720px;margin:0 auto;background:#fff;padding:18px;border-radius:8px;box-shadow:0 6px 18px rgba(0,0,0,0.08)}
    label{display:block;margin-bottom:8px;font-weight:600}
    input[type=file]{display:block;margin-bottom:12px}
    .row{display:flex;gap:8px;align-items:center}
    button{padding:8px 12px;border-radius:6px;border:1px solid #0b6; background:#06c270;color:#fff;cursor:pointer}
    pre{background:#111;color:#0f0;padding:12px;border-radius:6px;overflow:auto;max-height:240px}
  </style>
</head>
<body>
  <main class="card">
    <h2>Upload Photos to Cloudinary</h2>
    <form id="uform" method="post" enctype="multipart/form-data">
      <label for="incident_id">Incident ID</label>
      <input id="incident_id" name="incident_id" type="number" min="1" required>

      <label for="photos">Choose photos (multiple)</label>
      <input id="photos" name="photos[]" type="file" accept="image/*" multiple required>

      <div class="row">
        <button id="submit">Upload</button>
        <span id="status"></span>
      </div>
    </form>

    <h3>Response</h3>
    <pre id="out">(no activity)</pre>
  </main>

  <script>
    (function(){
      const form = document.getElementById('uform');
      const out = document.getElementById('out');
      const status = document.getElementById('status');
      form.addEventListener('submit', async (e)=>{
        e.preventDefault();
        status.textContent = 'Uploading...';
        out.textContent = '';
        const fd = new FormData(form);
        try {
          const resp = await fetch(location.pathname, { method: 'POST', body: fd });
          const txt = await resp.text();
          try { const json = JSON.parse(txt); out.textContent = JSON.stringify(json, null, 2); }
          catch(err){ out.textContent = txt; }
          status.textContent = resp.ok ? 'Done' : 'Error';
        } catch (err) {
          status.textContent = 'Network error';
          out.textContent = String(err);
        }
      });
    })();
  </script>
</body>
</html>
