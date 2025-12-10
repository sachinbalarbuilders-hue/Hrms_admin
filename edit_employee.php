<?php
// edit_employee.php
include 'db.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    header("Location: employees.php");
    exit;
}

// ---------- Helper: Fetch dropdown data ----------
$deptRes = $con->query("SELECT id, department_name FROM departments ORDER BY department_name ASC");

$desigRes = $con->query("
    SELECT d.id, d.designation_name, dept.department_name 
    FROM designations d
    JOIN departments dept ON dept.id = d.department_id
    ORDER BY dept.department_name, d.designation_name
");

$shiftRes = $con->query("SELECT * FROM shifts ORDER BY shift_name ASC");

// ---------- Fetch current employee data ----------
$stmt = $con->prepare("SELECT * FROM employees WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$empRes   = $stmt->get_result();
$employee = $empRes->fetch_assoc();

if (!$employee) {
    header("Location: employees.php");
    exit;
}

// Device ID sirf employees table se
$deviceIdVal = $employee['device_id'] ?? '';

// Errors array
$errors = [];

// Helper for prefilling form
function old($key, $default = '') {
    return isset($_POST[$key]) ? $_POST[$key] : $default;
}

// ---------- Handle Form Submit (UPDATE) ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name           = trim($_POST['name'] ?? '');
    $mobile         = trim($_POST['mobile'] ?? '');
    $email          = trim($_POST['email'] ?? '');
    $dob            = $_POST['dob'] ?? null;
    $joining_date   = $_POST['joining_date'] ?? null;
    $department_id  = (int)($_POST['department_id'] ?? 0);
    $designation_id = (int)($_POST['designation_id'] ?? 0);
    $shift_id       = (int)($_POST['shift_id'] ?? 0);
    // Weekoff days is optional - if no checkboxes selected, set to NULL
    $weekoff_days   = isset($_POST['weekoff_days']) && !empty($_POST['weekoff_days']) 
                      ? implode(',', $_POST['weekoff_days']) 
                      : null;
    $reset_device   = !empty($_POST['reset_device']); // button se 1 aayega

    // Basic validation
    if ($name === '') {
        $errors[] = "Employee name is required.";
    }
    if ($department_id <= 0) {
        $errors[] = "Please select a department.";
    }
    if ($designation_id <= 0) {
        $errors[] = "Please select a designation.";
    }
    if ($shift_id <= 0) {
        $errors[] = "Please select a shift.";
    }
    if (!$joining_date) {
        $errors[] = "Joining date is required.";
    }
    if (!$dob) {
        $errors[] = "Date of birth is required.";
    }

    if (empty($errors)) {
        if ($reset_device) {
            // ✅ Sirf employees table ka device_id reset
            $sql = "
                UPDATE employees
                SET name = ?, mobile = ?, email = ?, dob = ?, 
                    department_id = ?, designation_id = ?, shift_id = ?, weekoff_days = ?, joining_date = ?, 
                    device_id = NULL,
                    updated_at = NOW()
                WHERE id = ?
            ";
        } else {
            $sql = "
                UPDATE employees
                SET name = ?, mobile = ?, email = ?, dob = ?, 
                    department_id = ?, designation_id = ?, shift_id = ?, weekoff_days = ?, joining_date = ?, 
                    updated_at = NOW()
                WHERE id = ?
            ";
        }

        $stmtUpd = $con->prepare($sql);
        $stmtUpd->bind_param(
            "ssssiiissi",
            $name,
            $mobile,
            $email,
            $dob,
            $department_id,
            $designation_id,
            $shift_id,
            $weekoff_days,
            $joining_date,
            $id
        );

        if ($stmtUpd->execute()) {
            header("Location: employees.php?updated=1");
            exit;
        } else {
            $errors[] = "Database error while updating: " . $con->error;
        }
    }
}

// Form values
$emp_code    = $employee['emp_code'] ?? '';
$nameVal     = old('name', $employee['name'] ?? '');
$mobileVal   = old('mobile', $employee['mobile'] ?? '');
$emailVal    = old('email', $employee['email'] ?? '');
$dobVal      = old('dob', $employee['dob'] ?? '');
$joinVal     = old('joining_date', $employee['joining_date'] ?? '');
$deptCur     = old('department_id', $employee['department_id'] ?? '');
$desigCur    = old('designation_id', $employee['designation_id'] ?? '');
$shiftCur    = old('shift_id', $employee['shift_id'] ?? '');

// Week Off Days - handle both POST and existing data
$weekoffCurrent = $employee['weekoff_days'] ?? '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['weekoff_days'])) {
    $weekoffSelected = $_POST['weekoff_days'];
} else {
    // Parse existing weekoff_days string (e.g., "Wednesday" or "Saturday,Sunday")
    $weekoffSelected = !empty($weekoffCurrent) ? array_map('trim', explode(',', $weekoffCurrent)) : [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Edit Employee</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>

<div class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="mb-0">Edit Employee</h3>
    <a href="employees.php" class="btn btn-outline-secondary">← Back to List</a>
  </div>

  <?php if (!empty($errors)) { ?>
    <div class="alert alert-danger">
      <ul class="mb-0">
        <?php foreach ($errors as $e) { ?>
          <li><?php echo htmlspecialchars($e); ?></li>
        <?php } ?>
      </ul>
    </div>
  <?php } ?>

  <div class="card">
    <div class="card-body">
      <form method="POST" action="edit_employee.php?id=<?php echo $id; ?>" id="editEmployeeForm">
        <div class="row g-3">

          <!-- Emp Code (display only) -->
          <div class="col-md-3">
            <label class="form-label">Employee Code</label>
            <input type="text" class="form-control" value="<?php echo htmlspecialchars($emp_code); ?>" disabled>
            <div class="form-text">Auto generated (cannot change)</div>
          </div>

          <div class="col-md-5">
            <label class="form-label">Full Name</label>
            <input type="text" name="name" class="form-control" required
                   value="<?php echo htmlspecialchars($nameVal); ?>">
          </div>

          <div class="col-md-4">
            <label class="form-label">Mobile</label>
            <input type="text" name="mobile" class="form-control"
                   value="<?php echo htmlspecialchars($mobileVal); ?>">
          </div>

          <div class="col-md-4">
            <label class="form-label">Email</label>
            <input type="email" name="email" class="form-control"
                   value="<?php echo htmlspecialchars($emailVal); ?>">
          </div>

          <div class="col-md-4">
            <label class="form-label">Date of Birth</label>
            <input type="date" name="dob" class="form-control" required
                   value="<?php echo htmlspecialchars($dobVal); ?>">
          </div>

          <div class="col-md-4">
            <label class="form-label">Joining Date</label>
            <input type="date" name="joining_date" class="form-control" required
                   value="<?php echo htmlspecialchars($joinVal); ?>">
          </div>

          <div class="col-md-6">
            <label class="form-label">Department</label>
            <select name="department_id" class="form-select" required>
              <option value="">Select Department</option>
              <?php
              mysqli_data_seek($deptRes, 0);
              while ($d = $deptRes->fetch_assoc()) {
                  $selected = ($deptCur == $d['id']) ? 'selected' : '';
              ?>
                <option value="<?php echo $d['id']; ?>" <?php echo $selected; ?>>
                  <?php echo htmlspecialchars($d['department_name']); ?>
                </option>
              <?php } ?>
            </select>
          </div>

          <div class="col-md-6">
            <label class="form-label">Designation</label>
            <select name="designation_id" class="form-select" required>
              <option value="">Select Designation</option>
              <?php
              mysqli_data_seek($desigRes, 0);
              while ($r = $desigRes->fetch_assoc()) {
                  $text     = $r['designation_name'] . " (" . $r['department_name'] . ")";
                  $selected = ($desigCur == $r['id']) ? 'selected' : '';
              ?>
                <option value="<?php echo $r['id']; ?>" <?php echo $selected; ?>>
                  <?php echo htmlspecialchars($text); ?>
                </option>
              <?php } ?>
            </select>
          </div>

          <!-- SHIFT DROPDOWN -->
          <div class="col-md-6">
            <label class="form-label">Shift</label>
            <select name="shift_id" class="form-select" required>
              <option value="">Select Shift</option>
              <?php
              if ($shiftRes && $shiftRes->num_rows > 0) {
                  mysqli_data_seek($shiftRes, 0);
                  while ($s = $shiftRes->fetch_assoc()) {
                      $startDisp = date('h:i A', strtotime($s['start_time']));
                      $endDisp   = date('h:i A', strtotime($s['end_time']));
                      $label     = $s['shift_name'] . " ($startDisp – $endDisp)";
                      $selected  = ($shiftCur == $s['id']) ? 'selected' : '';
              ?>
                <option value="<?php echo $s['id']; ?>" <?php echo $selected; ?>>
                  <?php echo htmlspecialchars($label); ?>
                </option>
              <?php
                  }
              }
              ?>
            </select>
          </div>

          <!-- WEEK OFF DAYS -->
          <div class="col-md-6">
            <label class="form-label">Week Off Days</label>
            <div class="border rounded p-3" style="background-color: #f8f9fa;">
              <?php
              $daysOfWeek = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
              foreach ($daysOfWeek as $day) {
                  $checked = in_array($day, $weekoffSelected) ? 'checked' : '';
              ?>
                <div class="form-check form-check-inline">
                  <input class="form-check-input" type="checkbox" name="weekoff_days[]" 
                         value="<?php echo $day; ?>" id="weekoff_<?php echo strtolower($day); ?>" <?php echo $checked; ?>>
                  <label class="form-check-label" for="weekoff_<?php echo strtolower($day); ?>">
                    <?php echo $day; ?>
                  </label>
                </div>
              <?php } ?>
            </div>
            <div class="form-text">Select the days when this employee has week off</div>
          </div>

          <!-- DEVICE ID + reset button -->
          <div class="col-md-6">
            <label class="form-label">Device ID</label>
            <input type="text" class="form-control"
                   value="<?php echo $deviceIdVal ? htmlspecialchars($deviceIdVal) : 'Not registered'; ?>"
                   disabled>
            <div class="form-text">
              This comes from the mobile app. Admin cannot edit it, only reset.
            </div>

            <!-- Hidden field that JS will set to 1 when Reset pressed -->
            <input type="hidden" name="reset_device" id="resetDeviceInput" value="0">

            <button type="button"
                    class="btn btn-sm btn-outline-danger mt-2"
                    id="resetDeviceBtn">
              Reset Device ID
            </button>
          </div>

        </div>

        <div class="mt-4">
          <button type="submit" class="btn btn-primary">Save Changes</button>
          <a href="employees.php" class="btn btn-secondary ms-2">Cancel</a>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
  const resetBtn   = document.getElementById('resetDeviceBtn');
  const resetInput = document.getElementById('resetDeviceInput');
  const form       = document.getElementById('editEmployeeForm');

  if (resetBtn && resetInput && form) {
    resetBtn.addEventListener('click', function () {
      if (confirm('Are you sure you want to reset the device ID for this employee?\n\nThe app will need to register this device again.')) {
        resetInput.value = '1'; // PHP me ye true mana jayega
        form.submit();         // same form submit ho jayega
      }
    });
  }
});
</script>

</body>
</html>
