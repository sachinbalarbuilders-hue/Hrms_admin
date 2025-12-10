<?php
// employees.php  (main dashboard wrapper)
include 'db.php';

// flags for toast messages (delete / update)
$deleted = isset($_GET['deleted']) ? 1 : 0;
$updated = isset($_GET['updated']) ? 1 : 0;

/* ------- Departments & Employees for Mark Attendance Modal ------- */
$deptRes = $con->query("SELECT id, department_name FROM departments ORDER BY department_name ASC");

$employeesForJs = [];
$empRes = $con->query("SELECT id, name, department_id FROM employees ORDER BY name ASC");
if ($empRes && $empRes->num_rows > 0) {
  while ($e = $empRes->fetch_assoc()) {
    $employeesForJs[] = [
      'id'            => (int)$e['id'],
      'name'          => $e['name'],
      'department_id' => (int)$e['department_id'],
    ];
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Employees</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

  <style>
    body {
      background: #f3f5fb;
      font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
    }

    .page-wrapper {
      max-width: 1200px;
    }

    /* top tabs nav */
    .top-nav-wrapper {
      background: #ffffff;
      border-radius: 999px;
      padding: 6px 10px;
      box-shadow: 0 4px 14px rgba(15, 23, 42, 0.08);
      display: inline-flex;
      gap: 16px;
      align-items: center;
    }
    .top-nav-pill {
      padding: 8px 20px;
      border-radius: 999px;
      border: none;
      background: transparent;
      display: inline-flex;
      align-items: center;
      gap: 8px;
      font-size: 0.9rem;
      font-weight: 500;
      color: #4b5563;
      cursor: pointer;
      text-decoration: none;
    }
    .top-nav-pill.active {
      background: #111827;
      color: #ffffff;
    }
    .top-nav-pill span.icon {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      width: 22px;
      height: 22px;
      border-radius: 999px;
      background: rgba(255,255,255,0.12);
      font-size: 0.9rem;
    }

    .section-title {
      font-size: 1.8rem;
      font-weight: 700;
      letter-spacing: 0.02em;
    }

    .btn-round-icon {
      width: 40px;
      height: 40px;
      border-radius: 999px;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      border: none;
      background: #111827;
      color: #fff;
      font-size: 1.1rem;
      text-decoration: none;
    }

    /* toast */
    #statusAlertWrapper { z-index: 1080; }

    /* Loader overlay */
    #loaderOverlay {
      position: fixed;
      inset: 0;
      background: rgba(15,23,42,0.08);
      display: flex;
      align-items: center;
      justify-content: center;
      z-index: 2000;
    }
    #loaderOverlay.d-none {
      display: none;
    }
    .loader-spinner {
      width: 44px;
      height: 44px;
      border-radius: 50%;
      border: 4px solid #e5e7eb;
      border-top-color: #111827;
      animation: spin 0.7s linear infinite;
    }
    @keyframes spin {
      to { transform: rotate(360deg); }
    }

    /* Fade-in animation for content */
    .fade-in {
      animation: fadeIn .25s ease-out;
    }
    @keyframes fadeIn {
      from { opacity: 0; transform: translateY(4px); }
      to   { opacity: 1; transform: translateY(0); }
    }

    /* iOS switch (used inside employees_list) */
    .switch {
      position: relative;
      display: inline-block;
      width: 52px;
      height: 28px;
    }
    .switch input {
      opacity: 0;
      width: 0;
      height: 0;
    }
    .slider {
      position: absolute;
      cursor: pointer;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background-color: #d1d5db;
      transition: .3s;
      border-radius: 999px;
    }
    .slider:before {
      position: absolute;
      content: "";
      height: 22px;
      width: 22px;
      left: 3px;
      bottom: 3px;
      background-color: white;
      transition: .3s;
      border-radius: 50%;
    }
    input:checked + .slider {
      background-color: #000;
    }
    input:checked + .slider:before {
      transform: translateX(24px);
    }

    /* dropdown fix inside tables */
    .table-responsive { overflow: visible !important; }
    .dropdown-menu {
      position: absolute !important;
      transform: translate3d(0,0,0) !important;
      min-width: 140px;
      font-size: 0.85rem;
    }

  </style>
</head>
<body>

<!-- loader -->
<div id="loaderOverlay" class="d-none">
  <div class="loader-spinner"></div>
</div>

<!-- toast -->
<div id="statusAlertWrapper"
     class="position-fixed top-0 start-50 translate-middle-x p-3">
  <div id="statusAlert"
       class="alert alert-success shadow-sm d-none align-items-center justify-content-between mb-0 text-center"
       role="alert">
    <span id="statusAlertText"></span>
    <button type="button" class="btn-close ms-2" aria-label="Close"
            onclick="document.getElementById('statusAlert').classList.add('d-none');">
    </button>
  </div>
</div>

<div class="container-fluid py-3 d-flex justify-content-center">
  <div class="page-wrapper w-100">

    <!-- Top tabs row -->
    <div class="d-flex justify-content-between align-items-center mb-4">
      <div class="top-nav-wrapper">
        <button class="top-nav-pill active" data-page="employees_list.php">
          <span class="icon">👥</span>
          <span>Employees</span>
        </button>

        <button class="top-nav-pill" data-page="attendance_tab.php?ajax=1">
          <span class="icon">📅</span>
          <span>Attendance</span>
        </button>

        <button class="top-nav-pill" data-page="leaves_tab.php">
          <span class="icon">📝</span>
          <span>Leaves</span>
        </button>

        <button class="top-nav-pill" data-page="shifts.php?ajax=1">
          <span class="icon">📊</span>
          <span>Shift Roster</span>
        </button>

        <button class="top-nav-pill" data-page="departments.php?ajax=1">
          <span class="icon">🛠</span>
          <span>Department</span>
        </button>

        <button class="top-nav-pill" data-page="designations.php?ajax=1">
          <span class="icon">👤</span>
          <span>Designation</span>
        </button>
      </div>

      <!-- Mark Attendance Modal -->
      <div class="modal fade" id="markAttendanceModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered">
          <div class="modal-content border-0 rounded-4">

            <!-- Header -->
            <div class="modal-header border-0 pb-0">
              <div>
                <h5 class="modal-title fw-bold" style="font-size: 22px;">Mark Attendance</h5>
              </div>
              <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <!-- Body -->
            <div class="modal-body pt-2">

              <form id="markAttendanceForm">

                <div class="row g-4">

                  <!-- Department -->
                  <div class="col-md-6">
                    <label class="form-label fw-semibold">
                      Department <span class="text-danger">*</span>
                    </label>
                    <div class="input-group">
                      <select class="form-select" id="departmentSelect" name="department_id" required>
                        <option value="">Select Department...</option>
                        <?php
                        if ($deptRes && $deptRes->num_rows > 0) {
                          mysqli_data_seek($deptRes, 0);
                          while ($d = $deptRes->fetch_assoc()) {
                            ?>
                            <option value="<?php echo $d['id']; ?>">
                              <?php echo htmlspecialchars($d['department_name']); ?>
                            </option>
                            <?php
                          }
                        }
                        ?>
                      </select>
                      <button class="btn btn-outline-dark" type="button" title="Add Department">
                        +
                      </button>
                    </div>
                  </div>

                  <!-- Employee -->
                  <div class="col-md-6">
                    <label class="form-label fw-semibold">
                      Employee <span class="text-danger">*</span>
                    </label>
                    <div class="input-group">
                      <select class="form-select" id="employeeSelect" name="employee_id" required>
                        <option value="">Select Employee</option>
                        <!-- JS se dept wise fill hoga -->
                      </select>
                      <button class="btn btn-outline-dark" type="button" title="Add Employee">
                        +
                      </button>
                    </div>
                  </div>

                  <!-- Late -->
                  <div class="col-md-6 col-lg-3">
                    <label class="form-label fw-semibold">Late</label>
                    <div class="d-flex align-items-center gap-3 mt-1">
                      <div class="form-check">
                        <input class="form-check-input" type="radio" name="late" id="lateYes" value="1">
                        <label class="form-check-label" for="lateYes">Yes</label>
                      </div>
                      <div class="form-check">
                        <input class="form-check-input" type="radio" name="late" id="lateNo" value="0" checked>
                        <label class="form-check-label" for="lateNo">No</label>
                      </div>
                    </div>
                  </div>

                  <!-- Half Day -->
                  <div class="col-md-6 col-lg-3">
                    <label class="form-label fw-semibold">Half Day</label>
                    <div class="d-flex align-items-center gap-3 mt-1">
                      <div class="form-check">
                        <input class="form-check-input" type="radio" name="half_day" id="halfYes" value="1">
                        <label class="form-check-label" for="halfYes">Yes</label>
                      </div>
                      <div class="form-check">
                        <input class="form-check-input" type="radio" name="half_day" id="halfNo" value="0" checked>
                        <label class="form-check-label" for="halfNo">No</label>
                      </div>
                    </div>
                  </div>

                  <!-- Mark Attendance By -->
                  <div class="col-md-12">
                    <label class="form-label fw-semibold">Mark Attendance By</label>
                    <div class="d-flex align-items-center gap-4 mt-1 flex-wrap">
                      <div class="form-check">
                        <input class="form-check-input" type="radio" name="mark_by" id="markByDate" value="date" checked>
                        <label class="form-check-label" for="markByDate">Date</label>
                      </div>
                      <div class="form-check">
                        <input class="form-check-input" type="radio" name="mark_by" id="markByMultiple" value="multiple">
                        <label class="form-check-label" for="markByMultiple">Multiple</label>
                      </div>
                      <div class="form-check">
                        <input class="form-check-input" type="radio" name="mark_by" id="markByMonth" value="month">
                        <label class="form-check-label" for="markByMonth">Month</label>
                      </div>
                    </div>
                  </div>

                  <!-- Select Date -->
                  <div class="col-md-6 col-lg-3">
                    <label class="form-label fw-semibold">
                      Select Date <span class="text-danger">*</span>
                    </label>
                    <div class="input-group">
                      <span class="input-group-text">
                        📅
                      </span>
                      <input type="date" class="form-control" name="date" id="attendanceDate" required>
                    </div>
                  </div>

                  <!-- Clock In -->
                  <div class="col-md-6 col-lg-3">
                    <label class="form-label fw-semibold">
                      Clock In <span class="text-danger">*</span>
                    </label>
                    <div class="input-group">
                      <span class="input-group-text">
                        ⏰
                      </span>
                      <input type="time" class="form-control" name="clock_in" id="clockIn" required>
                    </div>
                  </div>

                  <!-- Clock Out -->
                  <div class="col-md-6 col-lg-3">
                    <label class="form-label fw-semibold">
                      Clock Out <span class="text-danger">*</span>
                    </label>
                    <div class="input-group">
                      <span class="input-group-text">
                        ⏱
                      </span>
                      <input type="time" class="form-control" name="clock_out" id="clockOut" required>
                    </div>
                  </div>

                  <!-- Working From -->
                  <div class="col-md-6 col-lg-3">
                    <label class="form-label fw-semibold">
                      Working From <span class="text-danger">*</span>
                    </label>
                    <div class="input-group">
                      <select class="form-select" name="working_from" id="workingFrom" required>
                        <option value="">Select Working From...</option>
                        <option value="office">Office</option>
                        <option value="home">Home</option>
                        <option value="client">Client Site</option>
                      </select>
                      <button class="btn btn-outline-dark" type="button" title="Add Working From">
                        +
                      </button>
                    </div>
                  </div>

                  <!-- Reason -->
                <div class="col-md-6 col-lg-3">
                  <label class="form-label fw-semibold">
                    Reason / Break Type
                  </label>
                  <select class="form-select" name="reason" id="attendanceReason">
                    <option value="normal">Normal Day</option>
                    <option value="lunch">Lunch Break</option>
                    <option value="tea">Tea / Short Break</option>
                    <option value="short_leave">Short Leave</option>
                    <option value="office_leave">Office Leave</option>
                  </select>
                </div>

                  <!-- Attendance Overwrite -->
                  <div class="col-12">
                    <div class="form-check mt-2">
                      <input class="form-check-input" type="checkbox" value="1" id="overwrite" name="overwrite" checked>
                      <label class="form-check-label fw-semibold" for="overwrite">
                        Attendance Overwrite
                      </label>
                    </div>
                  </div>

                </div><!-- /.row -->

              </form>

            </div><!-- /.modal-body -->

            <!-- Footer -->
            <div class="modal-footer border-0 pt-0 d-flex justify-content-end gap-2">
              <button type="button" class="btn btn-light border" data-bs-dismiss="modal">
                Cancel
              </button>
              <button type="button" class="btn btn-dark" id="saveAttendanceBtn">
                Save
              </button>
            </div>

          </div>
          
        </div>
      </div>

      <!-- Modal styling -->
      <style>
        #markAttendanceModal .modal-content{
          box-shadow:0 18px 45px rgba(15,23,42,.25);
        }
        #markAttendanceModal .form-label{
          font-size:13px;
          text-transform:none;
        }
        #markAttendanceModal .form-control,
        #markAttendanceModal .form-select{
          border-radius:12px;
          font-size:14px;
        }
        #markAttendanceModal .input-group-text{
          border-radius:12px 0 0 12px;
        }
      </style>

      <!-- settings icon -> settings.php -->
      <a href="settings.php" class="btn-round-icon" title="Settings">
        ⚙
      </a>
    </div>

    <!-- Content area: yahi par sab sections AJAX se load honge -->
    <div id="contentArea"></div>

  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
// PHP se employees array JS me
const ALL_EMPLOYEES = <?php echo json_encode($employeesForJs, JSON_UNESCAPED_UNICODE); ?>;

let statusTimer;

// toast function
function showStatus(message, type = 'success') {
  const box  = document.getElementById('statusAlert');
  const text = document.getElementById('statusAlertText');
  if (!box || !text) return;

  text.textContent = message;

  box.classList.remove('d-none', 'alert-success', 'alert-danger', 'd-flex');
  box.classList.add(type === 'danger' ? 'alert-danger' : 'alert-success', 'd-flex');

  if (statusTimer) clearTimeout(statusTimer);
  statusTimer = setTimeout(() => {
    box.classList.add('d-none');
  }, 2500);
}

// loader helpers
function showLoader() {
  document.getElementById('loaderOverlay').classList.remove('d-none');
}
function hideLoader() {
  document.getElementById('loaderOverlay').classList.add('d-none');
}

// Employees list ke buttons/toggles ko init karne ka function
function initEmployeesListEvents() {
  const toggles       = document.querySelectorAll('.status-toggle');
  const deleteButtons = document.querySelectorAll('.delete-employee');
  const deleteModalEl = document.getElementById('deleteConfirmModal');

  if (!deleteModalEl) return; // agar employees_list load hi nahi hua

  const deleteModal   = new bootstrap.Modal(deleteModalEl);
  let deleteId        = null;

  // STATUS TOGGLE AJAX
  toggles.forEach(function (toggle) {
    toggle.addEventListener('change', function () {
      const checkbox   = this;
      const employeeId = this.dataset.id;
      const newStatus  = this.checked ? 1 : 0;

      checkbox.disabled = true;

      fetch('toggle_employee_status.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'id=' + encodeURIComponent(employeeId) +
              '&status=' + encodeURIComponent(newStatus)
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          showStatus(
            data.message || (newStatus ? 'Account activated successfully.' : 'Account deactivated successfully.'),
            'success'
          );
        } else {
          showStatus(data.message || 'Status update failed. Please try again.', 'danger');
          checkbox.checked = !checkbox.checked;
        }
      })
      .catch(() => {
        showStatus('Something went wrong. Please check your connection.', 'danger');
        checkbox.checked = !checkbox.checked;
      })
      .finally(() => {
        checkbox.disabled = false;
      });
    });
  });

  // DELETE BUTTON -> custom modal
  deleteButtons.forEach(function (btn) {
    btn.addEventListener('click', function (e) {
      e.preventDefault();
      deleteId = this.dataset.id;
      deleteModal.show();
    });
  });

  const confirmDeleteBtn = document.getElementById('confirmDeleteBtn');
  if (confirmDeleteBtn) {
    confirmDeleteBtn.addEventListener('click', function () {
      if (!deleteId) return;
      window.location.href = 'delete_employee.php?id=' + encodeURIComponent(deleteId);
    });
  }
}

// generic AJAX loader with animation
function loadPage(page, button) {
  const contentArea = document.getElementById("contentArea");

  // Active tab UI
  document.querySelectorAll('.top-nav-pill').forEach(btn => btn.classList.remove('active'));
  if (button) button.classList.add('active');

  contentArea.classList.remove('fade-in');
  showLoader();

  fetch(page)
    .then(response => {
      if (!response.ok) throw new Error('Network error');
      return response.text();
    })
    .then(html => {
      contentArea.innerHTML = html;
      // fade-in
      void contentArea.offsetWidth;
      contentArea.classList.add('fade-in');

      // agar employees list load hui hai to uske events init karo
      if (page === 'employees_list.php') {
        initEmployeesListEvents();
      }
    })
    .catch(err => {
      console.error(err);
      contentArea.innerHTML =
        "<div class='alert alert-danger m-3'>Failed to load page.</div>";
    })
    .finally(() => {
      hideLoader();
    });
}

document.addEventListener('DOMContentLoaded', function () {
  // success messages from PHP flags (delete / update)
  const deletedFlag = <?php echo json_encode((bool)$deleted); ?>;
  const updatedFlag = <?php echo json_encode((bool)$updated); ?>;
  if (deletedFlag) {
    showStatus('Employee deleted successfully.', 'success');
  } else if (updatedFlag) {
    showStatus('Employee updated successfully.', 'success');
  }

  // Tab click handlers
  document.querySelectorAll('.top-nav-pill').forEach(btn => {
    btn.addEventListener("click", () => {
      const page = btn.dataset.page;
      if (page) loadPage(page, btn);
    });
  });

  // Default: Employees tab
  const defaultBtn = document.querySelector('.top-nav-pill.active');
  if (defaultBtn) {
    loadPage(defaultBtn.dataset.page, defaultBtn);
  }

  // 🔹 Department change -> Employee dropdown populate
  const deptSelect = document.getElementById('departmentSelect');
  const empSelect  = document.getElementById('employeeSelect');

  if (deptSelect && empSelect) {
    deptSelect.addEventListener('change', function () {
      const deptId = this.value;
      empSelect.innerHTML = '<option value="">Select Employee</option>';

      if (!deptId) return;

      ALL_EMPLOYEES
        .filter(e => String(e.department_id) === String(deptId))
        .forEach(e => {
          const opt = document.createElement('option');
          opt.value = e.id;
          opt.textContent = e.name;
          empSelect.appendChild(opt);
        });
    });
  }

    // Save attendance button -> REAL AJAX SAVE
  const saveBtn = document.getElementById('saveAttendanceBtn');
  const markForm = document.getElementById('markAttendanceForm');

  if (saveBtn && markForm) {
    saveBtn.addEventListener('click', function () {
      // HTML5 validation
      if (!markForm.reportValidity()) return;

      const formData = new FormData(markForm);

      showLoader();

      fetch('save_admin_attendance.php', {
        method: 'POST',
        body: formData
      })
      .then(res => res.json())
      .then(data => {
        if (data.success) {
          // modal close
          const modalEl = document.getElementById('markAttendanceModal');
          const modal   = bootstrap.Modal.getInstance(modalEl);
          modal.hide();

          showStatus(data.message || 'Attendance saved successfully.', 'success');

          // OPTIONAL: agar attendance tab open hai to refresh kara sakte ho
          // const tabBtn = document.querySelector('[data-page^="attendance_tab.php"]');
          // if (tabBtn && tabBtn.classList.contains('active')) {
          //   loadPage('attendance_tab.php?ajax=1', tabBtn);
          // }

        } else {
          showStatus(data.message || 'Failed to save attendance.', 'danger');
        }
      })
      .catch(() => {
        showStatus('Error while saving attendance. Please try again.', 'danger');
      })
      .finally(() => {
        hideLoader();
      });
    });
  }
});

document.addEventListener("submit", function (e) {
    const form = e.target;

    // Only intercept Department AJAX form
    if (form.closest("#contentArea") && form.action.includes("departments.php")) {
        e.preventDefault();

        const formData = new FormData(form);
        const url = form.action + "?ajax=1";

        showLoader();

        fetch(url, {
            method: "POST",
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                loadPage(data.reload, document.querySelector('[data-page="departments.php?ajax=1"]'));
                showStatus("Department saved", "success");
            }
        })
        .catch(() => showStatus("Error saving department", "danger"))
        .finally(hideLoader);

    }
});

document.addEventListener("click", function (e) {
    if (e.target.classList.contains("dept-edit")) {
        const id = e.target.dataset.editId;

        loadPage("departments.php?ajax=1&edit=" + id,
                 document.querySelector('[data-page="departments.php?ajax=1"]'));
    }
});

// Designation Edit (AJAX load)
document.addEventListener("click", function (e) {
  const editLink = e.target.closest(".desig-edit");
  if (editLink) {
    e.preventDefault();
    const id = editLink.dataset.editId;
    const tabBtn = document.querySelector('[data-page="designations.php?ajax=1"]');
    loadPage("designations.php?ajax=1&edit=" + encodeURIComponent(id), tabBtn);
  }
});

document.addEventListener("submit", function (e) {
  const form = e.target;

  // SPA ke contentArea ke andar ka form hi intercept karna hai
  if (!form.closest("#contentArea")) return;

  // DEPARTMENT form
  if (form.action.includes("departments.php")) {
    e.preventDefault();

    const formData = new FormData(form);
    const url = "departments.php?ajax=1";

    showLoader();
    fetch(url, {
      method: "POST",
      body: formData
    })
    .then(res => res.json())
    .then(data => {
      if (data.success) {
        const tabBtn = document.querySelector('[data-page="departments.php?ajax=1"]');
        loadPage(data.reload, tabBtn);
        showStatus("Department saved successfully.", "success");
      }
    })
    .catch(() => showStatus("Error saving department", "danger"))
    .finally(hideLoader);

    return;
  }

  // DESIGNATION form
  if (form.action.includes("designations.php")) {
    e.preventDefault();

    const formData = new FormData(form);
    const url = "designations.php?ajax=1";

    showLoader();
    fetch(url, {
      method: "POST",
      body: formData
    })
    .then(res => res.json())
    .then(data => {
      if (data.success) {
        const tabBtn = document.querySelector('[data-page="designations.php?ajax=1"]');
        loadPage(data.reload, tabBtn);
        showStatus("Designation saved successfully.", "success");
      }
    })
    .catch(() => showStatus("Error saving designation", "danger"))
    .finally(hideLoader);

    return;
  }
});

document.addEventListener("submit", function (e) {
  const form = e.target;

  // sirf SPA contentArea ke andar wale form intercept karo
  if (!form.closest("#contentArea")) return;

  // SHIFTS form
  if (form.action.includes("shifts.php")) {
    e.preventDefault();

    const formData = new FormData(form);
    const url = "shifts.php?ajax=1";

    showLoader();
    fetch(url, {
      method: "POST",
      body: formData
    })
    .then(res => res.json())
    .then(data => {
      if (data.success) {
        const tabBtn = document.querySelector('[data-page="shifts.php?ajax=1"]');
        loadPage(data.reload, tabBtn);
        showStatus("Shift saved successfully.", "success");
      } else if (data.errors) {
        showStatus(data.errors.join(" "), "danger");
      }
    })
    .catch(() => showStatus("Error saving shift", "danger"))
    .finally(hideLoader);

    return;
  }
});

document.addEventListener("click", function (e) {
  const editBtn = e.target.closest(".shift-edit");
  if (editBtn) {
    e.preventDefault();
    const id = editBtn.dataset.editId;
    const tabBtn = document.querySelector('[data-page="shifts.php?ajax=1"]');
    loadPage("shifts.php?ajax=1&edit=" + encodeURIComponent(id), tabBtn);
    return;
  }

  const delBtn = e.target.closest(".shift-delete");
  if (delBtn) {
    e.preventDefault();
    const id = delBtn.dataset.delId;
    if (!confirm("Delete this shift?")) return;

    showLoader();
    fetch("shifts.php?ajax=1&delete=" + encodeURIComponent(id))
      .then(res => res.json())
      .then(data => {
        if (data.success) {
          const tabBtn = document.querySelector('[data-page="shifts.php?ajax=1"]');
          loadPage(data.reload, tabBtn);
          showStatus("Shift deleted successfully.", "success");
        } else {
          showStatus("Failed to delete shift.", "danger");
        }
      })
      .catch(() => showStatus("Failed to delete shift.", "danger"))
      .finally(hideLoader);
  }
});

// Attendance filter (attendance_tab.php ke andar ka form)
document.addEventListener("submit", function (e) {
  const form = e.target;

  // 1) Attendance FILTER form (AJAX)
  if (form.id === "attendanceFilterForm") {
    e.preventDefault();

    const month = form.month.value;
    const year  = form.year.value;

    const tabBtn = document.querySelector('[data-page^="attendance_tab.php"]');
    const url    = "attendance_tab.php?ajax=1"
                 + "&month=" + encodeURIComponent(month)
                 + "&year="  + encodeURIComponent(year);

    loadPage(url, tabBtn);  // same animation + loader use hoga
    return;
  }
});

</script>

</body>
</html>
