<?php
session_start();
require_once '../includes/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'patient') {
    header("Location: ../auth/login.php");
    exit();
}

$patient_id = (int)$_SESSION['user_id'];
$user_name = $_SESSION['user_name'];

/* Upcoming appointments */
$upcoming_appointments = [];

$stmt = mysqli_prepare($conn, "
    SELECT 
        a.id,
        a.appointment_date,
        a.payment_method,
        a.card_payment_type,
        u.name AS doctor_name,
        GROUP_CONCAT(DISTINCT s.name SEPARATOR ', ') AS specialty_name,
        d.room_number,
        a.status
    FROM appointments a
    INNER JOIN users u ON a.doctor_id = u.id
    INNER JOIN doctor_specialty ds ON u.id = ds.doctor_id
    INNER JOIN specialties s ON ds.specialty_id = s.id
    INNER JOIN doctor_info d ON u.id = d.doctor_id
    WHERE a.patient_id = ? AND a.status IN ('pending','checked')
    GROUP BY a.id
    ORDER BY a.appointment_date ASC
");


if ($stmt) {
    mysqli_stmt_bind_param($stmt, "i", $patient_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);

    if ($res) {
        while ($row = mysqli_fetch_assoc($res)) {
            $upcoming_appointments[] = $row;
        }
    }

    mysqli_stmt_close($stmt);
}

/* Finished appointments */
$finished_appointments = [];

$stmt = mysqli_prepare($conn, "
    SELECT 
        a.id,
        a.appointment_date,
        u.name AS doctor_name,
        GROUP_CONCAT(s.name SEPARATOR ', ') AS specialty_name,
        d.room_number,
        a.doctor_notes
    FROM appointments a
    INNER JOIN users u ON a.doctor_id = u.id
    INNER JOIN doctor_specialty ds ON u.id = ds.doctor_id
    INNER JOIN specialties s ON ds.specialty_id = s.id
    INNER JOIN doctor_info d ON u.id = d.doctor_id
    WHERE a.patient_id = ? AND a.status = 'finished'
    GROUP BY a.id
    ORDER BY a.appointment_date DESC
");

if ($stmt) {
    mysqli_stmt_bind_param($stmt, "i", $patient_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);

    if ($res) {
        while ($row = mysqli_fetch_assoc($res)) {
            $finished_appointments[] = $row;
        }
    }

    mysqli_stmt_close($stmt);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>Patient Dashboard</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f5f8fa;
            color: #333;
            margin: 0;
            padding: 0 20px 40px 20px;
        }
        h2, h3 { color: #2c3e50; }
        a { color: #2980b9; text-decoration: none; }
        a:hover { text-decoration: underline; }
        p { margin-top: 0; margin-bottom: 15px; }
        .nav-links a { margin-right: 15px; font-weight: bold; }

        table {
            border-collapse: collapse;
            width: 100%;
            background: white;
            box-shadow: 0 0 10px rgba(0,0,0,0.05);
            margin-bottom: 40px;
        }
        th, td {
            padding: 12px 15px;
            border: 1px solid #ddd;
            text-align: left;
        }
        th {
            background-color: #2980b9;
            color: white;
            font-weight: 600;
        }
        tr:nth-child(even) { background-color: #f9fbfc; }
        tr:hover { background-color: #e8f0fe; }
        ul { padding-left: 20px; margin: 0; }

        @media (max-width: 768px) {
            table, thead, tbody, th, td, tr { display: block; }
            thead tr { display: none; }
            tr {
                margin-bottom: 20px;
                border-bottom: 2px solid #ddd;
                padding-bottom: 10px;
            }
            td {
                padding-left: 50%;
                position: relative;
            }
            td::before {
                content: attr(data-label);
                position: absolute;
                left: 15px;
                top: 12px;
                font-weight: 700;
                white-space: nowrap;
                color: #2980b9;
            }
        }
    </style>
</head>
<body>
    <h2>Welcome, <?php echo htmlspecialchars($user_name); ?>!</h2>

    <div class="nav-links">
        <a href="../auth/logout.php">Logout</a>
        <a href="book_appointment.php">+ Book a new appointment</a>
        <a href="medical_records.php">View Medical Records</a>
    </div>

    <h3>Your Upcoming Appointments</h3>
    <?php if (empty($upcoming_appointments)): ?>
        <p>No upcoming appointments.</p>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Doctor</th>
                    <th>Department</th>
                    <th>Room</th>
                    <th>Date &amp; Time</th>
                    <th>Payment Method</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($upcoming_appointments as $appt): ?>
                    <tr>
                        <td data-label="Doctor"><?php echo htmlspecialchars($appt['doctor_name']); ?></td>
                        <td data-label="Department"><?php echo htmlspecialchars($appt['specialty_name']); ?></td>
                        <td data-label="Room"><?php echo htmlspecialchars($appt['room_number']); ?></td>
                        <td data-label="Date & Time"><?php echo date('d M Y, H:i', strtotime($appt['appointment_date'])); ?></td>
                        <td data-label="Payment Method">
                            <?php echo htmlspecialchars(ucfirst($appt['payment_method'])); ?>
                            <?php if ($appt['payment_method'] === 'card'): ?>
                                (<?php echo htmlspecialchars($appt['card_payment_type']); ?>)
                            <?php endif; ?>
                        </td>
                        <td data-label="Status"><?php echo htmlspecialchars(ucfirst($appt['status'])); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <h3>Past Appointments & Reports</h3>
    <?php if (empty($finished_appointments)): ?>
        <p>No past appointments with reports.</p>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Date &amp; Time</th>
                    <th>Doctor</th>
                    <th>Department</th>
                    <th>Room</th>
                    <th>Doctor Notes</th>
                    <th>Prescriptions</th>
                    <th>Feedback</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($finished_appointments as $appt): ?>
                    <tr>
                        <td data-label="Date & Time"><?php echo date('d M Y, H:i', strtotime($appt['appointment_date'])); ?></td>
                        <td data-label="Doctor"><?php echo htmlspecialchars($appt['doctor_name']); ?></td>
                        <td data-label="Department"><?php echo htmlspecialchars($appt['specialty_name']); ?></td>
                        <td data-label="Room"><?php echo htmlspecialchars($appt['room_number']); ?></td>
                        <td data-label="Doctor Notes"><?php echo nl2br(htmlspecialchars($appt['doctor_notes'])); ?></td>

                        <td data-label="Prescriptions">
                            <?php
                            $stmt2 = mysqli_prepare($conn, "
                                SELECT m.name, p.dosage, p.instructions
                                FROM prescriptions p
                                INNER JOIN medications m ON p.medication_id = m.id
                                WHERE p.appointment_id = ?
                            ");

                            if ($stmt2) {
                                $appt_id = (int)$appt['id'];
                                mysqli_stmt_bind_param($stmt2, "i", $appt_id);
                                mysqli_stmt_execute($stmt2);
                                $presRes = mysqli_stmt_get_result($stmt2);

                                if ($presRes && mysqli_num_rows($presRes) > 0) {
                                    echo "<ul>";
                                    while ($pres = mysqli_fetch_assoc($presRes)) {
                                        echo "<li>" .
                                            htmlspecialchars($pres['name']) .
                                            " - Dosage: " . htmlspecialchars($pres['dosage']) .
                                            ", Instructions: " . htmlspecialchars($pres['instructions']) .
                                        "</li>";
                                    }
                                    echo "</ul>";
                                } else {
                                    echo "No prescriptions.";
                                }

                                mysqli_stmt_close($stmt2);
                            } else {
                                echo "No prescriptions.";
                            }
                            ?>
                        </td>

                        <td data-label="Feedback">
                            <a href="give_feedback.php?appointment_id=<?php echo (int)$appt['id']; ?>">Give Feedback</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</body>
</html>
<?php mysqli_close($conn); ?>
