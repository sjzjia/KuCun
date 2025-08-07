<?php
// change_password.php - 修改密码页面

session_start(); // 启动会话

// 检查用户是否已登录，否则重定向到登录页面
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: login.php");
    exit;
}

require_once 'db_connect.php'; // 引入数据库连接文件

$message = '';
$error = '';

// 处理修改密码表单提交
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_new_password = $_POST['confirm_new_password'];
    $user_id = $_SESSION['id']; // 获取当前登录用户的ID

    // 1. 验证新密码和确认密码是否一致
    if ($new_password !== $confirm_new_password) {
        $error = "新密码和确认密码不匹配。";
    } elseif (empty($new_password) || strlen($new_password) < 6) {
        $error = "新密码不能为空且至少需要6个字符。";
    } else {
        // 2. 从数据库获取当前用户的哈希密码
        $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows == 1) {
            $stmt->bind_result($hashed_password);
            $stmt->fetch();

            // 3. 验证当前密码是否正确
            if (password_verify($current_password, $hashed_password)) {
                // 4. 哈希新密码
                $new_hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

                // 5. 更新数据库中的密码
                $update_stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
                $update_stmt->bind_param("si", $new_hashed_password, $user_id);

                if ($update_stmt->execute()) {
                    $message = "密码修改成功！";
                } else {
                    $error = "密码修改失败: " . $update_stmt->error;
                }
                $update_stmt->close();
            } else {
                $error = "当前密码不正确。";
            }
        } else {
            $error = "未找到用户。"; // 理论上不应该发生，因为用户已登录
        }
        $stmt->close();
    }
}
$conn->close();
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>修改密码</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f0f2f5;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            padding: 2rem;
        }
    </style>
</head>
<body class="bg-gray-100">
    <nav class="bg-blue-600 p-4 shadow-md">
        <div class="container flex justify-between items-center">
            <h1 class="text-white text-2xl font-bold">库存管理系统</h1>
            <div class="flex items-center space-x-4">
                <a href="dashboard.php" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg transition duration-150 ease-in-out">
                    返回仪表盘
                </a>
                <a href="logout.php" class="bg-red-500 hover:bg-red-700 text-white font-bold py-2 px-4 rounded-lg transition duration-150 ease-in-out">
                    注销
                </a>
            </div>
        </div>
    </nav>

    <div class="container mt-8">
        <h2 class="text-3xl font-bold text-gray-800 mb-6">修改密码</h2>

        <?php if (!empty($message)): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">
                <span class="block sm:inline"><?php echo $message; ?></span>
            </div>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
                <span class="block sm:inline"><?php echo $error; ?></span>
            </div>
        <?php endif; ?>

        <div class="bg-white p-8 rounded-lg shadow-lg">
            <form action="change_password.php" method="post">
                <div class="mb-4">
                    <label for="current_password" class="block text-gray-700 text-sm font-bold mb-2">当前密码:</label>
                    <input type="password" id="current_password" name="current_password" required
                           class="shadow appearance-none border rounded-lg w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                </div>
                <div class="mb-4">
                    <label for="new_password" class="block text-gray-700 text-sm font-bold mb-2">新密码:</label>
                    <input type="password" id="new_password" name="new_password" required
                           class="shadow appearance-none border rounded-lg w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                </div>
                <div class="mb-6">
                    <label for="confirm_new_password" class="block text-gray-700 text-sm font-bold mb-2">确认新密码:</label>
                    <input type="password" id="confirm_new_password" name="confirm_new_password" required
                           class="shadow appearance-none border rounded-lg w-full py-2 px-3 text-gray-700 mb-3 leading-tight focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                </div>
                <div class="flex items-center justify-between">
                    <button type="submit"
                            class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg focus:outline-none focus:shadow-outline w-full transition duration-150 ease-in-out">
                        修改密码
                    </button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>