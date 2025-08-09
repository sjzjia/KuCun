<?php
// api/check_mp_auth.php - 小程序API认证检查

// 调试模式下开启错误报告 (生产环境请禁用)
error_reporting(E_ALL);
ini_set('display_errors', 1); // 开发环境下显示错误
ini_set('log_errors', 1); // 开启PHP错误日志记录
ini_set('error_log', __DIR__ . '/check_mp_auth_errors.log'); // 指定日志文件路径

// 引入数据库连接文件
// 假设 db_connect.php 在 api 目录的上一级
require_once '../db_connect.php'; 

header('Content-Type: application/json'); // 设置响应头为 JSON 格式

// 全局变量，用于存储认证成功后的用户ID和用户名
$authenticated_mp_user_id = null;
$authenticated_mp_username = null;

// 从请求头中获取认证令牌
$headers = getallheaders();
$authToken = $headers['X-Auth-Token'] ?? '';

// 调试日志：记录接收到的所有请求头
error_log("check_mp_auth.php: Received headers: " . print_r($headers, true));
// 调试日志：记录接收到的认证令牌
error_log("check_mp_auth.php: Received authToken: " . ($authToken ? $authToken : '[EMPTY]'));

if (empty($authToken)) {
    error_log("check_mp_auth.php: AuthToken is empty. Returning 401.");
    http_response_code(401); // 未授权
    echo json_encode(['success' => false, 'message' => '未提供认证令牌。']);
    $conn->close(); // 关闭数据库连接
    exit(); // 终止脚本
}

// ✨ 查询 user_sessions 表，验证令牌是否有效且未过期
// 关联 users 表以获取用户名
$stmt = $conn->prepare("SELECT us.user_id, u.username FROM user_sessions us JOIN users u ON us.user_id = u.id WHERE us.session_token = ? AND (us.expires_at IS NULL OR us.expires_at > NOW()) LIMIT 1");
if (!$stmt) {
    error_log("check_mp_auth.php: Database prepare failed for user_sessions query: " . $conn->error);
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => '数据库查询准备失败: ' . $conn->error]);
    $conn->close();
    exit();
}

$stmt->bind_param("s", $authToken);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 1) {
    // 认证成功
    $session_data = $result->fetch_assoc();
    $authenticated_mp_user_id = $session_data['user_id'];
    $authenticated_mp_username = $session_data['username'];
    // 调试日志：记录认证成功
    error_log("check_mp_auth.php: Authentication successful for user ID: " . $authenticated_mp_user_id . ", Username: " . $authenticated_mp_username . ", Session Token: " . $authToken);
    // 继续执行后续逻辑（例如 get_inventory.php）

    // 可选：每次成功认证后更新会话的 expires_at，实现“滑动窗口”式过期
    /*
    $new_expires_at = date('Y-m-d H:i:s', strtotime('+7 days'));
    $stmt_update_expiry = $conn->prepare("UPDATE user_sessions SET expires_at = ? WHERE session_token = ?");
    if ($stmt_update_expiry) {
        $stmt_update_expiry->bind_param("ss", $new_expires_at, $authToken);
        $stmt_update_expiry->execute();
        $stmt_update_expiry->close();
    } else {
        error_log("check_mp_auth.php: Failed to prepare statement for session expiry update: " . $conn->error);
    }
    */

} else {
    // 认证失败：令牌无效或已过期
    error_log("check_mp_auth.php: Authentication failed for authToken: " . $authToken . ". Token not found or expired.");
    
    http_response_code(401); // 未授权
    echo json_encode(['success' => false, 'message' => '认证令牌无效或已过期。']);
    $stmt->close();
    $conn->close(); // 关闭数据库连接
    exit(); // 终止脚本
}

$stmt->close();
// 认证成功后，不在此处关闭 $conn，留给调用方（如 get_inventory.php）关闭
?>