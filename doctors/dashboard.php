<?php
session_start();
require_once '../includes/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'doctor') {
    header("Location: ../auth/login.php");
    exit();
}

$doctor_id = (int)$_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_checked'])) {
    $appointment_date = $_POST['appointment_date'] ?? '';

    if ($appointment_date !== '') {
        $stmt = mysqli_prepare($conn, "UPDATE appointments SET status='checked' WHERE doctor_id=? AND appointment_date=?");
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "is", $doctor_id, $appointment_date);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }
    }

    header("Location: dashboard.php");
    exit();
}

$appointments = [];

$stmt = mysqli_prepare($conn, "
    SELECT a.appointment_date, a.reason, a.status, u.name AS patient_name
    FROM appointments a
    INNER JOIN users u ON a.patient_id = u.id
    WHERE a.doctor_id = ?
    ORDER BY a.appointment_date ASC
");

if ($stmt) {
    mysqli_stmt_bind_param($stmt, "i", $doctor_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $appointments[] = $row;
        }
    }

    mysqli_stmt_close($stmt);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>Doctor Dashboard</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f4f7fa;
            margin: 0;
            padding: 20px;
            color: #333;
        }
        h2 { color: #2a7ae2; }
        a.logout-link {
            float: right;
            text-decoration: none;
            color: #999;
            font-size: 0.9rem;
            margin-top: -30px;
            border: 1px solid #ccc;
            padding: 5px 12px;
            border-radius: 4px;
            transition: background-color 0.3s, color 0.3s;
        }
        a.logout-link:hover {
            background-color: #2a7ae2;
            color: white;
            border-color: #2a7ae2;
        }
        table {
            border-collapse: collapse;
            width: 100%;
            max-width: 900px;
            margin-top: 20px;
            background: white;
            box-shadow: 0 0 10px rgba(0,0,0,0.05);
            border-radius: 8px;
            overflow: hidden;
        }
        th, td { padding: 12px 15px; text-align: left; }
        th {
            background-color: #2a7ae2;
            color: white;
            font-weight: 600;
            font-size: 1rem;
        }
        tr:nth-child(even) { background-color: #f9fbfd; }
        tr:hover { background-color: #e8f0fe; }

        button, a.button-link {
            background-color: #2a7ae2;
            border: none;
            color: white;
            padding: 8px 14px;
            font-size: 0.9rem;
            cursor: pointer;
            border-radius: 5px;
            text-decoration: none;
            display: inline-block;
            transition: background-color 0.3s ease;
        }
        button:hover, a.button-link:hover { background-color: #1958a3; }
        form { margin: 0; }
        td > form { display: inline; }
    </style>
</head>
<body>
    <h2>Welcome, Dr. <?php echo htmlspecialchars($_SESSION['user_name']); ?></h2>
    <a href="../auth/logout.php" class="logout-link">Logout</a>

    <h3>Upcoming Appointments</h3>

    <?php if (empty($appointments)): ?>
        <p>No appointments yet.</p>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Date &amp; Time</th>
                    <th>Patient</th>
                    <th>Reason</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($appointments as $appt): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($appt['appointment_date']); ?></td>
                        <td><?php echo htmlspecialchars($appt['patient_name']); ?></td>
                        <td><?php echo htmlspecialchars($appt['reason']); ?></td>
                        <td style="text-transform: capitalize;"><?php echo htmlspecialchars($appt['status']); ?></td>
                        <td>
                            <?php if ($appt['status'] === 'pending'): ?>
                                <form method="POST" action="dashboard.php">
                                    <input type="hidden" name="appointment_date" value="<?php echo htmlspecialchars($appt['appointment_date']); ?>">
                                    <button type="submit" name="mark_checked">Mark as Checked</button>
                                </form>
                            <?php elseif ($appt['status'] === 'checked'): ?>
                                <a href="prescribe.php?appointment_date=<?php echo urlencode($appt['appointment_date']); ?>" class="button-link">
                                    Add Prescription &amp; Finish
                                </a>
                            <?php else: ?>
                                <span style="color: green; font-weight: 600;">Finished</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</body>
</html>
<?php mysqli_close($conn); ?>
