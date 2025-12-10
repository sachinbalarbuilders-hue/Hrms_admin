<?php
// departments_tab.php
include 'db.php';

// Departments list
$list = $con->query("SELECT * FROM departments ORDER BY department_name ASC");
?>

<div class="mb-2">
  <small class="text-muted">Dashboard &gt; Departments</small>
</div>

<div class="d-flex justify-content-between align-items-center mb-3">
  <h1 class="section-title mb-0">Department Master</h1>

  <!-- Full page manage button -->
  <a href="departments.php" class="btn btn-pill btn-add">
    Manage Departments
  </a>
</div>

<div class="card card-main">
  <div class="card-header card-main-header d-flex justify-content-between align-items-center">
    <span class="fw-semibold">Department List</span>
    <small class="text-muted">
      Total: <?php echo $list ? $list->num_rows : 0; ?>
    </small>
  </div>

  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead>
          <tr class="text-nowrap">
            <th>#</th>
            <th>Department Name</th>
            <th>Created At</th>
          </tr>
        </thead>
        <tbody>
        <?php
        $i = 1;
        if ($list && $list->num_rows > 0) {
          while ($row = $list->fetch_assoc()) {
        ?>
          <tr>
            <td><?php echo $i++; ?></td>
            <td><?php echo htmlspecialchars($row['department_name']); ?></td>
            <td>
              <?php
                echo !empty($row['created_at'])
                  ? date('d M Y, h:i A', strtotime($row['created_at']))
                  : '-';
              ?>
            </td>
          </tr>
        <?php
          }
        } else {
        ?>
          <tr>
            <td colspan="3" class="text-center py-4 text-muted">
              No departments found. Use "Manage Departments" to add one.
            </td>
          </tr>
        <?php } ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
