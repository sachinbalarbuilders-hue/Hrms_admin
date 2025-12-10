<?php
header('Content-Type: application/json');
include 'db.php';

if (!isset($_POST['id'], $_POST['status'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
    exit;
}

$id     = intval($_POST['id']);
$status = intval($_POST['status']) === 1 ? 1 : 0;

$stmt = $con->prepare("UPDATE employees SET status = ?, updated_at = NOW() WHERE id = ?");
$stmt->bind_param("ii", $status, $id);

if ($stmt->execute()) {
    echo json_encode([
        'success' => true,
        'message' => $status ? 'Account activated successfully.' : 'Account deactivated successfully.'
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
