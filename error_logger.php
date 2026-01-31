<?php
declare(strict_types=1);
// Simple CSV error logger for development and lightweight auditing
function log_error_to_csv(string $file, string $error): void {
    $csvPath = __DIR__ . '/error.csv';
    $id = uniqid('', true);
    $time = (new DateTime())->format('c');

    $row = [$id, $time, $file, $error];

    $needHeader = !file_exists($csvPath) || filesize($csvPath) === 0;

    $fh = @fopen($csvPath, 'a');
    if ($fh === false) return;
    if (!flock($fh, LOCK_EX)) { fclose($fh); return; }
    if ($needHeader) {
        fputcsv($fh, ['error_id', 'log_time', 'file', 'error']);
    }
    fputcsv($fh, $row);
    fflush($fh);
    flock($fh, LOCK_UN);
    fclose($fh);
}
