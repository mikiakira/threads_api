<?php

class threads
{
    public $appId = APPID;
    public $apiSecret = APISECRET;
    public $redirectUri = REDIRECT_URI;
    public $userId = null;
    public $shortAccessToken = null;
    public $longAccessToken = null;
    public $expires_in = null;
    public $limit_date = null;
    public $code = null;
    public $endPointUri = 'https://graph.threads.net/';
    public $version = 'v1.0/';
    public $result = null;
    public $creation_id = null;
    public $dbh = null;
    private $pdo;
    private $dbFile = 'threads_tokens.sqlite';  // SQLiteファイルの名前

    /**
     * コンストラクタ
     * データベースファイル(SQLite)が存在しない場合は作成する
     */
    public function __construct()
    {
        try {
            // データベースファイルが存在しない場合は作成する
            if (!file_exists($this->dbFile)) {
                $this->pdo = new PDO('sqlite:' . $this->dbFile);
                $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                $this->createTable();
            } else {
                // データベースファイルが存在する場合は接続する
                $this->pdo = new PDO('sqlite:' . $this->dbFile);
                $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                // トークンの有効期限を取得する
                $sql = 'SELECT limit_date FROM threads';
                $stmt = $this->pdo->query($sql);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($result) {
                    $this->limit_date = $result['limit_date'];
                }
            }
        } catch (PDOException $e) {
            echo "Database connection failed: " . $e->getMessage();
        }
    }

    /**
     * トークンの有効期限が近づいているかどうかを判定する
     *
     * @return boolean
     */
    public function isTokenExpiringSoon()
    {
        // limit_date が null の場合は false を返す
        if (!$this->limit_date) {
            return false;
        }
        // limit_date が現在日時よりも前の場合は true を返す
        $currentDate = new DateTime();
        $limitDate = new DateTime($this->limit_date);
        if ($currentDate > $limitDate) {
            return true;
        }
        return false;
    }

    /**
     * トークン管理テーブルを作成する
     *
     * @return void
     */
    private function createTable()
    {
        // テーブルが存在しない場合に作成する
        $createTableSQL = "
        CREATE TABLE IF NOT EXISTS `threads` (
            `id` INTEGER PRIMARY KEY,
            `user_id` TEXT NOT NULL,
            `expires_in` TEXT DEFAULT 0,
            `limit_date` TEXT DEFAULT 0,
            `long_access_token` TEXT NOT NULL
        );
        ";
        try {
            $this->pdo->exec($createTableSQL);
        } catch (PDOException $e) {
            echo "Table creation failed: " . $e->getMessage();
        }
    }
    /**
     * 認証コードを取得するための
     * 認証ウィンドウを開く URL を生成する
     *
     * @param string $uri
     * @return string $url
     */
    public function authorize($uri = 'authorize')
    {
        $url = 'https://threads.net/oauth/authorize';

        $params = [
            'client_id' => $this->appId,
            'scope' => 'threads_basic,threads_content_publish',
            'redirect_uri' => $this->redirectUri,
            'response_type' => 'code',
        ];

        return $url . '?' . http_build_query($params);
    }

    /**
     * トークン情報を取得する
     *
     * @return void
     */
    public function getlongAccessTokenAndUserId()
    {
        try {
            // SQLite への接続とデータの取得
            $this->dbh = new PDO('sqlite:' . $this->dbFile);
            $this->dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $sql = 'SELECT * FROM threads';
            $stmt = $this->dbh->query($sql);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $this->userId = $result['user_id'];
            $this->longAccessToken = $result['long_access_token'];
            $this->expires_in = $result['expires_in'];
            $this->limit_date = $result['limit_date'];
        } catch (PDOException $e) {
            print('Error:' . $e->getMessage());
            die();
        }

        return $this;
    }

    /**
     * 短期アクセストークンを取得する
     * @param string $uri
     */
    public function getAccessToken($uri = 'oauth/access_token')
    {
        if (!isset($_GET['code'])) {
            throw new Exception('code not found');
        }

        // 「承認コード」から不要な文字列を削除
        $this->code = str_replace('#_', '', $_GET['code']);

        // アクセストークンを取得するための URL を生成
        $url = $this->endPointUri . $uri;

        $params = [
            'client_id' => $this->appId,
            'client_secret' => $this->apiSecret,
            'code' => $this->code,
            'grant_type' => 'authorization_code',
            'redirect_uri' => $this->redirectUri
        ];

        $options = [
            'http' => [
                'header'  => "Content-Type: application/x-www-form-urlencoded; charset=utf-8\r\n",
                'method'  => 'POST',
                'content' => http_build_query($params),
                'timeout' => 30
            ]
        ];
        $context  = stream_context_create($options);
        $response = @file_get_contents($url, false, $context);
        if ($response === FALSE) {
            $error = error_get_last();
            echo "HTTP request failed. Error was: " . $error['message'];
        } else {
            $data = json_decode($response, true);
            if (isset($data['access_token'])) {
                // アクセストークンと有効期限とユーザーIDを取得
                $this->shortAccessToken = $data['access_token'];
                $this->userId = $data['user_id'];
                return $this;
            } else {
                echo "Error in response: " . $response;
            }
        }
    }

    /**
     * 長期アクセストークンを取得する
     *
     * @param string $uri
     */
    public function changeLongAccessToken()
    {
        $url = "https://graph.threads.net/access_token?grant_type=th_exchange_token&client_secret={$this->apiSecret}&access_token={$this->shortAccessToken}";
        $response = file_get_contents($url);
        $res = json_decode($response);
        $this->longAccessToken = $res->access_token;
        $this->expires_in = $res->expires_in;
        // 有効期限を設定する
        $this->limit_date = $this->getExpiresDate();
        return $this;
    }

    /**
     * 長期アクセストークンを更新する
     *
     * @return void
     */
    public function refreshLongAccessToken()
    {
        if ($this->isTokenExpiringSoon()) {
            $url = "https://graph.threads.net/refresh_access_token?grant_type=th_refresh_token&access_token={$this->longAccessToken}";
            $response = file_get_contents($url);
            $res = json_decode($response);
            $this->longAccessToken = $res->access_token;
            $this->expires_in = $res->expires_in;
            // 有効期限を設定する
            $this->limit_date = $this->getExpiresDate();
            $this->setUpdate();
        }

        return $this;
    }

    /**
     * 投稿する
     *
     * @param string $text
     * @param string $imgUrl
     * @param string $media_type
     * @param string $uri
     * @return void
     */
    public function post($text, $imgUrl = null, $media_type = 'TEXT', $uri = '/threads')
    {
        $url = $this->endPointUri . $this->version . $this->userId . $uri;

        $params = [
            'text' => $text,
            'access_token' => $this->longAccessToken,
            'media_type' => $media_type,
        ];

        if ($media_type === 'IMAGE') {
            $params['image_url'] = $imgUrl;
        }

        $options = [
            'http' => [
                'header'  => "Content-Type: application/x-www-form-urlencoded\r\n",
                'method'  => 'POST',
                'content' => http_build_query($params),
                'timeout' => 10
            ]
        ];
        $context  = stream_context_create($options);
        $response = file_get_contents($url, false, $context);
        $creation_id = json_decode($response);
        $this->creation_id = $creation_id->id;

        return $this;
    }

    /**
     * 投稿したものを公開する
     *
     * @param string $uri
     * @return void
     */
    public function publishPost($uri = '/threads_publish')
    {
        if ($this->creation_id) {
            $url = $this->endPointUri . $this->version . $this->userId . $uri;

            $params = [
                'creation_id' => $this->creation_id,
                'access_token' => $this->longAccessToken,
            ];

            $options = [
                'http' => [
                    'header'  => "Content-Type: application/x-www-form-urlencoded\r\n",
                    'method'  => 'POST',
                    'content' => http_build_query($params),
                    'timeout' => 10
                ]
            ];
            $context  = stream_context_create($options);
            $response = file_get_contents($url, false, $context);
            $response = json_decode($response);
        }
        return $this;
    }

    /**
     * トークンを保存する(初回認証時)
     *
     * @return void
     */
    public function saveToken()
    {
        try {
            // user_id で検索して、データが存在する場合は何もしない、存在しない場合は新規追加
            $this->dbh = new PDO('sqlite:' . $this->dbFile);
            $this->dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            // user_id で検索してカウントする
            $sql = 'SELECT COUNT(*) FROM threads WHERE user_id = ?';
            $stmt = $this->dbh->prepare($sql);
            $stmt->execute([$this->userId]);
            $count = $stmt->fetchColumn();
            if ($count > 0) {
                // データが存在する場合は終了
                exit;
            } else {
                // データが存在しない場合は新規追加
                $this->limit_date = $this->getExpiresDate(); // 秒数を日時に変換
                $sql = 'INSERT INTO threads (user_id, expires_in, long_access_token, limit_date) VALUES (?, ?, ?, ?)';
                $stmt = $this->dbh->prepare($sql);
                $flag = $stmt->execute([$this->userId, $this->expires_in, $this->longAccessToken, $this->limit_date]);
                if ($flag) {
                    return true;
                } else {
                    return false;
                }
            }
        } catch (PDOException $e) {
            print('Error:' . $e->getMessage());
            die();
        }
    }

    /**
     * データを更新する
     *
     * @return void
     */
    public function setUpdate()
    {
        try {
            // SQLite への接続とデータの更新
            $this->dbh = new PDO('sqlite:' . $this->dbFile);
            $this->dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $sql = 'UPDATE threads SET expires_in = ?, long_access_token = ?, limit_date = ? WHERE user_id = ?';
            $stmt = $this->dbh->prepare($sql);
            $flag = $stmt->execute([$this->expires_in, $this->longAccessToken, $this->limit_date, $this->userId]);
            if ($flag) {
                return true;
            } else {
                return false;
            }
        } catch (PDOException $e) {
            print('Error:' . $e->getMessage());
            die();
        }
    }

    /**
     * テスト用: 有効期限を任意に設定する
     *
     * @param [type] $date
     * @return void
     */
    public function setExpiresIn($date)
    {
        $this->limit_date = $date;
        return $this;
    }

    // レート制限の使用状況を確認するメソッド
    public function checkRateLimit()
    {
        $url = $this->endPointUri . $this->version . $this->userId . '/threads_publishing_limit';
        $url .= '?fields=quota_usage,config&access_token=' . $this->longAccessToken;

        $options = [
            'http' => [
                'method' => 'GET',
                'timeout' => 10
            ]
        ];
        $context = stream_context_create($options);
        $response = file_get_contents($url, false, $context);
        $rateLimitInfo = json_decode($response);

        return $rateLimitInfo;
    }

    /**
     * expires_in の値(秒数）を現在日時に加算して有効期限年月日を取得する
     * @return string
     */
    public function getExpiresDate()
    {
        $currentDate = new DateTime();
        $expiryDate = (clone $currentDate)->add(new DateInterval('PT' . $this->expires_in . 'S'));
        return $expiryDate->format('Y-m-d H:i:s');
    }
}
