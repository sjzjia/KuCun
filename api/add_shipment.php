<?php
// api/add_shipment.php - 后端 API，用于处理多物品发货

// 此文件应位于你的PHP后端，例如 /api/add_shipment.php
require_once '../db_connect.php'; // 确保路径正确

header('Content-Type: application/json'); // 设置响应头为JSON

$response = [
    'success' => false,
    'message' => '未知错误'
];

// 实际项目中应在此处添加认证和授权逻辑
// 例如：验证小程序传来的会话Token，确保只有合法用户才能执行发货操作
/*
$headers = getallheaders();
$authToken = null;
if (isset($headers['Authorization'])) {
    $authHeader = $headers['Authorization'];
    if (preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
        $authToken = $matches[1];
    }
}
if (!$authToken || !isValidAuthTokenInDatabase($authToken)) {
    $response['message'] = '未授权或认证失败';
    echo json_encode($response);
    exit;
}
*/

// 从 POST 请求中获取数据
$shipping_number = $_POST['shipping_number'] ?? '';
$shipping_date = $_POST['shipping_date'] ?? '';
$recipient_name = $_POST['recipient_name'] ?? '';
$recipient_phone = $_POST['recipient_phone'] ?? '';
$recipient_address = $_POST['recipient_address'] ?? '';
$remarks = $_POST['remarks'] ?? '';
$items_json = $_POST['items'] ?? '[]'; // 接收 JSON 字符串

$items_to_ship = json_decode($items_json, true); // 解析 JSON 字符串为 PHP 数组

// 1. 验证通用输入字段
if (empty($shipping_number)) {
    $response['message'] = "发货单号不能为空。";
    echo json_encode($response);
    exit;
}
if (empty($shipping_date)) {
    $response['message'] = "发货日期不能为空。";
    echo json_encode($response);
    exit;
}
if (empty($items_to_ship) || !is_array($items_to_ship)) {
    $response['message'] = "请选择至少一个物品进行发货。";
    echo json_encode($response);
    exit;
}

$conn->begin_transaction(); // 启动事务

try {
    foreach ($items_to_ship as $index => $item_data) {
        $inventory_item_id = $item_data['id'] ?? null;
        $quantity_shipped = $item_data['quantity'] ?? null;

        // 验证每个物品的数据
        if (!is_numeric($inventory_item_id) || $inventory_item_id <= 0) {
            throw new Exception("第 " . ($index + 1) . " 行：请选择一个有效的物品。");
        }
        if (!is_numeric($quantity_shipped) || $quantity_shipped <= 0) {
            throw new Exception("第 " . ($index + 1) . " 行：发货数量必须是大于0的数字。");
        }

        // 检查库存中是否有足够的数量
        $stmt_check_quantity = $conn->prepare("SELECT quantity FROM inventory WHERE id = ?");
        if (!$stmt_check_quantity) {
            throw new Exception("SQL 预处理失败 (检查库存): " . $conn->error);
        }
        $stmt_check_quantity->bind_param("i", $inventory_item_id);
        $stmt_check_quantity->execute();
        $stmt_check_quantity->bind_result($current_quantity);
        $stmt_check_quantity->fetch();
        $stmt_check_quantity->close();

        if ($quantity_shipped > $current_quantity) {
            throw new Exception("第 " . ($index + 1) . " 行：发货数量超出当前库存量。当前库存: " . $current_quantity);
        }

        // 插入发货记录
        $stmt_insert_shipment = $conn->prepare("INSERT INTO shipments (inventory_item_id, shipping_number, quantity_shipped, shipping_date, recipient_name, recipient_phone, recipient_address, remarks) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        if (!$stmt_insert_shipment) {
            throw new Exception("SQL 预处理失败 (插入发货): " . $conn->error);
        }
        $stmt_insert_shipment->bind_param("isississ", $inventory_item_id, $shipping_number, $quantity_shipped, $shipping_date, $recipient_name, $recipient_phone, $recipient_address, $remarks);
        
        if (!$stmt_insert_shipment->execute()) {
            throw new Exception("插入发货记录失败 (物品ID: {$inventory_item_id}): " . $stmt_insert_shipment->error);
        }
        $stmt_insert_shipment->close();

        // 更新库存数量
        $stmt_update_inventory = $conn->prepare("UPDATE inventory SET quantity = quantity - ? WHERE id = ?");
        if (!$stmt_update_inventory) {
            throw new Exception("SQL 预处理失败 (更新库存): " . $conn->error);
        }
        $stmt_update_inventory->bind_param("ii", $quantity_shipped, $inventory_item_id);
        if (!$stmt_update_inventory->execute()) {
            throw new Exception("更新库存数量失败 (物品ID: {$inventory_item_id}): " . $stmt_update_inventory->error);
        }
        $stmt_update_inventory->close();
    }

    $conn->commit(); // 提交事务
    $response['success'] = true;
    $response['message'] = "发货记录添加成功，库存已更新！";
} catch (Exception /*\Exception*/ $e) { // Use /*\Exception*/ for broader compatibility if strict
    $conn->rollback(); // 回滚事务
    $response['message'] = "发货失败: " . $e->getMessage();
} finally {
    if ($conn) {
        $conn->close();
    }
}

echo json_encode($response); // 输出JSON响应
?>