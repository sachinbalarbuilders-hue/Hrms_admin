<?php
// add_employee.php
include 'db.php';

// ---------- Helper: Generate Next Employee Code ----------
function generateEmpCode(mysqli $con): string {
    $res = $con->query("SELECT MAX(id) AS max_id FROM employees");
    $row = $res ? $res->fetch_assoc() : null;
    $nextId = ($row && $row['max_id']) ? ((int)$row['max_id'] + 1) : 1;

    return 'EMP' . str_pad($nextId, 3, '0', STR_PAD_LEFT); // EMP001, EMP002...
}

// ---------- Fetch Dropdown Data (Departments, Designations, Shifts) ----------
$deptRes = $con->query("SELECT id, department_name FROM departments ORDER BY department_name ASC");
$desigRes = $con->query("
    SELECT d.id, d.designation_name, dept.department_name 
    FROM designations d
    JOIN departments dept ON dept.id = d.department_id
    ORDER BY dept.department_name, d.designation_name
");

// Shifts dropdown ke liye
$shiftRes = $con->query("SELECT * FROM shifts ORDER BY shift_name ASC");

// Next emp code for display in form (readonly)
$displayEmpCode = generateEmpCode($con);

// Errors array (global scope me)
$errors = [];

// ---------- Handle Form Submit ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name           = trim($_POST['name'] ?? '');
    $mobile         = trim($_POST['mobile'] ?? '');
    $email          = trim($_POST['email'] ?? '');
    $dob            = $_POST['dob'] ?? null;
    $department_id  = (int)($_POST['department_id'] ?? 0);
    $designation_id = (int)($_POST['designation_id'] ?? 0);
    $shift_id       = (int)($_POST['shift_id'] ?? 0);
    $joining_date   = $_POST['joining_date'] ?? null;

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
        $emp_code = generateEmpCode($con);

        // NOTE: shift_name + device_id hata diya, ab sirf shift_id store kar rahe hain
        $sql = "INSERT INTO employees 
            (emp_code, name, mobile, email, dob, department_id, designation_id, shift_id, joining_date, status)
            VALUES (?,?,?,?,?,?,?,?,?,1)";

        $stmt = $con->prepare($sql);
        if ($stmt) {
            $stmt->bind_param(
                "ssssiiiss",
                $emp_code,
                $name,
                $mobile,
                $email,
                $dob,
                $department_id,
                $designation_id,
                $shift_id,
                $joining_date
            );

            if ($stmt->execute()) {
                echo "<script>alert('Employee added successfully.');window.location='employees.php';</script>";
                exit;
            } else {
                $errors[] = "Database error: " . $con->error;
            }
        } else {
            $errors[] = "Failed to prepare query: " . $con->error;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Add Employee</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>

<div class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="mb-0">Add Employee</h3>
    <a href="employees.php" class="btn btn-outline-secondary">Back to List</a>
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
      <form method="POST" action="add_employee.php">
        <div class="row g-3">
          <!-- Emp Code (display only) -->
          <div class="col-md-3">
            <label class="form-label">Employee Code</label>
            <input type="text" class="form-control" value="<?php echo htmlspecialchars($displayEmpCode); ?>" disabled>
            <div class="form-text">Auto generated</div>
          </div>

          <div class="col-md-5">
            <label class="form-label">Full Name</label>
            <input type="text" name="name" class="form-control" required
                   value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>">
          </div>

          <div class="col-md-4">
            <label class="form-label">Mobile</label>
            <input type="text" name="mobile" class="form-control"
                   value="<?php echo htmlspecialchars($_POST['mobile'] ?? ''); ?>">
          </div>

          <div class="col-md-4">
            <label class="form-label">Email</label>
            <input type="email" name="email" class="form-control"
                   value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
          </div>

          <div class="col-md-4">
            <label class="form-label">Date of Birth</label>
            <input type="date" name="dob" class="form-control" required
                   value="<?php echo htmlspecialchars($_POST['dob'] ?? ''); ?>">
          </div>

          <div class="col-md-4">
            <label class="form-label">Joining Date</label>
            <input type="date" name="joining_date" class="form-control" required
                   value="<?php echo htmlspecialchars($_POST['joining_date'] ?? ''); ?>">
          </div>

          <div class="col-md-6">
            <label class="form-label">Department</label>
            <select name="department_id" class="form-select" required>
              <option value="">Select Department</option>
              <?php
              mysqli_data_seek($deptRes, 0);
              while ($d = $deptRes->fetch_assoc()) {
                  $selected = (isset($_POST['department_id']) && $_POST['department_id'] == $d['id']) ? 'selected' : '';
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
                  $text = $r['designation_name'] . " (" . $r['department_name'] . ")";
                  $selected = (isset($_POST['designation_id']) && $_POST['designation_id'] == $r['id']) ? 'selected' : '';
              ?>
                <option value="<?php echo $r['id']; ?>" <?php echo $selected; ?>>
                  <?php echo htmlspecialchars($text); ?>
                </option>
              <?php } ?>
            </select>
            <div class="form-text">Later we can make this dependent on department with AJAX.</div>
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
                      $selected  = (isset($_POST['shift_id']) && $_POST['shift_id'] == $s['id']) ? 'selected' : '';
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

        </div>

        <div class="mt-4">
          <button type="submit" class="btn btn-primary">Save Employee</button>
          <a href="employees.php" class="btn btn-secondary ms-2">Cancel</a>
        </div>
      </form>
    </div>
  </div>
</div>

</body>
</html>
