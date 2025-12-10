<?php
// employees_list.php
include 'db.php';

// --- Fetch employee list with department + shift ---
$sql = "
SELECT e.*,
       d.department_name,
       s.shift_name,
       s.start_time,
       s.end_time
FROM employees e
LEFT JOIN departments d ON d.id = e.department_id
LEFT JOIN shifts s      ON s.id = e.shift_id
ORDER BY e.id DESC
";

$result = $con->query($sql);
?>

<!-- Breadcrumb + title -->
<div class="mb-2">
  <small class="text-muted">Dashboard &gt; Employees</small>
</div>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h1 class="section-title mb-0">Employee Management</h1>
</div>

<!-- Search + Add Employee row -->
<div class="d-flex justify-content-between align-items-center mb-3">
  <div class="search-wrapper flex-grow-1 me-3">
    <span>🔍</span>
    <input type="text" class="form-control search-input" placeholder="Search..." />
  </div>

  <a href="add_employee.php" class="btn btn-pill btn-add">
    + Add Employee
  </a>
</div>

<!-- Main card with table -->
<div class="card card-main">
  <div class="card-header card-main-header d-flex justify-content-between align-items-center">
    <span class="fw-semibold">Employee List</span>
    <small class="text-muted">
      Total: <?php echo $result ? $result->num_rows : 0; ?>
    </small>
  </div>

  <!-- Delete Confirm Modal -->
  <div class="modal fade" id="deleteConfirmModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">

        <div class="modal-header border-0 pb-0">
          <h5 class="modal-title">Delete Employee</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>

        <div class="modal-body">
          <p class="mb-0">
            Are you sure you want to delete this employee?
            <br>
            <small class="text-muted">This action cannot be undone.</small>
          </p>
        </div>

        <div class="modal-footer border-0 pt-0">
          <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="button" class="btn btn-sm btn-danger" id="confirmDeleteBtn">
            Delete
          </button>
        </div>

      </div>
    </div>
  </div>

  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead>
          <tr class="text-nowrap">
            <th>#</th>
            <th>Emp Code</th>
            <th>Name</th>
            <th>Department</th>
            <th>Shift</th>
            <th>Status</th>
            <th style="width: 150px;" class="text-end">Action</th>
          </tr>
        </thead>

        <tbody>
        <?php
        $i = 1;
        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {

                $department = $row["department_name"] ?? "-";

                $shiftName = "-";
                if (!empty($row['shift_name'])) {
                    $startDisp = $row['start_time'] ? date('h:i A', strtotime($row['start_time'])) : "";
                    $endDisp   = $row['end_time']   ? date('h:i A', strtotime($row['end_time']))   : "";
                    $shiftName = $row['shift_name'] .
                                 (($startDisp && $endDisp) ? " ($startDisp – $endDisp)" : "");
                }

                $isActive  = (int)$row['status'] === 1;
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

            <td><?php echo htmlspecialchars($department); ?></td>

            <td><?php echo htmlspecialchars($shiftName); ?></td>

            <!-- STATUS toggle -->
            <td>
              <label class="switch mb-0">
                <input
                  type="checkbox"
                  class="status-toggle"
                  data-id="<?php echo $row['id']; ?>"
                  <?php echo $isActive ? 'checked' : ''; ?>
                >
                <span class="slider"></span>
              </label>
            </td>

            <!-- Action 3-dots -->
            <td class="text-end text-nowrap">
              <div class="dropdown">
                <button class="btn btn-sm btn-light border-0"
                        type="button"
                        data-bs-toggle="dropdown"
                        data-bs-display="static"
                        aria-expanded="false">
                  &#x22EE;
                </button>

                <ul class="dropdown-menu dropdown-menu-end">
                  <li>
                    <a class="dropdown-item"
                       href="edit_employee.php?id=<?php echo $row['id']; ?>">
                      Edit
                    </a>
                  </li>

                  <li>
                    <a href="#"
                       class="dropdown-item text-danger delete-employee"
                       data-id="<?php echo $row['id']; ?>">
                      Delete
                    </a>
                  </li>
                </ul>
              </div>
            </td>

          </tr>
        <?php
            }
        } else {
        ?>
          <tr>
            <td colspan="7" class="text-center py-4 text-muted">
              No employees found. Please add one.
            </td>
          </tr>
        <?php } ?>
        </tbody>

      </table>
    </div>
  </div>
</div>
