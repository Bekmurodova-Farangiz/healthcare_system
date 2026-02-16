<?php
session_start();
require_once '../includes/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit();
}

if (isset($_GET['approve'])) {
    $approve_id = (int)$_GET['approve'];

    $stmt = mysqli_prepare($conn, "UPDATE users SET is_approved = 1 WHERE id = ? AND role = 'doctor'");
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "i", $approve_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }

    $check = mysqli_prepare($conn, "SELECT COUNT(*) FROM doctor_info WHERE doctor_id = ?");
    if ($check) {
        mysqli_stmt_bind_param($check, "i", $approve_id);
        mysqli_stmt_execute($check);
        mysqli_stmt_bind_result($check, $exists);
        mysqli_stmt_fetch($check);
        mysqli_stmt_close($check);

        if ((int)$exists === 0) {
            $room_number = "Unassigned";
            $insert = mysqli_prepare($conn, "INSERT INTO doctor_info (doctor_id, room_number) VALUES (?, ?)");
            if ($insert) {
                mysqli_stmt_bind_param($insert, "is", $approve_id, $room_number);
                mysqli_stmt_execute($insert);
                mysqli_stmt_close($insert);
            }
        }
    }

    header("Location: dashboard.php");
    exit();
}

$q = "";
if (isset($_GET['q'])) {
    $q = trim($_GET['q']);
}

$sql = "
    SELECT 
        u.id,
        u.name,
        u.email,
        u.is_approved,
        GROUP_CONCAT(DISTINCT s.name SEPARATOR ', ') AS specialties
    FROM users u
    LEFT JOIN doctor_specialty ds ON u.id = ds.doctor_id
    LEFT JOIN specialties s ON ds.specialty_id = s.id
    WHERE u.role = 'doctor'
";

$types = "";
$params = [];

if ($q !== "") {
    $like = "%" . $q . "%";

    if (ctype_digit($q)) {
        $sql .= " AND (
            u.id = ?
            OR u.name LIKE ?
            OR u.email LIKE ?
            OR s.name LIKE ?
        )";
        $types = "isss";
        $params = [(int)$q, $like, $like, $like];
    } else {
        $sql .= " AND (
            u.name LIKE ?
            OR u.email LIKE ?
            OR s.name LIKE ?
        )";
        $types = "sss";
        $params = [$like, $like, $like];
    }
}

$sql .= "
    GROUP BY u.id
    ORDER BY u.is_approved ASC, u.name ASC
";

$stmt = mysqli_prepare($conn, $sql);
if (!$stmt) {
    die("Query error");
}

if ($q !== "") {
    if ($types === "isss") {
        mysqli_stmt_bind_param($stmt, $types, $params[0], $params[1], $params[2], $params[3]);
    } else {
        mysqli_stmt_bind_param($stmt, $types, $params[0], $params[1], $params[2]);
    }
}

mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);

$users = [];
if ($res) {
    while ($row = mysqli_fetch_assoc($res)) {
        $users[] = $row;
    }
}

mysqli_stmt_close($stmt);

$doctor_feedback = [];
$ratings = mysqli_query($conn, "
    SELECT doctor_id, ROUND(AVG(rating),1) AS avg_rating, COUNT(*) AS total_reviews
    FROM feedback
    GROUP BY doctor_id
");
if ($ratings) {
    while ($row = mysqli_fetch_assoc($ratings)) {
        $doctor_feedback[$row['doctor_id']] = $row;
    }
}

$recent_comments = [];
$comments = mysqli_query($conn, "
    SELECT doctor_id, comments, created_at 
    FROM feedback
    WHERE comments IS NOT NULL AND comments != ''
    ORDER BY created_at DESC
");
if ($comments) {
    while ($c = mysqli_fetch_assoc($comments)) {
        $recent_comments[$c['doctor_id']][] = $c;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard</title>
    <style>
        :root {
            --bg: #121212;
            --text: #ffffff;
            --card: #1e1e1e;
            --table-head: #2a2a2a;
            --hover: #1a1f27;
            --badge-yes: #27ae60;
            --badge-no: #c0392b;
            --link: #2a7ae2;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: var(--bg);
            color: var(--text);
            margin: 0;
            padding: 30px;
        }

        h2 {
            text-align: center;
            color: var(--link);
        }

        .logout {
            text-align: center;
            margin: 10px 0;
        }

        a {
            color: var(--link);
            text-decoration: none;
            font-weight: 500;
        }

        .table-container {
            overflow-x: auto;
            background-color: var(--card);
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 1100px;
        }

        th, td {
            padding: 12px 16px;
            text-align: left;
            vertical-align: top;
        }

        th {
            background-color: var(--table-head);
            color: var(--text);
        }

        tr:hover {
            background-color: var(--hover);
        }

        .badge {
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 0.8rem;
            font-weight: bold;
        }

        .approved {
            background-color: var(--badge-yes);
            color: #fff;
        }

        .not-approved {
            background-color: var(--badge-no);
            color: #fff;
        }

        .rating {
            font-weight: bold;
            color: #f1c40f;
        }

        .feedback-comments {
            font-size: 0.85rem;
            margin-bottom: 5px;
        }

        .btn-approve {
            background-color: var(--link);
            color: white;
            padding: 6px 10px;
            border-radius: 4px;
            text-decoration: none;
            font-size: 0.85rem;
            display: inline-block;
        }

        .btn-approve:hover {
            background: #1958a3;
        }

        ul {
            padding-left: 16px;
            margin: 0;
        }

        small {
            color: #aaa;
        }

        .search-box {
            margin: 15px auto 20px auto;
            max-width: 700px;
            display: flex;
            gap: 10px;
        }

        .search-box input {
            flex: 1;
            padding: 10px;
            border-radius: 6px;
            border: 1px solid #333;
            background: #0f0f0f;
            color: var(--text);
        }

        .search-box button, .search-box a {
            padding: 10px 14px;
            border-radius: 6px;
            border: none;
            background: var(--link);
            color: #fff;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
        }

        .search-box a {
            background: #444;
        }
    </style>
</head>
<body>
    <h2>Admin Dashboard - Doctors</h2>

    <div class="logout">
        <a href="../auth/logout.php">🔓 Logout</a>
    </div>

    <form class="search-box" method="GET" action="dashboard.php">
        <input type="text" name="q" value="<?php echo htmlspecialchars($q); ?>" placeholder="Search by ID, name, email, specialty">
        <button type="submit">Search</button>
        <a href="dashboard.php">Reset</a>
    </form>

    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>#ID</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Specialties</th>
                    <th>Status</th>
                    <th>Avg Rating</th>
                    <th>Recent Feedback</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($users) > 0): ?>
                    <?php foreach ($users as $u): ?>
                        <tr>
                            <td><?php echo (int)$u['id']; ?></td>
                            <td><?php echo htmlspecialchars($u['name']); ?></td>
                            <td><?php echo htmlspecialchars($u['email']); ?></td>
                            <td><?php echo htmlspecialchars($u['specialties'] ?? '-'); ?></td>
                            <td>
                                <span class="badge <?php echo ((int)$u['is_approved'] === 1) ? 'approved' : 'not-approved'; ?>">
                                    <?php echo ((int)$u['is_approved'] === 1) ? 'Approved' : 'Pending'; ?>
                                </span>
                            </td>
                            <td>
                                <?php if (isset($doctor_feedback[$u['id']])): ?>
                                    <span class="rating"><?php echo htmlspecialchars($doctor_feedback[$u['id']]['avg_rating']); ?> ⭐</span>
                                    (<?php echo (int)$doctor_feedback[$u['id']]['total_reviews']; ?>)
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php
                                if (isset($recent_comments[$u['id']])) {
                                    echo "<ul>";
                                    foreach (array_slice($recent_comments[$u['id']], 0, 2) as $fb) {
                                        echo "<li class='feedback-comments'>" . htmlspecialchars($fb['comments']) .
                                            "<br><small>(" . date("d M Y", strtotime($fb['created_at'])) . ")</small></li>";
                                    }
                                    echo "</ul>";
                                } else {
                                    echo "-";
                                }
                                ?>
                            </td>
                            <td>
                                <?php if ((int)$u['is_approved'] === 0): ?>
                                    <a href="?approve=<?php echo (int)$u['id']; ?>" class="btn-approve">✅ Approve</a>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="8">No doctors found</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

</body>
</html>

<?php
mysqli_close($conn);
?>
