<?php
session_start();
require_once '../includes/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'patient') {
    header("Location: ../auth/login.php");
    exit();
}

$patient_id = (int)$_SESSION['user_id'];
$errors = [];
$success = "";

$specialties = [];
$res = mysqli_query($conn, "SELECT id, name FROM specialties ORDER BY name ASC");
if ($res) {
    while ($row = mysqli_fetch_assoc($res)) {
        $specialties[] = $row;
    }
}

$specialty_id = '';
$doctor_id = '';
$appointment_date = '';
$payment_method = 'cash';
$card_payment_type = 'hospital';
$card_holder_name = '';
$card_number = '';
$card_expiry = '';
$card_cvc = '';

$doctors = [];

if (isset($_POST['specialty'])) {
    $specialty_id = (int)($_POST['specialty']);
}

if (isset($_POST['doctor'])) {
    $doctor_id = (int)($_POST['doctor']);
}

if (isset($_POST['appointment_date'])) {
    $appointment_date = $_POST['appointment_date'];
}

if (isset($_POST['payment_method'])) {
    $payment_method = $_POST['payment_method'];
}

if (isset($_POST['card_payment_type'])) {
    $card_payment_type = $_POST['card_payment_type'];
}

$card_holder_name = trim($_POST['card_holder_name'] ?? '');
$card_number = trim($_POST['card_number'] ?? '');
$card_expiry = trim($_POST['card_expiry'] ?? '');
$card_cvc = trim($_POST['card_cvc'] ?? '');

if ($specialty_id > 0) {
    $stmt = mysqli_prepare($conn, "
        SELECT u.id, u.name
        FROM users u
        INNER JOIN doctor_specialty ds ON u.id = ds.doctor_id
        WHERE ds.specialty_id = ? AND u.is_approved = 1 AND u.role = 'doctor'
        ORDER BY u.name
    ");
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "i", $specialty_id);
        mysqli_stmt_execute($stmt);
        $r = mysqli_stmt_get_result($stmt);
        if ($r) {
            while ($row = mysqli_fetch_assoc($r)) {
                $doctors[] = $row;
            }
        }
        mysqli_stmt_close($stmt);
    }
}

$is_booking = isset($_POST['action']) && $_POST['action'] === 'book';

if ($is_booking) {
    if ($specialty_id <= 0 || $doctor_id <= 0 || !$appointment_date) {
        $errors[] = "Please fill in all required fields (specialty, doctor, appointment date).";
    } else {
        $check = mysqli_prepare($conn, "SELECT COUNT(*) FROM doctor_specialty WHERE doctor_id = ? AND specialty_id = ?");
        if ($check) {
            mysqli_stmt_bind_param($check, "ii", $doctor_id, $specialty_id);
            mysqli_stmt_execute($check);
            mysqli_stmt_bind_result($check, $count);
            mysqli_stmt_fetch($check);
            mysqli_stmt_close($check);

            if ((int)$count === 0) {
                $errors[] = "Selected doctor does not belong to the chosen specialty.";
            }
        } else {
            $errors[] = "Server error (validation failed).";
        }
    }

    if ($payment_method !== 'cash' && $payment_method !== 'card') {
        $errors[] = "Please select a valid payment method.";
    }

    if ($payment_method === 'card') {
        if ($card_payment_type !== 'online' && $card_payment_type !== 'hospital') {
            $errors[] = "Please select a valid card payment type.";
        }

        if ($card_payment_type === 'online') {
            if ($card_holder_name === '' || $card_number === '' || $card_expiry === '' || $card_cvc === '') {
                $errors[] = "Please fill all credit card details for online payment.";
            }
        }
    } else {
        $card_payment_type = null;
        $card_holder_name = null;
        $card_number = null;
        $card_expiry = null;
        $card_cvc = null;
    }

    if (empty($errors)) {
        $stmt = mysqli_prepare($conn, "
            INSERT INTO appointments
            (patient_id, doctor_id, appointment_date, status, payment_method, card_payment_type, card_holder_name, card_number, card_expiry, card_cvc)
            VALUES (?, ?, ?, 'pending', ?, ?, ?, ?, ?, ?)
        ");

        if ($stmt) {
            mysqli_stmt_bind_param(
                $stmt,
                "iisssssss",
                $patient_id,
                $doctor_id,
                $appointment_date,
                $payment_method,
                $card_payment_type,
                $card_holder_name,
                $card_number,
                $card_expiry,
                $card_cvc
            );

            if (mysqli_stmt_execute($stmt)) {
                $success = "Appointment booked successfully!";
                $specialty_id = 0;
                $doctor_id = 0;
                $appointment_date = '';
                $payment_method = 'cash';
                $card_payment_type = 'hospital';
                $card_holder_name = '';
                $card_number = '';
                $card_expiry = '';
                $card_cvc = '';
                $doctors = [];
            } else {
                $errors[] = "Failed to book appointment. Please try again.";
            }

            mysqli_stmt_close($stmt);
        } else {
            $errors[] = "Server error (insert failed).";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>Book Appointment</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f9fafb;
            color: #2c3e50;
            margin: 0;
            padding: 20px 30px 40px 30px;
        }
        h2 { color: #34495e; margin-bottom: 20px; }
        a { color: #2980b9; text-decoration: none; margin-right: 15px; }
        a:hover { text-decoration: underline; }
        form {
            background: white;
            padding: 25px 30px;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            max-width: 600px;
        }
        label {
            font-weight: 600;
            display: block;
            margin-bottom: 6px;
            margin-top: 15px;
            color: #34495e;
        }
        select, input[type="text"], input[type="datetime-local"] {
            width: 100%;
            padding: 8px 12px;
            font-size: 1rem;
            border: 1px solid #ccc;
            border-radius: 4px;
            box-sizing: border-box;
        }
        .radio-group { margin-top: 10px; }
        .radio-group label { font-weight: normal; margin-right: 15px; cursor: pointer; }
        .radio-group input[type="radio"] { margin-right: 5px; cursor: pointer; }

        button {
            margin-top: 18px;
            background-color: #2980b9;
            color: white;
            font-weight: 600;
            font-size: 1rem;
            padding: 12px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
        }
        button:hover { background-color: #1f6391; }

        ul.errors {
            background: #fdecea;
            border: 1px solid #f5c6cb;
            color: #b71c1c;
            padding: 12px 20px;
            border-radius: 6px;
            list-style: none;
            max-width: 600px;
        }
        ul.errors li { margin-bottom: 6px; }

        p.success {
            max-width: 600px;
            background: #e9f7ef;
            border: 1px solid #a3d9a5;
            color: #2e7d32;
            padding: 12px 20px;
            border-radius: 6px;
            font-weight: 600;
        }

        .hint {
            max-width: 600px;
            background: #eef6fc;
            border: 1px solid #b3d4fc;
            padding: 10px 14px;
            border-radius: 6px;
            margin-bottom: 12px;
        }
    </style>
</head>
<body>
    <h2>Book an Appointment</h2>
    <p>
        <a href="../patients/dashboard.php">← Back to Dashboard</a> |
        <a href="../auth/logout.php">Logout</a>
    </p>

    <?php if ($success): ?>
        <p class="success"><?php echo htmlspecialchars($success); ?></p>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
        <ul class="errors">
            <?php foreach ($errors as $e): ?>
                <li><?php echo htmlspecialchars($e); ?></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <div class="hint">
        1) Select a specialty and click <b>Load Doctors</b><br>
        2) Then choose a doctor, date, payment, and click <b>Book Appointment</b>
    </div>

    <form method="POST" action="">
        <label for="specialty">Specialty:</label>
        <select name="specialty" id="specialty" required>
            <option value="">-- Select Specialty --</option>
            <?php foreach ($specialties as $spec): ?>
                <option value="<?php echo (int)$spec['id']; ?>" <?php echo ((int)$specialty_id === (int)$spec['id']) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($spec['name']); ?>
                </option>
            <?php endforeach; ?>
        </select>

        <button type="submit" name="action" value="load" formnovalidate>Load Doctors</button>

        <label for="doctor">Doctor:</label>
        <select name="doctor" id="doctor" required>
            <?php if ((int)$specialty_id <= 0): ?>
                <option value="">Select a specialty first</option>
            <?php else: ?>
                <option value="">-- Select Doctor --</option>
                <?php if (count($doctors) === 0): ?>
                    <option value="">No doctors found for this specialty</option>
                <?php else: ?>
                    <?php foreach ($doctors as $d): ?>
                        <option value="<?php echo (int)$d['id']; ?>" <?php echo ((int)$doctor_id === (int)$d['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($d['name']); ?>
                        </option>
                    <?php endforeach; ?>
                <?php endif; ?>
            <?php endif; ?>
        </select>

        <label for="appointment_date">Appointment Date &amp; Time:</label>
        <input type="datetime-local" name="appointment_date" value="<?php echo htmlspecialchars($appointment_date); ?>" required>

        <label>Payment Method:</label>
        <div class="radio-group">
            <input type="radio" name="payment_method" value="cash" id="pay_cash" <?php echo ($payment_method === 'cash') ? 'checked' : ''; ?>>
            <label for="pay_cash">Cash</label>

            <input type="radio" name="payment_method" value="card" id="pay_card" <?php echo ($payment_method === 'card') ? 'checked' : ''; ?>>
            <label for="pay_card">Card</label>
        </div>

        <label>Card Payment Type (only if Card):</label>
        <div class="radio-group">
            <input type="radio" name="card_payment_type" value="hospital" id="card_hospital" <?php echo ($card_payment_type === 'hospital') ? 'checked' : ''; ?>>
            <label for="card_hospital">Pay at Hospital</label>

            <input type="radio" name="card_payment_type" value="online" id="card_online" <?php echo ($card_payment_type === 'online') ? 'checked' : ''; ?>>
            <label for="card_online">Pay Online</label>
        </div>

        <label>Card Holder Name (only if Online):</label>
        <input type="text" name="card_holder_name" value="<?php echo htmlspecialchars($card_holder_name); ?>">

        <label>Card Number (only if Online):</label>
        <input type="text" name="card_number" maxlength="20" value="<?php echo htmlspecialchars($card_number); ?>">

        <label>Expiry Date (MM/YY) (only if Online):</label>
        <input type="text" name="card_expiry" maxlength="5" placeholder="MM/YY" value="<?php echo htmlspecialchars($card_expiry); ?>">

        <label>CVC (only if Online):</label>
        <input type="text" name="card_cvc" maxlength="4" value="<?php echo htmlspecialchars($card_cvc); ?>">

        <button type="submit" name="action" value="book">Book Appointment</button>
    </form>
</body>
</html>
<?php mysqli_close($conn); ?>
