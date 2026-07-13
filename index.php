<?php
require 'db.php';

// Condition A: If the admin is already logged in, take them straight to the dashboard
if (isset($_SESSION['user_id'])) {
    header("Location: home.php");
    exit;
}

$error = "";

// Condition B: Process the secure admin authentication form submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['username'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        log_system_event($conn, 'ANONYMOUS', 'CSRF_VALIDATION_FAILURE');
        die("Security token validation failed.");
    }
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);
    $now = date('Y-m-d H:i:s');

    $stmt = $conn->prepare("SELECT id, password, login_attempts, lock_until FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $stmt->store_result();
    
    if ($stmt->num_rows > 0) {
        $stmt->bind_result($id, $hashed_password, $login_attempts, $lock_until);
        $stmt->fetch();
        
        if ($lock_until && $lock_until > $now) {
            $error = "Account locked temporarily due to successive initialization drops.";
        } else {
            if (password_verify($password, $hashed_password)) {
                $reset_stmt = $conn->prepare("UPDATE users SET login_attempts = 0, lock_until = NULL WHERE id = ?");
                $reset_stmt->bind_param("i", $id);
                $reset_stmt->execute(); $reset_stmt->close();

                $_SESSION['user_id'] = $id;
                $_SESSION['username'] = $username;
                $_SESSION['last_activity'] = time();
                header("Location: home.php");
                exit;
            } else {
                $login_attempts++;
                if ($login_attempts >= 5) {
                    $lock_until = date('Y-m-d H:i:s', strtotime('+10 minutes'));
                    $lock_stmt = $conn->prepare("UPDATE users SET login_attempts = ?, lock_until = ? WHERE id = ?");
                    $lock_stmt->bind_param("isi", $login_attempts, $lock_until, $id);
                } else {
                    $lock_stmt = $conn->prepare("UPDATE users SET login_attempts = ? WHERE id = ?");
                    $lock_stmt->bind_param("ii", $login_attempts, $id);
                }
                $lock_stmt->execute(); $lock_stmt->close();
                $error = "Invalid connection details.";
            }
        }
    } else {
        $error = "Invalid connection details.";
    }
    $stmt->close();
}

// Condition C: If there's an error OR you explicitly clicked the admin login button, show the login interface
if (!empty($error) || (isset($_GET['action']) && $_GET['action'] === 'login')) {
    // Execution falls through to render the HTML login box below
} else {
    // Condition D: Default normal browsing behavior sends users straight to the shop interface
    header("Location: shop.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Secure Core Portal</title>
    <style>
        body { font-family: 'Segoe UI', Arial, sans-serif; display: flex; justify-content: center; align-items: center; height: 100vh; background-color: #0f172a; color: #fff; margin: 0; }
        .box { background: #1e293b; padding: 40px; border-radius: 8px; width: 340px; border: 1px solid #334155; }
        h2 { margin-top: 0; color: #38bdf8; text-align: center; }
        input { width: 100%; padding: 12px; margin: 10px 0; border: 1px solid #475569; border-radius: 4px; background: #0f172a; color: #fff; outline: none; box-sizing: border-box; }
        button { width: 100%; padding: 12px; background: #0284c7; border: none; color: white; border-radius: 4px; cursor: pointer; font-weight: bold; }
        .error { color: #f87171; background: rgba(248,113,113,0.1); padding: 10px; font-size: 14px; border-radius: 4px; text-align: center; margin-bottom: 10px; }
        .back-btn { display: block; text-align: center; margin-top: 15px; color: #94a3b8; text-decoration: none; font-size: 13px; }
        .back-btn:hover { color: #fff; }
    </style>
</head>
<body>
    <div class="box">
        <h2>Secure Sign In</h2>
        <?php if (!empty($error)) echo "<div class='error'>".htmlspecialchars($error)."</div>"; ?>
        <form method="POST" action="index.php?action=login">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
            <input type="text" name="username" placeholder="Identity Target" required autocomplete="off">
            <input type="password" name="password" placeholder="Passkey Pattern" required>
            <button type="submit">Authenticate</button>
        </form>
        <a href="shop.php" class="back-btn">← Back to Storefront</a>
    </div>
</body>
</html>
