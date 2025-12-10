<?php
// shifts.php
include 'db.php';

$isAjax = isset($_GET['ajax']) && $_GET['ajax'] == '1';

$errors = [];

// ---------------- HANDLE FORM SUBMIT (ADD / EDIT) ----------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id                     = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $shift_name             = trim($_POST['shift_name'] ?? '');
    $start_time             = $_POST['start_time'] ?? '';
    $end_time               = $_POST['end_time'] ?? '';
    $lunch_start            = $_POST['lunch_start'] ?: null; // optional
    $lunch_end              = $_POST['lunch_end'] ?: null;   // optional
    $early_clock_in_before  = (int)($_POST['early_clock_in_before'] ?? 0);
    $late_mark_after        = (int)($_POST['late_mark_after'] ?? 0);
    $total_punches          = (int)($_POST['total_punches'] ?? 0);
    $half_day_time          = $_POST['half_day_time'] ?? '';

    // Basic validation
    if ($shift_name === '') {
        $errors[] = "Shift name is required.";
    }
    if ($start_time === '') {
        $errors[] = "Start time is required.";
    }
    if ($end_time === '') {
        $errors[] = "End time is required.";
    }
    if ($late_mark_after <= 0) {
        $errors[] = "Late mark after (minutes) must be greater than 0.";
    }
    if ($total_punches <= 0) {
        $errors[] = "Total punches per day must be greater than 0.";
    }

    // Half day time validation + minutes calculation
    if ($half_day_time === '') {
        $errors[] = "Half day time is required.";
    } else {
        if ($start_time === '') {
            $errors[] = "Start time is required to calculate half day.";
        } else {
            $startTs = strtotime($start_time);
            $halfTs  = strtotime($half_day_time);

            if ($halfTs <= $startTs) {
                $errors[] = "Half day time must be after shift start time.";
            } else {
                $half_day_after = (int)(($halfTs - $startTs) / 60); // minutes from shift start
            }
        }
    }

    if (empty($errors)) {
        if ($id === 0) {
            // INSERT
            $sql = "INSERT INTO shifts 
                (shift_name, start_time, end_time, lunch_start, lunch_end,
                 early_clock_in_before, late_mark_after, half_day_after, total_punches)
                VALUES (?,?,?,?,?,?,?,?,?)";

            $stmt = $con->prepare($sql);
            if ($stmt) {
                $stmt->bind_param(
                    "sssssiiii",
                    $shift_name,
                    $start_time,
                    $end_time,
                    $lunch_start,
                    $lunch_end,
                    $early_clock_in_before,
                    $late_mark_after,
                    $half_day_after,
                    $total_punches
                );

                if (!$stmt->execute()) {
                    $errors[] = "Database error: " . $con->error;
                }
            } else {
                $errors[] = "Failed to prepare insert query: " . $con->error;
            }
        } else {
            // UPDATE
            $updated_at = date("Y-m-d H:i:s");
            $sql = "UPDATE shifts SET 
                        shift_name = ?, 
                        start_time = ?, 
                        end_time = ?, 
                        lunch_start = ?, 
                        lunch_end = ?, 
                        early_clock_in_before = ?, 
                        late_mark_after = ?, 
                        half_day_after = ?, 
                        total_punches = ?, 
                        updated_at = ?
                    WHERE id = ?";

            $stmt = $con->prepare($sql);
            if ($stmt) {
                $stmt->bind_param(
                    "sssssiiiisi",
                    $shift_name,
                    $start_time,
                    $end_time,
                    $lunch_start,
                    $lunch_end,
                    $early_clock_in_before,
                    $late_mark_after,
                    $half_day_after,
                    $total_punches,
                    $updated_at,
                    $id
                );

                if (!$stmt->execute()) {
                    $errors[] = "Database error: " . $con->error;
                }
            } else {
                $errors[] = "Failed to prepare update query: " . $con->error;
            }
        }
    }

    // Response handling
    if ($isAjax) {
        header('Content-Type: application/json');
        if (empty($errors)) {
            echo json_encode([
                'success' => true,
                'reload'  => 'shifts.php?ajax=1',
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'errors'  => $errors,
            ]);
        }
        exit;
    } else {
        if (empty($errors)) {
            header("Location: shifts.php");
            exit;
        }
        // agar errors hain & normal mode hai to neeche form ke saath show karenge
    }
}

// ---------------- HANDLE DELETE ----------------
if (isset($_GET['delete'])) {
    $delId = (int)$_GET['delete'];
    $con->query("DELETE FROM shifts WHERE id = $delId");

    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'reload'  => 'shifts.php?ajax=1',
        ]);
        exit;
    } else {
        header("Location: shifts.php");
        exit;
    }
}

// ---------------- EDIT MODE: FETCH SINGLE SHIFT ----------------
$editRow = null;
if (isset($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
    $res = $con->query("SELECT * FROM shifts WHERE id = $editId");
    $editRow = $res->fetch_assoc();
}

// ---------------- LIST ALL SHIFTS ----------------
$list = $con->query("SELECT * FROM shifts ORDER BY shift_name ASC");

// ---------------- RENDER FUNCTION ----------------
function renderShiftContent($errors, $editRow, $list) {
?>
<div class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="mb-0">Shift Master</h3>
    <a href="employees.php" class="btn btn-outline-secondary">Go to Employees</a>
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

  <!-- ADD / EDIT FORM -->
  <div class="card mb-4">
    <div class="card-header">
      <?php echo $editRow ? 'Edit Shift' : 'Add New Shift'; ?>
    </div>
    <div class="card-body">
      <form method="POST" action="shifts.php">
        <input type="hidden" name="id" value="<?php echo $editRow['id'] ?? 0; ?>">

        <div class="row g-3">
          <div class="col-md-4">
            <label class="form-label">Shift Name</label>
            <input type="text"
                   name="shift_name"
                   class="form-control"
                   required
                   value="<?php echo htmlspecialchars($editRow['shift_name'] ?? ($_POST['shift_name'] ?? '')); ?>">
          </div>

          <div class="col-md-4">
            <label class="form-label">Start Time</label>
            <input type="time"
                   name="start_time"
                   class="form-control"
                   required
                   value="<?php echo htmlspecialchars($editRow['start_time'] ?? ($_POST['start_time'] ?? '')); ?>">
          </div>

          <div class="col-md-4">
            <label class="form-label">End Time</label>
            <input type="time"
                   name="end_time"
                   class="form-control"
                   required
                   value="<?php echo htmlspecialchars($editRow['end_time'] ?? ($_POST['end_time'] ?? '')); ?>">
          </div>

          <div class="col-md-4">
            <label class="form-label">Lunch Start (optional)</label>
            <input type="time"
                   name="lunch_start"
                   class="form-control"
                   value="<?php echo htmlspecialchars($editRow['lunch_start'] ?? ($_POST['lunch_start'] ?? '')); ?>">
          </div>

          <div class="col-md-4">
            <label class="form-label">Lunch End (optional)</label>
            <input type="time"
                   name="lunch_end"
                   class="form-control"
                   value="<?php echo htmlspecialchars($editRow['lunch_end'] ?? ($_POST['lunch_end'] ?? '')); ?>">
          </div>

          <div class="col-md-4">
            <label class="form-label">Early Clock-In (minutes before start)</label>
            <input type="number"
                   name="early_clock_in_before"
                   class="form-control"
                   min="0"
                   value="<?php echo htmlspecialchars($editRow['early_clock_in_before'] ?? ($_POST['early_clock_in_before'] ?? '0')); ?>">
          </div>

          <div class="col-md-4">
            <label class="form-label">Late Mark After (minutes from start)</label>
            <input type="number"
                   name="late_mark_after"
                   class="form-control"
                   min="1"
                   required
                   value="<?php echo htmlspecialchars($editRow['late_mark_after'] ?? ($_POST['late_mark_after'] ?? '10')); ?>">
          </div>

          <!-- Half Day TIME input (UI) -->
          <div class="col-md-4">
            <label class="form-label">Half Day After (Time)</label>
            <input type="time"
                   name="half_day_time"
                   class="form-control"
                   required
                   value="<?php
                     if (!empty($_POST['half_day_time'])) {
                         echo htmlspecialchars($_POST['half_day_time']);
                     } elseif (!empty($editRow['start_time']) && isset($editRow['half_day_after'])) {
                         $halfTs = strtotime($editRow['start_time']) + ((int)$editRow['half_day_after'] * 60);
                         echo date('H:i', $halfTs);
                     }
                   ?>">
            <div class="form-text">
              Example: shift 10:00 hai to yahan 14:30 (2:30 PM) doge.
            </div>
          </div>

          <div class="col-md-4">
            <label class="form-label">Total Punches Per Day</label>
            <input type="number"
                   name="total_punches"
                   class="form-control"
                   min="1"
                   required
                   value="<?php echo htmlspecialchars($editRow['total_punches'] ?? ($_POST['total_punches'] ?? '4')); ?>">
            <div class="form-text">
              Example: 2 = IN+OUT, 4 = IN/OUT/Lunch IN/OUT
            </div>
          </div>
        </div>

        <div class="mt-4">
          <button type="submit" class="btn btn-primary">
            <?php echo $editRow ? 'Update Shift' : 'Save Shift'; ?>
          </button>
          <?php if ($editRow) { ?>
            <a href="shifts.php" class="btn btn-secondary ms-2">Cancel</a>
          <?php } ?>
        </div>
      </form>
    </div>
  </div>

   <!-- SHIFT LIST TABLE -->
  <div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
      <span class="fw-semibold">Shift List</span>
      <small class="text-muted">
        Total: <?php echo $list ? $list->num_rows : 0; ?>
      </small>
    </div>

    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead class="table-light">
            <tr class="text-nowrap text-center">
              <th style="width: 50px;">#</th>
              <th class="text-start">Shift</th>
              <th>Timing</th>
              <th>Lunch</th>
              <th>Early In</th>
              <th>Late Mark</th>
              <th>Half Day</th>
              <th>Punches</th>
              <th>Updated</th>
              <th style="width: 130px;" class="text-end">Action</th>
            </tr>
          </thead>
          <tbody>
          <?php
          $i = 1;
          if ($list && $list->num_rows > 0) {
              while ($row = $list->fetch_assoc()) {

                  $startDisp = date('h:i A', strtotime($row['start_time']));
                  $endDisp   = date('h:i A', strtotime($row['end_time']));
                  $timing    = "$startDisp – $endDisp";

                  if (!empty($row['lunch_start']) && !empty($row['lunch_end'])) {
                      $lunchStart = date('h:i A', strtotime($row['lunch_start']));
                      $lunchEnd   = date('h:i A', strtotime($row['lunch_end']));
                      $lunch      = "$lunchStart – $lunchEnd";
                  } else {
                      $lunch = '—';
                  }

                  $halfTime = '—';
                  if (!empty($row['start_time']) && isset($row['half_day_after'])) {
                      $halfTs   = strtotime($row['start_time']) + ((int)$row['half_day_after'] * 60);
                      $halfTime = date('h:i A', $halfTs);
                  }

                  $updatedTs = $row['updated_at'] ?: $row['created_at'];
                  $updated   = date('d M Y, h:i A', strtotime($updatedTs));
          ?>
            <tr class="text-center text-nowrap">
              <td><?php echo $i++; ?></td>

              <td class="text-start">
                <div class="fw-semibold">
                  <?php echo htmlspecialchars($row['shift_name']); ?>
                </div>
              </td>

              <td><?php echo $timing; ?></td>
              <td><?php echo $lunch; ?></td>

              <td><?php echo (int)$row['early_clock_in_before']; ?> min</td>
              <td><?php echo (int)$row['late_mark_after']; ?> min</td>

              <td><?php echo $halfTime; ?></td>
              <td><?php echo (int)$row['total_punches']; ?></td>
              <td><?php echo $updated; ?></td>

              <td class="text-end text-nowrap">
                <?php if ($GLOBALS['isAjax']) { ?>
                  <a href="javascript:void(0)"
                     class="btn btn-sm btn-outline-primary me-1 shift-edit"
                     data-edit-id="<?php echo $row['id']; ?>">
                    Edit
                  </a>
                  <a href="javascript:void(0)"
                     class="btn btn-sm btn-outline-danger shift-delete"
                     data-del-id="<?php echo $row['id']; ?>">
                    Delete
                  </a>
                <?php } else { ?>
                  <a href="shifts.php?edit=<?php echo $row['id']; ?>"
                     class="btn btn-sm btn-outline-primary me-1">
                    Edit
                  </a>
                  <a href="shifts.php?delete=<?php echo $row['id']; ?>"
                     class="btn btn-sm btn-outline-danger"
                     onclick="return confirm('Delete this shift?');">
                    Delete
                  </a>
                <?php } ?>
              </td>
            </tr>
          <?php
              }
          } else {
          ?>
            <tr>
              <td colspan="10" class="text-center py-4 text-muted">
                No shifts found. Please add one.
              </td>
            </tr>
          <?php } ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

</div>
<?php
}

// ------------- AJAX vs FULL PAGE OUTPUT -------------
if ($isAjax) {
    renderShiftContent($errors, $editRow, $list);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Shift Master</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<?php renderShiftContent($errors, $editRow, $list); ?>
</body>
</html>
