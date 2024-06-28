<?php
// 「認証コード」を「短期アクセストークン」に交換、
// 次に「短期アクセストークン」を「長期アクセストークン」に交換し
// データベースに保存するスクリプト

require_once './const.php';
require_once './ThreadsClass.php';

try {
    $threads = new threads();
    // コールバック処理から GET:code を受け取り
    // 短期アクセストークンを取得する処理を行う
    $threads->getAccessToken();
    // 短期アクセストークンを長期アクセストークンと交換する処理を行う
    $threads->changeLongAccessToken();
    // 保存する
    $threads->saveToken();
} catch (Exception $e) {
    die('Error:' . $e->getMessage());
} catch (PDOException $e) {
    die('PDO_Error:' . $e->getMessage());
}
