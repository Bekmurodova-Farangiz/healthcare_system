<?php
session_start();
require_once '../includes/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'patient') {
    header("Location: ../auth/login.php");
    exit();
}

$patient_id = (int)$_SESSION['user_id'];
$user_name = $_SESSION['user_name'];

$records = [];

$stmt = mysqli_prepare($conn, "
    SELECT 
        mr.created_at,
        u.name AS doctor_name,
        mr.doctor_notes,
        a.appointment_date
    FROM medical_records mr
    INNER JOIN users u ON mr.doctor_id = u.id
    INNER JOIN appointments a ON mr.appointment_id = a.id
    WHERE mr.patient_id = ?
    ORDER BY mr.created_at DESC
");

if ($stmt) {
    mysqli_stmt_bind_param($stmt, "i", $patient_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);

    if ($res) {
        while ($row = mysqli_fetch_assoc($res)) {
            $records[] = $row;
        }
    }

    mysqli_stmt_close($stmt);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>Your Medical Records</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #fefefe;
            color: #2c3e50;
            margin: 30px;
        }
        h2 {
            color: #34495e;
            margin-bottom: 25px;
        }
        a {
            color: #2980b9;
            text-decoration: none;
            font-weight: 600;
            margin-bottom: 20px;
            display: inline-block;
        }
        a:hover { text-decoration: underline; }
        table {
            border-collapse: collapse;
            width: 100%;
            max-width: 900px;
            background: white;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            border-radius: 6px;
            overflow: hidden;
        }
        thead {
            background-color: #2980b9;
            color: white;
        }
        th, td {
            text-align: left;
            padding: 14px 18px;
            border-bottom: 1px solid #ddd;
            vertical-align: top;
        }
        tbody tr:hover { background-color: #f1f9ff; }
        td { word-break: break-word; }
    </style>
</head>
<body>
    <h2>Medical Records for <?php echo htmlspecialchars($user_name); ?></h2>
    <a href="dashboard.php">← Back to Dashboard</a>

    <?php if (empty($records)): ?>
        <p>No medical records found.</p>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Date Created</th>
                    <th>Doctor</th>
                    <th>Appointment Date</th>
                    <th>Doctor Notes</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($records as $rec): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($rec['created_at']); ?></td>
                        <td><?php echo htmlspecialchars($rec['doctor_name']); ?></td>
                        <td><?php echo date('d M Y, H:i', strtotime($rec['appointment_date'])); ?></td>
                        <td><?php echo nl2br(htmlspecialchars($rec['doctor_notes'])); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</body>
</html>
<?php mysqli_close($conn); ?>
