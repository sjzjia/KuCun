<?php
// api/add_inventory_item.php - 后端 API，用于处理添加新库存物品的请求

// 引入会话验证文件，这将同时引入 db_connect.php
require_once '../check_session.php'; 

header('Content-Type: application/json'); // 设置响应头为 JSON

$response = [
    'success' => false,
    'message' => '未知错误'
];

// 检查请求方法是否为 POST
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // 从 POST 数据中获取物品信息
    $name = $_POST['name'] ?? '';
    $country = $_POST['country'] ?? '';
    $production_date = $_POST['production_date'] ?? null;
    $expiration_date = $_POST['expiration_date'] ?? null;
    $remarks = $_POST['remarks'] ?? '';
    $specifications = $_POST['specifications'] ?? '';
    $quantity = $_POST['quantity'] ?? null;
    $brand = $_POST['brand'] ?? '';

    // 服务器端数据验证
    if (empty($name)) {
        $response['message'] = "物品名称不能为空。";
        echo json_encode($response);
        $conn->close();
        exit;
    }
    if (!is_numeric($quantity) || $quantity < 0) {
        $response['message'] = "数量必须是非负数字。";
        echo json_encode($response);
        $conn->close();
        exit;
    }
    // 检查日期格式，如果为空字符串，转换为 NULL 存入数据库
    $production_date = empty($production_date) ? NULL : $production_date;
    $expiration_date = empty($expiration_date) ? NULL : $expiration_date;


    try {
        // 准备 SQL 插入语句
        $stmt = $conn->prepare("INSERT INTO inventory (name, country, production_date, expiration_date, remarks, specifications, quantity, brand) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        
        if (!$stmt) {
            throw new Exception("SQL 预处理失败: " . $conn->error);
        }

        // 绑定参数
        // ssssssis - 字符串，字符串，字符串，字符串，字符串，字符串，整数，字符串
        $stmt->bind_param("ssssssis", 
                          $name, 
                          $country, 
                          $production_date, 
                          $expiration_date, 
                          $remarks, 
                          $specifications, 
                          $quantity, 
                          $brand);

        if ($stmt->execute()) {
            $response['success'] = true;
            $response['message'] = "物品添加成功！";
        } else {
            throw new Exception("添加物品失败: " . $stmt->error);
        }

        $stmt->close();

    } catch (Exception $e) {
        $response['message'] = "添加物品失败: " . $e->getMessage();
    } finally {
        // 确保在任何情况下都关闭数据库连接
        if ($conn) {
            $conn->close();
        }
    }
} else {
    $response['message'] = "无效的请求方法，只接受 POST 请求。";
}

echo json_encode($response); // 输出 JSON 响应
?>