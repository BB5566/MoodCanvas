<?php
require_once __DIR__ . '/../../../config/config.php';
require_once BASE_PATH . '/app/views/layout/header.php';

$pageTitle = '註冊';
$metaDescription = '註冊 MoodCanvas，立即擁有專屬帳號，體驗 AI 日記、心情日曆與智慧插畫功能。';
?>

<div class="container card">
    <h2 class="text-center">建立您的 MoodCanvas 帳戶</h2>
    <p class="text-center sub-heading">記錄心情，生成專屬您的 AI 畫作</p>

    <?php if (isset($_GET['error'])): ?>
        <div class="alert alert-danger">
            <?php 
                // 將錯誤碼轉換為使用者友善的訊息
                $errorMessages = [
                    'missing_fields' => '請填寫所有必填欄位。',
                    'invalid_email' => '電子郵件格式不正確。',
                    'password_mismatch' => '兩次輸入的密碼不一致。',
                    'password_too_short' => '密碼長度至少需要 8 個字元。',
                    'email_exists' => '這個電子郵件已經被註冊過了。',
                    'registration_failed' => '註冊失敗，請稍後再試或聯繫管理員。'
                ];
                $errorCode = $_GET['error'];
                echo htmlspecialchars($errorMessages[$errorCode] ?? '發生未知錯誤。');
            ?>
        </div>
    <?php endif; ?>

    <form action="index.php?action=register" method="POST" autocomplete="off">
        <div class="form-group">
            <label for="username">使用者名稱 <span style="color:#f55;">*</span></label>
            <input type="text" id="username" name="username" class="form-control" required maxlength="30" aria-label="使用者名稱，必填，最多30字">
        </div>
        <div class="form-group">
            <label for="email">電子郵件 <span style="color:#f55;">*</span></label>
            <input type="email" id="email" name="email" class="form-control" required maxlength="80" aria-label="電子郵件，必填，最多80字">
        </div>
        <div class="form-group">
            <label for="password">密碼 (至少8個字元) <span style="color:#f55;">*</span></label>
            <input type="password" id="password" name="password" class="form-control" required minlength="8" maxlength="50" aria-label="密碼，必填，8-50字">
        </div>
        <div class="form-group">
            <label for="confirm_password">確認密碼 <span style="color:#f55;">*</span></label>
            <input type="password" id="confirm_password" name="confirm_password" class="form-control" required minlength="8" maxlength="50" aria-label="確認密碼，必填，8-50字">
        </div>
        <div class="form-group">
            <button type="submit" class="btn btn-primary btn-block" aria-label="送出註冊表單">註冊</button>
        </div>
    </form>
    <div class="text-center mt-3">
        <p>已經有帳戶了？ <a href="index.php?action=login">點此登入</a></p>
    </div>
</div>

<?php
require_once BASE_PATH . '/app/views/layout/footer.php';
?>
