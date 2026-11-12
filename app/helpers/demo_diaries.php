<?php
// app/helpers/demo_diaries.php
// 訪客示範用的範例日記：只存在程式裡，不會寫進資料庫。
// 日期跟著正在看的月份走，讓訪客翻到哪個月都看得到範例。
// id 用負數，跟資料庫裡的真實日記（從 1 開始）不會撞到。
// 圖和短語都是把這幾篇日記丟進 MoodCanvas 自己的生成流程（GPT-5 nano＋Flux Schnell）跑出來的原樣結果，沒有手改。

function demoDiaries($year, $month)
{
    $samples = [
        [-1, 3,  '😊', '早餐店阿姨記得我', "今天去巷口早餐店，阿姨還沒等我開口就說「蛋餅加起司、大冰奶對吧」。\n被記得的感覺真好，一整個早上心情都很亮。", '被記得的感覺像晨光輕扣心房，安靜卻放大了整個早晨'],
        [-2, 8,  '😴', '加班到十點的星期一', "專案上線前一天，大家一起留到十點。\n回家路上腳很痠，但看到測試全部通過，還是覺得值得。", '夜色像一條柔軟的繭，包裹心口的疲憊也慢慢釋放'],
        [-3, 12, '🤔', '要不要換跑道', "跟學長吃飯，他說他轉職後很開心。\n我開始想，現在的工作是我真的想要的嗎？還沒有答案，先寫下來。", '在夜色裡寫下心事猶如把風握成線，慢慢織出新的日子'],
        [-4, 17, '😂', '貓把手機推下桌', "正在開視訊會議，家裡的貓一掌把手機推下桌。\n畫面只剩天花板，同事笑到停不下來，會議氣氛反而變好了。", '貓掌翻過日常的薄薄牆，笑聲像雨後的糖霜，意外地把緊張拋在一旁'],
        [-5, 22, '😰', '簡報前一晚', "明天要跟主管報告這季成果，練習了三次還是會緊張。\n深呼吸，資料都準備好了，明天就照著講。", '心裡有裂縫的日光，化成勇氣的果實，慢慢落在心上'],
        [-6, 26, '🥰', '跟家人吃火鍋', "週末回家，媽媽煮了一大鍋酸菜白肉鍋。\n大家邊吃邊聊，好久沒有這麼放鬆。", '與你們同桌的時光，如潮水慢慢退去，心底的風也安靜了下來'],
    ];

    $diaries = [];
    foreach ($samples as [$id, $day, $mood, $title, $content, $quote]) {
        $date = sprintf('%04d-%02d-%02d', $year, $month, $day);
        $diaries[] = [
            'id' => $id,
            'user_id' => 0,
            'title' => $title,
            'content' => $content,
            'mood' => $mood,
            'diary_date' => $date,
            'ai_generated_text' => $quote,
            'image_path' => 'assets/images/demo/demo' . $id . '.jpg', // id 是負數，所以檔名是 demo-1.jpg
            'created_at' => $date . ' 21:30:00',
            'is_demo' => true,
        ];
    }
    return $diaries;
}

// 依 id 找一篇範例日記，找不到回傳 null
function findDemoDiary($id, $year, $month)
{
    foreach (demoDiaries($year, $month) as $diary) {
        if ($diary['id'] === $id) {
            return $diary;
        }
    }
    return null;
}
