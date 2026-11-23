<?php
require __DIR__ . '/../includes/bootstrap.php';

header('Content-Type: application/json');

if (!current_user()) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Please log in again.']);
    exit;
}

$month = input('month', $_GET);
if ($month !== '') {
    if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) {
        echo json_encode(['ok' => false, 'error' => 'Invalid month.']);
        exit;
    }
    echo json_encode(['ok' => true, 'month' => $month, 'days' => month_availability($month)]);
    exit;
}

$date = input('date', $_GET);
$err = booking_date_error($date);
if ($err) {
    echo json_encode(['ok' => false, 'error' => $err]);
    exit;
}

echo json_encode(['ok' => true, 'date' => $date, 'slots' => slots_for_date($date)]);
