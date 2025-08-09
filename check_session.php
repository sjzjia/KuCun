<?php
// check_session.php - 用于验证用户会话的通用文件

// 确保会话已启动
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// 检查用户是否已登录
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    // 如果未登录，重定向到登录页面
    header("Location: login.php");
    exit;
}

// ✨ 移除以下与 current_session_id 相关的数据库查询和验证逻辑
// 引入数据库连接
require_once 'db_connect.php';

// 获取当前用户的数据库中存储的会话ID
// $user_id = $_SESSION['id'];
// $current_browser_session_id = session_id();

// $sql = "SELECT current_session_id FROM users WHERE id = ?";
// if ($stmt = $conn->prepare($sql)) {
//     $stmt->bind_param("i", $user_id);
//     $stmt->execute();
//     $stmt->bind_result($db_session_id);
//     $stmt->fetch();
//     $stmt->close();

//     // 如果数据库中的会话ID与当前浏览器会话ID不匹配，则表示用户已在其他地方登录
//     if ($db_session_id !== $current_browser_session_id) {
//         // 销毁当前会话
//         $_SESSION = array(); // 清空所有会话变量
//         session_destroy(); // 销毁会话
//         // 重定向到登录页面，并附带一个消息
//         header("Location: login.php?message=logged_out_elsewhere");
//         exit;
//     }
// } else {
//     error_log("Failed to prepare statement for session check: " . $conn->error);
// }

// 每次成功通过检查后，可以考虑更新会话的最后活动时间，以延长会话有效期
// session_regenerate_id(true); // 如果每次请求都重新生成ID，可能会导致一些问题，慎用
// $_SESSION['last_activity'] = time(); // 记录最后活动时间
?>