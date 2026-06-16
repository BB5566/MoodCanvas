<?php
// app/views/layout/footer.php
?>
        </main> <!-- .content-wrapper -->
    </div> <!-- .page-wrapper -->

    <!-- 全站共用的 JavaScript -->
    <script src="<?php echo APP_URL; ?>/public/assets/js/main.js?v=<?php echo filemtime(BASE_PATH . '/public/assets/js/main.js'); ?>"></script>

    <!-- 動畫層：GSAP（CDN）+ 自訂動畫，優雅降級 -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/gsap.min.js" defer></script>
    <script src="<?php echo APP_URL; ?>/public/assets/js/anim.js?v=<?php echo filemtime(BASE_PATH . '/public/assets/js/anim.js'); ?>" defer></script>

</body>
</html>
