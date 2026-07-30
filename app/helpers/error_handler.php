<?php
/**
 * app/helpers/error_handler.php
 *
 * 全域例外與錯誤處理：
 *  - set_exception_handler：將未捕捉的例外寫入檔案，並回傳友善頁面（正式環境不揭露細節）。
 *  - set_error_handler：將 PHP 錯誤轉為 ErrorException（尊重 @ 抑制與當前 error_reporting）。
 *
 * 請在 public/index.php 最上方（載入 config 之前）引入，以涵蓋後續所有階段。
 */

if (!function_exists('installGlobalErrorHandler')) {
    function installGlobalErrorHandler() {
        // 僅在明確 DEBUG 時顯示詳細錯誤
        $isDebug = function () {
            return defined('DEBUG') && DEBUG;
        };

        // 將例外寫入應用日誌；config.php 載入前 LOGS_PATH 尚未定義時退回系統錯誤日誌
        $logThrowable = function (Throwable $e) {
            $message = sprintf(
                "[%s] Uncaught %s: %s in %s:%d\n%s\n",
                date('Y-m-d H:i:s'),
                get_class($e),
                $e->getMessage(),
                $e->getFile(),
                $e->getLine(),
                $e->getTraceAsString()
            );
            if (defined('LOGS_PATH')) {
                $logFile = LOGS_PATH . '/error.log';
                if (is_writable(LOGS_PATH) || (is_file($logFile) && is_writable($logFile))) {
                    @file_put_contents($logFile, $message, FILE_APPEND | LOCK_EX);
                    return;
                }
            }
            error_log($message);
        };

        // 未捕捉的例外
        set_exception_handler(function (Throwable $e) use ($logThrowable, $isDebug) {
            $logThrowable($e);

            if (!headers_sent()) {
                http_response_code(500);
                header('Content-Type: text/html; charset=utf-8');
                header('X-Content-Type-Options: nosniff');
                header('X-Frame-Options: SAMEORIGIN');
            }

            if ($isDebug()) {
                printf(
                    '<div style="font-family:monospace;background:#fff3f3;border:1px solid #c62828;'
                  . 'color:#b71c1c;padding:1rem 1.25rem;margin:1rem;border-radius:8px;line-height:1.5">'
                  . '<strong>Uncaught %s</strong><br>%s<br><small>in %s:%d</small>'
                  . '<pre style="white-space:pre-wrap;margin-top:.75rem;background:#fff;padding:.75rem;'
                  . 'border:1px solid #eee;border-radius:6px">%s</pre></div>',
                    htmlspecialchars(get_class($e), ENT_QUOTES, 'UTF-8'),
                    nl2br(htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8')),
                    htmlspecialchars($e->getFile(), ENT_QUOTES, 'UTF-8'),
                    (int) $e->getLine(),
                    htmlspecialchars($e->getTraceAsString(), ENT_QUOTES, 'UTF-8')
                );
            } else {
                // 正式環境：友善頁面，不揭露任何細節
                echo '<!DOCTYPE html><html lang="zh-Hant"><head><meta charset="utf-8">'
                   . '<meta name="viewport" content="width=device-width, initial-scale=1">'
                   . '<title>系統發生錯誤</title></head>'
                   . '<body style="font-family:-apple-system,sans-serif;display:flex;'
                   . 'align-items:center;justify-content:center;min-height:100vh;margin:0;'
                   . 'background:#f7f5f0;color:#3a3a3a;text-align:center">'
                   . '<div style="max-width:420px;padding:2rem 1.5rem">'
                   . '<div style="font-size:3rem;margin-bottom:1rem">🌥️</div>'
                   . '<h1 style="font-size:1.4rem;margin:0 0 .5rem">系統暫時無法回應</h1>'
                   . '<p style="color:#777;line-height:1.6">很抱歉，伺服器發生預期外的錯誤，'
                   . '我們已經記錄問題。請稍後再試，或返回首頁。</p>'
                   . '<a href="index.php?action=home" style="display:inline-block;margin-top:1rem;'
                   . 'padding:.6rem 1.4rem;background:#2b2b2b;color:#fff;border-radius:999px;'
                   . 'text-decoration:none">🏠 返回首頁</a>'
                   . '</div></body></html>';
            }
        });

        // 將可回報的 PHP 錯誤轉為 ErrorException。
        // 透過 (error_reporting() & $severity) 檢查，尊重 @ 抑制與 config.php 的 error_reporting 設定：
        // 正式環境 error_reporting=0 時此處不會拋出，避免將警告變成 500。
        set_error_handler(function ($severity, $message, $file, $line) {
            if (!(error_reporting() & $severity)) {
                return false; // 被抑制或目前層級未回報 → 不處理
            }
            throw new ErrorException($message, 0, $severity, $file, $line);
        });
    }
}

installGlobalErrorHandler();
