<?php
session_start();
require_once '../includes/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'patient') {
    header("Location: ../auth/login.php");
    exit();
}

$appointment_id = isset($_GET['appointment_id']) ? (int)$_GET['appointment_id'] : 0;
$patient_id = (int)$_SESSION['user_id'];

if ($appointment_id <= 0) {
    die("Invalid appointment.");
}

$doctor_id = 0;

$stmt = mysqli_prepare($conn, "SELECT doctor_id FROM appointments WHERE id = ? AND patient_id = ? AND status = 'finished' LIMIT 1");
if (!$stmt) {
    die("Server error.");
}

mysqli_stmt_bind_param($stmt, "ii", $appointment_id, $patient_id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);

if (!$res || mysqli_num_rows($res) === 0) {
    mysqli_stmt_close($stmt);
    die("You can't give feedback for this appointment.");
}

$row = mysqli_fetch_assoc($res);
$doctor_id = (int)$row['doctor_id'];
mysqli_stmt_close($stmt);

$already = false;
$chk = mysqli_prepare($conn, "SELECT id FROM feedback WHERE appointment_id = ? AND patient_id = ? LIMIT 1");
if ($chk) {
    mysqli_stmt_bind_param($chk, "ii", $appointment_id, $patient_id);
    mysqli_stmt_execute($chk);
    $chkRes = mysqli_stmt_get_result($chk);
    if ($chkRes && mysqli_num_rows($chkRes) > 0) {
        $already = true;
    }
    mysqli_stmt_close($chk);
}

$errors = [];
$success = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rating = isset($_POST['rating']) ? (int)$_POST['rating'] : 0;
    $comment = trim($_POST['comment'] ?? '');

    if ($already) {
        $errors[] = "You already submitted feedback for this appointment.";
    }

    if ($rating < 1 || $rating > 5) {
        $errors[] = "Rating must be between 1 and 5.";
    }

    if (empty($errors)) {
        $stmt = mysqli_prepare($conn, "INSERT INTO feedback (patient_id, doctor_id, appointment_id, rating, comments) VALUES (?, ?, ?, ?, ?)");
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "iiiis", $patient_id, $doctor_id, $appointment_id, $rating, $comment);

            if (mysqli_stmt_execute($stmt)) {
                $success = "✅ Feedback submitted!";
                $already = true;
            } else {
                $errors[] = "❌ Failed to submit feedback.";
            }

            mysqli_stmt_close($stmt);
        } else {
            $errors[] = "Server error. Please try again.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Give Feedback</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f9faff;
            margin: 0;
            padding: 0;
        }
        .container {
            max-width: 600px;
            margin: 50px auto 0 auto;
            padding: 25px;
            background-color: #fff;
            border-radius: 10px;
            box-shadow: 0 3px 15px rgba(0,0,0,0.1);
            text-align: center;
        }
        h2 { color: #2a7ae2; margin-bottom: 10px; }
        a {
            display: inline-block;
            margin-bottom: 20px;
            color: #2a7ae2;
            text-decoration: none;
        }
        form { text-align: left; }
        label {
            font-weight: 600;
            display: block;
            margin-top: 15px;
            margin-bottom: 5px;
        }
        input[type="number"], textarea {
            width: 100%;
            padding: 10px;
            font-size: 1rem;
            border: 1px solid #ccc;
            border-radius: 5px;
            box-sizing: border-box;
        }
        textarea { resize: vertical; }
        button[type="submit"] {
            margin-top: 20px;
            background-color: #2a7ae2;
            border: none;
            color: white;
            padding: 12px 20px;
            border-radius: 5px;
            font-size: 1rem;
            cursor: pointer;
            transition: background-color 0.3s ease;
            width: 100%;
        }
        button[type="submit"]:hover { background-color: #1958a3; }
        .success { color: #27ae60; font-weight: bold; margin-bottom: 10px; }
        .error-list {
            color: #e74c3c;
            text-align: left;
            padding-left: 20px;
            margin-bottom: 10px;
        }
        ul li { margin-bottom: 6px; }
        .disabled-note {
            background: #eef6fc;
            border: 1px solid #b3d4fc;
            padding: 10px 12px;
            border-radius: 6px;
            text-align: left;
            margin-bottom: 10px;
        }
    </style>
</head>
<body>
<div class="container">
    <h2>Give Feedback</h2>
    <a href="dashboard.php">⬅️ Back to Dashboard</a>

    <?php if ($success): ?>
        <p class="success"><?php echo htmlspecialchars($success); ?></p>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
        <ul class="error-list">
            <?php foreach ($errors as $e): ?>
                <li><?php echo htmlspecialchars($e); ?></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <?php if ($already): ?>
        <div class="disabled-note">
            Feedback for this appointment is already submitted ✅
        </div>
    <?php else: ?>
        <form method="POST" action="">
            <label for="rating">Rating (1-5):</label>
            <input type="number" id="rating" name="rating" min="1" max="5" required>

            <label for="comment">Comment:</label>
            <textarea id="comment" name="comment" rows="4" placeholder="Write your feedback here..."></textarea>

            <button type="submit">Submit Feedback</button>
        </form>
    <?php endif; ?>
</div>
</body>
</html>
<?php mysqli_close($conn); ?>
