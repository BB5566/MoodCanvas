<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>系統錯誤 - MoodCanvas</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff; /* Changed for better contrast */
            overflow: hidden; /* Hide scrollbars from background elements */
        }

        /* Add some background shapes for a more dynamic look */
        body::before,
        body::after {
            content: '';
            position: absolute;
            border-radius: 50%;
            background: linear-gradient(45deg, rgba(255, 255, 255, 0.1), rgba(255, 255, 255, 0.3));
            filter: blur(30px);
        }

        body::before {
            width: 300px;
            height: 300px;
            top: 10%;
            left: 10%;
            animation: moveShape 15s infinite alternate;
        }

        body::after {
            width: 400px;
            height: 400px;
            bottom: 15%;
            right: 5%;
            animation: moveShape 20s infinite alternate-reverse;
        }

        @keyframes moveShape {
            from {
                transform: translate(0, 0) rotate(0deg);
            }
            to {
                transform: translate(100px, 50px) rotate(180deg);
            }
        }
        
        .error-container {
            background: rgba(255, 255, 255, 0.15); /* Frosted glass background */
            backdrop-filter: blur(15px); /* The magic */
            -webkit-backdrop-filter: blur(15px); /* For Safari */
            border: 1px solid rgba(255, 255, 255, 0.2);
            padding: 3rem;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.2);
            text-align: center;
            max-width: 500px;
            width: 90%;
            z-index: 10;
        }
        
        .error-icon {
            font-size: 4rem;
            margin-bottom: 1rem;
            color: #fff; /* Changed color */
            text-shadow: 0 0 15px rgba(255,255,255,0.5);
        }
        
        .error-title {
            font-size: 2rem;
            font-weight: 600;
            margin-bottom: 1rem;
            color: #ffffff; /* Changed color */
        }
        
        .error-message {
            font-size: 1.1rem;
            color: #e2e8f0; /* Changed color */
            margin-bottom: 2rem;
            line-height: 1.6;
        }
        
        .error-code {
            font-size: 0.9rem;
            color: #e2e8f0; /* Changed color */
            margin-bottom: 2rem;
            padding: 1rem;
            background: rgba(0, 0, 0, 0.2); /* Darker transparent background */
            border-radius: 8px;
            font-family: 'Courier New', monospace;
        }
        
        .action-buttons {
            display: flex;
            gap: 1rem;
            justify-content: center;
            flex-wrap: wrap;
        }
        
        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        
        .btn-primary {
            background: #667eea;
            color: white;
        }
        
        .btn-primary:hover {
            background: #5a67d8;
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }
        
        .btn-secondary {
            background: transparent; /* Changed for glass effect */
            color: #ffffff; /* Changed for glass effect */
            border: 1px solid rgba(255, 255, 255, 0.5);
        }
        
        .btn-secondary:hover {
            background: rgba(255, 255, 255, 0.1); /* Changed for glass effect */
            transform: translateY(-2px);
        }
        
        .support-info {
            margin-top: 2rem;
            padding-top: 2rem;
            border-top: 1px solid rgba(255, 255, 255, 0.2); /* Changed for glass effect */
            font-size: 0.9rem;
            color: #e2e8f0; /* Changed color */
        }
        
        @media (max-width: 480px) {
            .error-container {
                padding: 2rem;
            }
            
            .error-title {
                font-size: 1.5rem;
            }
            
            .action-buttons {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="error-container">
        <div class="error-icon">⚠️</div>
        <h1 class="error-title">系統發生錯誤</h1>
        <p class="error-message">
            很抱歉，系統遇到了一些問題。請稍後再試，或聯繫系統管理員。
        </p>
        
        <?php if (isset($error_code)): ?>
        <div class="error-code">
            錯誤代碼: <?php echo htmlspecialchars($error_code); ?>
        </div>
        <?php endif; ?>
        
        <div class="action-buttons">
            <a href="javascript:history.back()" class="btn btn-secondary">返回上頁</a>
            <a href="index.php" class="btn btn-primary">回到首頁</a>
        </div>
        
        <div class="support-info">
            <p>如果問題持續發生，請聯繫技術支援</p>
            <p>錯誤時間: <?php echo date('Y-m-d H:i:s'); ?></p>
        </div>
    </div>
    
    <script>
        // 自動重試機制（可選）
        setTimeout(function() {
            const retryBtn = document.createElement('button');
            retryBtn.textContent = '重新載入';
            retryBtn.className = 'btn btn-primary';
            retryBtn.onclick = function() {
                window.location.reload();
            };
            
            const buttons = document.querySelector('.action-buttons');
            buttons.appendChild(retryBtn);
        }, 5000); // 5秒後顯示重新載入按鈕
    </script>
</body>
</html>
