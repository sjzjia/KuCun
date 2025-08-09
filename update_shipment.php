<?php
// update_shipment.php - 处理发货记录更新请求

session_start(); // 启动会话

// 检查用户是否已登录
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: login.php");
    exit;
}

require_once 'db_connect.php'; // 引入数据库连接文件

// 设置响应头为 JSON 格式，方便前端处理
header('Content-Type: application/json');

$response = ['success' => false, 'message' => ''];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // 获取表单数据
    $shipment_id = $_POST['id'] ?? null;
    $new_inventory_item_id = $_POST['inventory_item_id'] ?? null;
    $new_quantity_shipped = $_POST['quantity_shipped'] ?? null;
    $shipping_number = $_POST['shipping_number'] ?? null;
    $shipping_date = $_POST['shipping_date'] ?? null;
    $recipient_name = $_POST['recipient_name'] ?? null;
    $recipient_phone = $_POST['recipient_phone'] ?? null;
    $recipient_address = $_POST['recipient_address'] ?? null;
    $remarks = $_POST['remarks'] ?? null;

    // 获取原始发货数据 (用于回滚库存)
    $original_inventory_item_id = $_POST['original_inventory_item_id'] ?? null;
    $original_quantity_shipped = $_POST['original_quantity_shipped'] ?? null;

    // 验证必要字段和数据类型
    if (empty($shipment_id) || !is_numeric($shipment_id)) {
        $response['message'] = '发货记录ID无效。';
        echo json_encode($response);
        $conn->close();
        exit;
    }
    if (empty($new_inventory_item_id) || !is_numeric($new_inventory_item_id)) {
        $response['message'] = '请选择要发货的物品。';
        echo json_encode($response);
        $conn->close();
        exit;
    }
    if (empty($new_quantity_shipped) || !is_numeric($new_quantity_shipped) || $new_quantity_shipped <= 0) {
        $response['message'] = '发货数量必须是大于0的数字。';
        echo json_encode($response);
        $conn->close();
        exit;
    }
    if (empty($shipping_number)) {
        $response['message'] = '发货单号不能为空。';
        echo json_encode($response);
        $conn->close();
        exit;
    }
    if (empty($shipping_date)) {
        $response['message'] = '发货日期不能为空。';
        echo json_encode($response);
        $conn->close();
        exit;
    }
    
    // 确保原始数据也是数字
    if (!is_numeric($original_inventory_item_id) || !is_numeric($original_quantity_shipped)) {
        $response['message'] = '原始发货数据缺失或无效。';
        echo json_encode($response);
        $conn->close();
        exit;
    }

    $conn->begin_transaction(); // 启动事务

    try {
        // 1. 将原始发货数量加回原始库存物品
        $stmt_rollback_stock = $conn->prepare("UPDATE inventory SET quantity = quantity + ? WHERE id = ?");
        if (!$stmt_rollback_stock) {
            throw new Exception("SQL 预处理失败 (回滚库存): " . $conn->error);
        }
        $stmt_rollback_stock->bind_param("ii", $original_quantity_shipped, $original_inventory_item_id);
        if (!$stmt_rollback_stock->execute()) {
            throw new Exception("回滚原始库存失败: " . $stmt_rollback_stock->error);
        }
        $stmt_rollback_stock->close();

        // 2. 检查新物品的库存是否足够 (在扣除之前)
        $stmt_check_new_quantity = $conn->prepare("SELECT quantity FROM inventory WHERE id = ?");
        if (!$stmt_check_new_quantity) {
            throw new Exception("SQL 预处理失败 (检查新库存): " . $conn->error);
        }
        $stmt_check_new_quantity->bind_param("i", $new_inventory_item_id);
        $stmt_check_new_quantity->execute();
        $result_new_quantity = $stmt_check_new_quantity->get_result();
        if ($result_new_quantity->num_rows == 0) {
            throw new Exception("新发货物品ID不存在。");
        }
        $row_new_quantity = $result_new_quantity->fetch_assoc();
        $available_stock = $row_new_quantity['quantity'];
        $stmt_check_new_quantity->close();

        if ($new_quantity_shipped > $available_stock) {
            throw new Exception("发货数量超出当前物品库存。当前库存: " . $available_stock);
        }
        
        // 3. 从新库存物品中扣除数量
        $stmt_deduct_new_stock = $conn->prepare("UPDATE inventory SET quantity = quantity - ? WHERE id = ?");
        if (!$stmt_deduct_new_stock) {
            throw new Exception("SQL 预处理失败 (扣除新库存): " . $conn->error);
        }
        $stmt_deduct_new_stock->bind_param("ii", $new_quantity_shipped, $new_inventory_item_id);
        if (!$stmt_deduct_new_stock->execute()) {
            throw new Exception("扣除新库存失败: " . $stmt_deduct_new_stock->error);
        }
        $stmt_deduct_new_stock->close();

        // 4. 更新发货记录
        $stmt_update_shipment = $conn->prepare("UPDATE shipments SET 
                                        inventory_item_id = ?, 
                                        shipping_number = ?, 
                                        quantity_shipped = ?, 
                                        shipping_date = ?, 
                                        recipient_name = ?, 
                                        recipient_phone = ?, 
                                        recipient_address = ?, 
                                        remarks = ? 
                                    WHERE id = ?");
        if (!$stmt_update_shipment) {
            throw new Exception("SQL 预处理失败 (更新发货记录): " . $conn->error);
        }
        $stmt_update_shipment->bind_param("isississi", 
                                        $new_inventory_item_id, 
                                        $shipping_number, 
                                        $new_quantity_shipped, 
                                        $shipping_date, 
                                        $recipient_name, 
                                        $recipient_phone, 
                                        $recipient_address, 
                                        $remarks, 
                                        $shipment_id);
        
        if (!$stmt_update_shipment->execute()) {
            throw new Exception("更新发货记录失败: " . $stmt_update_shipment->error);
        }
        $stmt_update_shipment->close();

        $conn->commit(); // 提交事务
        $response['success'] = true;
        $response['message'] = '发货记录更新成功！';

    } catch (Exception $e) {
        $conn->rollback(); // 回滚事务
        $response['message'] = "更新失败: " . $e->getMessage();
    } finally {
        if ($conn) {
            $conn->close();
        }
    }
} else {
    $response['message'] = '无效的请求方法。';
}

echo json_encode($response);
?>