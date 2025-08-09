    <?php
    // api/wx_login.php - 微信小程序登录接口

    // 调试模式下开启错误报告 (生产环境请禁用)
    error_reporting(E_ALL);
    ini_set('display_errors', 1);

    // 引入数据库连接
    require_once '../db_connect.php'; 

    // 设置响应头为 JSON 格式
    header('Content-Type: application/json');

    $response = [
        'success' => false,
        'message' => '登录失败',
        'auth_token' => null,
        'openid' => null
    ];

    // 获取小程序传来的 code
    $code = $_POST['code'] ?? '';

    if (empty($code)) {
        $response['message'] = '缺少微信登录凭证 (code)。';
        echo json_encode($response);
        exit;
    }

    // --- 以下部分在实际部署时需要替换为真实的微信 API 调用 ---
    // 你的小程序 AppID 和 AppSecret
    // 请在微信小程序后台 -> 开发 -> 开发设置 中获取
    // !!! IMPORTANT: Replace 'YOUR_WECHAT_APP_ID' and 'YOUR_WECHAT_APP_SECRET' with your actual values for production.
    $app_id = ''; 
    $app_secret = '';

    // 模拟调用微信 code2Session 接口，获取 openid 和 session_key
    // 实际生产环境请使用 curl 或 file_get_contents 发送 HTTP 请求到微信官方接口
    /*
    $wx_api_url = "https://api.weixin.qq.com/sns/jscode2session?appid={$app_id}&secret={$app_secret}&js_code={$code}&grant_type=authorization_code";
    $wx_response_json = file_get_contents($wx_api_url);
    $wx_response = json_decode($wx_response_json, true);

    if (isset($wx_response['openid'])) {
        $openid = $wx_response['openid'];
        $session_key = $wx_response['session_key'];
    } else {
        $response['message'] = '微信授权失败: ' . ($wx_response['errmsg'] ?? '未知错误');
        echo json_encode($response);
        $conn->close();
        exit;
    }
    */
    // 模拟微信响应 (用于测试，实际应从 $wx_response 获取)
    $openid = 'oTestMiniProgramUser_' . substr(md5($code), 0, 16); // 基于code生成模拟openid
    $session_key = 'simulated_session_key_' . uniqid(); 
    // --- 模拟部分结束 ---

    try {
        // 检查用户是否已存在（这里假设用 openid 作为用户名）
        $stmt_check_user = $conn->prepare("SELECT id FROM users WHERE username = ?");
        if (!$stmt_check_user) {
            throw new Exception("数据库准备语句失败: " . $conn->error);
        }
        $stmt_check_user->bind_param("s", $openid);
        $stmt_check_user->execute();
        $stmt_check_user->store_result();

        $user_id = null;
        if ($stmt_check_user->num_rows > 0) {
            // 用户已存在
            $stmt_check_user->bind_result($user_id);
            $stmt_check_user->fetch();
        } else {
            // 用户不存在，创建一个新用户（并设置一个空的或默认密码，因为小程序登录无需密码）
            $dummy_password_hash = password_hash(uniqid(), PASSWORD_DEFAULT); // 生成一个随机哈希密码
            $stmt_insert_user = $conn->prepare("INSERT INTO users (username, password) VALUES (?, ?)");
            if (!$stmt_insert_user) {
                throw new Exception("数据库准备语句失败: " . $conn->error);
            }
            $stmt_insert_user->bind_param("ss", $openid, $dummy_password_hash);
            if (!$stmt_insert_user->execute()) {
                throw new Exception("创建用户失败: " . $stmt_insert_user->error);
            }
            $user_id = $stmt_insert_user->insert_id; // 获取新插入的用户ID
            $stmt_insert_user->close();
        }
        $stmt_check_user->close();

        // 生成一个会话令牌（这里使用一个随机字符串作为示例）
        $auth_token = bin2hex(random_bytes(32)); // 生成一个随机的64字符令牌

        // 将令牌存储到用户的 current_session_id 字段
        // !!! IMPORTANT: Ensure your 'users' table has a 'current_session_id' column (VARCHAR(255) NULL).
        // If not, run: ALTER TABLE users ADD COLUMN current_session_id VARCHAR(255) NULL;
        $stmt_update_token = $conn->prepare("UPDATE users SET current_session_id = ? WHERE id = ?");
        if (!$stmt_update_token) {
            throw new Exception("数据库准备语句失败: " . $conn->error);
        }
        $stmt_update_token->bind_param("si", $auth_token, $user_id);
        if (!$stmt_update_token->execute()) {
            throw new Exception("更新会话令牌失败: " . $stmt_update_token->error);
        }
        $stmt_update_token->close();

        $response['success'] = true;
        $response['message'] = '登录成功';
        $response['auth_token'] = $auth_token;
        $response['openid'] = $openid; // 返回 openid，小程序可能需要
        
    } catch (Exception $e) {
        $response['message'] = '服务器错误: ' . $e->getMessage();
    } finally {
        if ($conn) {
            $conn->close();
        }
    }

    echo json_encode($response);
    ?>
