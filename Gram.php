<?php

class Gram {

    /**
     * 關鍵字
     * @var type 
     */
    var $word = null;

    /**
     * 關鍵字出現次數
     * @var type 
     */
    var $tf = 0;

    /**
     * 在文件中的出現次數
     * @var type 
     */
    var $df = 0;

    /**
     * DF的倒數
     * @var type 
     */
    var $idf = 0;

    /**
     * TF-IDF權數
     * @var type 
     */
    var $tf_idf = 0;

    /**
     * 用於紀錄關鍵字出現在哪些文章
     * @var type 
     */
    var $documents = array();

    /**
     * 建構子
     * @param type $w
     */
    public function Gram($w) {
        $this->word = $w;
    }

}
