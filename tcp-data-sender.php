<?php
// PHP kısmı - TCP işlemleri
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    $action = $_POST['action'] ?? '';
    $ip = $_POST['ip'] ?? '';
    $port = $_POST['port'] ?? '';
    $message = $_POST['message'] ?? '';
    $hexMode = isset($_POST['hexMode']) ? filter_var($_POST['hexMode'], FILTER_VALIDATE_BOOLEAN) : false;

    // Güvenlik önlemleri
    $ip = filter_var($ip, FILTER_VALIDATE_IP);
    $port = filter_var($port, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 65535]]);

    if (!$ip || !$port) {
        echo json_encode(['success' => false, 'message' => 'Invalid IP or port number']);
        exit;
    }

    if ($action === 'connect') {
        // Bağlantı testi yap
        $socket = @fsockopen($ip, $port, $errno, $errstr, 2);
        if ($socket) {
            fclose($socket);
            echo json_encode(['success' => true, 'message' => 'Connection successful']);
        } else {
            echo json_encode(['success' => false, 'message' => "Connection error: $errstr ($errno)"]);
        }
    } elseif ($action === 'disconnect') {
        echo json_encode(['success' => true, 'message' => 'The connection is lost']);
    } elseif ($action === 'send') {
        try {
            // Soket oluştur ve veri gönder
            $socket = @fsockopen($ip, $port, $errno, $errstr, 10);
            
            if (!$socket) {
                throw new Exception("Connection error: $errstr ($errno)");
            }
            
            // Hex modunda ise mesajı dönüştür
            if ($hexMode) {
                // Hex stringini temizle (boşlukları ve geçersiz karakterleri kaldır)
                $hexString = preg_replace('/[^0-9a-fA-F]/', '', $message);
                
                // Hex uzunluğu kontrolü (1-32 byte)
                if (strlen($hexString) === 0 || strlen($hexString) > 64) {
                    throw new Exception("Hex data must be between 1-32 bytes");
                }
                
                // Hex uzunluğu çift mi kontrol et
                if (strlen($hexString) % 2 !== 0) {
                    throw new Exception("Hex data is invalid (length is odd number of characters)");
                }
                
                // Hex stringini binary'e dönüştür
                $binaryData = hex2bin($hexString);
                if ($binaryData === false) {
                    throw new Exception("Invalid hex data");
                }
                
                $message = $binaryData;
            }
            
            // Mesajı gönder
            fwrite($socket, $message);
            
            // Yanıtı oku (eğer varsa)
            $response = '';
            stream_set_timeout($socket, 2); // 2 saniye timeout
            while (!feof($socket)) {
                $line = fgets($socket, 1024);
                if ($line === false) break;
                $response .= $line;
            }
            
            fclose($socket);
            
            echo json_encode([
                'success' => true,
                'response' => $hexMode ? bin2hex($response) : trim($response)
            ]);
        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid operation']);
    }
    exit;
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TCP Data Sender</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .container {
            background-color: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            text-align: center;
        }
        .input-group {
            margin-bottom: 15px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: #555;
        }
        input, textarea, button {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }
        textarea {
            height: 100px;
            resize: vertical;
        }
        button {
            background-color: #4CAF50;
            color: white;
            cursor: pointer;
            border: none;
            font-weight: bold;
            margin-top: 5px;
            transition: background-color 0.3s;
        }
        button:hover {
            background-color: #45a049;
        }
        #connectBtn {
            background-color: #2196F3;
        }
        #connectBtn:hover {
            background-color: #0b7dda;
        }
        #disconnectBtn {
            background-color: #f44336;
            display: none;
        }
        #disconnectBtn:hover {
            background-color: #d32f2f;
        }
        .status {
            margin-top: 15px;
            padding: 10px;
            border-radius: 4px;
            text-align: center;
        }
        .connected {
            background-color: #dff0d8;
            color: #3c763d;
            border: 1px solid #d6e9c6;
        }
        .disconnected {
            background-color: #f2dede;
            color: #a94442;
            border: 1px solid #ebccd1;
        }
        #response {
            margin-top: 15px;
            padding: 10px;
            background-color: #f8f9fa;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .checkbox-group {
            margin: 10px 0;
            display: flex;
            align-items: center;
        }
        .checkbox-group input {
            width: auto;
            margin-right: 10px;
        }
        .button-group {
            display: flex;
            gap: 10px;
        }
        .button-group button {
            flex: 1;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>TCP Data Sender </h1>
        
        <div class="input-group">
            <label for="serverAddress">Server Adress (IP:Port):</label>
            <input type="text" id="serverAddress" placeholder="Sample: 192.168.1.123:8080" value="192.168.1.123:8080">
        </div>
        
        <div class="button-group">
            <button id="connectBtn">Connect</button>
            <button id="disconnectBtn">Disconnect</button>
        </div>
        
        <div class="input-group">
            <label for="message">Data to be sent:</label>
            <textarea id="message" placeholder="Enter the data you want to send"></textarea>
        </div>
        
        <div class="checkbox-group">
            <input type="checkbox" id="hexMode">
            <label for="hexMode">Send in Hex mode (1-32 bytes)</label>
        </div>
        
        <button id="sendBtn" disabled>Send</button>
        
        <div id="status" class="status disconnected">No connection</div>
        <div id="response"></div>
    </div>

    <script>
        let isConnected = false;
        const serverAddressInput = document.getElementById('serverAddress');
        const connectBtn = document.getElementById('connectBtn');
        const disconnectBtn = document.getElementById('disconnectBtn');
        const messageInput = document.getElementById('message');
        const sendBtn = document.getElementById('sendBtn');
        const statusDiv = document.getElementById('status');
        const responseDiv = document.getElementById('response');
        const hexModeCheckbox = document.getElementById('hexMode');
        
        connectBtn.addEventListener('click', function() {
            const serverAddress = serverAddressInput.value;
            const [ip, port] = serverAddress.split(':');
            
            if (!ip || !port) {
                alert('Please enter a valid IP:Port!');
                return;
            }
            
            fetch(window.location.href, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `ip=${encodeURIComponent(ip)}&port=${encodeURIComponent(port)}&action=connect`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    isConnected = true;
                    statusDiv.className = 'status connected';
                    statusDiv.textContent = 'Connection established: ' + serverAddress;
                    sendBtn.disabled = false;
                    connectBtn.style.display = 'none';
                    disconnectBtn.style.display = 'block';
                } else {
                    alert('Connection error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Hata:', error);
                alert('An error occurred during connection');
            });
        });
        
        disconnectBtn.addEventListener('click', function() {
            const serverAddress = serverAddressInput.value;
            const [ip, port] = serverAddress.split(':');
            
            fetch(window.location.href, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `ip=${encodeURIComponent(ip)}&port=${encodeURIComponent(port)}&action=disconnect`
            })
            .then(response => response.json())
            .then(data => {
                isConnected = false;
                statusDiv.className = 'status disconnected';
                statusDiv.textContent = 'The connection is lost';
                sendBtn.disabled = true;
                connectBtn.style.display = 'block';
                disconnectBtn.style.display = 'none';
            })
            .catch(error => {
                console.error('Hata:', error);
            });
        });
        
        sendBtn.addEventListener('click', function() {
            if (!isConnected) {
                alert('You must connect first!');
                return;
            }
            
            const message = messageInput.value;
            const serverAddress = serverAddressInput.value;
            const [ip, port] = serverAddress.split(':');
            const hexMode = hexModeCheckbox.checked;
            
            if (!message) {
                alert('Please enter a message to send!');
                return;
            }
            
            fetch(window.location.href, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `ip=${encodeURIComponent(ip)}&port=${encodeURIComponent(port)}&action=send&message=${encodeURIComponent(message)}&hexMode=${hexMode}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    responseDiv.innerHTML = 
                        `<strong>Sent to:</strong> ${hexMode ? 'HEX: ' + message : message}<br>
                         <strong>Server Response:</strong> ${data.response || 'No response'}`;
                } else {
                    responseDiv.innerHTML = 
                        `<strong>Hata:</strong> ${data.message}`;
                    isConnected = false;
                    statusDiv.className = 'status disconnected';
                    statusDiv.textContent = 'The connection is lost';
                    sendBtn.disabled = true;
                    connectBtn.style.display = 'block';
                    disconnectBtn.style.display = 'none';
                }
            })
            .catch(error => {
                console.error('Hata:', error);
                responseDiv.innerHTML = 
                    '<strong>Hata:</strong> An error occurred while sending data';
            });
        });
    </script>
</body>
</html>
