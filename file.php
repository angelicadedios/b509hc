<?php
require __DIR__ . '/includes/bootstrap.php';

$u = current_user();
if (!$u) { http_response_code(403); exit('Please log in.'); }

$name = input('f', $_GET);
if (!preg_match('/^[a-f0-9]{32}\.(jpg|png|pdf)$/', $name, $m)) { http_response_code(404); exit('File not found.'); }

// Patients may only open their own files (booking attachments or their PhilHealth ID). Staff can open all.
if ($u['role'] !== 'staff') {
    $own = (int)q('SELECT COUNT(*) FROM appointments WHERE user_id = ? AND (result_file = ? OR id_file = ?)', [$u['id'], $name, $name])->fetchColumn()
         + (int)q('SELECT COUNT(*) FROM patients WHERE user_id = ? AND philhealth_file = ?', [$u['id'], $name])->fetchColumn();
    if ($own === 0) { http_response_code(403); exit('Access denied.'); }
}

$file = q('SELECT mime, size, data FROM files WHERE name = ?', [$name])->fetch();
if (!$file) { http_response_code(404); exit('File not found.'); }

header('Content-Type: ' . $file['mime']);
header('Content-Length: ' . strlen($file['data']));
header('Content-Disposition: inline; filename="attachment.' . $m[1] . '"');
header('Cache-Control: private, max-age=0');
echo $file['data'];
