<?php
// app/controllers/DiaryController.php — v3 重構版
// 合併重複邏輯、CSRF 驗證、編輯功能、速率限制

namespace App\Controllers;

use App\Models\Diary;
use Exception;

class DiaryController
{
    private $diaryModel;

    public function __construct()
    {
        $this->diaryModel = new Diary();
    }

    // ============================================================
    // 頁面：日曆首頁
    // ============================================================
    public function index()
    {
        $user_id = $_SESSION['user_id'] ?? 1;
        $year = (int)($_GET['year'] ?? date('Y'));
        $month = str_pad((int)($_GET['month'] ?? date('m')), 2, '0', STR_PAD_LEFT);

        $diaries = $this->diaryModel->getDiariesByMonth($user_id, $year, $month);
        $pageTitle = '心情日曆';
        include BASE_PATH . '/app/views/diary/calendar.php';
    }

    // ============================================================
    // 頁面：建立／處理日記
    // ============================================================
    public function create()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->saveDiary();
            return;
        }
        $this->showCreateForm();
    }

    // ============================================================
    // 表單處理（合併原 store + handleCreateDiary）
    // ============================================================
    public function store()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: index.php?action=diary_create');
            exit;
        }
        $this->saveDiary();
    }

    private function saveDiary()
    {
        // CSRF 驗證
        $token = $_POST['csrf_token'] ?? '';
        if (!verifyCsrfToken($token)) {
            $this->showError('安全驗證失敗，請重新整理頁面');
            return;
        }

        $user_id = $_SESSION['user_id'] ?? 1;
        $title = trim($_POST['title'] ?? '');
        $content = trim($_POST['content'] ?? '');
        $mood = $_POST['mood'] ?? '😊';
        $diary_date = $_POST['diary_date'] ?? date('Y-m-d');

        if (empty($content)) {
            $this->showError('日記內容不能為空');
            return;
        }
        if (mb_strlen($content) > 1000) {
            $this->showError('日記內容不能超過 1000 字');
            return;
        }
        if (empty($title)) {
            $title = '無標題日記';
        }

        try {
            $diary_id = $this->diaryModel->create($user_id, $title, $content, $mood, $diary_date, null, null);

            if ($diary_id) {
                $_SESSION['diary_art_style'] = $_POST['image_style'] ?? 'random';
                header('Location: ' . APP_URL . '/public/index.php?action=diary_detail&id=' . $diary_id . '&ai=generating');
                exit;
            }
            $this->showError('建立日記失敗，請稍後再試');
        } catch (Exception $e) {
            logMessage("建立日記失敗: " . $e->getMessage(), 'ERROR');
            $this->showError('系統錯誤：' . $e->getMessage());
        }
    }

    public function edit()
    {
        $user_id = $_SESSION['user_id'] ?? 1;
        $diary_id = $_GET['id'] ?? null;

        if (!$diary_id) {
            header('Location: index.php?action=home');
            exit;
        }

        $diary = $this->diaryModel->findById($diary_id, $user_id);
        if (!$diary) {
            $this->showError('找不到該日記');
            return;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $token = $_POST['csrf_token'] ?? '';
            if (!verifyCsrfToken($token)) {
                $this->showError('安全驗證失敗');
                return;
            }

            $title = trim($_POST['title'] ?? '');
            $content = trim($_POST['content'] ?? '');
            $mood = $_POST['mood'] ?? $diary['mood'];

            if (empty($content)) {
                $error = '日記內容不能為空';
                $pageTitle = '編輯日記';
                include BASE_PATH . '/app/views/diary/edit.php';
                return;
            }

            $this->diaryModel->update($diary_id, [
                'title' => $title ?: '無標題日記',
                'content' => $content,
                'mood' => $mood,
            ]);

            header('Location: ' . APP_URL . '/public/index.php?action=diary_detail&id=' . $diary_id);
            exit;
        }

        $pageTitle = '編輯日記';
        include BASE_PATH . '/app/views/diary/edit.php';
    }

    public function show()
    {
        $user_id = $_SESSION['user_id'] ?? 1;
        $diary_id = $_GET['id'] ?? null;

        if (!$diary_id) {
            $this->showError('無效的日記 ID');
            return;
        }

        $diary = $this->diaryModel->findById($diary_id, $user_id);
        if (!$diary) {
            $this->showError('找不到該日記');
            return;
        }

        $pageTitle = '日記詳情';
        $is_owner = ($_SESSION['user_id'] ?? 0) == ($diary['user_id'] ?? 0);
        $ai_generating = ($_GET['ai'] ?? '') === 'generating';
        include BASE_PATH . '/app/views/diary/detail.php';
    }

    public function showByDate()
    {
        $user_id = $_SESSION['user_id'] ?? 1;
        $date = $_GET['date'] ?? date('Y-m-d');
        $diaries = $this->diaryModel->getDiariesByDate($user_id, $date);
        $pageTitle = $date . ' 的日記';
        include BASE_PATH . '/app/views/diary/date_list.php';
    }

    public function delete()
    {
        $user_id = $_SESSION['user_id'] ?? 1;
        $diary_id = $_GET['id'] ?? null;
        if (!$diary_id) { header('Location: index.php?action=home'); exit; }
        $diary = $this->diaryModel->findById($diary_id, $user_id);
        if ($diary && !empty($diary['image_path'])) Diary::deleteImageFile($diary['image_path']);
        $this->diaryModel->delete($diary_id, $user_id);
        header('Location: index.php?action=home&message=diary_deleted');
        exit;
    }

    public function quickCreate()
    {
        header('Content-Type: application/json');
        $input = json_decode(file_get_contents('php://input'), true);
        $diary_date = $input['diary_date'] ?? null;
        if (!$diary_date) { echo json_encode(['success'=>false,'message'=>'日期必填']); return; }
        try {
            $id = $this->diaryModel->create($_SESSION['user_id']??1, $input['title']??'快速記錄', $input['content']??'', $input['mood']??'📝', $diary_date, null, null);
            echo json_encode(['success'=>true,'diary_id'=>$id]);
        } catch(Exception $e) { echo json_encode(['success'=>false,'message'=>$e->getMessage()]); }
    }

    public function generateCardContent()
    {
        header('Content-Type: application/json');
        $diary_id = $_GET['id'] ?? null;
        if (!$diary_id) { echo json_encode(['success'=>false,'message'=>'缺少 ID']); return; }
        $user_id = $_SESSION['user_id'] ?? 1;
        $rateKey = 'ai_gen_last_'.$user_id;
        if (isset($_SESSION[$rateKey]) && (time()-$_SESSION[$rateKey])<60) {
            echo json_encode(['success'=>false,'message'=>'請等待 '.(60-(time()-$_SESSION[$rateKey])).' 秒']); return;
        }
        $_SESSION[$rateKey] = time();
        $diary = $this->diaryModel->findById($diary_id, $user_id);
        if (!$diary) { echo json_encode(['success'=>false,'message'=>'找不到']); return; }
        $content = $diary['content']??''; $mood = $diary['mood']??'😊';
        $style = $_SESSION['diary_art_style']??'random'; unset($_SESSION['diary_art_style']);
        $r = ['success'=>true,'image_url'=>null,'quote'=>null];
        try { $img=$this->callAIImageGeneration($content,$style,$mood); if($img){$r['image_url']=$img; $this->diaryModel->update($diary_id,['image_path'=>$img]);} } catch(Exception $e){$r['image_error']=$e->getMessage();}
        try { $q=$this->callAIQuoteGeneration($content,$mood); if($q){$r['quote']=$q; $this->diaryModel->update($diary_id,['ai_generated_text'=>$q]);} } catch(Exception $e){$r['quote_error']=$e->getMessage();}
        echo json_encode($r);
    }

    public function dashboard()
    {
        $diaries = $this->diaryModel->findAllByUserId($_SESSION['user_id']??1);
        $pageTitle = '心情觀測儀表板';
        include BASE_PATH . '/app/views/dashboard/index.php';
    }

    private function showCreateForm() { $pageTitle='新增日記'; include BASE_PATH.'/app/views/diary/create.php'; }
    private function showError($m,$e='😅',$h='') { $error=$m; $pageTitle='錯誤'; if(file_exists(BASE_PATH.'/app/views/error/general.php')) include BASE_PATH.'/app/views/error/general.php'; else include BASE_PATH.'/app/views/diary/create.php'; }

    private function callAIImageGeneration($content,$style,$mood) {
        $k=getenv('REPLICATE_API_KEY'); if(!$k) throw new Exception('REPLICATE_API_KEY missing');
        $p=$this->buildImagePrompt($content,$style,$mood);
        $ch=curl_init('https://api.replicate.com/v1/predictions');
        curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>30,CURLOPT_HTTPHEADER=>['Authorization: Bearer '.$k,'Content-Type: application/json'],CURLOPT_POSTFIELDS=>json_encode(['version'=>'19335492dbe879d4b5983bff2149f597db8314ccc7fe374e6313af7c2b52792f','input'=>['prompt'=>$p,'aspect_ratio'=>'1:1','safety_filter_level'=>'block_medium_and_above']])]);
        $r=curl_exec($ch); $h=curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch);
        if(!in_array($h,[200,201])) throw new Exception("HTTP $h");
        $d=json_decode($r,true); $pid=$d['id']??null; if(!$pid) throw new Exception('No prediction');
        $u=$this->pollReplicatePrediction($pid,$k); if(!$u) throw new Exception('Timeout');
        return $this->downloadImage($u);
    }

    private function pollReplicatePrediction($pid,$k) {
        for($i=0;$i<30;$i++){ sleep(2); $ch=curl_init("https://api.replicate.com/v1/predictions/$pid"); curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>10,CURLOPT_HTTPHEADER=>['Authorization: Bearer '.$k]]); $r=curl_exec($ch); curl_close($ch); $d=json_decode($r,true); $s=$d['status']??'processing'; if($s==='succeeded'){ $o=$d['output']??null; return is_array($o)?($o[0]??null):$o; } if(in_array($s,['failed','canceled'])) return null; } return null;
    }

    private function downloadImage($url) {
        $ch=curl_init($url); curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>30,CURLOPT_FOLLOWLOCATION=>true,CURLOPT_SSL_VERIFYPEER=>false]); $d=curl_exec($ch); $h=curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch);
        if(!$d||$h!==200) return null;
        $id=uniqid('img_').'.png'; $dir=BASE_PATH.'/public/storage/generated_images/'; if(!is_dir($dir)) mkdir($dir,0755,true);
        file_put_contents($dir.$id,$d); return 'storage/generated_images/'.$id;
    }

    private function callAIQuoteGeneration($content,$mood) {
        $k=getenv('PIONEER_API_KEY'); if(!$k) throw new Exception('PIONEER_API_KEY missing');
        $p="請根據以下日記內容，生成一句溫暖、有詩意的心情短語（不超過 40 字）。\n\n日記內容：{$content}\n心情：{$mood}\n\n只需回傳短語本身，不要加任何說明。";
        $ch=curl_init('https://api.pioneer.ai/v1/chat/completions');
        curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>30,CURLOPT_HTTPHEADER=>['Authorization: Bearer '.$k,'Content-Type: application/json'],CURLOPT_POSTFIELDS=>json_encode(['model'=>'claude-haiku-4-5','messages'=>[['role'=>'user','content'=>$p]],'max_tokens'=>100,'temperature'=>0.9])]);
        $r=curl_exec($ch); curl_close($ch); $d=json_decode($r,true);
        return trim($d['choices'][0]['message']['content']??'')?:null;
    }

    private function buildImagePrompt($content,$style,$mood) {
        $mm=['😊'=>'joyful, bright, warm','😢'=>'melancholic, soft, blue','😡'=>'intense, dramatic, red','😍'=>'romantic, dreamy, pink','😴'=>'peaceful, quiet, twilight','🤔'=>'contemplative, surreal','😂'=>'playful, colorful','😰'=>'atmospheric, moody','🥰'=>'cozy, intimate, golden','🙄'=>'subtle, muted, ironic'];
        $sm=['photographic'=>'photorealistic','ghibli'=>'Ghibli style, soft colors','pixel-art'=>'pixel art 16-bit','3d-render'=>'Pixar-style 3D','flat-illustration'=>'flat design','sketch'=>'pencil sketch','ink-wash'=>'Chinese ink wash'];
        $md=$mm[$mood]??'neutral'; $sd=$sm[$style]??''; $sc=mb_substr($content,0,300);
        return "A beautiful illustration. Mood: {$md}. ".($sd?"Style: {$sd}. ":'')."Scene: \"{$sc}\". No text.";
    }
}
