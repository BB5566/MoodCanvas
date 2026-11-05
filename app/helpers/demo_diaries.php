<?php
// app/helpers/demo_diaries.php
// 訪客示範用的範例日記：只存在程式裡，不會寫進資料庫。
// 日期跟著正在看的月份走，讓訪客翻到哪個月都看得到範例。
// id 用負數，跟資料庫裡的真實日記（從 1 開始）不會撞到。

function demoDiaries($year, $month)
{
    $samples = [
        [-1, 3,  '😊', '早餐店阿姨記得我', "今天去巷口早餐店，阿姨還沒等我開口就說「蛋餅加起司、大冰奶對吧」。\n被記得的感覺真好，一整個早上心情都很亮。", '被記得的小事，是日子裡最暖的光。'],
        [-2, 8,  '😴', '加班到十點的星期一', "專案上線前一天，大家一起留到十點。\n回家路上腳很痠，但看到測試全部通過，還是覺得值得。", '累是真的，努力也是真的。先好好睡一覺吧。'],
        [-3, 12, '🤔', '要不要換跑道', "跟學長吃飯，他說他轉職後很開心。\n我開始想，現在的工作是我真的想要的嗎？還沒有答案，先寫下來。", '問題寫下來，答案就會慢慢浮現。'],
        [-4, 17, '😂', '貓把手機推下桌', "正在開視訊會議，家裡的貓一掌把手機推下桌。\n畫面只剩天花板，同事笑到停不下來，會議氣氛反而變好了。", '生活偶爾失控一下，也挺可愛的。'],
        [-5, 22, '😰', '簡報前一晚', "明天要跟主管報告這季成果，練習了三次還是會緊張。\n深呼吸，資料都準備好了，明天就照著講。", '緊張代表你在乎，你已經準備好了。'],
        [-6, 26, '🥰', '跟家人吃火鍋', "週末回家，媽媽煮了一大鍋酸菜白肉鍋。\n大家邊吃邊聊，好久沒有這麼放鬆。", '一鍋熱湯，裝的是家的味道。'],
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
            'image_path' => null,
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
