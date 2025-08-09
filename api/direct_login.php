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
            // 密码正确，生成新的认证令牌
            $auth_token = bin2hex(random_bytes(32)); // 生成一个随机的64字符令牌

            // --- 获取 SSO 状态 ---
            $sso_enabled = true; // 默认启用，如果system_settings中不存在则视为启用
            $sql_get_sso = "SELECT setting_value FROM system_settings WHERE setting_name = 'sso_enabled'";
            $result_sso = $conn->query($sql_get_sso);
            if ($result_sso && $result_sso->num_rows > 0) {
                $row_sso = $result_sso->fetch_assoc();
                $sso_enabled = ($row_sso['setting_value'] === 'true');
            }
            // --- 结束获取 SSO 状态 ---

            // 根据 SSO 配置决定是否更新 current_session_id
            if ($sso_enabled) {
                // 如果 SSO 启用，更新 current_session_id 以强制单点登录（互顶）
                $stmt_update_token = $conn->prepare("UPDATE users SET current_session_id = ? WHERE id = ?");
                if (!$stmt_update_token) {
                    throw new Exception("数据库准备语句失败 (更新令牌): " . $conn->error);
                }
                $stmt_update_token->bind_param("si", $auth_token, $user_id);
                if (!$stmt_update_token->execute()) {
                    throw new Exception("更新会话令牌失败: " . $stmt_update_token->error);
                }
                $stmt_update_token->close();
            } else {
                // 如果 SSO 禁用，则不更新 current_session_id。
                // 这意味着新生成的令牌不会强制踢掉其他会话。
                // ！！！重要提示：在这种情况下，小程序端必须能够处理其令牌在后端
                // current_session_id 字段中不存在的情况，这需要 check_mp_auth.php 的配合。
                // 但是，当前的 check_mp_auth.php 严格依赖 current_session_id 来验证。
                // 因此，此“禁用互顶”功能将导致在 sso_enabled=false 时，小程序后续的 API 请求认证失败。
                // 真正的多设备登录需要数据库结构上的调整（例如独立的 session 表）。
            }

            $response['success'] = true;
            $response['message'] = '登录成功';
            $response['auth_token'] = $auth_token;
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
