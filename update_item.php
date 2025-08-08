<?php
// update_item.php - 处理库存物品更新请求

require_once 'check_session.php'; // 引入会话验证文件
// db_connect.php 已经在 check_session.php 中引入

header('Content-Type: application/json'); // 设置响应头为 JSON

$response = ['success' => false, 'message' => ''];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $id = $_POST['id'] ?? null;
    $name = $_POST['name'] ?? null;
    $country = $_POST['country'] ?? null;
    $production_date = $_POST['production_date'] ?? null;
    $expiration_date = $_POST['expiration_date'] ?? null;
    $remarks = $_POST['remarks'] ?? null;
    $specifications = $_POST['specifications'] ?? null;
    $quantity = $_POST['quantity'] ?? null;
    $brand = $_POST['brand'] ?? null;

    // 验证必要字段
    if (empty($id) || empty($name) || !isset($quantity) || !is_numeric($quantity) || $quantity < 0) {
        $response['message'] = '缺少必要的物品ID、名称或数量，或数量无效。';
        echo json_encode($response);
        $conn->close();
        exit;
    }

    // 准备 SQL 更新语句
    $stmt = $conn->prepare("UPDATE inventory SET 
                            name = ?, 
                            country = ?, 
                            production_date = ?, 
                            expiration_date = ?, 
                            remarks = ?, 
                            specifications = ?, 
                            quantity = ?, 
                            brand = ? 
                            WHERE id = ?");

    // 绑定参数
    // ssssssis - string, string, string, string, string, string, integer, string, integer
    $stmt->bind_param("ssssssisi", 
                      $name, 
                      $country, 
                      $production_date, 
                      $expiration_date, 
                      $remarks, 
                      $specifications, 
                      $quantity, 
                      $brand, 
                      $id);

    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            $response['success'] = true;
            $response['message'] = '物品更新成功！';
        } else {
            $response['success'] = false;
            $response['message'] = '物品信息未改变或未找到该物品。';
        }
    } else {
        $response['message'] = '更新物品失败: ' . $stmt->error;
    }

    $stmt->close();
} else {
    $response['message'] = '无效的请求方法。';
}

echo json_encode($response);
$conn->close();
?>