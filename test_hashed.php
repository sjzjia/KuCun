<?php
// hash_test.php - 用于生成和验证密码哈希值的简单脚本

$password = 'password123';
$hashed_password = password_hash($password, PASSWORD_DEFAULT);

echo "原始密码: " . $password . "<br>";
echo "生成的哈希值: <span style='color: blue; font-weight: bold;'>" . $hashed_password . "</span><br><br>";

// 验证哈希值
if (password_verify($password, $hashed_password)) {
    echo "验证成功: 原始密码与生成的哈希值匹配。<br>";
} else {
    echo "验证失败: 原始密码与生成的哈希值不匹配。<br>";
}

// 尝试验证你在数据库中使用的哈希值
$db_hashed_password = '$2y$10$Gf5zQ/mY2X3Y4Z5A6B7C8D9E0F1G2H3I4J5K6L7M8N9O0P1Q2R3S4T5U6V7W8X9Y0Z1'; // 这是我们之前提供的哈希值
if (password_verify($password, $db_hashed_password)) {
    echo "验证数据库中的哈希值成功: 原始密码与数据库中的哈希值匹配。<br>";
} else {
    echo "验证数据库中的哈希值失败: 原始密码与数据库中的哈希值不匹配。<br>";
}
?>