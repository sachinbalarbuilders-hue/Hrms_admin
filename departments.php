<?php
include 'db.php'; // yaha tumhara mysqli connection hai: $con

// --------- INSERT / UPDATE HANDLE ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $dept_name = trim($_POST['department_name'] ?? '');
    $dept_id   = $_POST['id'] ?? '';

    if ($dept_name !== '') {
        if ($dept_id == '') {
            // NEW INSERT
            $stmt = $con->prepare("INSERT INTO departments (department_name) VALUES (?)");
            $stmt->bind_param("s", $dept_name);
            $stmt->execute();
        } else {
            // UPDATE
            $stmt = $con->prepare("UPDATE departments SET department_name=? WHERE id=?");
            $stmt->bind_param("si", $dept_name, $dept_id);
            $stmt->execute();
        }
    }
    header("Location: departments.php");
    exit;
}

// --------- DELETE (SOFT DEACTIVATE NAHI CHAHIYE TO HARD DELETE) ----------
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $con->query("DELETE FROM departments WHERE id = $id");
    header("Location: departments.php");
    exit;
}

// EDIT ke liye record laana
$editDept = null;
if (isset($_GET['edit'])) {
    $id = (int)$_GET['edit'];
    $res = $con->query("SELECT * FROM departments WHERE id = $id");
    $editDept = $res->fetch_assoc();
}

// List data
$list = $con->query("SELECT * FROM departments ORDER BY department_name ASC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Departments</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="mb-0">Departments</h3>
  </div>

  <!-- ADD / EDIT FORM -->
  <div class="card mb-4">
    <div class="card-header">
      <?php echo $editDept ? 'Edit Department' : 'Add Department'; ?>
    </div>
    <div class="card-body">
      <form method="POST" action="departments.php">
        <input type="hidden" name="id" value="<?php echo $editDept['id'] ?? ''; ?>">
        <div class="mb-3">
          <label class="form-label">Department Name</label>
          <input type="text"
                 name="department_name"
                 class="form-control"
                 required
                 value="<?php echo htmlspecialchars($editDept['department_name'] ?? ''); ?>">
        </div>
        <button type="submit" class="btn btn-primary">
          <?php echo $editDept ? 'Update' : 'Save'; ?>
        </button>
        <?php if ($editDept) { ?>
          <a href="departments.php" class="btn btn-secondary ms-2">Cancel</a>
        <?php } ?>
      </form>
    </div>
  </div>

  <!-- LIST TABLE -->
  <div class="card">
    <div class="card-header">Department List</div>
    <div class="card-body p-0">
      <table class="table table-striped mb-0">
        <thead class="table-light">
          <tr>
            <th>#</th>
            <th>Department Name</th>
            <th>Created</th>
            <th class="text-end">Action</th>
          </tr>
        </thead>
        <tbody>
        <?php
        $i = 1;
        while ($row = $list->fetch_assoc()) { ?>
          <tr>
            <td><?php echo $i++; ?></td>
            <td><?php echo htmlspecialchars($row['department_name']); ?></td>
            <td><?php echo date('d-m-Y', strtotime($row['created_at'])); ?></td>
            <td class="text-end">
              <a href="departments.php?edit=<?php echo $row['id']; ?>" class="btn btn-sm btn-outline-primary">Edit</a>
              <a href="departments.php?delete=<?php echo $row['id']; ?>"
                 class="btn btn-sm btn-outline-danger"
                 onclick="return confirm('Delete this department?');">
                 Delete
              </a>
            </td>
          </tr>
        <?php } ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
</body>
</html>
