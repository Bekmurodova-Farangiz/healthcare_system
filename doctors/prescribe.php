<?php
session_start();
require_once '../includes/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'doctor') {
    header("Location: ../auth/login.php");
    exit();
}

$doctor_id = (int)$_SESSION['user_id'];
$appointment_date = $_GET['appointment_date'] ?? '';

if ($appointment_date === '') {
    echo "Invalid appointment.";
    exit();
}

$appointment_id = 0;
$patient_id = 0;
$status = '';

$stmt = mysqli_prepare($conn, "SELECT id, patient_id, status FROM appointments WHERE doctor_id = ? AND appointment_date = ? LIMIT 1");
if (!$stmt) {
    echo "Server error.";
    exit();
}
mysqli_stmt_bind_param($stmt, "is", $doctor_id, $appointment_date);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);

if (!$res || mysqli_num_rows($res) === 0) {
    mysqli_stmt_close($stmt);
    echo "Appointment not found.";
    exit();
}

$row = mysqli_fetch_assoc($res);
$appointment_id = (int)$row['id'];
$patient_id = (int)$row['patient_id'];
$status = $row['status'];

mysqli_stmt_close($stmt);

if ($status !== 'checked') {
    echo "You can only add prescription for appointments with status 'checked'.";
    exit();
}

$medications = [];
$resMed = mysqli_query($conn, "SELECT id, name FROM medications ORDER BY name ASC");
if ($resMed) {
    while ($m = mysqli_fetch_assoc($resMed)) {
        $medications[] = $m;
    }
}

$errors = [];
$doctor_notes_value = trim($_POST['doctor_notes'] ?? '');

$rows = 1;
if (isset($_POST['rows'])) {
    $rows = (int)$_POST['rows'];
} elseif (isset($_GET['rows'])) {
    $rows = (int)$_GET['rows'];
}
if ($rows < 1) $rows = 1;
if ($rows > 5) $rows = 5; 

$is_finish = (isset($_POST['action']) && $_POST['action'] === 'finish');
$is_addrow = (isset($_POST['action']) && $_POST['action'] === 'addrow');

if ($is_addrow) {
    $rows++;
    if ($rows > 5) $rows = 5;
}

if ($is_finish) {
    $doctor_notes = trim($_POST['doctor_notes'] ?? '');
    $med_ids = $_POST['medication_id'] ?? [];
    $dosages = $_POST['dosage'] ?? [];
    $instructions = $_POST['instructions'] ?? [];

    if ($doctor_notes === '') {
        $errors[] = "Please enter doctor notes.";
    }

    $valid_prescriptions = [];
    for ($i = 0; $i < count($med_ids); $i++) {
        $mid = (int)($med_ids[$i] ?? 0);
        $dos = trim($dosages[$i] ?? '');
        $ins = trim($instructions[$i] ?? '');

        if ($mid === 0 && $dos === '' && $ins === '') {
            continue;
        }

        if ($mid === 0 || $dos === '' || $ins === '') {
            $errors[] = "All prescription fields are required (medication, dosage, instructions).";
            break;
        }

        $valid_prescriptions[] = ['mid' => $mid, 'dos' => $dos, 'ins' => $ins];
    }

    if (empty($valid_prescriptions)) {
        $errors[] = "Please add at least one prescription.";
    }

    if (empty($errors)) {
        mysqli_begin_transaction($conn);

        $ok = true;

        $st1 = mysqli_prepare($conn, "UPDATE appointments SET doctor_notes = ?, status = 'finished' WHERE id = ?");
        if (!$st1) $ok = false;
        if ($ok) {
            mysqli_stmt_bind_param($st1, "si", $doctor_notes, $appointment_id);
            if (!mysqli_stmt_execute($st1)) $ok = false;
            mysqli_stmt_close($st1);
        }

        if ($ok) {
            $st2 = mysqli_prepare($conn, "DELETE FROM prescriptions WHERE appointment_id = ?");
            if (!$st2) $ok = false;
            if ($ok) {
                mysqli_stmt_bind_param($st2, "i", $appointment_id);
                if (!mysqli_stmt_execute($st2)) $ok = false;
                mysqli_stmt_close($st2);
            }
        }

        if ($ok) {
            $st3 = mysqli_prepare($conn, "INSERT INTO prescriptions (appointment_id, medication_id, dosage, instructions) VALUES (?, ?, ?, ?)");
            if (!$st3) $ok = false;

            if ($ok) {
                foreach ($valid_prescriptions as $p) {
                    mysqli_stmt_bind_param($st3, "iiss", $appointment_id, $p['mid'], $p['dos'], $p['ins']);
                    if (!mysqli_stmt_execute($st3)) {
                        $ok = false;
                        break;
                    }
                }
                mysqli_stmt_close($st3);
            }
        }

        if ($ok) {
            $st4 = mysqli_prepare($conn, "INSERT INTO medical_records (appointment_id, patient_id, doctor_id, doctor_notes) VALUES (?, ?, ?, ?)");
            if (!$st4) $ok = false;
            if ($ok) {
                mysqli_stmt_bind_param($st4, "iiis", $appointment_id, $patient_id, $doctor_id, $doctor_notes);
                if (!mysqli_stmt_execute($st4)) $ok = false;
                mysqli_stmt_close($st4);
            }
        }

        if ($ok) {
            mysqli_commit($conn);
            header("Location: dashboard.php");
            exit();
        } else {
            mysqli_rollback($conn);
            $errors[] = "Failed to save prescriptions. Please try again.";
        }
    }
}

$posted_med_ids = $_POST['medication_id'] ?? [];
$posted_dosages = $_POST['dosage'] ?? [];
$posted_instructions = $_POST['instructions'] ?? [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>Add Prescription and Finish Appointment</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f9faff;
            margin: 20px;
            color: #333;
        }
        h2 { color: #2a7ae2; }
        form {
            max-width: 760px;
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
        }
        label {
            font-weight: 600;
            display: block;
            margin-top: 15px;
            margin-bottom: 5px;
        }
        textarea {
            width: 100%;
            resize: vertical;
            padding: 8px;
            font-size: 1rem;
            border: 1px solid #ccc;
            border-radius: 5px;
            min-height: 100px;
        }
        select, input[type=text] {
            width: 100%;
            padding: 8px;
            font-size: 1rem;
            border: 1px solid #ccc;
            border-radius: 5px;
            box-sizing: border-box;
        }
        .prescription-group {
            border: 1px solid #ddd;
            padding: 15px 20px;
            margin-top: 16px;
            border-radius: 6px;
            background: #f7f9fc;
        }
        button[type="submit"] {
            background-color: #2a7ae2;
            border: none;
            color: white;
            padding: 12px 20px;
            margin-top: 18px;
            border-radius: 5px;
            font-size: 1rem;
            cursor: pointer;
        }
        button[type="submit"]:hover { background-color: #1958a3; }
        .btn-secondary {
            background: #6c757d;
            margin-left: 10px;
        }
        .btn-secondary:hover { background: #555e66; }
        ul { color: #e74c3c; padding-left: 20px; }
        ul li { margin-bottom: 8px; }

        .topbar {
            max-width: 760px;
            margin-bottom: 12px;
        }
        .topbar a {
            color: #2a7ae2;
            text-decoration: none;
            font-weight: 600;
        }
        .note {
            max-width: 760px;
            background: #eef6fc;
            border: 1px solid #b3d4fc;
            padding: 10px 14px;
            border-radius: 6px;
            margin-bottom: 12px;
        }
    </style>
</head>
<body>
    <div class="topbar">
        <a href="dashboard.php">← Back to Dashboard</a>
    </div>

    <h2>Add Prescription and Finish Appointment</h2>

    <?php if (!empty($errors)): ?>
        <ul>
            <?php foreach ($errors as $e): ?>
                <li><?php echo htmlspecialchars($e); ?></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <form method="POST" action="">
        <input type="hidden" name="rows" value="<?php echo (int)$rows; ?>">

        <label for="doctor_notes">Doctor Notes:</label>
        <textarea name="doctor_notes" id="doctor_notes"><?php echo htmlspecialchars($doctor_notes_value); ?></textarea>

        <h3>Prescriptions</h3>

        <?php for ($i = 0; $i < $rows; $i++): ?>
            <div class="prescription-group">
                <label>Medication:</label>
                <select name="medication_id[]">
                    <option value="">-- Select medication --</option>
                    <?php foreach ($medications as $med): ?>
                        <?php
                            $selected = '';
                            $posted_val = isset($posted_med_ids[$i]) ? (int)$posted_med_ids[$i] : 0;
                            if ($posted_val === (int)$med['id']) $selected = 'selected';
                        ?>
                        <option value="<?php echo (int)$med['id']; ?>" <?php echo $selected; ?>>
                            <?php echo htmlspecialchars($med['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <label>Dosage:</label>
                <input type="text" name="dosage[]" value="<?php echo htmlspecialchars($posted_dosages[$i] ?? ''); ?>">

                <label>Instructions:</label>
                <input type="text" name="instructions[]" value="<?php echo htmlspecialchars($posted_instructions[$i] ?? ''); ?>">
            </div>
        <?php endfor; ?>

        <button type="submit" name="action" value="addrow" class="btn-secondary">+ Add One More Row</button>
        <button type="submit" name="action" value="finish">Finish Appointment</button>
    </form>
</body>
</html>
<?php mysqli_close($conn); ?>
