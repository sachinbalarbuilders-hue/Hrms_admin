<?php
include 'db.php';

/* ---------- MONTH & YEAR (from GET) ---------- */
$currentYear  = (int)date('Y');
$currentMonth = (int)date('n');

$year  = isset($_GET['year'])  ? (int)$_GET['year']  : $currentYear;
$month = isset($_GET['month']) ? (int)$_GET['month'] : $currentMonth;

$monthName  = date('F Y', strtotime("$year-$month-01"));
$totalDays  = cal_days_in_month(CAL_GREGORIAN, $month, $year);

/* ---------- Fetch Employees (with emp_code, shift info, weekoff_days for mapping) ---------- */
$empSql = "
    SELECT e.id, e.emp_code, e.name, desig.designation_name, e.shift_id, e.weekoff_days,
           s.start_time, s.end_time, s.late_mark_after, s.half_day_after
    FROM employees e
    LEFT JOIN designations desig ON desig.id = e.designation_id
    LEFT JOIN shifts s ON s.id = e.shift_id
    ORDER BY e.name ASC
";
$empRes = $con->query($empSql);

$employees      = [];
$empByLogUserId = []; // key = attendance_logs.user_id, value = employee_id
$empShifts      = []; // key = employee_id, value = shift data
$empWeekOffs    = []; // key = employee_id, value = weekoff_days string

if ($empRes && $empRes->num_rows > 0) {
    while ($row = $empRes->fetch_assoc()) {
        // Example: EMP001 -> 1
        $num = (int) filter_var($row['emp_code'] ?? '', FILTER_SANITIZE_NUMBER_INT);
        if ($num <= 0) {
            $num = (int)$row['id']; // fallback
        }
        $row['log_user_id']    = $num;
        $empId = (int)$row['id'];
        $employees[]           = $row;
        $empByLogUserId[$num]  = $empId;
        
        // Store shift info
        if ($row['shift_id']) {
            $empShifts[$empId] = [
                'start_time' => $row['start_time'],
                'end_time' => $row['end_time'],
                'late_mark_after' => (int)($row['late_mark_after'] ?? 30), // minutes
                'half_day_after' => (int)($row['half_day_after'] ?? 270) // minutes
            ];
        }
        
        // Store weekoff days
        if (!empty($row['weekoff_days'])) {
            $empWeekOffs[$empId] = $row['weekoff_days']; // e.g., "Wednesday" or "Saturday,Sunday"
        }
    }
}

/* ---------- Fetch Holidays for this month ---------- */
$holidaysMap = []; // [date] = holiday_name (e.g., ['2025-12-25' => 'Christmas'])
$startDate = sprintf('%04d-%02d-01', $year, $month);
$endDate = date('Y-m-t', strtotime($startDate));

// Check if holidays table exists
$tableCheck = $con->query("SHOW TABLES LIKE 'holidays'");
if ($tableCheck && $tableCheck->num_rows > 0) {
    $holidaySql = "SELECT holiday_date, holiday_name FROM holidays WHERE holiday_date BETWEEN ? AND ?";
    $stmtHoliday = $con->prepare($holidaySql);
    if ($stmtHoliday) {
        $stmtHoliday->bind_param("ss", $startDate, $endDate);
        $stmtHoliday->execute();
        $holidayRes = $stmtHoliday->get_result();
        while ($holiday = $holidayRes->fetch_assoc()) {
            $holidaysMap[$holiday['holiday_date']] = $holiday['holiday_name'];
        }
        $stmtHoliday->close();
    }
}

/* ---------- Fetch Attendance Logs for this month ---------- */
$attendanceMap = []; // [employee_id][day] = ['logs'=>[], 'status'=>'P']

if (!empty($empByLogUserId)) {
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

/* ---------- Function to Determine Attendance Status ---------- */
function determineAttendanceStatus($dayData, $date, $shiftInfo = null, $weekoffDays = null, $holidayName = null) {
    // Check if date is in the future
    $today = date('Y-m-d');
    $isFuture = strtotime($date) > strtotime($today);
    
    // Check for Holiday FIRST (highest priority - overrides weekoff)
    if ($holidayName) {
        // If employee has logs on holiday, show as holiday with present indicator
        if ($dayData && !empty($dayData['logs'])) {
            $logCount = count($dayData['logs']);
            $firstIn = null;
            foreach ($dayData['logs'] as $log) {
                if (strtolower($log['type']) === 'in') {
                    $firstIn = $log;
                    break;
                }
            }
            $tooltip = "Holiday: {$holidayName} ({$logCount} punches)";
            if ($firstIn && !empty($firstIn['working_from'])) {
                $tooltip .= " · " . ucfirst($firstIn['working_from']);
            }
            return ['status' => 'H', 'tooltip' => $tooltip];
        }
        // No logs on holiday
        return ['status' => 'H', 'tooltip' => "Holiday: {$holidayName}"];
    }
    
    // Check for Week Off (after holiday check) - this is the second priority
    if ($weekoffDays) {
        $dayName = date('l', strtotime($date)); // Full day name: Monday, Tuesday, etc.
        $weekoffArray = array_map('trim', explode(',', $weekoffDays));
        if (in_array($dayName, $weekoffArray)) {
            // If employee has logs on week off day, show as week off with present indicator
            if ($dayData && !empty($dayData['logs'])) {
                $logCount = count($dayData['logs']);
                $firstIn = null;
                foreach ($dayData['logs'] as $log) {
                    if (strtolower($log['type']) === 'in') {
                        $firstIn = $log;
                        break;
                    }
                }
                $tooltip = "Week Off ({$logCount} punches)";
                if ($firstIn && !empty($firstIn['working_from'])) {
                    $tooltip .= " · " . ucfirst($firstIn['working_from']);
                }
                return ['status' => 'WO', 'tooltip' => $tooltip];
            }
            // No logs on week off day - show as week off even for future dates
            return ['status' => 'WO', 'tooltip' => 'Week Off'];
        }
    }
    
    // If no logs, check if it's a future date
    if (!$dayData || empty($dayData['logs'])) {
        if ($isFuture) {
            return ['status' => '-', 'tooltip' => 'Future Date'];
        }
        return ['status' => 'A', 'tooltip' => 'Absent'];
    }
    
    $logs = $dayData['logs'];
    $logCount = count($logs);
    
    // Separate IN and OUT logs
    $inLogs = [];
    $outLogs = [];
    foreach ($logs as $log) {
        if (strtolower($log['type']) === 'in') {
            $inLogs[] = $log;
        } elseif (strtolower($log['type']) === 'out') {
            $outLogs[] = $log;
        }
    }
    
    // Sort by time
    usort($inLogs, function($a, $b) {
        return strtotime($a['time']) - strtotime($b['time']);
    });
    usort($outLogs, function($a, $b) {
        return strtotime($a['time']) - strtotime($b['time']);
    });
    
    $firstIn = !empty($inLogs) ? $inLogs[0] : null;
    $lastOut = !empty($outLogs) ? $outLogs[count($outLogs) - 1] : null;
    
    // Get reason from logs (ENUM: 'normal', 'lunch', 'tea', 'short_leave', 'office_leave')
    $reason = strtolower(trim($firstIn['reason'] ?? $lastOut['reason'] ?? 'normal'));
    
    // Check reason field (based on actual ENUM values)
    if ($reason === 'short_leave') {
        return ['status' => 'SL', 'tooltip' => 'Short Leave'];
    }
    
    if ($reason === 'office_leave') {
        return ['status' => 'LV', 'tooltip' => 'Leave'];
    }
    
    // Check if clock out is missing
    if ($firstIn && !$lastOut) {
        // Check if it's end of day (after shift end time + buffer)
        $currentTime = time();
        $dayEnd = strtotime($date . ' 23:59:59');
        
        // If current time is past day end, mark as didn't clock out
        if ($currentTime > $dayEnd) {
            return ['status' => 'DCO', 'tooltip' => "Didn't Clock Out"];
        }
    }
    
    // Calculate times if shift info available
    if ($shiftInfo && $firstIn) {
        $clockInTime = strtotime($firstIn['time']);
        $shiftStart = strtotime($date . ' ' . $shiftInfo['start_time']);
        $shiftEnd = strtotime($date . ' ' . $shiftInfo['end_time']);
        $lateMarkAfter = $shiftInfo['late_mark_after']; // minutes
        $halfDayAfter = $shiftInfo['half_day_after']; // minutes
        
        // Check for Late
        $lateThreshold = $shiftStart + ($lateMarkAfter * 60);
        if ($clockInTime > $lateThreshold) {
            $lateMinutes = round(($clockInTime - $shiftStart) / 60);
            return ['status' => 'L', 'tooltip' => "Late by {$lateMinutes} minutes"];
        }
        
        // Check for Early Go (if clocked out before shift end)
        if ($lastOut) {
            $clockOutTime = strtotime($lastOut['time']);
            $earlyGoThreshold = $shiftEnd - (60 * 60); // 1 hour before shift end
            
            if ($clockOutTime < $earlyGoThreshold) {
                $earlyMinutes = round(($shiftEnd - $clockOutTime) / 60);
                return ['status' => 'EG', 'tooltip' => "Early Go by {$earlyMinutes} minutes"];
            }
            
            // Check for half day (less than half_day_after minutes worked)
            // Exclude lunch/tea breaks from work duration
            $workDuration = ($clockOutTime - $clockInTime) / 60; // minutes
            
            // If reason is lunch or tea, it's a break, not half day
            if ($reason !== 'lunch' && $reason !== 'tea' && $workDuration < $halfDayAfter) {
                // Determine first or second half based on clock in time
                $midDay = strtotime($date . ' 12:00:00');
                if ($clockInTime < $midDay) {
                    return ['status' => 'FH', 'tooltip' => 'First Half'];
                } else {
                    return ['status' => 'SH', 'tooltip' => 'Second Half'];
                }
            }
        }
    }
    
    // No automatic weekend detection - weekends are only week off if set in weekoff_days
    
    // Default: Present
    $tooltip = "Present ({$logCount} punches)";
    if ($firstIn && !empty($firstIn['working_from'])) {
        $tooltip .= " · " . ucfirst($firstIn['working_from']);
    }
    if ($reason && $reason !== 'normal') {
        $tooltip .= " · " . ucfirst(str_replace('_', ' ', $reason));
    }
    
    return ['status' => 'P', 'tooltip' => $tooltip];
}
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
    gap:12px;
    flex-wrap:wrap;
    margin-bottom:16px;
    background:#fff;
    padding:12px 16px;
    border-radius:12px;
    border:1px solid #e3e3e3;
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
.att-pill-icon{
    width:14px;height:14px;
    display:flex;
    align-items:center;
    justify-content:center;
    font-size:10px;
}
.att-pill-dot{
    width:12px;height:12px;border-radius:50%;
}
.dot-present{background:#16a34a;}
.dot-absent{background:#dc2626;}
.dot-late{background:#2563eb;}
.dot-earlygo{background:#ea580c;}
.dot-holiday{background:#ec4899;}
.dot-leave{background:#9333ea;}
.dot-shortleave{background:#7c3aed;}
.dot-weekoff{background:#06b6d4;}
.dot-firsthalf{background:#f97316;}
.dot-secondhalf{background:#f97316;}
.dot-autoclockout{background:#6b7280;}
.dot-didntclockout{background:#6b7280;}

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
    font-size:12px;
    font-weight:bold;
    display:flex;
    justify-content:center;
    align-items:center;
    margin:auto;
    position:relative;
    line-height:1;
}
.att-P { background:#dcfce7; color:#166534; }
.att-A { background:#fee2e2; color:#b91c1c; }
.att-L { background:#dbeafe; color:#1e40af; } /* Late */
.att-EG { background:#fed7aa; color:#c2410c; } /* Early Go */
.att-H { background:#fce7f3; color:#be185d; } /* Holiday */
.att-LV { background:#f3e8ff; color:#6b21a8; } /* Leave */
.att-SL { background:#ede9fe; color:#5b21b6; } /* Short Leave */
.att-WO { background:#cffafe; color:#0e7490; } /* Week Off */
.att-FH { background:#fed7aa; color:#c2410c; } /* First Half */
.att-SH { background:#fed7aa; color:#c2410c; } /* Second Half */
.att-ACO { background:#f3f4f6; color:#374151; } /* Auto Clock Out */
.att-DCO { background:#f3f4f6; color:#374151; } /* Didn't Clock Out */
.att-future { background:#ffffff; color:#9ca3af; border:1px solid #e5e7eb; } /* Future Date */

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
        <div class="att-pill" style="color:#16a34a;">
            <span class="att-pill-dot dot-present"></span> Present
        </div>
        <div class="att-pill" style="color:#dc2626;">
            <span class="att-pill-dot dot-absent"></span> Absent
        </div>
        <div class="att-pill" style="color:#2563eb;">
            <span class="att-pill-icon">⏰</span> Late
        </div>
        <div class="att-pill" style="color:#ea580c;">
            <span class="att-pill-icon">↓</span> Early Go
        </div>
        <div class="att-pill" style="color:#ec4899;">
            <span class="att-pill-icon">🎉</span> Holiday
        </div>
        <div class="att-pill" style="color:#9333ea;">
            <span class="att-pill-icon">✈</span> Leave
        </div>
        <div class="att-pill" style="color:#7c3aed;">
            <span class="att-pill-icon">🏷</span> Short Leave
        </div>
        <div class="att-pill" style="color:#06b6d4;">
            <span class="att-pill-icon">📅</span> Week Off
        </div>
        <div class="att-pill" style="color:#f97316;">
            <span class="att-pill-icon">☀↑</span> First Half
        </div>
        <div class="att-pill" style="color:#f97316;">
            <span class="att-pill-icon">☀↓</span> Second Half
        </div>
        <div class="att-pill" style="color:#6b7280;">
            <span class="att-pill-icon">⚙</span> Auto Clock Out
        </div>
        <div class="att-pill" style="color:#6b7280;">
            <span class="att-pill-icon">?</span> Didn't Clock Out
        </div>
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
                            $currentDate = sprintf('%04d-%02d-%02d', $year, $month, $d);
                            $dayData = $attendanceMap[$empId][$d] ?? null;
                            
                            // Get shift info and weekoff days for this employee
                            $shiftInfo = $empShifts[$empId] ?? null;
                            $weekoffDays = $empWeekOffs[$empId] ?? null;
                            
                            // Check if this date is a holiday
                            $holidayName = $holidaysMap[$currentDate] ?? null;
                            
                            // Determine status using the function (holiday overrides weekoff)
                            $statusResult = determineAttendanceStatus($dayData, $currentDate, $shiftInfo, $weekoffDays, $holidayName);
                            $status = $statusResult['status'];
                            $tooltip = $statusResult['tooltip'];
                            
                            // Count present days (excluding holidays, week offs, leaves)
                            if (in_array($status, ['P', 'L', 'EG', 'FH', 'SH', 'ACO', 'DCO'])) {
                                $presentCount++;
                            }
                            
                            // Status display mapping
                            $statusDisplay = [
                                'P' => '✓',
                                'A' => '✗',
                                'L' => '⏰',
                                'EG' => '↓',
                                'H' => '🎉',
                                'LV' => '✈',
                                'SL' => '🏷',
                                'WO' => '📅',
                                'FH' => '☀↑',
                                'SH' => '☀↓',
                                'ACO' => '⚙',
                                'DCO' => '?',
                                '-' => '-'
                            ];
                            
                            $display = $statusDisplay[$status] ?? '?';
                        ?>
                        <td>
                            <div class="att-cell-wrapper att-clickable" 
                                 data-emp-id="<?php echo $empId; ?>"
                                 data-emp-name="<?php echo htmlspecialchars($emp['name']); ?>"
                                 data-emp-role="<?php echo htmlspecialchars($emp['designation_name'] ?? ''); ?>"
                                 data-date="<?php echo $currentDate; ?>"
                                 style="cursor: pointer;">
                                <div class="att-badge att-<?php echo $status === '-' ? 'future' : $status; ?>">
                                    <?php echo $display; ?>
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

</div>
