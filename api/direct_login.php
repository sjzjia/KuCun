<?php
// api/direct_login.php - 用户名密码直接登录接口

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
    'username' => null // 返回登录成功的用户名
];

// 获取 POST 请求中的用户名和密码
$username = $_POST['username'] ?? '';
$password = $_POST['password'] ?? '';

if (empty($username) || empty($password)) {
    $response['message'] = '用户名或密码不能为空。';
    echo json_encode($response);
    exit;
}

try {
    // 查询数据库中是否有匹配的用户名
    $stmt = $conn->prepare("SELECT id, username, password FROM users WHERE username = ?");
    if (!$stmt) {
        throw new Exception("数据库准备语句失败: " . $conn->error);
    }
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows == 1) {
        $stmt->bind_result($user_id, $db_username, $hashed_password);
        $stmt->fetch();

        // 验证密码
        if (password_verify($password, $hashed_password)) {
            // 密码正确，生成新的认证令牌 (会话令牌)
            $session_token = bin2hex(random_bytes(32)); // 生成一个随机的64字符令牌

            // --- 获取 SSO 状态 ---
            $sso_enabled = true; // 默认启用，如果system_settings中不存在则视为启用
            $sql_get_sso = "SELECT setting_value FROM system_settings WHERE setting_name = 'sso_enabled'";
            $result_sso = $conn->query($sql_get_sso);
            if ($result_sso && $result_sso->num_rows > 0) {
                $row_sso = $result_sso->fetch_assoc();
                $sso_enabled = ($row_sso['setting_value'] === 'true');
            }
            // --- 结束获取 SSO 状态 ---

            // 根据 SSO 配置决定是否清除旧会话
            if ($sso_enabled) {
                // 如果 SSO 启用，删除该用户所有旧的会话令牌，实现单点登录（踢掉其他设备）
                $stmt_delete_old_sessions = $conn->prepare("DELETE FROM user_sessions WHERE user_id = ?");
                if (!$stmt_delete_old_sessions) {
                    throw new Exception("数据库准备语句失败 (删除旧会话): " . $conn->error);
                }
                $stmt_delete_old_sessions->bind_param("i", $user_id);
                if (!$stmt_delete_old_sessions->execute()) {
                    throw new Exception("删除旧会话令牌失败: " . $stmt_delete_old_sessions->error);
                }
                $stmt_delete_old_sessions->close();
            }
            // 如果 SSO 禁用 ($sso_enabled 为 false)，则不删除旧会话，允许同时在线

            // 将新生成的会话令牌插入到 user_sessions 表
            // 可以选择设置一个过期时间，例如 7 天后过期
            $expires_at = date('Y-m-d H:i:s', strtotime('+7 days')); // 设置令牌有效期为7天
            
            $stmt_insert_session = $conn->prepare("INSERT INTO user_sessions (session_token, user_id, expires_at) VALUES (?, ?, ?)");
            if (!$stmt_insert_session) {
                throw new Exception("数据库准备语句失败 (插入新会话): " . $conn->error);
            }
            $stmt_insert_session->bind_param("sis", $session_token, $user_id, $expires_at);
            if (!$stmt_insert_session->execute()) {
                throw new Exception("插入新会话令牌失败: " . $stmt_insert_session->error);
            }
            $stmt_insert_session->close();

            $response['success'] = true;
            $response['message'] = '登录成功';
            $response['auth_token'] = $session_token; // 返回新生成的会话令牌
            $response['username'] = $db_username;
            
        } else {
            $response['message'] = '用户名或密码不正确。';
        }
    } else {
        $response['message'] = '用户名或密码不正确。';
    }
    $stmt->close();

} catch (Exception $e) {
    $response['message'] = '服务器错误: ' . $e->getMessage();
} finally {
    if ($conn) {
        $conn->close();
    }
}

echo json_encode($response);
?>
