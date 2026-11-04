<?php
session_start();

// If already logged in, skip the form entirely.
if (isset($_SESSION['userName'])) {
    header("Location: dashboard.php");
    exit();
}

$current_page = 'login';
$servername = "pharmacyinventory-pims.g.aivencloud.com";
$dbUser = "avnadmin";
$dbPass = "AVNS_i_8rycbSeTXKyVFFeS1";
$port = "24966";

try {
    $db = new PDO("mysql:host=$servername;  port=$port; dbname=defaultdb", $dbUser, $dbPass);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username !== "" && $password !== "") {
        // Select user by username using a prepared statement to prevent SQL injection
        $stmt = $db->prepare("SELECT * FROM users WHERE userName = :username LIMIT 1");
        $stmt->execute(['username' => $username]);
        $rep = $stmt->fetch();

        $authenticated = false;

        if ($rep !== false) {
            // 1. Verify hashed password with password_verify()
            if (password_verify($password, $rep['password'])) {
                $authenticated = true;
                // Re-hash if algorithm or cost parameters have changed
                if (password_needs_rehash($rep['password'], PASSWORD_DEFAULT)) {
                    $newHash = password_hash($password, PASSWORD_DEFAULT);
                    $upd = $db->prepare("UPDATE users SET password = :password WHERE userId = :id");
                    $upd->execute(['password' => $newHash, 'id' => $rep['userId']]);
                }
            }
            // 2. Migration for existing accounts: verify old plain-text password once and immediately re-hash
            elseif ($rep['password'] === $password || hash_equals($rep['password'], $password)) {
                $authenticated = true;
                $newHash = password_hash($password, PASSWORD_DEFAULT);
                $upd = $db->prepare("UPDATE users SET password = :password WHERE userId = :id");
                $upd->execute(['password' => $newHash, 'id' => $rep['userId']]);
            }
        }

        if ($authenticated) {
            if (isset($rep['status']) && strtolower($rep['status']) === 'inactive') {
                $error_msg = "Your account is inactive. Please contact an administrator.";
            } else {
                $_SESSION['userName'] = $rep['userName'];
                $_SESSION['fullName'] = $rep['fullName'];
                $_SESSION['role']     = $rep['role'];
                $_SESSION['userId']   = $rep['userId'];

                header("Location: dashboard.php");
                exit();
            }
        } else {
            $error_msg = "Username or password incorrect !";
        }
    } else {
        $error_msg = "Please enter both username and password.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>
    <link rel="stylesheet" href="includes/style.css">
</head>

<body class="login-page" style="background-color: #1e303d;">

    <div class="main-container" style="background-color: #1e303d;">
        <div class="logo-container">
            <img src="includes/logo.png" alt="Logo">
        </div>

        <div class="login-card">
            <h1>Login</h1>
            <p class="subtitle">Enter your credentials to access</p>
            <form action="" method="POST">
                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" name="username" placeholder="e.g admin" required>
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" name="password" placeholder="e.g admin123" required>
                </div>

                <button type="submit" class="login-btn">Login</button>
            </form>
            <?php if (isset($error_msg)): ?>
                <p><?php echo htmlspecialchars($error_msg); ?></p>
            <?php endif; ?>
        </div>
    </div>

    <br>
    <?php require_once 'includes/footer.php'; ?>

</body>

</html>
