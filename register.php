<?php
require 'db.php';
$message = ""; $status = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("Security token validation failed.");
    }
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    if (strlen($password) < 12 || !preg_match('/[A-Z]/', $password) || !preg_match('/[a-z]/', $password) || !preg_match('/[0-9]/', $password) || !preg_match('/[^a-zA-Z0-9]/', $password)) {
        $message = "Registration Rejected: Password strategy violation."; $status = "error";
    } else if (!empty($username)) {
        $hashed_password = password_hash($password, PASSWORD_ARGON2ID);
        $stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute(); $stmt->store_result();

        if ($stmt->num_rows > 0) {
            $message = "Identity unavailable."; $status = "error";
        } else {
            $stmt->close();
            $stmt = $conn->prepare("INSERT INTO users (username, password) VALUES (?, ?)");
            $stmt->bind_param("ss", $username, $hashed_password);
            if ($stmt->execute()) {
                $message = "Account provisioned."; $status = "success";
            }
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Provision Security Node</title>
    <style>
        body { font-family: 'Segoe UI', Arial, sans-serif; display: flex; justify-content: center; align-items: center; height: 100vh; background-color: #0f172a; color: #fff; margin: 0; }
        .box { background: #1e293b; padding: 40px; border-radius: 8px; width: 360px; border: 1px solid #334155; }
        h2 { margin-top: 0; color: #10b981; text-align: center; }
        input { width: 100%; padding: 12px; margin: 10px 0; border: 1px solid #475569; border-radius: 4px; background: #0f172a; color: #fff; box-sizing: border-box; outline: none; }
        button { width: 100%; padding: 12px; background: #10b981; border: none; color: white; font-weight: bold; border-radius: 4px; cursor: pointer; }
        .error { color: #f87171; background: rgba(248,113,113,0.1); padding: 10px; border-radius: 4px; margin-bottom: 15px; }
        .success { color: #34d399; background: rgba(52,211,153,0.1); padding: 10px; border-radius: 4px; margin-bottom: 15px; }
    </style>
</head>
<body>
    <div class="box">
        <h2>Register Node</h2>
        <?php 
        if ($status === "success") echo "<div class='success'>".$message." <a href='index.php' style='color:#38bdf8;'>Sign In</a></div>";
        if ($status === "error") echo "<div class='error'>".$message."</div>";
        ?>
        <form method="POST" action="register.php">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
            <input type="text" name="username" placeholder="Username Allocation" required>
            <input type="password" name="password" placeholder="Complex Strategy Password" required>
            <button type="submit">Establish Identity</button>
        </form>
    </div>
</body>
</html>