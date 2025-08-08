<?php
// bulk_update_items.php - 处理库存物品批量更新请求

// For debugging: display all errors and log them
// error_reporting(E_ALL);
// ini_set('display_errors', 1); // Set to 0 in production
// ini_set('log_errors', 1);
// ini_set('error_log', __DIR__ . '/php_error.log'); // Logs errors to a file in the same directory as this script

require_once 'check_session.php'; // 引入会话验证文件
// db_connect.php 已经在 check_session.php 中引入

header('Content-Type: application/json'); // 设置响应头为 JSON

$response = ['success' => false, 'message' => ''];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $ids_json = $_POST['ids'] ?? null;
    $ids = $ids_json ? json_decode($ids_json, true) : [];

    // error_log("Bulk update request received. IDs JSON: " . ($ids_json ?? 'null'), 0);
    // error_log("Decoded IDs: " . print_r($ids, true), 0);

    if (empty($ids) || !is_array($ids)) {
        $response['message'] = '未选择任何物品进行批量修改。';
        // error_log("Error: No items selected for bulk update.", 0);
        echo json_encode($response);
        $conn->close();
        exit;
    }

    $updateFields = [];
    $params = [];
    $types = '';

    // Check and collect fields to update
    if (isset($_POST['brand'])) {
        $updateFields[] = 'brand = ?';
        $params[] = $_POST['brand'];
        $types .= 's';
    }
    if (isset($_POST['name'])) {
        $updateFields[] = 'name = ?';
        $params[] = $_POST['name'];
        $types .= 's';
    }
    if (isset($_POST['specifications'])) {
        $updateFields[] = 'specifications = ?';
        $params[] = $_POST['specifications'];
        $types .= 's';
    }
    if (isset($_POST['country'])) {
        $updateFields[] = 'country = ?';
        $params[] = $_POST['country'];
        $types .= 's';
    }
    if (isset($_POST['production_date'])) {
        // Handle empty date string for production_date: set to NULL in DB
        $prod_date = $_POST['production_date'] === '' ? NULL : $_POST['production_date'];
        $updateFields[] = 'production_date = ?';
        $params[] = $prod_date;
        $types .= 's';
    }
    if (isset($_POST['expiration_date'])) {
        // Handle empty date string for expiration_date: set to NULL in DB
        $exp_date = $_POST['expiration_date'] === '' ? NULL : $_POST['expiration_date'];
        $updateFields[] = 'expiration_date = ?';
        $params[] = $exp_date;
        $types .= 's';
    }
    if (isset($_POST['remarks'])) {
        $updateFields[] = 'remarks = ?';
        $params[] = $_POST['remarks'];
        $types .= 's';
    }

    // error_log("Update fields: " . print_r($updateFields, true), 0);
    // error_log("Parameters for binding (before IDs): " . print_r($params, true), 0);
    // error_log("Types string (before IDs): " . $types, 0);


    if (empty($updateFields)) {
        $response['message'] = '没有指定要修改的字段。';
        // error_log("Error: No fields specified for update.", 0);
        echo json_encode($response);
        $conn->close();
        exit;
    }

    $setClause = implode(', ', $updateFields);
    
    // 构建 WHERE IN 子句
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $types .= str_repeat('i', count($ids)); // 'i' for integer IDs
    $params = array_merge($params, $ids); // Merge parameters for fields and IDs

    $sql = "UPDATE inventory SET $setClause WHERE id IN ($placeholders)";
    // error_log("Generated SQL: " . $sql, 0);
    // error_log("Final types string for binding: " . $types, 0);
    // error_log("Final parameters for binding: " . print_r($params, true), 0);

    $stmt = $conn->prepare($sql);

    if ($stmt === false) {
        $response['message'] = '准备 SQL 语句失败: ' . $conn->error;
        // error_log("SQL prepare failed: " . $conn->error, 0);
        echo json_encode($response);
        $conn->close();
        exit;
    }

    // Dynamic parameter binding
    $bind_params = [];
    $bind_params[] = $types; // First element is the types string
    foreach ($params as $key => $value) {
        $bind_params[] = &$params[$key]; // Pass by reference
    }
    
    if (!call_user_func_array([$stmt, 'bind_param'], $bind_params)) {
        $response['message'] = '绑定参数失败: ' . $stmt->error;
        // error_log("Binding parameters failed: " . $stmt->error, 0);
        echo json_encode($response);
        $stmt->close();
        $conn->close();
        exit;
    }

    if ($stmt->execute()) {
        $response['success'] = true;
        $response['message'] = '成功更新 ' . $stmt->affected_rows . ' 个物品。';
        // error_log("Bulk update successful. Affected rows: " . $stmt->affected_rows, 0);

        // --- Fetch updated data for the affected items ---
        $updated_items_data = [];
        if ($stmt->affected_rows > 0) {
            $ids_placeholders = implode(',', array_fill(0, count($ids), '?'));
            $ids_types = str_repeat('i', count($ids));
            
            $fetch_sql = "SELECT id, name, country, production_date, expiration_date, remarks, specifications, quantity, brand FROM inventory WHERE id IN ($ids_placeholders)";
            $fetch_stmt = $conn->prepare($fetch_sql);
            
            if ($fetch_stmt === false) {
                // error_log("Failed to prepare fetch statement: " . $conn->error, 0);
                // Continue without updated data if fetch fails
            } else {
                $fetch_bind_params = [];
                $fetch_bind_params[] = $ids_types;
                foreach ($ids as $key => $value) {
                    $fetch_bind_params[] = &$ids[$key];
                }
                call_user_func_array([$fetch_stmt, 'bind_param'], $fetch_bind_params);
                
                $fetch_stmt->execute();
                $fetch_result = $fetch_stmt->get_result();
                
                while ($row = $fetch_result->fetch_assoc()) {
                    $updated_items_data[] = $row;
                }
                $fetch_stmt->close();
            }
        }
        $response['updatedItems'] = $updated_items_data; // Add updated items to the response
    } else {
        $response['message'] = '批量更新失败: ' . $stmt->error;
        // error_log("Bulk update failed: " . $stmt->error, 0);
    }

    $stmt->close();
} else {
    $response['message'] = '无效的请求方法。';
    // error_log("Error: Invalid request method.", 0);
}

echo json_encode($response);
$conn->close();
?>