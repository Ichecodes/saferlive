<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', '1');
error_reporting(E_ALL);

require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

// optional global error logger
if (file_exists(dirname(__DIR__, 2) . '/error_logger.php')) {
    require_once dirname(__DIR__, 2) . '/error_logger.php';
}

use Cloudinary\Cloudinary;

function respond(array $data, int $status = 200): void {
    http_response_code($status);
    echo json_encode($data);
    exit;
}

try {
    $db = getDatabaseConnection();
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

    /* =========================
       GET — fetch incident media
       ========================= */
    if ($method === 'GET') {
        $id = $_GET['id'] ?? null;

        if (!$id || !ctype_digit($id)) {
            respond(['success' => false, 'error' => 'Invalid incident id'], 400);
        }

        $stmt = $db->prepare(
            'SELECT cloudinary_public_id, secure_url, width, height
             FROM incident_media
             WHERE incident_id = :id AND media_type = "image"
             ORDER BY created_at ASC'
        );
        $stmt->execute([':id' => (int)$id]);

        respond([
            'success' => true,
            'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)
        ]);
    }

    /* =========================
       POST — upload photos
       ========================= */
    if ($method === 'POST') {

        if (
            empty($_POST['incident_id']) ||
            !ctype_digit($_POST['incident_id'])
        ) {
            respond(['success' => false, 'error' => 'Invalid incident_id'], 400);
        }

        if (empty($_FILES['photos'])) {
            respond(['success' => false, 'error' => 'No photos uploaded'], 400);
        }

        $incidentId = (int)$_POST['incident_id'];
        $files = $_FILES['photos'];

        $cloudinary = new Cloudinary([
            'cloud' => [
                'cloud_name' => 'dphdvbdwg',
                'api_key'    => '578132374856611',
                'api_secret' => 'HwtzajSrqai4h5iV0jatHo0XyBI'
            ]
        ]);

        $uploaded = [];
        $failed   = [];
        $maxSize  = 5 * 1024 * 1024;

        for ($i = 0; $i < count($files['name']); $i++) {

            if ($files['error'][$i] !== UPLOAD_ERR_OK) {
                $failed[] = ['name' => $files['name'][$i], 'error' => 'Upload error'];
                continue;
            }

            if ($files['size'][$i] > $maxSize) {
                $failed[] = ['name' => $files['name'][$i], 'error' => 'File too large'];
                continue;
            }

            if (!getimagesize($files['tmp_name'][$i])) {
                $failed[] = ['name' => $files['name'][$i], 'error' => 'Invalid image'];
                continue;
            }

            try {
                $result = $cloudinary->uploadApi()->upload(
                    $files['tmp_name'][$i],
                    ['folder' => "incidents/$incidentId"]
                );

                if (empty($result['public_id']) || empty($result['secure_url'])) {
                    throw new Exception('Cloudinary response incomplete');
                }

                $stmt = $db->prepare(
                    'INSERT INTO incident_media
                     (incident_id, cloudinary_public_id, secure_url, width, height, media_type)
                     VALUES
                     (:incident_id, :public_id, :secure_url, :width, :height, "image")'
                );

                $stmt->execute([
                    ':incident_id' => $incidentId,
                    ':public_id'   => $result['public_id'],
                    ':secure_url'  => $result['secure_url'],
                    ':width'       => $result['width'] ?? null,
                    ':height'      => $result['height'] ?? null,
                ]);

                $uploaded[] = [
                    'public_id'  => $result['public_id'],
                    'secure_url' => $result['secure_url']
                ];

            } catch (Throwable $e) {
                error_log('Photo upload failed: ' . $e->getMessage());
                if (function_exists('log_error_to_csv')) {
                    try { log_error_to_csv(basename(__FILE__), $e->getMessage() . "\n" . $e->getTraceAsString()); } catch (Throwable $_) {}
                }
                $failed[] = ['name' => $files['name'][$i], 'error' => 'Upload failed'];
            }
        }

        respond([
            'success'  => true,
            'uploaded' => $uploaded,
            'failed'   => $failed
        ]);
    }

    respond(['success' => false, 'error' => 'Method not allowed'], 405);

} catch (Throwable $e) {
    error_log('photos.php fatal: ' . $e->getMessage());
    if (function_exists('log_error_to_csv')) {
        try { log_error_to_csv(basename(__FILE__), $e->getMessage() . "\n" . $e->getTraceAsString()); } catch (Throwable $_) {}
    }
    respond(['success' => false, 'error' => 'Server error'], 500);
}
