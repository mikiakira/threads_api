<?php
require_once './const.php';
require_once './ThreadsClass.php';

try {
    $threads = new threads();
    // トークンを取得する
    $threads->getlongAccessTokenAndUserId();
    // テスト: 期限切れの時間をセットする
    //$threads->limit_date = '2021-09-01 00:00:00';
    // 長期トークンの有効期限を確認して必要があれば更新する
    $threads->refreshLongAccessToken();
    // 投稿する内容を設定する
    $threads->post('これはテスト投稿です。PHP で Threads API を使っています。', 'https://placehold.jp/150x150.png', 'IMAGE');
    // 投稿したものを公開する
    $threads->publishPost();
} catch (Exception $e) {
    die('Error:' . $e->getMessage());
} catch (PDOException $e) {

    die('PDO_Error:' . $e->getMessage());
}
