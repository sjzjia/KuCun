<?php
// db_connect.php - 数据库连接文件

$servername = "mysql"; // 数据库服务器名
$username = "root";      // 数据库用户名 (通常是 XAMPP/WAMP 的默认值)
$password = "JIAshaowei1990++";          // 数据库密码 (通常是 XAMPP/WAMP 的默认值)
$dbname = "inventory_db"; // 你的数据库名

// 创建数据库连接
$conn = new mysqli($servername, $username, $password, $dbname);

// 检查连接
if ($conn->connect_error) {
    die("连接失败: " . $conn->connect_error);
}

// 设置字符集为 UTF-8，以支持中文
$conn->set_charset("utf8mb4");
?>