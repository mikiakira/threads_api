<?php

// 保存されたトークンを表示するスクリプト

require_once './const.php';
require_once './ThreadsClass.php';

try {
    $threads = new threads();
    // 保存された長期トークンを表示する
    $threads->getlongAccessTokenAndUserId();
    echo 'user_id: ' . $threads->userId . '<br>';
    echo 'longAccessToken: ' . $threads->longAccessToken . '<br>';
    echo 'expires_in: ' . $threads->expires_in . '<br>';
    echo 'limit_date: ' . $threads->limit_date . '<br>';
} catch (Exception $e) {
    die('Error:' . $e->getMessage());
} catch (PDOException $e) {

    die('PDO_Error:' . $e->getMessage());
}
