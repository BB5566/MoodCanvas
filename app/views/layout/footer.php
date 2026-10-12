<?php
// app/views/layout/footer.php
?>
    </main> <!-- #main -->

    <footer class="site-footer" role="contentinfo">
        <p>© <?php echo date('Y'); ?> MoodCanvas · 記錄每一刻的心情</p>
    </footer>

    <!-- 全站共用的 JavaScript -->
    <script src="<?php echo APP_URL; ?>/public/assets/js/main.js?v=<?php echo filemtime(BASE_PATH . '/public/assets/js/main.js'); ?>"></script>

    <!-- 動畫層：GSAP（本地）+ 自訂動畫，優雅降級 -->
    <script src="<?php echo APP_URL; ?>/public/assets/js/vendor/gsap.min.js" defer></script>
    <script src="<?php echo APP_URL; ?>/public/assets/js/anim.js?v=<?php echo filemtime(BASE_PATH . '/public/assets/js/anim.js'); ?>" defer></script>

</body>
</html>
