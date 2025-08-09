<?php
// login.php - 用户登录页面

session_start(); // 启动会话

require_once 'db_connect.php'; // 引入数据库连接文件

$username = $password = "";
$username_err = $password_err = $login_err = "";

// 检查是否已提交表单
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // 检查用户名是否为空
    if (empty(trim($_POST["username"]))) {
        $username_err = "请输入用户名。";
    } else {
        $username = trim($_POST["username"]);
    }

    // 检查密码是否为空
    if (empty(trim($_POST["password"]))) {
        $password_err = "请输入密码。";
    } else {
        $password = trim($_POST["password"]);
    }

    // 验证凭据
    if (empty($username_err) && empty($password_err)) {
        $sql = "SELECT id, username, password FROM users WHERE username = ?";

        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("s", $param_username);
            $param_username = $username;

            if ($stmt->execute()) {
                $stmt->store_result();

                // 检查用户名是否存在，如果存在则验证密码
                if ($stmt->num_rows == 1) {
                    $stmt->bind_result($id, $username, $hashed_password);
                    if ($stmt->fetch()) {
                        if (password_verify($password, $hashed_password)) {
                            // 密码正确，启动新会话
                            session_regenerate_id(true); // 生成新的会话ID，防止会话固定攻击

                            $_SESSION["loggedin"] = true;
                            $_SESSION["id"] = $id;
                            $_SESSION["username"] = $username;
                            // ✨ 移除以下行，不再将 current_session_id 存储到 $_SESSION 或数据库
                            // $_SESSION["current_session_id"] = session_id(); 

                            // 将当前会话ID更新到数据库
                            // ✨ 移除以下代码块，不再更新 users 表的 current_session_id
                            /*
                            $update_sql = "UPDATE users SET current_session_id = ? WHERE id = ?";
                            if ($update_stmt = $conn->prepare($update_sql)) {
                                $update_stmt->bind_param("si", $_SESSION["current_session_id"], $id);
                                $update_stmt->execute();
                                $update_stmt->close();
                            }
                            */

                            // 重定向到仪表盘页面
                            header("Location: dashboard.php");
                            exit;
                        } else {
                            $login_err = "用户名或密码无效。";
                        }
                    }
                } else {
                    $login_err = "用户名或密码无效。";
                }
            } else {
                echo "糟糕！出错了。请稍后再试。";
            }

            $stmt->close();
        }
    }

    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>用户登录</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f0f2f5;
        }
        .container {
            max-width: 400px;
            margin: 0 auto;
            padding: 2rem;
        }
        /* 全屏背景图片样式 */
        .background-image-container {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            /* 使用 PHP 自动填入完整的网站 URL */
            background-image: url('<?php
                $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
                $host = $_SERVER['HTTP_HOST'];
                echo $protocol . "://" . $host . "/login.jpg";
            ?>'); 
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            filter: brightness(70%); /* 调暗背景图，使文字更清晰 */
            z-index: -1; /* 将背景图置于底层 */
        }
    </style>
</head>
<body class="bg-gray-100 flex items-center justify-center min-h-screen">
    <!-- 背景图片容器 -->
    <div class="background-image-container"></div>

    <div class="container bg-white p-8 rounded-lg shadow-lg relative z-10"> <!-- 添加 z-10 确保表单在背景之上 -->
        <h2 class="text-3xl font-bold text-gray-800 mb-6 text-center">用户登录</h2>
        <p class="text-gray-600 text-center mb-6">请输入您的凭据登录。</p>

        <?php
        if (!empty($login_err)) {
            echo '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">' . $login_err . '</div>';
        }
        ?>

        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
            <div class="mb-4">
                <label for="username" class="block text-gray-700 text-sm font-bold mb-2">用户名:</label>
                <input type="text" id="username" name="username"
                       class="shadow appearance-none border rounded-lg w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent <?php echo (!empty($username_err)) ? 'border-red-500' : ''; ?>"
                       value="<?php echo $username; ?>">
                <span class="text-red-500 text-xs italic"><?php echo $username_err; ?></span>
            </div>
            <div class="mb-6">
                <label for="password" class="block text-gray-700 text-sm font-bold mb-2">密码:</label>
                <input type="password" id="password" name="password"
                       class="shadow appearance-none border rounded-lg w-full py-2 px-3 text-gray-700 mb-3 leading-tight focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent <?php echo (!empty($password_err)) ? 'border-red-500' : ''; ?>">
                <span class="text-red-500 text-xs italic"><?php echo $password_err; ?></span>
            </div>
            <div class="flex items-center justify-between">
                <button type="submit"
                        class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg focus:outline-none focus:shadow-outline w-full transition duration-150 ease-in-out">
                    登录
                </button>
            </div>
        </form>
    </div>
</body>
</html>
