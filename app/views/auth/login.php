<?php
// app/views/auth/login.php

// 引入頁首
require_once BASE_PATH . '/app/views/layout/header.php';

$pageTitle = '登入';
$metaDescription = '登入 MoodCanvas，開始記錄你的心情日記，體驗 AI 插畫與智慧語錄功能。';
?>

<div class="auth-container">
    <h2>登入 Mood Canvas</h2>
    <p>記錄你的心情，生成專屬的 AI 插畫。</p>

    <?php 
    if (isset($_GET['status']) && $_GET['status'] == 'registered') {
        echo '<div class="alert alert-success">註冊成功！請現在登入。</div>';
    }
    if (isset($_GET['status']) && $_GET['status'] == 'logged_out') {
        echo '<div class="alert alert-info">您已成功登出。</div>';
    }
    ?>

    <?php if (isset($_GET['error'])): ?>
        <div class="alert alert-danger">
            <?php 
                $errorMessages = [
                    'missing_fields' => '請輸入電子郵件和密碼。',
                    'invalid_credentials' => '電子郵件或密碼錯誤，請重新確認。',
                    'login_failed' => '登入失敗，請稍後再試。'
                ];
                $errorCode = $_GET['error'];
                echo htmlspecialchars($errorMessages[$errorCode] ?? '發生未知錯誤。');
            ?>
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['success']) && $_GET['success'] == 'register'): ?>
        <div class="alert alert-success">
            註冊成功！現在您可以登入了。
        </div>
    <?php endif; ?>

    <form action="index.php?action=login" method="POST">
        <div class="form-group">
            <label for="username">使用者名稱</label>
            <input type="text" id="username" name="username" required>
        </div>
        <div class="form-group">
            <label for="password">密碼</label>
            <input type="password" id="password" name="password" required>
        </div>
        <div class="form-group">
            <button type="submit" class="btn btn-primary btn-block">登入</button>
        </div>
    </form>
    <div class="text-center mt-3">
        <p>還沒有帳戶？ <a href="index.php?action=register">點此註冊</a></p>
    </div>
</div>

<?php
// 引入頁尾
require_once BASE_PATH . '/app/views/layout/footer.php';
?>
