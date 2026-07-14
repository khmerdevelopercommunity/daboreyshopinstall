<?php
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1); 
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_samesite', 'Strict');
    session_start();
}

// --- AUTOMATIC HTTPS DETECTION & ENFORCEMENT ROUTINE ---
$is_localhost = in_array($_SERVER['REMOTE_ADDR'], ['127.0.0.1', '::1']) || ($_SERVER['HTTP_HOST'] === 'localhost');
$is_https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || $_SERVER['SERVER_PORT'] == 443;

// If we are NOT on localhost, and NOT using HTTPS, force the browser to redirect to HTTPS
if (!$is_localhost && !$is_https) {
    $secure_url = "https://" . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
    header("Location: " . $secure_url);
    exit;
}
// -------------------------------------------------------

// Left empty for the installer script to write dynamically
$host = "";
$user = "";
$pass = "";    
$dbname = "";

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Loop Block Check: Find out if the user is currently reading the installer page
$is_on_installer = (basename($_SERVER['SCRIPT_NAME']) === 'install.php');

if (empty($host) || empty($user) || empty($dbname)) {
    if (!$is_on_installer && file_exists('install.php')) {
        header("Location: install.php");
        exit;
    }
}

try {
    $conn = new mysqli($host, $user, $pass, $dbname);
    $conn->set_charset("utf8mb4");
} catch (Exception $e) {
    if (!$is_on_installer && file_exists('install.php')) {
        header("Location: install.php");
        exit;
    } elseif (!$is_on_installer) {
        die("Database connection fault. Please verify configuration details.");
    }
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function log_system_event($conn, $username, $action) {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN_IP';
    if ($conn) {
        $check = @$conn->query("SHOW TABLES LIKE 'audit_logs'");
        if ($check && $check->num_rows > 0) {
            $log_stmt = $conn->prepare("INSERT INTO audit_logs (username, action_performed, network_ip) VALUES (?, ?, ?)");
            $log_stmt->bind_param("sss", $username, $action, $ip);
            $log_stmt->execute();
            $log_stmt->close();
        }
    }
}
?>
