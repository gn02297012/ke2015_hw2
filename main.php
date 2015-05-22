<?php

/* ========== */
/* 執行環境設定 */
/* ========== */
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('memory_limit', -1);
set_time_limit(0);
mb_internal_encoding('UTF-8');

/* ========== */
/* 載入相關檔案 */
/* ========== */
require_once('db_config.php');
require_once('Timer.php');
require_once('Controller.php');

/* ========== */
/* 程式主要流程 */
/* ========== */
echo "<pre>\n";
try {
    $controller = new Controller($DB_CONFIG);

    $action = (isset($_GET['action']) ? $_GET['action'] : null);
    if (!method_exists($controller, $action)) {
        throw new Exception("action is not allow");
    }
    $result = $controller->$action();
} catch (PDOException $e) {
    die($e->getMessage());
}
echo "</pre>";
?>