<?php
// test_db_connection.php - 独立测试数据库连接

$servername = "mysql"; // 请确保这里与您的 db_connect.php 一致
$username = "root";      // 请确保这里与您的 db_connect.php 一致
$password = "JIAshaowei1990++";          // 请确保这里与您的 db_connect.php 一致
$dbname = "inventory_db"; // 请确保这里与您的 db_connect.php 一致

// 创建数据库连接
$conn = new mysqli($servername, $username, $password, $dbname);

// 检查连接
if ($conn->connect_error) {
    echo "<h2>数据库连接失败!</h2>";
    echo "<p>错误信息: " . $conn->connect_error . "</p>";
    echo "<p>请检查 db_connect.php 中的服务器名、用户名、密码和数据库名是否正确。</p>";
} else {
    echo "<h2>数据库连接成功!</h2>";
    echo "<p>您已成功连接到数据库 '" . $dbname . "'。</p>";
    $conn->close();
}
?>
