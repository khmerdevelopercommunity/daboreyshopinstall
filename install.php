<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$db_file = 'db.php';

// Security Filter: If already installed correctly with active parameters, deny setup access
if (file_exists($db_file)) {
    include $db_file;
    if (isset($conn) && !$conn->connect_error && !empty($host)) {
        die("<h3 style='color:#ef4444; font-family:sans-serif; text-align:center; margin-top:50px;'>Security Guard: System is already configured. Delete your db.php file to overwrite configuration variables manually.</h3>");
    }
}

$error = "";
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $db_host = trim($_POST['db_host'] ?? 'localhost');
    $db_user = trim($_POST['db_user'] ?? 'root');
    $db_pass = trim($_POST['db_pass'] ?? '');
    $db_name = trim($_POST['db_name'] ?? '');
    
    $admin_user = trim($_POST['admin_user'] ?? 'admin');
    $admin_pass = trim($_POST['admin_pass'] ?? '');

    // 1. Initial Handshake Link
    $conn = @new mysqli($db_host, $db_user, $db_pass);
    if ($conn->connect_error) {
        $error = "Database Server Handshake Failure: " . $conn->connect_error;
    } else {
        // 2. Automate Schema Creation
        $conn->query("CREATE DATABASE IF NOT EXISTS `$db_name` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        
        if (!$conn->select_db($db_name)) {
            $error = "Database access denied. Please verify user permission rules for account user: '$db_user'.";
        } else {
            // 3. Sequential Table Installation Pipelines
            $queries = [
                "CREATE TABLE IF NOT EXISTS `users` (
                  `id` int(11) NOT NULL AUTO_INCREMENT,
                  `username` varchar(50) NOT NULL,
                  `password` varchar(255) NOT NULL,
                  `login_attempts` int(11) DEFAULT 0,
                  `lock_until` datetime DEFAULT NULL,
                  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                  PRIMARY KEY (`id`),
                  UNIQUE KEY `username` (`username`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

                "CREATE TABLE IF NOT EXISTS `products` (
                  `id` int(11) NOT NULL AUTO_INCREMENT,
                  `title` varchar(255) NOT NULL,
                  `description` text NOT NULL,
                  `price` decimal(10,2) NOT NULL,
                  `image_path` varchar(255) NOT NULL,
                  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                  PRIMARY KEY (`id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

                "CREATE TABLE IF NOT EXISTS `sms_chats` (
                  `id` int(11) NOT NULL AUTO_INCREMENT,
                  `phone_number` varchar(20) NOT NULL,
                  `sender` enum('customer','admin') NOT NULL,
                  `message` text DEFAULT NULL,
                  `image_path` varchar(255) DEFAULT NULL,
                  `is_read` tinyint(1) DEFAULT 0,
                  `session_status` enum('active','left') DEFAULT 'active',
                  `timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                  PRIMARY KEY (`id`),
                  KEY `phone_number` (`phone_number`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;"
            ];

            $schema_error = false;
            foreach ($queries as $q) {
                if (!$conn->query($q)) {
                    $schema_error = true;
                    $error = "Architecture Deployment Error: " . $conn->error;
                    break;
                }
            }

            if (!$schema_error) {
                // 4. Clean & Deploy Administrator Account using secure ARGON2ID (matches register module)
                $conn->query("TRUNCATE TABLE users");
                $hashed_password = password_hash($admin_pass, PASSWORD_ARGON2ID);
                $stmt = $conn->prepare("INSERT INTO users (username, password) VALUES (?, ?)");
                $stmt->bind_param("ss", $admin_user, $hashed_password);
                $stmt->execute();
                $stmt->close();

                // 5. Package Runtime variables back inside configuration file
                $config_payload = "<?php\n"
                                 . "if (session_status() === PHP_SESSION_NONE) {\n"
                                 . "    ini_set('session.cookie_httponly', 1); \n"
                                 . "    ini_set('session.use_only_cookies', 1);\n"
                                 . "    ini_set('session.cookie_samesite', 'Strict');\n"
                                 . "    session_start();\n"
                                 . "}\n\n"
                                 . "\$host = \"" . addslashes($db_host) . "\";\n"
                                 . "\$user = \"" . addslashes($db_user) . "\";\n"
                                 . "\$pass = \"" . addslashes($db_pass) . "\";    \n"
                                 . "\$dbname = \"" . addslashes($db_name) . "\";\n\n"
                                 . "mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);\n\n"
                                 . "try {\n"
                                 . "    \$conn = new mysqli(\$host, \$user, \$pass, \$dbname);\n"
                                 . "    \$conn->set_charset(\"utf8mb4\");\n"
                                 . "} catch (Exception \$e) {\n"
                                 . "    if (file_exists('install.php')) {\n"
                                 . "        header('Location: install.php');\n"
                                 . "        exit;\n"
                                 . "    }\n"
                                 . "    die(\"Critical system mapping broken.\");\n"
                                 . "}\n\n"
                                 . "if (empty(\$_SESSION['csrf_token'])) {\n"
                                 . "    \$_SESSION['csrf_token'] = bin2hex(random_bytes(32));\n"
                                 . "}\n\n"
                                 . "function log_system_event(\$conn, \$username, \$action) {\n"
                                 . "    \$ip = \$_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN_IP';\n"
                                 . "    if (\$conn) {\n"
                                 . "        \$check = @\$conn->query(\"SHOW TABLES LIKE 'audit_logs'\");\n"
                                 . "        if (\$check && \$check->num_rows > 0) {\n"
                                 . "            \$log_stmt = \$conn->prepare(\"INSERT INTO audit_logs (username, action_performed, network_ip) VALUES (?, ?, ?)\");\n"
                                 . "            \$log_stmt->bind_param(\"sss\", \$username, \$action, \$ip);\n"
                                 . "            \$log_stmt->execute();\n"
                                 . "            \$log_stmt->close();\n"
                                 . "        }\n"
                                 . "    }\n"
                                 . "}\n"
                                 . "?>";

                if (file_put_contents($db_file, $config_payload)) {
                    $success = true;
                } else {
                    $error = "Database operation passed, but server system permissions blocked write access to db.php.";
                }
            }
        }
        $conn->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Daborey Shop Installer Suite</title>
    <style>
        body { font-family: 'Segoe UI', Arial, sans-serif; background: #0f172a; color: #fff; display: flex; justify-content: center; align-items: center; min-height: 100vh; margin: 0; padding: 20px;}
        .card { background: #1e293b; border: 1px solid #334155; padding: 30px; border-radius: 8px; width: 420px; box-shadow: 0 10px 25px rgba(0,0,0,0.5); }
        h2 { margin: 0 0 5px 0; color: #38bdf8; text-align: center; font-size: 24px; }
        p { color: #94a3b8; font-size: 13px; text-align: center; margin: 0 0 20px 0; }
        h4 { margin: 18px 0 5px 0; color: #38bdf8; border-bottom: 1px solid #334155; padding-bottom: 4px; font-size: 13px; text-transform: uppercase; }
        label { font-size: 11px; color: #94a3b8; display: block; margin-top: 8px; font-weight: 600; }
        input { width: 100%; padding: 10px; margin: 4px 0 10px 0; border: 1px solid #475569; background: #0f172a; color: #fff; border-radius: 4px; box-sizing: border-box; font-size: 14px; }
        input:focus { border-color: #38bdf8; outline: none; }
        button { width: 100%; padding: 12px; background: #10b981; border: none; color: white; border-radius: 4px; font-weight: bold; cursor: pointer; font-size: 14px; margin-top: 10px; transition: background 0.2s; }
        button:hover { background: #059669; }
        .error-msg { background: rgba(239,68,68,0.1); border: 1px solid #ef4444; color: #f87171; padding: 12px; border-radius: 4px; font-size: 13px; text-align: center; margin-bottom: 15px; line-height: 1.4; }
        .success-msg { background: rgba(16,185,129,0.1); border: 1px solid #10b981; color: #34d399; padding: 20px; border-radius: 4px; text-align: center; font-size: 14px; line-height: 1.5; }
        .link-btn { display: inline-block; margin-top: 15px; background: #0284c7; color: #fff; padding: 10px 20px; border-radius: 4px; text-decoration: none; font-weight: bold; font-size: 14px; }
        .link-btn:hover { background: #0369a1; }
    </style>
</head>
<body>
    <div class="card">
        <h2>Daborey Setup Engine</h2>
        <p>WordPress-style distribution configuration engine</p>
        
        <?php if ($success): ?>
            <div class="success-msg">
                <strong>✨ Installation Finished!</strong><br><br>
                Database environment mapped successfully, schemas deployed, config file compiled, and primary admin record registered.<br><br>
                <a href="shop.php" class="link-btn">Navigate to Storefront →</a>
            </div>
        <?php else: ?>
            <?php if (!empty($error)) echo "<div class='error-msg'>$error</div>"; ?>
            <form method="POST">
                <h4>1. Database Routing Properties</h4>
                <label>Database Server Host</label>
                <input type="text" name="db_host" value="localhost" required>
                
                <label>Database Profile Username</label>
                <input type="text" name="db_user" value="root" required>
                
                <label>Database Profile Password</label>
                <input type="password" name="db_pass" placeholder="Leave blank if using default XAMPP">
                
                <label>Target Database Name</label>
                <input type="text" name="db_name" placeholder="e.g. local_daborey_shop" required>
                
                <h4>2. Define System Administrator Account</h4>
                <label>Dashboard Admin Username</label>
                <input type="text" name="admin_user" value="admin" required>
                
                <label>Dashboard Admin Password</label>
                <input type="password" name="admin_pass" placeholder="Create entry login password" required>
                
                <button type="submit">Execute Script Setup</button>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>