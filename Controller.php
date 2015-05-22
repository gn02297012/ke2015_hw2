<?php

/**
 * 定義其他類別的文字，避免出現"其他"、"其它"這種同義不同字的情況
 */
define('TEXT_OTHER', '其他');

class Controller {

    private $dbh = null;
    private $postData = -1;
    private $timer = null;

    /**
     * 建構子
     * @param type $DB_CONFIG
     */
    public function Controller($DB_CONFIG) {
        try {
            $this->timer = new Timer();
            //資料庫連線
            $this->dbh = new PDO("mysql:host={$DB_CONFIG['hostname']};dbname={$DB_CONFIG['dbname']};charset=utf8", $DB_CONFIG['username'], $DB_CONFIG['password']);
            //將錯誤模式設定為拋出例外
            $this->dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->showText('mysql資料庫連線成功');
            $this->showText('');
        } catch (PDOException $e) {
            die($e->getMessage());
        }
    }

    /**
     * 印出文字
     * @param type $text 要印出的文字
     */
    function showText($text = '') {
        echo "{$text}\n";
        ob_flush();
        flush();
    }

    /**
     * 執行SQL並傳回第一筆結果，適用於SELECT
     * @param type $sql 要執行的SQL
     * @param type $params 參數
     * @return type
     */
    private function fetch($sql, $params = array()) {
        $sth = $this->dbh->prepare($sql);
        $sth->execute($params);
        return $sth->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * 執行SQL並傳回全部結果，適用於SELECT
     * @param type $sql 要執行的SQL
     * @param type $params 參數
     * @return type
     */
    private function fetchAll($sql, $params = array()) {
        $sth = $this->dbh->prepare($sql);
        $sth->execute($params);
        return $sth->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * 執行SQL，適用於INSERT, UPDATE, DELETE
     * @param type $sql 要執行的SQL
     * @param type $params 參數
     * @return type
     */
    private function executeSQL($sql, $params = array()) {
        $sth = $this->dbh->prepare($sql);
        $result = $sth->execute($params);
        return $result;
    }

    /**
     * 根據傳入的section算出是哪個分類的
     */
    private function getTopic($section) {
        $topicList = array(
            '影劇娛樂' => array('影', '劇', '娛', '樂', '名采人間事總覽'),
            '運動' => array('棒球', '運動', '體壇', '體育'),
            '兩岸' => array('兩岸'),
            '財經' => array('財經', '股市', '房市'),
            '保健' => array('醫藥', '健康'),
            '政治' => array('政治'),
            '社會' => array('社會'),
        );
        $findTopic = false;
        foreach ($topicList as $topic => $list) {
            //判斷section中是否出現特定的字
            for ($i = 0, $count = count($list); $i < $count; $i++) {
                if (mb_strpos($section, $list[$i]) !== false) {
                    $findTopic = $topic;
                    break;
                }
            }
            //如果找到topic就跳離foreach迴圈
            if ($findTopic !== false) {
                break;
            }
        }
        //預設值: 其他
        if ($findTopic === false) {
            $findTopic = TEXT_OTHER;
        }
        return $findTopic;
    }

    /**
     * 分類標記
     */
    public function classify() {
        //讀出所有文章
        $this->timer->Start(); //開始計時
        $sql = "SELECT * FROM `ke2015_sample_news` WHERE 1;";
        $documents = $this->fetchAll($sql);
        $docCount = count($documents);
        $spentTime = $this->timer->StopAndReset(); //算出耗時
        $this->showText("查詢所有文章成功，共有{$docCount}筆資料，耗時: {$spentTime}");
        $this->showText();

        //讀出所有關鍵字
        $this->timer->Start(); //開始計時
        $sql = "SELECT *, CAST(`tf` / `tf_count` AS DECIMAL(10,8)) AS `p` FROM `words` a INNER JOIN (SELECT `class`, SUM(`tf`) AS `tf_count` FROM `words` WHERE 1 GROUP BY `class`) b ON a.`class` = b.`class` WHERE 1;";
        $words = $this->fetchAll($sql);
        $wordCount = count($words);
        $spentTime = $this->timer->StopAndReset(); //算出耗時
        $this->showText("查詢所有關鍵字成功，共有{$wordCount}筆資料，耗時: {$spentTime}");
        $this->showText();

        //讀出各分類的文章數量
        $this->timer->Start(); //開始計時
        $sql = "SELECT `class`, `doc_count` FROM `words` WHERE 1 GROUP BY `class`;";
        $classes = $this->fetchAll($sql);
        //將分類文章數變成MAP
        $classCountMap = array(
            TEXT_OTHER => array(
                'count' => $docCount,
                'p' => 0,
            ),
        );
        for ($i = 0, $count = count($classes); $i < $count; $i++) {
            $class = $classes[$i];
            $value = intval($class['doc_count']);
            $classCountMap[$class['class']] = array(
                'count' => $value,
                'p' => 0,
            );
            //把其他分類的文章數量減掉此分類的文章數量
            $classCountMap[TEXT_OTHER]['count'] -= $value;
        }
        //算出各分類的出現機率
        foreach ($classCountMap as $key => &$obj) {
            $obj['p'] = $obj['count'] / $docCount;
        }

        //啟動資料庫交易
        $this->dbh->beginTransaction();
        $sql = array(
            'delete' => 'DELETE FROM `classify` WHERE `id` = ?;',
            'insert' => 'INSERT INTO `classify` (`id`, `class`, `keyword`, `section`) VALUES (?, ?, ?, ?);',
        );
        //開始分類所有文章
        $this->timer->Start(); //開始計時
        for ($index = 0; $index < $docCount; $index++) {
            $data = &$documents[$index];
            //只要處理文章的內文
            $content = $data['content'];
            //根據section算出對應的topic
            $data['topic'] = $this->getTopic($data['section']);
            //算出各關鍵字的機率
            $keywords = [];
            $keywords1 = [];
            $prob = array();
            for ($i = 0; $i < $wordCount; $i++) {
                $word = $words[$i];
                if (mb_strpos($content, $word['word']) !== false) {
                    if (!isset($prob[$word['class']])) {
                        $prob[$word['class']] = 1;
                    }
                    //算出此關鍵字在文章中的出現次數
                    $n = mb_substr_count($content, $word['word']);
                    //計算加權後的機率P
                    $tf = intval($word['tf']);
                    $tf_count = intval($word['tf_count']);
                    $p = ($tf) / ($tf_count) * $n;
                    $prob[$word['class']] *= $p;
                    //將出現過的關鍵字記錄起來
                    $keywords[] = array(
                        'word' => $word['word'],
                        'n' => $n
                    );
                    $keywords1[] = $word['word'];
                }
            }

            //將類別機率算進去
            foreach ($prob as $key => $value) {
                $prob[$key] *= $classCountMap[$key]['p'];
            }

            //找出最有可能的類別
            $answer = array(
                'class' => TEXT_OTHER,
                'p' => 0,
            );
            foreach ($prob as $key => $value) {
                if ($value > $answer['p']) {
                    $answer['class'] = $key;
                    $answer['p'] = $value;
                }
            }

            $keywordsSeq = implode(', ', $keywords1);
            $this->showText("{$index} {$answer['class']} {$data['id']} {$data['title']} [{$keywordsSeq}]");
            //將結果存到資料庫中
            $sth = $this->dbh->prepare($sql['delete']);
            $sth->execute(array($data['id']));
            $sth = $this->dbh->prepare($sql['insert']);
            $sth->execute(array($data['id'], $answer['class'], json_encode($keywords), $data['topic']));
        }
        $this->dbh->commit();
        $spentTime = $this->timer->StopAndReset(); //算出耗時
        $this->showText("所有文章分類完畢，耗時: {$spentTime}");
        $this->showText();
    }

    public function test() {
        //讀出一篇文章
        $doc = (isset($_GET['doc']) ? trim($_GET['doc']) : null);
        $target = null;
        $sql = "SELECT * FROM `ke2015_sample_news` INNER JOIN `classify` ON `ke2015_sample_news`.`id` = `classify`.`id` WHERE `ke2015_sample_news`.`id` = ? LIMIT 1;";
        $target = $this->fetch($sql, array($doc));
        if (count($target) === 0) {
            $doc = '1427958013238_N01';
            $target = $this->fetch($sql, array($doc));
        }
        //處理關鍵字成物件，並且將所有的關鍵字放到一個陣列，讓後面可以快速判斷是否為目標文章擁有的關鍵字(維度)
        $target['keyword'] = json_decode($target['keyword'], true);
        $targetKeywords = array();
        for ($i = 0, $count = count($target['keyword']); $i < $count; $i++) {
            $targetKeywords[] = $target['keyword'][$i]['word'];
        }

        //讀出所有分類結果
        $this->timer->Start(); //開始計時
        $sql = "SELECT * FROM `classify` WHERE 1;";
        $classes = $this->fetchAll($sql);
        $classCount = count($classes);
        $spentTime = $this->timer->StopAndReset(); //算出耗時
        //$this->showText("查詢所有分類結果成功，共有{$classCount}筆資料，耗時: {$spentTime}");
        $this->showText();

        //可能相似的文章
        $similarDocuments = array();
        $similars = array();

        //全部都算成單位向量
        for ($i = 0; $i < $classCount; $i++) {
            $data = $classes[$i];
            $vector = json_decode($data['keyword'], true);
            $similarFlag = false;
            //向量長度、將關鍵字建立MAP
            $classes[$i]['keywordMap'] = array();
            $norm = 0;
            foreach ($vector as $dim) {
                $word = $dim['word'];
                $n = intval($dim['n']);
                if (in_array($word, $targetKeywords)) {
                    $similarFlag = true;
                }
                $classes[$i]['keywordMap'][$word] = $n;
                $norm += pow($n, 2);
            }
            $norm = sqrt($norm);
            $classes[$i]['keyword'] = $vector;
            $classes[$i]['norm'] = $norm;
            //只存跟目標文章有重複關鍵字的文章
            if ($similarFlag === true) {
                //因為這邊都算好了，就直接把id跟目標文章相同的資料也存到目標文章中
                if ($classes[$i]['id'] === $target['id']) {
                    $target['norm'] = $classes[$i]['norm'];
                }
                $similarDocuments[] = $classes[$i];
                $similars[] = 0;
            }
        }

        //計算相似度
        for ($i = 0, $count = count($similarDocuments); $i < $count; $i++) {
            $doc = $similarDocuments[$i];
            $sum = 0;
            foreach ($target['keyword'] as $dim) {
                $word = $dim['word'];
                $n = intval($dim['n']);
                if (isset($doc['keywordMap'][$word])) {
                    $sum += $n * $doc['keywordMap'][$word];
                }
                $similar = $sum / ($target['norm'] * $doc['norm']);
                $similarDocuments[$i]['similar'] = $similar;
                $similars[$i] = $similar;
            }
        }

        //排序相似文章
        array_multisort($similars, SORT_DESC, $similarDocuments);

        //顯示結果
        $keywordSeq = implode(', ', $targetKeywords);
        $this->showText("查詢: {$target['id']} {$target['source']} {$target['section']} {$target['title']} [{$keywordSeq}]");
        $this->showText("相似文章:");
        //相似文章
        $knnMap = array();
        $sql = "SELECT * FROM `ke2015_sample_news` WHERE `id` = ?;";
        for ($i = 0; $i < 7; $i++) {
            if (!isset($similarDocuments[$i])) {
                break;
            }
            $doc = $similarDocuments[$i];
            //從資料庫中讀取文章的詳細資料
            $docDetail = $this->fetch($sql, array($similarDocuments[$i]['id']));
            foreach ($similarDocuments[$i]['keywordMap'] as $key => $value) {
                if (!in_array($key, $targetKeywords)) {
                    unset($similarDocuments[$i]['keywordMap'][$key]);
                }
            }
            //重新取得分類
            $similarDocuments[$i]['class'] = $this->getTopic($docDetail['section']);
            //顯示結果
            $keywordSeq = implode(', ', array_keys($similarDocuments[$i]['keywordMap']));
            $this->showText(" - {$i} {$similarDocuments[$i]['class']} {$similarDocuments[$i]['id']} {$docDetail['source']} {$docDetail['section']} {$docDetail['title']} {$similarDocuments[$i]['similar']} [{$keywordSeq}]");
            //計算knn
            if (!isset($knnMap[$similarDocuments[$i]['class']])) {
                $knnMap[$similarDocuments[$i]['class']] = 0;
            }
            $knnMap[$similarDocuments[$i]['class']] += 1;
        }
        //移除knn結果的其他，讓預設都先出現有主題
        unset($knnMap[TEXT_OTHER]);
        //計算knn結果
        $knnKeys = array_keys($knnMap);
        $knnValues = array_values($knnMap);
        array_multisort($knnValues, SORT_DESC, $knnKeys);
        if (count($knnKeys) > 0) {
            $this->showText("knn result: {$knnKeys[0]}");
        } else {
            //如果都找不到分類，就顯示其他
            $this->showText("knn result: 其他");
        }

        //自動摘要
        $this->showText("文章摘要:");
        //將句號。替換成逗號，
        $target['content'] = mb_ereg_replace('。', '，', $target['content']);
        //用逗號，去切句子
        $sentence = mb_split('，', $target['content']);
        $sentenceCount = count($sentence);
        //去除尾端的空白句子
        while (empty($sentence[$sentenceCount - 1])) {
            array_splice($sentence, -1, 1);
            $sentenceCount--;
        }
        //統計每個句子出現過幾個關鍵字
        $sentenceKeywordStat = array();
        //自動摘要會取多少句子
        $summaryCount = 3;
        for ($i = 0; $i < $sentenceCount; $i++) {
            $keywordCount = 0;
            //檢查句子中是否有出現關鍵字，並進行統計
            foreach ($target['keyword'] as $dim) {
                $word = $dim['word'];
                $keywordCount += mb_substr_count($sentence[$i], $word);
            }
            //進行累加
            if ($i > 0) {
                $keywordCount += $sentenceKeywordStat[$i - 1];
            }
            $sentenceKeywordStat[] = $keywordCount;
        }
        if ($sentenceCount < $summaryCount) {
            $this->showText($target['content']);
        } else {
            //算出每五句中出現關鍵字的數量
            $maxIndex = 0;
            $maxSum = $sentenceKeywordStat[$summaryCount - 1];
            for ($i = $summaryCount; $i < $sentenceCount; $i++) {
                $sum = $sentenceKeywordStat[$i] - $sentenceKeywordStat[$i - $summaryCount];
                if ($sum > $maxSum) {
                    $maxIndex = $i - $summaryCount + 1;
                    $maxSum = $sum;
                }
            }
            //將句子組合成摘要
            $summary = array_slice($sentence, $maxIndex, $summaryCount);
            $this->showText(implode('，', $summary));
        }
    }

}
