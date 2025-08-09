<?php
// api/get_inventory.php - 获取库存及预设API端点

// 引入小程序API认证检查文件
require_once 'check_mp_auth.php'; 
// 如果认证失败，check_mp_auth.php 会自动退出并发送错误响应。
// 如果认证成功， authenticated_mp_user_id 和 authenticated_mp_username 将可用。

// 设置响应头为 JSON 格式 (在 check_mp_auth.php 中已设置，这里再次确保)
header('Content-Type: application/json');

// 初始化响应数据结构
$response = [
    'success' => false,
    'message' => '未知错误',
    'data' => [
        'inventory' => [],
        'brands' => [],
        'names' => [],
        'specifications' => [],
        'countries' => [],
        'expiration_dates' => [],
        'statistics' => [
            'total_quantity' => 0,
            'expired_quantity' => 0,
            'expiring_soon_quantity' => 0,
            'shipped_quantity' => 0 // 新增：已发货数量
        ]
    ]
];

// 请注意：这里假设您的小程序用户与您的 web 后台用户是共享 users 表，
// 并且 current_session_id 字段用于存储小程序会话令牌。
// 如果您的设计不同，请相应调整。

try {
    // 1. 查询库存表中的所有物品
    $sql_inventory = "SELECT 
                        id, 
                        name, 
                        brand, 
                        specifications, 
                        country, 
                        production_date,  -- ✨ 修正：添加 production_date 字段
                        expiration_date, 
                        quantity 
                    FROM 
                        inventory 
                    ORDER BY 
                        expiration_date IS NULL ASC, expiration_date ASC, name ASC";
    $result_inventory = $conn->query($sql_inventory);

    if ($result_inventory) {
        if ($result_inventory->num_rows > 0) {
            while ($row = $result_inventory->fetch_assoc()) {
                $response['data']['inventory'][] = $row;
            }
        }
    } else {
        throw new Exception("库存数据查询失败: " . $conn->error);
    }

    // 2. 获取预设品牌列表 (从 preset_brands 表)
    $sql_brands = "SELECT name FROM preset_brands ORDER BY name ASC";
    $result_brands = $conn->query($sql_brands);
    if ($result_brands) {
        while ($row = $result_brands->fetch_assoc()) {
            $response['data']['brands'][] = $row['name'];
        }
    } else {
        throw new Exception("预设品牌查询失败: " . $conn->error);
    }

    // 3. 获取预设名称列表 (从 preset_names 表)
    $sql_names = "SELECT name FROM preset_names ORDER BY name ASC";
    $result_names = $conn->query($sql_names);
    if ($result_names) {
        while ($row = $result_names->fetch_assoc()) {
            $response['data']['names'][] = $row['name'];
        }
    } else {
        throw new Exception("预设名称查询失败: " . $conn->error);
    }

    // 4. 获取预设规格列表 (从 preset_specifications 表)
    $sql_specifications = "SELECT name FROM preset_specifications ORDER BY name ASC";
    $result_specifications = $conn->query($sql_specifications);
    if ($result_specifications) {
        while ($row = $result_specifications->fetch_assoc()) {
            $response['data']['specifications'][] = $row['name'];
        }
    } else {
        throw new Exception("预设规格查询失败: " . $conn->error);
    }

    // 5. 获取预设国家列表 (从 preset_countries 表)
    $sql_countries = "SELECT name FROM preset_countries ORDER BY name ASC";
    $result_countries = $conn->query($sql_countries);
    if ($result_countries) {
        while ($row = $result_countries->fetch_assoc()) {
            $response['data']['countries'][] = $row['name'];
        }
    } else {
        throw new Exception("预设国家查询失败: " . $conn->error);
    }

    // 6. 获取所有不重复的到期日期列表 (从 inventory 表)
    $sql_expiration_dates = "SELECT DISTINCT expiration_date FROM inventory WHERE expiration_date IS NOT NULL ORDER BY expiration_date ASC";
    $result_expiration_dates = $conn->query($sql_expiration_dates);
    if ($result_expiration_dates) {
        while ($row = $result_expiration_dates->fetch_assoc()) {
            $response['data']['expiration_dates'][] = $row['expiration_date'];
        }
    } else {
        throw new Exception("到期日期查询失败: " . $conn->error);
    }

    // 7. 获取库存统计数据
    // 总量统计
    $sql_total_quantity = "SELECT SUM(quantity) AS total FROM inventory";
    $result_total = $conn->query($sql_total_quantity);
    if ($result_total && $row_total = $result_total->fetch_assoc()) {
        $response['data']['statistics']['total_quantity'] = (int)$row_total['total'];
    }

    // 已过期统计
    $sql_expired_quantity = "SELECT SUM(quantity) AS expired FROM inventory WHERE expiration_date IS NOT NULL AND expiration_date < CURDATE()";
    $result_expired = $conn->query($sql_expired_quantity);
    if ($result_expired && $row_expired = $result_expired->fetch_assoc()) {
        $response['data']['statistics']['expired_quantity'] = (int)$row_expired['expired'];
    }

    // 30天快过期统计
    // 修复：定义 $sql_expiring_soon 变量
    $sql_expiring_soon = "SELECT SUM(quantity) AS expiring_soon FROM inventory WHERE expiration_date IS NOT NULL AND expiration_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)";
    $result_expiring_soon = $conn->query($sql_expiring_soon);
    if ($result_expiring_soon && $row_expiring_soon = $result_expiring_soon->fetch_assoc()) {
        $response['data']['statistics']['expiring_soon_quantity'] = (int)$row_expiring_soon['expiring_soon'];
    }

    // 新增：已发货数量统计
    $sql_shipped_quantity = "SELECT SUM(quantity_shipped) AS shipped_total FROM shipments";
    $result_shipped = $conn->query($sql_shipped_quantity);
    if ($result_shipped && $row_shipped = $result_shipped->fetch_assoc()) {
        $response['data']['statistics']['shipped_quantity'] = (int)$row_shipped['shipped_total'];
    }


    $response['success'] = true;
    $response['message'] = '数据获取成功';

} catch (Exception $e) {
    $response['message'] = '服务器错误: ' . $e->getMessage();
} finally {
    // 无论成功或失败，最后都要关闭数据库连接
    // 注意：db_connect.php 建立的 $conn 连接需要在 check_mp_auth.php 之后关闭
    if (isset($conn) && $conn) {
        $conn->close();
    }
}

echo json_encode($response);
?>