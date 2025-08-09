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

// 查询数据库，验证令牌
$stmt = $conn->prepare("SELECT id, username, current_session_id FROM users WHERE current_session_id = ? LIMIT 1"); // 添加 current_session_id 到 SELECT 列表以进行日志记录
if (!$stmt) {
    error_log("check_mp_auth.php: Database prepare failed: " . $conn->error);
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
    $user = $result->fetch_assoc();
    $authenticated_mp_user_id = $user['id'];
    $authenticated_mp_username = $user['username'];
    // 调试日志：记录认证成功
    error_log("check_mp_auth.php: Authentication successful for user ID: " . $user['id'] . ", Username: " . $user['username']);
    error_log("check_mp_auth.php: Database current_session_id: " . ($user['current_session_id'] ? $user['current_session_id'] : '[NULL]'));
    // 继续执行后续逻辑（例如 get_inventory.php）
} else {
    // 认证失败：令牌无效或已过期
    error_log("check_mp_auth.php: Authentication failed for authToken: " . $authToken);
    
    http_response_code(401); // 未授权
    echo json_encode(['success' => false, 'message' => '认证令牌无效或已过期。']);
    $stmt->close();
    $conn->close(); // 关闭数据库连接
    exit(); // 终止脚本
}

$stmt->close();
// 认证成功后，不在此处关闭 $conn，留给调用方（如 get_inventory.php）关闭
?>