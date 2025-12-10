<?php
include 'db.php';

// Kya yeh AJAX se bulaya gaya hai?
$isAjax = isset($_GET['ajax']) && $_GET['ajax'] == '1';

// All departments for dropdown
$deptRes = $con->query("SELECT id, department_name FROM departments ORDER BY department_name");

// INSERT / UPDATE
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $designation_name = trim($_POST['designation_name'] ?? '');
    $department_id    = (int)($_POST['department_id'] ?? 0);
    $id               = $_POST['id'] ?? '';

    if ($designation_name !== '' && $department_id > 0) {
        if ($id == '') {
            $stmt = $con->prepare("INSERT INTO designations (department_id, designation_name) VALUES (?, ?)");
            $stmt->bind_param("is", $department_id, $designation_name);
            $stmt->execute();
        } else {
            $stmt = $con->prepare("UPDATE designations SET department_id=?, designation_name=? WHERE id=?");
            $stmt->bind_param("isi", $department_id, $designation_name, $id);
            $stmt->execute();
        }
    }

    // AJAX mode: JSON return, page reload nahi
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'reload'  => 'designations.php?ajax=1'
        ]);
        exit;
    }

    // Normal mode
    header("Location: designations.php");
    exit;
}

// DELETE
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $con->query("DELETE FROM designations WHERE id = $id");

    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'reload'  => 'designations.php?ajax=1'
        ]);
        exit;
    }

    header("Location: designations.php");
    exit;
}

// EDIT
$editRow = null;
if (isset($_GET['edit'])) {
    $id = (int)$_GET['edit'];
    $resE = $con->query("SELECT * FROM designations WHERE id = $id");
    $editRow = $resE->fetch_assoc();
}

// List with department name
$list = $con->query("
    SELECT dsg.*, dept.department_name 
    FROM designations dsg
    JOIN departments dept ON dsg.department_id = dept.id
    ORDER BY dept.department_name, dsg.designation_name
");

// --------- Common render function ----------
function renderDesignationsContent($deptRes, $editRow, $list, $isAjax) {
?>
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="mb-0">Designations</h3>
  </div>

  <!-- ADD / EDIT FORM -->
  <div class="card mb-4">
    <div class="card-header">
      <?php echo $editRow ? 'Edit Designation' : 'Add Designation'; ?>
    </div>
    <div class="card-body">
      <form method="POST" action="designations.php">
        <input type="hidden" name="id" value="<?php echo $editRow['id'] ?? ''; ?>">

        <div class="mb-3">
          <label class="form-label">Department</label>
          <select name="department_id" class="form-select" required>
            <option value="">Select Department</option>
            <?php
            mysqli_data_seek($deptRes, 0);
            while ($d = $deptRes->fetch_assoc()) {
              $selected = ($editRow && $editRow['department_id'] == $d['id']) ? 'selected' : '';
            ?>
              <option value="<?php echo $d['id']; ?>" <?php echo $selected; ?>>
                <?php echo htmlspecialchars($d['department_name']); ?>
              </option>
            <?php } ?>
          </select>
        </div>

        <div class="mb-3">
          <label class="form-label">Designation Name</label>
          <input type="text"
                 name="designation_name"
                 class="form-control"
                 required
                 value="<?php echo htmlspecialchars($editRow['designation_name'] ?? ''); ?>">
        </div>

        <button type="submit" class="btn btn-primary">
          <?php echo $editRow ? 'Update' : 'Save'; ?>
        </button>
        <?php if ($editRow) { ?>
          <a href="designations.php" class="btn btn-secondary ms-2">Cancel</a>
        <?php } ?>
      </form>
    </div>
  </div>

  <!-- LIST TABLE -->
  <div class="card">
    <div class="card-header">Designation List</div>
    <div class="card-body p-0">
      <table class="table table-striped mb-0">
        <thead class="table-light">
          <tr>
            <th>#</th>
            <th>Department</th>
            <th>Designation</th>
            <th>Created</th>
            <th class="text-end">Action</th>
          </tr>
        </thead>
        <tbody>
        <?php
        $i = 1;
        if ($list && $list->num_rows > 0) {
          while ($row = $list->fetch_assoc()) { ?>
          <tr>
            <td><?php echo $i++; ?></td>
            <td><?php echo htmlspecialchars($row['department_name']); ?></td>
            <td><?php echo htmlspecialchars($row['designation_name']); ?></td>
            <td>
              <?php
                echo !empty($row['created_at'])
                  ? date('d-m-Y', strtotime($row['created_at']))
                  : '-';
              ?>
            </td>
            <td class="text-end">
              <?php if ($isAjax) { ?>
                <!-- SPA mode: Edit via JS -->
                <a href="javascript:void(0)"
                   class="btn btn-sm btn-outline-primary desig-edit"
                   data-edit-id="<?php echo $row['id']; ?>">
                   Edit
                </a>
              <?php } else { ?>
                <!-- Normal mode: direct link -->
                <a href="designations.php?edit=<?php echo $row['id']; ?>"
                   class="btn btn-sm btn-outline-primary">Edit</a>
              <?php } ?>

              <a href="designations.php?delete=<?php echo $row['id']; ?>"
                 class="btn btn-sm btn-outline-danger"
                 onclick="return confirm('Delete this designation?');">
                 Delete
              </a>
            </td>
          </tr>
        <?php
          }
        } else { ?>
          <tr>
            <td colspan="5" class="text-center py-4 text-muted">
              No designations found. Please add one.
            </td>
          </tr>
        <?php } ?>
        </tbody>
      </table>
    </div>
  </div>
<?php
} // end renderDesignationsContent

// ---------- AJAX request -> sirf inner content ----------
if ($isAjax) {
    renderDesignationsContent($deptRes, $editRow, $list, $isAjax);
    exit;
}

// ---------- Normal full page ----------
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Designations</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container py-4">
  <?php renderDesignationsContent($deptRes, $editRow, $list, $isAjax); ?>
</div>
</body>
</html>
