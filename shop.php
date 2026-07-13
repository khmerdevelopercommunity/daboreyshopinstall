<?php
require 'db.php';

// Fetch all published products from database
$products = [];
$result = $conn->query("SELECT title, description, price, image_path FROM products ORDER BY id DESC");
if ($result) {
    while ($row = $result->fetch_assoc()) { 
        $products[] = $row; 
    }
}
?>
<!DOCTYPE html>
<html lang="km">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daborey Shop - Storefront</title>
    <style>
        body { font-family: 'Segoe UI', Arial, sans-serif; background-color: #0f172a; color: #f8fafc; margin: 0; padding: 20px; }
        
        /* Navigation & Header */
        header { display: flex; justify-content: space-between; align-items: center; padding: 15px 40px; background: #1e293b; border-bottom: 1px solid #334155; border-radius: 8px; margin-bottom: 30px; }
        .logo h1 { font-size: 24px; color: #38bdf8; margin: 0; }
        .nav-links { display: flex; align-items: center; gap: 15px; }
        .btn-admin-login { padding: 8px 16px; background: #0284c7; color: white; text-decoration: none; border-radius: 4px; font-weight: bold; font-size: 14px; transition: background 0.2s; }
        .btn-admin-login:hover { background: #0369a1; }

        /* Products Grid Setup */
        .store-container { max-width: 1200px; margin: 0 auto; }
        .products-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); gap: 20px; }
        .product-card { background: #1e293b; border: 1px solid #334155; border-radius: 8px; overflow: hidden; display: flex; flex-direction: column; }
        .product-img { width: 100%; height: 180px; object-fit: cover; background: #0f172a; }
        .product-info { padding: 15px; flex: 1; display: flex; flex-direction: column; justify-content: space-between; }
        .product-title { font-size: 18px; font-weight: bold; color: #f8fafc; margin: 0 0 8px 0; }
        .product-desc { font-size: 13px; color: #94a3b8; margin: 0 0 12px 0; line-height: 1.4; }
        .product-price { font-size: 18px; font-weight: bold; color: #ffb700; }

        /* Floating SMS Support Widget Engine */
        .sms-widget { position: fixed; bottom: 25px; right: 25px; z-index: 9999; font-family: sans-serif; }
        .sms-badge { width: 60px; height: 60px; background: #10b981; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-size: 24px; cursor: pointer; box-shadow: 0 4px 12px rgba(0,0,0,0.3); transition: transform 0.2s; }
        .sms-badge:hover { transform: scale(1.05); }
        
        .sms-window { width: 330px; height: 400px; background: #1e293b; border: 1px solid #334155; border-radius: 8px; display: none; flex-direction: column; overflow: hidden; box-shadow: 0 8px 24px rgba(0,0,0,0.4); }
        .sms-header { background: #0f172a; padding: 12px; font-weight: bold; font-size: 14px; color: #38bdf8; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #334155; }
        .sms-close { cursor: pointer; color: #ef4444; font-weight: bold; }
        
        .sms-auth-box { padding: 20px; display: flex; flex-direction: column; gap: 12px; justify-content: center; height: 100%; box-sizing: border-box; }
        .sms-auth-box input { padding: 10px; background: #0f172a; border: 1px solid #475569; color: white; border-radius: 4px; outline: none; }
        .sms-auth-box button { padding: 10px; background: #10b981; border: none; color: white; font-weight: bold; border-radius: 4px; cursor: pointer; }
        
        .sms-chat-area { flex: 1; display: none; flex-direction: column; overflow: hidden; }
        .sms-box-stream { flex: 1; padding: 12px; overflow-y: auto; display: flex; flex-direction: column; gap: 8px; background: #0f172a; }
        
        .c-msg { max-width: 80%; padding: 8px 12px; border-radius: 6px; font-size: 13px; line-height: 1.4; word-wrap: break-word; }
        .c-msg.customer { background: #10b981; color: white; align-self: flex-end; }
        .c-msg.admin { background: #334155; color: #f8fafc; align-self: flex-start; }
        .c-img-node { max-width: 100%; border-radius: 4px; display: block; margin-bottom: 4px; max-height: 110px; object-fit: cover; border: 1px solid #475569; cursor: pointer; }
        
        .sms-input-row { display: flex; gap: 5px; padding: 8px; background: #1e293b; border-top: 1px solid #334155; align-items: center; }
        .sms-input-row input[type="text"] { flex: 1; padding: 8px; background: #0f172a; border: 1px solid #475569; color: white; border-radius: 4px; outline: none; font-size: 13px; }
        
        .btn-sms-attach { background: #475569; color: white; border: none; padding: 7px 9px; border-radius: 4px; cursor: pointer; font-size: 13px; font-weight: bold; }
        .btn-sms-attach.has-file { background: #10b981; }
        
        .sms-input-row button.btn-sms-send { padding: 8px 12px; background: #10b981; border: none; color: white; border-radius: 4px; cursor: pointer; font-weight: bold; font-size: 13px; }
    </style>
</head>
<body>

    <header>
        <div class="logo">
            <h1>Daborey Shop</h1>
        </div>
        <div class="nav-links">
            <a href="index.php?action=login" class="btn-admin-login">Admin Login</a>
        </div>
    </header>

    <div class="store-container">
        <h2 style="color: #38bdf8; border-bottom: 1px solid #334155; padding-bottom: 10px; margin-bottom: 20px;">Available Showroom Inventory</h2>
        
        <div class="products-grid">
            <?php if (empty($products)): ?>
                <div style="color: #64748b; grid-column: span 4; text-align: center; padding: 40px;">No inventory items listed at this time.</div>
            <?php else: ?>
                <?php foreach ($products as $prod): ?>
                    <div class="product-card">
                        <img class="product-img" src="<?php echo htmlspecialchars($prod['image_path']); ?>" alt="Product">
                        <div class="product-info">
                            <div>
                                <div class="product-title"><?php echo htmlspecialchars($prod['title']); ?></div>
                                <div class="product-desc"><?php echo htmlspecialchars($prod['description']); ?></div>
                            </div>
                            <div class="product-price">$<?php echo number_format($prod['price'], 2); ?></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <div class="sms-widget">
        <div class="sms-badge" id="widget-badge" onclick="toggleWidgetView(true)">💬</div>
        
        <div class="sms-window" id="widget-window">
            <div class="sms-header">
                <span>Live SMS Service</span>
                <span class="sms-close" onclick="toggleWidgetView(false)">✕</span>
            </div>
            
            <div class="sms-auth-box" id="sms-auth-panel">
                <label style="font-size: 12px; color: #94a3b8;">Enter phone number to initialize thread:</label>
                <input type="text" id="cust-phone-input" placeholder="012345678..." autocomplete="off">
                <button onclick="connectCustomerThread()">Establish Line</button>
            </div>
            
            <div class="sms-chat-area" id="sms-chat-panel">
                <div class="sms-box-stream" id="customer-stream-box"></div>
                <div class="sms-input-row">
                    <input type="file" id="cust-file-input" accept="image/*" style="display:none;" onchange="updateAttachBadge()">
                    <button type="button" class="btn-sms-attach" id="cust-attach-btn" onclick="document.getElementById('cust-file-input').click()">📷</button>
                    <input type="text" id="cust-msg-input" placeholder="Type message details...">
                    <button class="btn-sms-send" onclick="submitCustomerMessage()">Send</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        let customerActivePhone = "";
        let backgroundPollInterval = null;

        function toggleWidgetView(show) {
            document.getElementById('widget-window').style.display = show ? 'flex' : 'none';
            document.getElementById('widget-badge').style.display = show ? 'none' : 'flex';
        }

        function updateAttachBadge() {
            const picker = document.getElementById('cust-file-input');
            const btn = document.getElementById('cust-attach-btn');
            if (picker.files && picker.files.length > 0) {
                btn.classList.add('has-file');
                btn.innerText = "✓";
            } else {
                btn.classList.remove('has-file');
                btn.innerText = "📷";
            }
        }

        function connectCustomerThread() {
            const num = document.getElementById('cust-phone-input').value.trim();
            if (num === "") return;
            customerActivePhone = num;

            document.getElementById('sms-auth-panel').style.display = 'none';
            document.getElementById('sms-chat-panel').style.display = 'flex';
            
            refreshCustomerStream();
            backgroundPollInterval = setInterval(refreshCustomerStream, 2000);
        }

        function refreshCustomerStream() {
            if (customerActivePhone === "") return;
            fetch(`sms_gateway.php?action=fetch_thread&phone_number=${encodeURIComponent(customerActivePhone)}`)
                .then(res => res.json())
                .then(data => {
                    const box = document.getElementById('customer-stream-box');
                    const isScrolledDown = box.scrollHeight - box.clientHeight <= box.scrollTop + 20;
                    box.innerHTML = "";

                    data.forEach(m => {
                        const div = document.createElement('div');
                        div.className = `c-msg ${m.sender}`;
                        
                        if (m.image_path) {
                            const img = document.createElement('img');
                            img.src = m.image_path;
                            img.className = "c-img-node";
                            img.onclick = () => window.open(m.image_path, '_blank');
                            div.appendChild(img);
                        }
                        
                        if (m.message && m.message.trim() !== "") {
                            const text = document.createElement('span');
                            text.innerText = m.message;
                            div.appendChild(text);
                        }
                        box.appendChild(div);
                    });
                    if (isScrolledDown) box.scrollTop = box.scrollHeight;
                });
        }

        function submitCustomerMessage() {
            const textEl = document.getElementById('cust-msg-input');
            const fileEl = document.getElementById('cust-file-input');
            const msgText = textEl.value.trim();

            if (msgText === "" && (!fileEl.files || fileEl.files.length === 0)) return;

            const form = new FormData();
            form.append('phone_number', customerActivePhone);
            form.append('sender', 'customer');
            form.append('message', msgText);

            if (fileEl.files && fileEl.files.length > 0) {
                form.append('image', fileEl.files[0]);
            }

            fetch('sms_gateway.php?action=send', { method: 'POST', body: form })
                .then(() => {
                    textEl.value = "";
                    fileEl.value = "";
                    updateAttachBadge();
                    refreshCustomerStream();
                });
        }
    </script>
</body>
</html>
