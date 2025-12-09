<?php
// employees.php
include 'db.php';

// --- Active / Inactive toggle (optional) ---
if (isset($_GET['toggle']) && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $curStatus = (int)($_GET['status'] ?? 1);
    $newStatus = $curStatus === 1 ? 0 : 1;

    $stmt = $con->prepare("UPDATE employees SET status = ?, updated_at = NOW() WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param("ii", $newStatus, $id);
        $stmt->execute();
    }
    header("Location: employees.php");
    exit;
}

// --- Fetch employee list with department, designation, shift ---
$sql = "
SELECT e.*,
       d.department_name,
       desig.designation_name,
       s.shift_name,
       s.start_time,
       s.end_time
FROM employees e
LEFT JOIN departments d   ON d.id = e.department_id
LEFT JOIN designations desig ON desig.id = e.designation_id
LEFT JOIN shifts s        ON s.id = e.shift_id
ORDER BY e.id DESC
";

$result = $con->query($sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Employees</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>

<div class="container py-4">

  <div class="d-flex justify-content-between align-items-center mb-3">
    <div>
      <h3 class="mb-0">Employees</h3>
      <small class="text-muted">Manage all registered employees</small>
    </div>
    <div>
      <a href="shifts.php" class="btn btn-outline-secondary me-2">Shift Master</a>
      <a href="add_employee.php" class="btn btn-primary">+ Add Employee</a>
    </div>
  </div>

  <div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
      <span class="fw-semibold">Employee List</span>
      <small class="text-muted">
        Total: <?php echo $result ? $result->num_rows : 0; ?>
      </small>
    </div>

    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead class="table-light">
            <tr class="text-nowrap">
              <th>#</th>
              <th>Emp Code</th>
              <th>Name</th>
              <th>Department / Designation</th>
              <th>Shift</th>
              <th>Mobile</th>
              <th>Email</th>
              <th>Joining</th>
              <th>Status</th>
              <th style="width: 150px;" class="text-end">Action</th>
            </tr>
          </thead>
          <tbody>
          <?php
          $i = 1;
          if ($result && $result->num_rows > 0) {
              while ($row = $result->fetch_assoc()) {
                  $dept  = $row['department_name'] ?? '-';
                  $desig = $row['designation_name'] ?? '-';

                  $shiftName = $row['shift_name'] ?? '-';
                  if (!empty($row['shift_name']) && !empty($row['start_time']) && !empty($row['end_time'])) {
                      $startDisp = date('h:i A', strtotime($row['start_time']));
                      $endDisp   = date('h:i A', strtotime($row['end_time']));
                      $shiftName = $row['shift_name'] . " ($startDisp – $endDisp)";
                  }

                  $joining = $row['joining_date']
                      ? date('d-m-Y', strtotime($row['joining_date']))
                      : '-';

                  $statusText  = ((int)$row['status'] === 1) ? 'Active' : 'Inactive';
                  $statusClass = ((int)$row['status'] === 1) ? 'success' : 'secondary';

                  $updatedTs = $row['updated_at'] ?: $row['created_at'];
                  $updated   = $updatedTs ? date('d M Y, h:i A', strtotime($updatedTs)) : '-';
          ?>
            <tr class="text-nowrap">
              <td><?php echo $i++; ?></td>
              <td><?php echo htmlspecialchars($row['emp_code'] ?? ''); ?></td>
              <td>
                <div class="fw-semibold"><?php echo htmlspecialchars($row['name']); ?></div>
                <div class="small text-muted">Updated: <?php echo $updated; ?></div>
              </td>
              <td>
                <div><?php echo htmlspecialchars($dept); ?></div>
                <div class="small text-muted"><?php echo htmlspecialchars($desig); ?></div>
              </td>
              <td><?php echo htmlspecialchars($shiftName); ?></td>
              <td><?php echo htmlspecialchars($row['mobile'] ?? ''); ?></td>
              <td><?php echo htmlspecialchars($row['email'] ?? ''); ?></td>
              <td><?php echo $joining; ?></td>
              <td>
                <span class="badge bg-<?php echo $statusClass; ?>">
                  <?php echo $statusText; ?>
                </span>
              </td>
              <td class="text-end text-nowrap">
                <!-- Edit page baad me banayenge, abhi placeholder -->
                <!-- <a href="edit_employee.php?id=<?php echo $row['id']; ?>" class="btn btn-sm btn-outline-primary me-1">Edit</a> -->

                <a href="employees.php?toggle=1&id=<?php echo $row['id']; ?>&status=<?php echo (int)$row['status']; ?>"
                   class="btn btn-sm btn-outline-warning me-1">
                  <?php echo ((int)$row['status'] === 1) ? 'Deactivate' : 'Activate'; ?>
                </a>
              </td>
            </tr>
          <?php
              }
          } else {
          ?>
            <tr>
              <td colspan="10" class="text-center py-4 text-muted">
                No employees found. Please add one.
              </td>
            </tr>
          <?php } ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

</div>

</body>
</html>
