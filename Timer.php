<?php

/**
 * 簡單的計時器，用來計算程式碼的執行時間
 */
class Timer {

    private $startTime = null;
    private $stopTime = null;
    private $interval = 0;

    /**
     * 開始計時
     * @return type 開始時間
     */
    function Start() {
        $this->startTime = microtime(true);
        return $this->startTime;
    }

    /**
     * 停止計時並計算出時間間隔
     * @return type 停止時間
     */
    function Stop() {
        $this->stopTime = microtime(true);
        $this->interval = $this->stopTime - $this->startTime;
        return $this->stopTime;
    }

    /**
     * 重置
     * @return boolean
     */
    function Reset() {
        $this->startTime = null;
        $this->stopTime = null;
        return true;
    }

    /**
     * 停止並重置
     * @return type 時間間隔
     */
    function StopAndReset() {
        $this->Stop();
        $this->Reset();
        return $this->interval;
    }

}
