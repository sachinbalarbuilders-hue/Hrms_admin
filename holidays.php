<?php
include 'db.php';

// Check if holidays table exists, if not create it
$tableCheck = $con->query("SHOW TABLES LIKE 'holidays'");
if ($tableCheck->num_rows == 0) {
    // Create holidays table
    $createTable = "
        CREATE TABLE `holidays` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `holiday_name` varchar(100) NOT NULL,
          `holiday_date` date NOT NULL,
          `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
          `updated_at` timestamp NULL DEFAULT NULL,
          PRIMARY KEY (`id`),
          UNIQUE KEY `holiday_date` (`holiday_date`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
    ";
    $con->query($createTable);
}

// Kya yeh AJAX se bulaya gaya hai?
$isAjax = isset($_GET['ajax']) && $_GET['ajax'] == '1';

// INSERT / UPDATE
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $holiday_name = trim($_POST['holiday_name'] ?? '');
    $holiday_date = $_POST['holiday_date'] ?? '';
    $id           = $_POST['id'] ?? '';

    if ($holiday_name !== '' && $holiday_date !== '') {
        if ($id == '') {
            $stmt = $con->prepare("INSERT INTO holidays (holiday_name, holiday_date) VALUES (?, ?)");
            $stmt->bind_param("ss", $holiday_name, $holiday_date);
            $stmt->execute();
        } else {
            $stmt = $con->prepare("UPDATE holidays SET holiday_name=?, holiday_date=? WHERE id=?");
            $stmt->bind_param("ssi", $holiday_name, $holiday_date, $id);
            $stmt->execute();
        }
    }

    // AJAX mode: JSON return, page reload nahi
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'reload'  => 'holidays.php?ajax=1'
        ]);
        exit;
    }

    // Normal mode
    header("Location: holidays.php");
    exit;
}

// DELETE
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $con->query("DELETE FROM holidays WHERE id = $id");

    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'reload'  => 'holidays.php?ajax=1'
        ]);
        exit;
    }

    header("Location: holidays.php");
    exit;
}

// EDIT
$editRow = null;
if (isset($_GET['edit'])) {
    $id = (int)$_GET['edit'];
    $resE = $con->query("SELECT * FROM holidays WHERE id = $id");
    $editRow = $resE->fetch_assoc();
}

// List holidays ordered by date
$list = $con->query("
    SELECT * 
    FROM holidays 
    ORDER BY holiday_date DESC
");

// --------- Common render function ----------
function renderHolidaysContent($editRow, $list, $isAjax) {
?>
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="mb-0">Holidays</h3>
  </div>

  <!-- ADD / EDIT FORM -->
  <div class="card mb-4">
    <div class="card-header">
      <?php echo $editRow ? 'Edit Holiday' : 'Add Holiday'; ?>
    </div>
    <div class="card-body">
      <form method="POST" action="holidays.php" id="holidayForm">
        <input type="hidden" name="id" value="<?php echo $editRow['id'] ?? ''; ?>">

        <div class="mb-3">
          <label class="form-label">Holiday Name</label>
          <input type="text"
                 name="holiday_name"
                 class="form-control"
                 required
                 placeholder="e.g., New Year, Diwali, Christmas"
                 value="<?php echo htmlspecialchars($editRow['holiday_name'] ?? ''); ?>">
        </div>

        <div class="mb-3">
          <label class="form-label">Holiday Date</label>
          <input type="date"
                 name="holiday_date"
                 class="form-control"
                 required
                 value="<?php echo $editRow['holiday_date'] ?? ''; ?>">
        </div>

        <button type="submit" class="btn btn-primary">
          <?php echo $editRow ? 'Update' : 'Save'; ?>
        </button>
        <?php if ($editRow) { ?>
          <a href="holidays.php" class="btn btn-secondary ms-2">Cancel</a>
        <?php } ?>
      </form>
    </div>
  </div>

  <!-- LIST TABLE -->
  <div class="card">
    <div class="card-header">Holiday List</div>
    <div class="card-body p-0">
      <table class="table table-striped mb-0">
        <thead class="table-light">
          <tr>
            <th>#</th>
            <th>Holiday Name</th>
            <th>Date</th>
            <th>Day</th>
            <th class="text-end">Action</th>
          </tr>
        </thead>
        <tbody>
        <?php
        $i = 1;
        if ($list && $list->num_rows > 0) {
          while ($row = $list->fetch_assoc()) {
            $dayName = date('l', strtotime($row['holiday_date']));
            $dateFormatted = date('d-m-Y', strtotime($row['holiday_date']));
          ?>
          <tr>
            <td><?php echo $i++; ?></td>
            <td><?php echo htmlspecialchars($row['holiday_name']); ?></td>
            <td><?php echo $dateFormatted; ?></td>
            <td><?php echo $dayName; ?></td>
            <td class="text-end">
              <?php if ($isAjax) { ?>
                <!-- SPA mode: Edit via JS -->
                <a href="javascript:void(0)"
                   class="btn btn-sm btn-outline-primary holiday-edit"
                   data-edit-id="<?php echo $row['id']; ?>">
                   Edit
                </a>
              <?php } else { ?>
                <!-- Normal mode: direct link -->
                <a href="holidays.php?edit=<?php echo $row['id']; ?>"
                   class="btn btn-sm btn-outline-primary">Edit</a>
              <?php } ?>

              <a href="holidays.php?delete=<?php echo $row['id']; ?>"
                 class="btn btn-sm btn-outline-danger"
                 onclick="return confirm('Delete this holiday?');">
                 Delete
              </a>
            </td>
          </tr>
        <?php
          }
        } else { ?>
          <tr>
            <td colspan="5" class="text-center py-4 text-muted">
              No holidays found. Please add one.
            </td>
          </tr>
        <?php } ?>
        </tbody>
      </table>
    </div>
  </div>

  <?php if ($isAjax) { ?>
  <script>
  // Handle edit button clicks in AJAX mode
  document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.holiday-edit').forEach(function(btn) {
      btn.addEventListener('click', function() {
        const id = this.getAttribute('data-edit-id');
        loadPage('holidays.php?ajax=1&edit=' + id);
      });
    });
  });
  </script>
  <?php } ?>
<?php
} // end renderHolidaysContent

// ---------- AJAX request -> sirf inner content ----------
if ($isAjax) {
    renderHolidaysContent($editRow, $list, $isAjax);
    exit;
}

// ---------- Normal full page ----------
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Holidays</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container py-4">
  <?php renderHolidaysContent($editRow, $list, $isAjax); ?>
</div>
</body>
</html>

