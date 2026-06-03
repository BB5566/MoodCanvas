<?php require __DIR__ . '/../layout/header.php'; ?>
<div class="bento-card form-container" style="text-align:center">
    <div style="font-size:4rem;margin-bottom:1rem"><?php echo htmlspecialchars($emoji ?? '😅'); ?></div>
    <h2 style="margin-bottom:1rem"><?php echo htmlspecialchars($error ?? '發生錯誤'); ?></h2>
    <p style="color:var(--color-text-muted);margin-bottom:1.5rem"><?php echo htmlspecialchars($hint ?? '請稍後再試，或返回首頁'); ?></p>
    <a href="index.php?action=home" class="btn btn-primary">🏠 返回首頁</a>
</div>
<?php require __DIR__ . '/../layout/footer.php'; ?>
