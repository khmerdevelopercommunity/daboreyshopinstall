<?php
require 'db.php';
header('Content-Type: application/json');

error_reporting(0);
ini_set('display_errors', 0);

$action = $_GET['action'] ?? '';

switch ($action) {

    // 1. MONITOR POLL ROUTE
    case 'admin_poll':
        $unread_count = 0;
        $unread_res = $conn->query("SELECT COUNT(*) as total FROM sms_chats WHERE is_read = 0 AND sender = 'customer'");
        if ($unread_res) {
            $unread_count = $unread_res->fetch_assoc()['total'];
        }

        $threads = [];
        $query = "SELECT phone_number, session_status, MAX(id) as max_id FROM sms_chats GROUP BY phone_number, session_status ORDER BY max_id DESC";
        $result = $conn->query($query);
        
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $threads[] = [
                    "phone_number" => $row['phone_number'],
                    "session_status" => $row['session_status']
                ];
            }
        }

        echo json_encode([
            "unread" => intval($unread_count),
            "threads" => $threads
        ]);
        exit;

    // 2. FETCH THREAD MESSAGES
    case 'fetch_thread':
        $phone = $_GET['phone_number'] ?? '';
        $messages = [];

        if (!empty($phone)) {
            $stmt = $conn->prepare("SELECT sender, message, image_path, timestamp FROM sms_chats WHERE phone_number = ? ORDER BY id ASC");
            $stmt->bind_param("s", $phone);
            if ($stmt->execute()) {
                $result = $stmt->get_result();
                while ($row = $result->fetch_assoc()) {
                    $messages[] = [
                        "sender" => $row['sender'],
                        "message" => $row['message'],
                        "image_path" => !empty($row['image_path']) ? $row['image_path'] : null,
                        "display_time" => date('h:i A', strtotime($row['timestamp']))
                    ];
                }
            }
            $stmt->close();
        }
        echo json_encode($messages);
        exit;

    // 3. SEND/RECEIVE PAYLOADS (ROUTED TO ./chat_uploads/)
    case 'send':
        $phone = trim($_POST['phone_number'] ?? '');
        $sender = trim($_POST['sender'] ?? 'customer'); 
        $message = trim($_POST['message'] ?? '');
        $image_path = null;

        $uploaded_file = null;
        if (!empty($_FILES)) {
            $first_key = array_key_first($_FILES);
            if ($_FILES[$first_key]['error'] === UPLOAD_ERR_OK) {
                $uploaded_file = $_FILES[$first_key];
            }
        }

        if ($uploaded_file !== null) {
            $tmpPath = $uploaded_file['tmp_name'];
            $name = $uploaded_file['name'];
            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));

            if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'])) {
                $dir = './chat_uploads/';
                if (!is_dir($dir)) {
                    mkdir($dir, 0755, true);
                }
                
                $dest = $dir . md5(time() . $name) . '.' . $ext;
                if (move_uploaded_file($tmpPath, $dest)) {
                    $image_path = $dest;
                }
            }
        }

        if (!empty($phone) && ($message !== '' || $image_path !== null)) {
            $is_read = ($sender === 'admin') ? 1 : 0;
            
            $stmt = $conn->prepare("INSERT INTO sms_chats (phone_number, sender, message, image_path, is_read) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssi", $phone, $sender, $message, $image_path, $is_read);
            $stmt->execute();
            $stmt->close();

            echo json_encode(["status" => "success", "image_path" => $image_path]);
            exit;
        }

        echo json_encode(["status" => "error", "message" => "Invalid parameters"]);
        exit;

    // 4. MARK READ
    case 'mark_read':
        $phone = trim($_POST['phone_number'] ?? '');
        if (!empty($phone)) {
            $stmt = $conn->prepare("UPDATE sms_chats SET is_read = 1 WHERE phone_number = ? AND sender = 'customer'");
            $stmt->bind_param("s", $phone);
            $stmt->execute();
            $stmt->close();
            echo json_encode(["status" => "success"]);
            exit;
        }
        echo json_encode(["status" => "error", "message" => "Missing parameters"]);
        exit;

    // 5. DELETE CONVERSATION THREAD
    case 'delete_thread':
        $phone = trim($_POST['phone_number'] ?? '');
        if (!empty($phone)) {
            $stmt = $conn->prepare("DELETE FROM sms_chats WHERE phone_number = ?");
            $stmt->bind_param("s", $phone);
            if ($stmt->execute()) {
                echo json_encode(["status" => "success"]);
            } else {
                echo json_encode(["status" => "error", "message" => "Query execution error"]);
            }
            $stmt->close();
            exit;
        }
        echo json_encode(["status" => "error", "message" => "Missing phone parameter"]);
        exit;

    default:
        echo json_encode(["status" => "error", "message" => "Invalid Action Route"]);
        exit;
}
