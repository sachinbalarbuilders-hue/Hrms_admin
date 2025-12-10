<?php
include 'db.php';

header('Content-Type: application/json');

$emp_id = isset($_GET['emp_id']) ? (int)$_GET['emp_id'] : 0;
$date = isset($_GET['date']) ? trim($_GET['date']) : '';

if ($emp_id <= 0 || $date === '') {
    echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
    exit;
}

// Get employee info
$empStmt = $con->prepare("SELECT e.id, e.name, e.emp_code, desig.designation_name 
                          FROM employees e 
                          LEFT JOIN designations desig ON desig.id = e.designation_id 
                          WHERE e.id = ?");
$empStmt->bind_param("i", $emp_id);
$empStmt->execute();
$empResult = $empStmt->get_result();
$employee = $empResult->fetch_assoc();

if (!$employee) {
    echo json_encode(['success' => false, 'message' => 'Employee not found']);
    exit;
}

// Get numeric user_id from emp_code
$num = (int) filter_var($employee['emp_code'] ?? '', FILTER_SANITIZE_NUMBER_INT);
if ($num <= 0) {
    $num = (int)$employee['id'];
}

// Get attendance logs for this date
$startDateTime = $date . ' 00:00:00';
$endDateTime = $date . ' 23:59:59';

$logStmt = $con->prepare("
    SELECT time, type, working_from, reason 
    FROM attendance_logs 
    WHERE user_id = ? AND time BETWEEN ? AND ?
    ORDER BY time ASC
");
$logStmt->bind_param("iss", $num, $startDateTime, $endDateTime);
$logStmt->execute();
$logResult = $logStmt->get_result();

$logs = [];
while ($log = $logResult->fetch_assoc()) {
    $logs[] = [
        'time' => $log['time'],
        'type' => $log['type'],
        'working_from' => $log['working_from'],
        'reason' => $log['reason']
    ];
}

echo json_encode([
    'success' => true,
    'employee' => [
        'name' => $employee['name'],
        'role' => $employee['designation_name'] ?? '',
        'emp_code' => $employee['emp_code']
    ],
    'date' => $date,
    'logs' => $logs
]);

