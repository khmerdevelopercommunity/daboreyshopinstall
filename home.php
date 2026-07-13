<?php
require 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$max_idle_seconds = 900;
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $max_idle_seconds)) {
    log_system_event($conn, $_SESSION['username'], 'SESSION_TIMEOUT_EXPIRED');
    session_unset(); session_destroy();
    header("Location: index.php?expired=1");
    exit;
}
$_SESSION['last_activity'] = time();

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['title'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("Security token validation failed.");
    }
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $price = floatval($_POST['price'] ?? 0);
    
    if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] === UPLOAD_ERR_OK) {
        $fileTmpPath = $_FILES['product_image']['tmp_name'];
        $fileName = $_FILES['product_image']['name'];
        $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        if (in_array($fileExtension, ['jpg', 'jpeg', 'png', 'webp'])) {
            $uploadFileDir = './uploads/';
            if (!is_dir($uploadFileDir)) mkdir($uploadFileDir, 0755, true);
            $dest_path = $uploadFileDir . md5(time() . $fileName) . '.' . $fileExtension;
            if(move_uploaded_file($fileTmpPath, $dest_path)) {
                $stmt = $conn->prepare("INSERT INTO products (title, description, price, image_path) VALUES (?, ?, ?, ?)");
                $stmt->bind_param("ssds", $title, $description, $price, $dest_path);
                if ($stmt->execute()) {
                    header("Location: home.php?success=1");
                    exit;
                }
                $stmt->close();
            }
        }
    }
}

$products = [];
$result = $conn->query("SELECT title, description, price, image_path FROM products ORDER BY id DESC");
while ($row = $result->fetch_assoc()) { $products[] = $row; }
?>
<!DOCTYPE html>
<html lang="km">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <style>
        body { font-family: 'Segoe UI', Arial, sans-serif; background-color: #0f172a; color: #f8fafc; margin: 0; padding: 20px; }
        header { display: flex; justify-content: space-between; align-items: center; padding: 15px 40px; background: #1e293b; border-bottom: 1px solid #334155; border-radius: 8px; margin-bottom: 30px; flex-wrap: wrap; gap: 20px;}
        .header-title-zone h1 { font-size: 24px; color: #38bdf8; margin: 0; }
        .btn-logout { padding: 8px 16px; background: #ef4444; color: white; text-decoration: none; border-radius: 4px; font-weight: bold; margin-left: 15px; }
        .btn-view-shop { padding: 8px 16px; background: #10b981; color: white; text-decoration: none; border-radius: 4px; font-weight: bold; }
        
        .clock-container { background-color: #090a0f; padding: 10px; border-radius: 8px; border: 1px solid #383121; display: grid; grid-template-columns: repeat(4, 70px); gap: 6px; text-align: center; }
        .clock-cell { background-color: #161922; padding: 6px 4px; border-radius: 4px; }
        .cell-label { font-size: 10px; color: #d1b477; display: block; }
        .cell-value { font-size: 20px; font-weight: bold; color: #ffb700; }
        .date-cell { grid-column: span 4; font-size: 12px; color: #bdc5e1; }

        .main-layout { display: flex; gap: 30px; flex-wrap: wrap; max-width: 1400px; margin: 0 auto; }
        .left-panel { flex: 1; min-width: 300px; }
        .right-panel { width: 420px; background: #1e293b; border: 1px solid #334155; border-radius: 8px; padding: 20px; box-sizing: border-box; }

        .product-uploader { background: #1e293b; padding: 25px; border-radius: 8px; border: 1px solid #334155; margin-bottom: 30px; }
        .product-uploader input, .product-uploader textarea { width: 100%; padding: 10px; margin-bottom: 15px; background: #0f172a; border: 1px solid #475569; color: #f8fafc; border-radius: 4px; box-sizing: border-box; outline: none;}
        .product-uploader button { width: 100%; background: #0284c7; color: white; border: none; padding: 12px; border-radius: 4px; font-weight: bold; cursor: pointer; }
        
        .products-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 15px; }
        .product-card { background: #1e293b; border: 1px solid #334155; border-radius: 8px; overflow: hidden; }
        .product-img { width: 100%; height: 130px; object-fit: cover; }
        .product-info { padding: 12px; }

        .chat-thread-list { max-height: 160px; overflow-y: auto; border: 1px solid #475569; margin-bottom: 15px; border-radius: 4px; background: #0f172a; }
        .thread-item { padding: 10px; border-bottom: 1px solid #334155; cursor: pointer; display: flex; justify-content: space-between; font-size: 13px; align-items: center;}
        .thread-item:hover { background: #233146; }
        .thread-item.active-selection { background: #0284c7; color: #fff; }
        
        .thread-details { display: flex; align-items: center; gap: 8px; }
        .btn-delete-thread { background: #ef4444; border: none; color: white; font-size: 11px; padding: 2px 6px; border-radius: 3px; cursor: pointer; font-weight: bold; }
        .btn-delete-thread:hover { background: #dc2626; }

        .status-badge { font-size: 10px; padding: 2px 6px; border-radius: 10px; font-weight: bold; }
        .status-active { background: #10b981; color: white; }
        .status-left { background: #64748b; color: white; }
        
        .chat-box-window { height: 200px; overflow-y: auto; background: #0f172a; border: 1px solid #475569; border-radius: 4px; padding: 10px; margin-bottom: 10px; display: flex; flex-direction: column; gap: 8px; }
        .msg { max-width: 80%; padding: 8px 12px; border-radius: 6px; font-size: 13px; line-height: 1.4; word-wrap: break-word; }
        .msg.customer { background: #334155; align-self: flex-start; }
        .msg.admin { background: #0284c7; align-self: flex-end; }
        
        .chat-img-node { max-width: 100%; border-radius: 4px; display: block; margin-bottom: 4px; max-height: 120px; object-fit: cover; cursor: pointer; border: 1px solid #475569; }
        
        .chat-input-row { display: flex; gap: 6px; align-items: center; }
        .chat-input-row input[type="text"] { flex: 1; padding: 8px; background: #0f172a; border: 1px solid #475569; color: white; border-radius: 4px; outline: none;}
        
        .btn-chat-attach { background: #475569; color: white; border: none; padding: 8px 10px; border-radius: 4px; cursor: pointer; font-size: 14px; display: flex; align-items: center; justify-content: center; font-weight: bold; }
        .btn-chat-attach:hover { background: #64748b; }
        .btn-chat-attach.selected { background: #10b981; }

        .chat-input-row button.btn-send { padding: 8px 14px; background: #10b981; border: none; color: white; border-radius: 4px; cursor: pointer; font-weight: bold; }
        .chat-input-row button.btn-send:hover { background: #059669; }

        .alert-banner { display: none; background: #ef4444; color: white; text-align: center; padding: 8px; font-weight: bold; border-radius: 4px; margin-bottom: 15px; animation: flash 1.5s infinite; }
        @keyframes flash { 0%, 100% { opacity: 0.8; } 50% { opacity: 1; } }
    </style>
</head>
<body>

    <header>
        <div class="header-title-zone">
            <h1>Admin Panel Architecture</h1>
            <div style="margin-top:5px;">
                User: <strong><?php echo htmlspecialchars($_SESSION['username']); ?></strong>
                <a href="logout.php" class="btn-logout">Sign Out</a>
            </div>
        </div>

        <div class="clock-container">
            <div class="clock-cell"><span class="cell-label">ម៉ោង</span><div id="hours" class="cell-value">០០</div></div>
            <div class="clock-cell"><span class="cell-label">នាទី</span><div id="minutes" class="cell-value">០០</div></div>
            <div class="clock-cell"><span class="cell-label">វិនាទី</span><div id="seconds" class="cell-value">០០</div></div>
            <div class="clock-cell"><span class="cell-label">វេលា</span><div id="ampm" class="cell-value">--</div></div>
            <div class="clock-cell date-cell"><span id="day-display">ថ្ងៃ...</span> | <span id="date-display">ថ្ងៃ-ខែ-ឆ្នាំ</span></div>
        </div>
        
        <div><a href="shop.php" target="_blank" class="btn-view-shop">Open Storefront Layout ↗</a></div>
    </header>

    <div class="main-layout">
        <div class="left-panel">
            <form class="product-uploader" method="POST" action="home.php" enctype="multipart/form-data">
                <h2>Publish Inventory Item</h2>
                <?php if (isset($_GET['success'])) echo "<div style='color:#34d399; margin-bottom:10px;'>Product successfully added.</div>"; ?>
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <input type="file" name="product_image" accept="image/*" required>
                <input type="text" name="title" placeholder="Product Title" required>
                <textarea name="description" placeholder="Description information..." rows="2" required></textarea>
                <input type="number" name="price" placeholder="Price ($)" step="0.01" min="0" required>
                <button type="submit">Publish Item</button>
            </form>

            <h3>Active Showcase Inventory</h3>
            <div class="products-grid">
                <?php foreach ($products as $prod): ?>
                    <div class="product-card">
                        <img class="product-img" src="<?php echo htmlspecialchars($prod['image_path']); ?>">
                        <div class="product-info">
                            <strong><?php echo htmlspecialchars($prod['title']); ?></strong>
                            <div style="color:#ffb700; margin-top:5px;">$<?php echo number_format($prod['price'], 2); ?></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="right-panel">
            <h3 style="margin-top:0; color:#38bdf8;">SMS Gateway Operations</h3>
            <div id="admin-alert" class="alert-banner">🚨 INCOMING CUSTOMER MESSAGE INCOMING!</div>
            
            <label style="font-size:12px; color:#94a3b8;">Broadcast Channels:</label>
            <div class="chat-thread-list" id="thread-container"></div>

            <div id="active-chat-area" style="display:none;">
                <label style="font-size:12px; color:#94a3b8;">Chat Stream (<span id="active-phone-lbl"></span>):</label>
                <div class="chat-box-window" id="admin-chat-box"></div>
                
                <div class="chat-input-row">
                    <input type="file" id="admin-chat-file" accept="image/*" style="display:none;" onchange="visualizeSelection()">
                    <button type="button" class="btn-chat-attach" id="attach-btn" onclick="document.getElementById('admin-chat-file').click()">📷</button>
                    <input type="text" id="admin-reply-input" placeholder="Type response message...">
                    <button class="btn-send" onclick="sendAdminReply()">Reply</button>
                </div>
            </div>
            <div id="no-chat-selected" style="color:#64748b; text-align:center; padding-top:40px;">
                Select a phone communications row to establish an active line.
            </div>
        </div>
    </div>

    <script>
        let selectedPhone = "";
        let globalUnreadCount = 0;

        function visualizeSelection() {
            const picker = document.getElementById('admin-chat-file');
            const btn = document.getElementById('attach-btn');
            if(picker.files && picker.files.length > 0) {
                btn.classList.add('selected');
                btn.innerText = "✓";
            } else {
                btn.classList.remove('selected');
                btn.innerText = "📷";
            }
        }

        function playSystemBeep() {
            try {
                const audioCtx = new (window.AudioContext || window.webkitAudioContext)();
                const oscillator = audioCtx.createOscillator();
                const gainNode = audioCtx.createGain();
                oscillator.type = 'sine';
                oscillator.frequency.setValueAtTime(620, audioCtx.currentTime);
                gainNode.gain.setValueAtTime(0.2, audioCtx.currentTime);
                oscillator.connect(gainNode);
                gainNode.connect(audioCtx.destination);
                oscillator.start();
                oscillator.stop(audioCtx.currentTime + 0.12);
            } catch(e) {}
        }

        function syncGatewayMonitor() {
            fetch('sms_gateway.php?action=admin_poll')
                .then(res => res.json())
                .then(data => {
                    if (data.unread > globalUnreadCount) {
                        document.getElementById('admin-alert').style.display = 'block';
                        playSystemBeep();
                    } else if (data.unread === 0) {
                        document.getElementById('admin-alert').style.display = 'none';
                    }
                    globalUnreadCount = data.unread;

                    const container = document.getElementById('thread-container');
                    container.innerHTML = "";
                    if(!data.threads || data.threads.length === 0) {
                        container.innerHTML = "<div style='color:#475569; padding:10px; text-align:center;'>Empty dashboard index.</div>";
                    }
                    data.threads.forEach(t => {
                        const row = document.createElement('div');
                        row.className = `thread-item ${selectedPhone === t.phone_number ? 'active-selection' : ''}`;
                        
                        row.onclick = (e) => {
                            if(e.target.tagName !== 'BUTTON') {
                                focusChannel(t.phone_number);
                            }
                        };
                        
                        const badge = t.session_status === 'active' 
                            ? `<span class="status-badge status-active">Stay</span>` 
                            : `<span class="status-badge status-left">Left</span>`;
                        
                        row.innerHTML = `
                            <span>📱 ${t.phone_number}</span> 
                            <div class="thread-details">
                                ${badge}
                                <button class="btn-delete-thread" onclick="deleteThread('${t.phone_number}')">❌</button>
                            </div>
                        `;
                        container.appendChild(row);
                    });

                    if(selectedPhone !== "") updateMessageWindow();
                });
        }

        function deleteThread(phone) {
            if(!confirm(`Are you sure you want to delete the complete conversation for ${phone}?`)) return;
            
            const payload = new FormData();
            payload.append('phone_number', phone);
            
            fetch('sms_gateway.php?action=delete_thread', { method: 'POST', body: payload })
                .then(res => res.json())
                .then(data => {
                    if(selectedPhone === phone) {
                        selectedPhone = "";
                        document.getElementById('active-chat-area').style.display = 'none';
                        document.getElementById('no-chat-selected').style.display = 'block';
                    }
                    syncGatewayMonitor();
                });
        }

        function focusChannel(phone) {
            selectedPhone = phone;
            document.getElementById('active-chat-area').style.display = 'block';
            document.getElementById('no-chat-selected').style.display = 'none';
            document.getElementById('active-phone-lbl').innerText = phone;
            
            const payload = new FormData();
            payload.append('phone_number', phone);
            fetch('sms_gateway.php?action=mark_read', { method: 'POST', body: payload });
            updateMessageWindow();
        }

        function updateMessageWindow() {
            if(selectedPhone === "") return;
            fetch(`sms_gateway.php?action=fetch_thread&phone_number=${encodeURIComponent(selectedPhone)}`)
                .then(res => res.json())
                .then(messages => {
                    const box = document.getElementById('admin-chat-box');
                    const autoScroll = box.scrollHeight - box.clientHeight <= box.scrollTop + 20;
                    box.innerHTML = "";
                    
                    messages.forEach(m => {
                        const bubble = document.createElement('div');
                        bubble.className = `msg ${m.sender}`;
                        
                        if (m.image_path) {
                            const imgNode = document.createElement('img');
                            imgNode.src = m.image_path;
                            imgNode.className = "chat-img-node";
                            imgNode.onclick = () => window.open(m.image_path, '_blank');
                            bubble.appendChild(imgNode);
                        }
                        
                        if (m.message && m.message.trim() !== "") {
                            const textSpan = document.createElement('span');
                            textSpan.innerText = m.message;
                            bubble.appendChild(textSpan);
                        }
                        
                        box.appendChild(bubble);
                    });
                    if(autoScroll) box.scrollTop = box.scrollHeight;
                });
        }

        function sendAdminReply() {
            const textEl = document.getElementById('admin-reply-input');
            const fileEl = document.getElementById('admin-chat-file');
            const txt = textEl.value.trim();
            
            if(selectedPhone === "") return;
            if(txt === "" && (!fileEl.files || fileEl.files.length === 0)) return;

            const form = new FormData();
            form.append('phone_number', selectedPhone);
            form.append('sender', 'admin');
            form.append('message', txt);
            
            if(fileEl.files && fileEl.files.length > 0) {
                form.append('image', fileEl.files[0]);
            }

            fetch('sms_gateway.php?action=send', { method: 'POST', body: form })
                .then(() => { 
                    textEl.value = ""; 
                    fileEl.value = ""; 
                    visualizeSelection(); 
                    updateMessageWindow(); 
                });
        }

        setInterval(syncGatewayMonitor, 2000);
        syncGatewayMonitor();
    </script>

    <script>
        function khmerize(str) {
            const arr = ['០', '១', '២', '៣', '៤', '៥', '៦', '៧', '៨', '៩'];
            return str.toString().replace(/[0-9]/g, (match) => arr[+match]);
        }
        const weekdays = ["ថ្ងៃអាទិត្យ", "ថ្ងៃចន្ទ", "ថ្ងៃអង្គារ", "ថ្ងៃពុធ", "ថ្ងៃព្រហស្បតិ៍", "ថ្ងៃសុក្រ", "ថ្ងៃសៅរ៍"];
        function runClock() {
            const dateObj = new Date();
            let hr = dateObj.getHours(), mn = dateObj.getMinutes().toString().padStart(2,'0'), sc = dateObj.getSeconds().toString().padStart(2,'0');
            let cycle = hr >= 12 ? (hr >= 16 ? (hr >= 19 ? "យប់" : "ល្ងាច") : "ថ្ងៃ") : "ព្រឹក";
            let displayHr = (hr % 12 ? hr % 12 : 12).toString().padStart(2,'0');
            document.getElementById("hours").innerHTML = khmerize(displayHr);
            document.getElementById("minutes").innerHTML = khmerize(mn);
            document.getElementById("seconds").innerHTML = khmerize(sc);
            document.getElementById("ampm").innerHTML = cycle;
            document.getElementById("day-display").innerHTML = weekdays[dateObj.getDay()];
            document.getElementById("date-display").innerHTML = khmerize(`${dateObj.getDate().toString().padStart(2,'0')}-${(dateObj.getMonth()+1).toString().padStart(2,'0')}-${dateObj.getFullYear()}`);
        }
        runClock(); setInterval(runClock, 1000);
    </script>
</body>
</html>
