<?php
require_once '../includes/db.php';

header('Content-Type: application/json; charset=utf-8');

$specialty_id = isset($_GET['specialty_id']) ? (int)$_GET['specialty_id'] : 0;

if ($specialty_id <= 0) {
    echo json_encode([]);
    exit();
}

$sql = "
    SELECT u.id, u.name
    FROM users u
    INNER JOIN doctor_specialty ds ON u.id = ds.doctor_id
    WHERE ds.specialty_id = ? AND u.is_approved = 1 AND u.role = 'doctor'
    ORDER BY u.name
";

$stmt = mysqli_prepare($conn, $sql);
if (!$stmt) {
    echo json_encode([]);
    exit();
}

mysqli_stmt_bind_param($stmt, "i", $specialty_id);
mysqli_stmt_execute($stmt);

$res = mysqli_stmt_get_result($stmt);

$doctors = [];
if ($res) {
    while ($row = mysqli_fetch_assoc($res)) {
        $doctors[] = [
            'id' => (int)$row['id'],
            'name' => $row['name']
        ];
    }
}

mysqli_stmt_close($stmt);
mysqli_close($conn);

echo json_encode($doctors);
?>
