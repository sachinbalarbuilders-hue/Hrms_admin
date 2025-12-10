<?php
include 'db.php';

/* ---------- MONTH & YEAR (from GET) ---------- */
$currentYear  = (int)date('Y');
$currentMonth = (int)date('n');

$year  = isset($_GET['year'])  ? (int)$_GET['year']  : $currentYear;
$month = isset($_GET['month']) ? (int)$_GET['month'] : $currentMonth;

$monthName  = date('F Y', strtotime("$year-$month-01"));
$totalDays  = cal_days_in_month(CAL_GREGORIAN, $month, $year);

/* ---------- Fetch Employees (with emp_code for mapping) ---------- */
$empSql = "
    SELECT e.id, e.emp_code, e.name, desig.designation_name 
    FROM employees e
    LEFT JOIN designations desig ON desig.id = e.designation_id
    ORDER BY e.name ASC
";
$empRes = $con->query($empSql);

$employees      = [];
$empByLogUserId = []; // key = attendance_logs.user_id, value = employee_id

if ($empRes && $empRes->num_rows > 0) {
    while ($row = $empRes->fetch_assoc()) {
        // Example: EMP001 -> 1
        $num = (int) filter_var($row['emp_code'] ?? '', FILTER_SANITIZE_NUMBER_INT);
        if ($num <= 0) {
            $num = (int)$row['id']; // fallback
        }
        $row['log_user_id']    = $num;
        $employees[]           = $row;
        $empByLogUserId[$num]  = (int)$row['id'];
    }
}

/* ---------- Fetch Attendance Logs for this month ---------- */
$attendanceMap = []; // [employee_id][day] = ['logs'=>[], 'status'=>'P']

if (!empty($empByLogUserId)) {
    $startDate      = sprintf('%04d-%02d-01', $year, $month);
    $endDate        = date('Y-m-t', strtotime($startDate));
    $startDateTime  = $startDate . ' 00:00:00';
    $endDateTime    = $endDate   . ' 23:59:59';

    $logSql = "
        SELECT user_id, time, type, working_from, reason
        FROM attendance_logs
        WHERE time BETWEEN ? AND ?
    ";
    $stmtLog = $con->prepare($logSql);
    if ($stmtLog) {
        $stmtLog->bind_param("ss", $startDateTime, $endDateTime);
        $stmtLog->execute();
        $logsRes = $stmtLog->get_result();

        while ($log = $logsRes->fetch_assoc()) {
            $logUserId = (int)$log['user_id'];
            if (!isset($empByLogUserId[$logUserId])) {
                continue;
            }

            $empId = $empByLogUserId[$logUserId];

            $ts  = strtotime($log['time']);
            $day = (int)date('j', $ts); // 1..31

            if (!isset($attendanceMap[$empId])) {
                $attendanceMap[$empId] = [];
            }
            if (!isset($attendanceMap[$empId][$day])) {
                $attendanceMap[$empId][$day] = [
                    'logs'   => [],
                    'status' => 'P', // ek bhi log hai to present
                ];
            }

            $attendanceMap[$empId][$day]['logs'][] = $log;
        }
        $stmtLog->close();
    }
}

/* ---------- Month List ---------- */
$monthNames = [
 1=>'January',2=>'February',3=>'March',4=>'April',5=>'May',6=>'June',
 7=>'July',8=>'August',9=>'September',10=>'October',11=>'November',12=>'December'
];
?>

<style>
body { background:#f5f6f8; font-family: Arial, sans-serif; }

/* ---------- Header row (title + filters + add button) ---------- */
.att-header-bar{
    display:flex;
    justify-content:space-between;
    align-items:flex-end;
    gap:16px;
    margin-bottom:16px;
    flex-wrap:wrap;
}
.att-header-right{
    display:flex;
    align-items:center;
    gap:10px;
    flex-wrap:wrap;
}

/* Legend */
.att-legend {
    display:flex;
    gap:16px;
    flex-wrap:wrap;
    margin-bottom:10px;
}
.att-pill {
    display:flex;
    align-items:center;
    gap:6px;
    font-size:13px;
    background:#f3f4f6;
    border-radius:999px;
    padding:4px 12px;
}
.att-pill-dot{
    width:12px;height:12px;border-radius:50%;
}
.dot-present{background:#16a34a;}
.dot-absent{background:#dc2626;}

/* ---------- Filter Styling (Compact) ---------- */
.att-filter-select {
    border-radius: 8px !important;
    padding: 4px 8px !important;
    height: 36px !important;
    font-size: 14px !important;
}
#attendanceFilterForm .btn {
    height: 36px !important;
    padding: 0px 18px !important;
    font-size: 14px !important;
    border-radius: 8px;
}

/* Add Attendance button */
.att-add-btn{
    border-radius: 999px;
    font-weight: 600;
    padding: 6px 20px;
    font-size: 14px;
}

/* ---------- Card ---------- */
.att-card {
    background: #fff;
    border-radius: 16px;
    padding: 0;
    border: 1px solid #e3e3e3;
    overflow: hidden;
}

/* ---------- Scroll wrapper ---------- */
.att-scroll-wrapper {
    overflow-x: auto;
    overflow-y: hidden;
    padding-bottom: 5px;
}

.att-table {
    width: max-content;
    border-collapse: collapse;
}

/* Sticky employee & total columns */
.att-sticky-left {
    position: sticky;
    left: 0;
    background: #fff;
    z-index: 10;
}
.att-sticky-right {
    position: sticky;
    right: 0;
    background: #fff;
    z-index: 10;
}

/* Header */
.att-table th {
    text-align: center;
    padding: 10px 6px;
    background: #eef3fa;
    border-bottom: 1px solid #d8dce2;
    font-size: 13px;
}

/* Employee Row */
.att-table td {
    border-bottom: 1px solid #eee;
    padding: 8px 6px;
    text-align: center;
}

/* Employee Layout */
.emp-info {
    display: flex;
    align-items: center;
    gap: 12px;
}
.emp-avatar {
    min-width: 40px;
    height: 40px;
    border-radius: 50%;
    background: #111;
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
}
.emp-details { line-height: 1.2; text-align: left; }
.emp-name { font-weight: 600; font-size: 15px; }
.emp-role { font-size: 12px; color:#6b7280; }

/* Attendance Badges */
.att-badge {
    width: 30px; height: 30px;
    border-radius: 50%;
    font-size:13px;
    font-weight:bold;
    display:flex;
    justify-content:center;
    align-items:center;
    margin:auto;
}
.att-P { background:#dcfce7; color:#166534; }
.att-A { background:#fee2e2; color:#b91c1c; }

/* Tooltip */
.att-cell-wrapper {
    position: relative;
}
.att-tooltip {
    display:none;
    position:absolute;
    top:-50px;
    left:50%;
    transform:translateX(-50%);
    background:#fff;
    padding:6px 12px;
    border-radius:999px;
    font-size:12px;
    box-shadow:0px 6px 18px rgba(0,0,0,0.18);
    white-space:nowrap;
    z-index:20;
}
.att-cell-wrapper:hover .att-tooltip {
    display:block;
}
</style>

<div style="padding:20px;">

    <!-- HEADER ROW: title left, filters + add button right -->
    <div class="att-header-bar">

        <!-- LEFT: Title -->
        <div>
            <h2 style="font-weight:700; margin-bottom:4px;">Attendance Management</h2>
            <div style="color:#6b7280; font-size:14px;">
                Month: <strong><?php echo htmlspecialchars($monthName); ?></strong>
            </div>
        </div>

        <!-- RIGHT: Filters + +Add Attendance -->
        <div class="att-header-right">

            <!-- FILTERS -->
            <form id="attendanceFilterForm"
                  method="GET"
                  style="display:flex; gap:10px;">

                <select name="month" class="att-filter-select">
                    <?php foreach ($monthNames as $m => $label): ?>
                        <option value="<?php echo $m; ?>" <?php echo ($m == $month?'selected':''); ?>>
                            <?php echo $label; ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <select name="year" class="att-filter-select">
                    <?php for ($y = $currentYear-3; $y <= $currentYear+3; $y++): ?>
                        <option value="<?php echo $y; ?>" <?php echo ($y == $year?'selected':''); ?>>
                            <?php echo $y; ?>
                        </option>
                    <?php endfor; ?>
                </select>

                <button class="btn btn-dark" type="submit">Apply</button>
            </form>

            <!-- ADD ATTENDANCE BUTTON (yehi se modal open hoga) -->
            <button type="button"
                    class="btn btn-primary att-add-btn"
                    data-bs-toggle="modal"
                    data-bs-target="#markAttendanceModal">
                + Add Attendance
            </button>
        </div>
    </div>

    <!-- LEGEND -->
    <div class="att-legend">
        <div class="att-pill"><span class="att-pill-dot dot-present"></span> Present</div>
        <div class="att-pill"><span class="att-pill-dot dot-absent"></span> Absent</div>
    </div>

    <!-- CARD -->
    <div class="att-card">
        <div class="att-scroll-wrapper">

            <table class="att-table">
                <thead>
                    <tr>
                        <th class="att-sticky-left" style="text-align:left; padding-left:15px;">EMPLOYEE</th>

                        <?php for ($d=1; $d <= $totalDays; $d++):
                            $day = date('D', strtotime("$year-$month-$d"));
                        ?>
                            <th>
                                <?php echo $d; ?><br>
                                <small><?php echo $day; ?></small>
                            </th>
                        <?php endfor; ?>

                        <th class="att-sticky-right">TOTAL</th>
                    </tr>
                </thead>

                <tbody>
                <?php if (!empty($employees)): ?>
                    <?php foreach ($employees as $emp): 
                        $empId        = (int)$emp['id'];
                        $presentCount = 0;
                    ?>
                    <tr>

                        <!-- EMPLOYEE CELL -->
                        <td class="att-sticky-left" style="background:#fff;">
                            <div class="emp-info">
                                <div class="emp-avatar">
                                    <?php echo strtoupper(substr($emp['name'],0,1)); ?>
                                </div>
                                <div class="emp-details">
                                    <div class="emp-name"><?php echo htmlspecialchars($emp['name']); ?></div>
                                    <div class="emp-role"><?php echo htmlspecialchars($emp['designation_name'] ?? ''); ?></div>
                                </div>
                            </div>
                        </td>

                        <!-- ATTENDANCE CELLS -->
                        <?php for ($d=1; $d <= $totalDays; $d++): 
                            $dayData = $attendanceMap[$empId][$d] ?? null;

                            if ($dayData && !empty($dayData['logs'])) {
                                $status = 'P';
                                $presentCount++;

                                $logCount = count($dayData['logs']);
                                $firstLog = $dayData['logs'][0];
                                $lastLog  = $dayData['logs'][$logCount-1];

                                $wfrom = $lastLog['working_from'] ?? '';
                                $reason= $lastLog['reason'] ?? '';

                                $tooltip = "Present ($logCount punches)";
                                if ($wfrom)  $tooltip .= " · " . ucfirst($wfrom);
                                if ($reason) $tooltip .= " · " . str_replace('_',' ', $reason);
                            } else {
                                $status  = 'A';
                                $tooltip = "Absent";
                            }
                        ?>
                        <td>
                            <div class="att-cell-wrapper">
                                <div class="att-badge att-<?php echo $status; ?>">
                                    <?php echo $status === 'P' ? 'P' : 'X'; ?>
                                </div>
                                <div class="att-tooltip">
                                    <?php echo htmlspecialchars($tooltip); ?>
                                </div>
                            </div>
                        </td>
                        <?php endfor; ?>

                        <!-- TOTAL -->
                        <td class="att-sticky-right" style="background:#fff; font-weight:700;">
                            <?php echo $presentCount . '/' . $totalDays; ?>
                        </td>

                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="<?php echo $totalDays + 2; ?>" class="text-center py-4 text-muted">
                            No employees found.
                        </td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>

        </div>
    </div>

</div>
