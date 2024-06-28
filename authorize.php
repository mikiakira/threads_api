<?php
// 「認証ウィンドゥ」を開くための URL を取得するスクリプト
// （事前に const.php に必要なパラメータを設定しておくこと）

require_once './const.php';
require_once './ThreadsClass.php';

try {
    $threads = new threads();

    // アプリを認証するための「承認ウィンドゥ」を開くための URL を取得
    $authUrl = $threads->authorize();
    // リダイレクトで開く:
    header('Location: ' . $authUrl);

    // --- 処理説明 ---
    // ダイレクト URI にアクセスすると、
    // コールバックで GET パラメータ: code が付与される
    // code(「認証コード」)と短期アクセストークンを交換する処理を行い、
    // 次に長期アクセストークンを取得する処理を行う
    // 投稿処理は、長期アクセストークンを用いて実施する
} catch (Exception $e) {
    die('Error:' . $e->getMessage());
} catch (PDOException $e) {
    die('PDO_Error:' . $e->getMessage());
}
