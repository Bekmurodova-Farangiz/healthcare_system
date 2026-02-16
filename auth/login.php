<?php
session_start();
require_once '../includes/db.php';

$email = '';
$password = '';
$errors = [];

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($email === '' || $password === '') {
        $errors[] = "Please enter email and password.";
    } else {
        $sql = "SELECT id, name, password, role, is_approved FROM users WHERE email = ? LIMIT 1";
        $stmt = mysqli_prepare($conn, $sql);

        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "s", $email);
            mysqli_stmt_execute($stmt);

            $res = mysqli_stmt_get_result($stmt);

            if ($res && mysqli_num_rows($res) === 1) {
                $row = mysqli_fetch_assoc($res);

                $id = (int)$row['id'];
                $name = $row['name'];
                $hashed_password = $row['password'];
                $role = $row['role'];
                $is_approved = (int)$row['is_approved'];

                if (password_verify($password, $hashed_password)) {
                    if ($role === 'doctor' && $is_approved === 0) {
                        $errors[] = "Your account is pending admin approval.";
                    } else {
                        $_SESSION['user_id'] = $id;
                        $_SESSION['user_name'] = $name;
                        $_SESSION['user_role'] = $role;

                        if ($role === 'doctor') {
                            header("Location: ../doctors/dashboard.php");
                        } elseif ($role === 'admin') {
                            header("Location: ../admin/dashboard.php");
                        } else {
                            header("Location: ../patients/dashboard.php");
                        }
                        exit();
                    }
                } else {
                    $errors[] = "Invalid email or password.";
                }
            } else {
                $errors[] = "Invalid email or password.";
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
    <meta charset="UTF-8" />
    <title>Login</title>
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #1b2735, #090a0f);
            height: 100vh;
            margin: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            color: #eee;
        }
        .login-container {
            background: #222831;
            padding: 30px 40px;
            border-radius: 12px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.7);
            width: 320px;
            text-align: center;
        }
        h1.welcome {
            color: #00adb5;
            margin-bottom: 20px;
            font-weight: 700;
            font-size: 1.5rem;
        }
        h2 { margin-bottom: 25px; color: #eeeeee; }
        label {
            display: block;
            text-align: left;
            margin-bottom: 6px;
            font-weight: 600;
            color: #eeeeee;
        }
        input[type="email"], input[type="password"] {
            width: 100%;
            padding: 10px 12px;
            margin-bottom: 20px;
            border: 1.8px solid #393e46;
            border-radius: 5px;
            background-color: #393e46;
            color: #eeeeee;
            transition: border-color 0.3s ease;
            font-size: 14px;
        }
        input[type="email"]:focus, input[type="password"]:focus {
            border-color: #00adb5;
            outline: none;
            background-color: #222831;
        }
        button {
            width: 100%;
            background-color: #00adb5;
            color: white;
            font-weight: 600;
            padding: 12px;
            font-size: 16px;
            border: none;
            border-radius: 7px;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }
        button:hover { background-color: #007f87; }
        ul.errors {
            color: #ff5555;
            margin-bottom: 20px;
            padding-left: 18px;
            text-align: left;
        }
        p.register-link {
            margin-top: 15px;
            font-size: 14px;
            color: #cccccc;
        }
        p.register-link a {
            color: #00adb5;
            text-decoration: none;
            font-weight: 600;
        }
        p.register-link a:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <div class="login-container">
        <h1 class="welcome">Welcome to Turaev's hospital!</h1>
        <h2>Login</h2>

        <?php if (!empty($errors)): ?>
            <ul class="errors">
                <?php foreach ($errors as $e): ?>
                    <li><?php echo htmlspecialchars($e); ?></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <form method="POST" action="">
            <label for="email">Email:</label>
            <input type="email" id="email" name="email" required value="<?php echo htmlspecialchars($email); ?>">

            <label for="password">Password:</label>
            <input type="password" id="password" name="password" required>

            <button type="submit">Login</button>
        </form>

        <p class="register-link">Don't have an account? <a href="register.php">Register here</a></p>
    </div>
</body>
</html>
