<?php
session_start();
require_once '../includes/db.php';

$name = '';
$email = '';
$password = '';
$role = '';
$specialties = [];
$errors = [];

$specialtiesList = [];
$result = mysqli_query($conn, "SELECT id, name FROM specialties ORDER BY name ASC");
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $specialtiesList[] = $row;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name        = trim($_POST['name'] ?? '');
    $email       = trim($_POST['email'] ?? '');
    $password    = $_POST['password'] ?? '';
    $role        = $_POST['role'] ?? '';
    $specialties = $_POST['specialties'] ?? [];

    if ($name === '' || $email === '' || $password === '' || $role === '') {
        $errors[] = "Please fill in all required fields.";
    }

    if ($role !== 'patient' && $role !== 'doctor') {
        $errors[] = "Please select a valid role.";
    }

    if ($role === 'doctor' && empty($specialties)) {
        $errors[] = "Please select at least one specialty.";
    }

    if (empty($errors)) {
        $check = mysqli_prepare($conn, "SELECT id FROM users WHERE email = ? LIMIT 1");
        if (!$check) {
            $errors[] = "Server error. Please try again.";
        } else {
            mysqli_stmt_bind_param($check, "s", $email);
            mysqli_stmt_execute($check);
            $checkRes = mysqli_stmt_get_result($check);

            if ($checkRes && mysqli_num_rows($checkRes) > 0) {
                $errors[] = "Email is already registered.";
                mysqli_stmt_close($check);
            } else {
                mysqli_stmt_close($check);

                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $is_approved = ($role === 'doctor') ? 0 : 1;

                $ins = mysqli_prepare($conn, "INSERT INTO users (name, email, password, role, is_approved) VALUES (?, ?, ?, ?, ?)");
                if (!$ins) {
                    $errors[] = "Something went wrong. Please try again.";
                } else {
                    mysqli_stmt_bind_param($ins, "ssssi", $name, $email, $hashed_password, $role, $is_approved);

                    if (mysqli_stmt_execute($ins)) {
                        $user_id = mysqli_insert_id($conn);
                        mysqli_stmt_close($ins);

                        if ($role === 'doctor') {
                            $stmtSpec = mysqli_prepare($conn, "INSERT INTO doctor_specialty (doctor_id, specialty_id) VALUES (?, ?)");
                            if ($stmtSpec) {
                                foreach ($specialties as $spec_id) {
                                    $spec_id = (int)$spec_id;
                                    mysqli_stmt_bind_param($stmtSpec, "ii", $user_id, $spec_id);
                                    mysqli_stmt_execute($stmtSpec);
                                }
                                mysqli_stmt_close($stmtSpec);
                            }
                        }

                        header("Location: login.php");
                        exit();
                    } else {
                        mysqli_stmt_close($ins);
                        $errors[] = "Something went wrong. Please try again.";
                    }
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>Register</title>
    <style>
        * { box-sizing: border-box; }
        body {
            background: linear-gradient(135deg, #1b2735, #090a0f);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: #eee;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            padding: 20px;
        }
        .register-container {
            background-color: #222831;
            padding: 30px 35px;
            border-radius: 12px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.7);
            width: 380px;
        }
        h2 {
            text-align: center;
            margin-bottom: 25px;
            color: #00adb5;
            font-weight: 700;
            font-size: 1.6rem;
        }
        label {
            display: block;
            margin-bottom: 6px;
            font-weight: 600;
            color: #eeeeee;
        }
        input[type="text"], input[type="email"], input[type="password"], select {
            width: 100%;
            padding: 10px 12px;
            margin-bottom: 20px;
            border-radius: 5px;
            border: 1.8px solid #393e46;
            background-color: #393e46;
            color: #eeeeee;
            font-size: 14px;
        }
        input:focus, select:focus {
            outline: none;
            border-color: #00adb5;
            background-color: #222831;
        }

        .note {
            background: #1f2a36;
            border: 1px solid #2d3b4a;
            padding: 10px 12px;
            border-radius: 8px;
            margin-bottom: 12px;
            font-size: 0.92rem;
            color: #d7e7ff;
        }

        .specialtyField {
            padding: 10px;
            background-color: #393e46;
            border-radius: 8px;
            max-height: 180px;
            overflow-y: auto;
            margin-bottom: 18px;
        }
        .checkbox-row {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 10px;
        }
        .checkbox-row input {
            transform: scale(1.1);
            cursor: pointer;
        }
        .checkbox-row label {
            margin: 0;
            font-weight: 500;
        }

        button {
            width: 100%;
            padding: 12px;
            background-color: #00adb5;
            border: none;
            border-radius: 7px;
            color: white;
            font-weight: 700;
            font-size: 16px;
            cursor: pointer;
        }
        button:hover { background-color: #007f87; }

        ul.errors {
            color: #ff5555;
            margin-bottom: 20px;
            padding-left: 20px;
            text-align: left;
        }

        .login-link {
            margin-top: 15px;
            text-align: center;
            font-size: 0.9rem;
        }
        .login-link a {
            color: #00adb5;
            text-decoration: none;
            font-weight: 600;
        }
        .login-link a:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <div class="register-container">
        <h2>Register (Patient or Doctor)</h2>

        <?php if (!empty($errors)): ?>
            <ul class="errors">
                <?php foreach ($errors as $e): ?>
                    <li><?php echo htmlspecialchars($e); ?></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <div class="note">
            If you register as <b>Doctor</b>, select at least one specialty (your account will be pending admin approval).
        </div>

        <form method="POST" action="">
            <label for="name">Name:</label>
            <input type="text" id="name" name="name" required value="<?php echo htmlspecialchars($name); ?>">

            <label for="email">Email:</label>
            <input type="email" id="email" name="email" required value="<?php echo htmlspecialchars($email); ?>">

            <label for="password">Password:</label>
            <input type="password" id="password" name="password" required>

            <label for="role">Role:</label>
            <select name="role" id="role" required>
                <option value="">-- Select Role --</option>
                <option value="patient" <?php echo ($role === 'patient') ? 'selected' : ''; ?>>Patient</option>
                <option value="doctor" <?php echo ($role === 'doctor') ? 'selected' : ''; ?>>Doctor</option>
            </select>

            <label>Select Specialties (only if Doctor):</label>
            <div class="specialtyField">
                <?php foreach ($specialtiesList as $spec): ?>
                    <div class="checkbox-row">
                        <input
                            type="checkbox"
                            id="spec_<?php echo (int)$spec['id']; ?>"
                            name="specialties[]"
                            value="<?php echo (int)$spec['id']; ?>"
                            <?php echo in_array($spec['id'], $specialties) ? 'checked' : ''; ?>
                        >
                        <label for="spec_<?php echo (int)$spec['id']; ?>">
                            <?php echo htmlspecialchars($spec['name']); ?>
                        </label>
                    </div>
                <?php endforeach; ?>
            </div>

            <button type="submit">Register</button>
        </form>

        <div class="login-link">
            Already have an account? <a href="login.php">Login here</a>
        </div>
    </div>
</body>
</html>
<?php mysqli_close($conn); ?>
