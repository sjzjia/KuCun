<?php
// add_shipment.php - 添加发货记录页面

session_start(); // 启动会话

// 检查用户是否已登录，否则重定向到登录页面
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: login.php");
    exit;
}

require_once 'db_connect.php'; // 引入数据库连接文件

$message = '';
$error = '';
$inventory_items_for_dropdown = [];

// 获取所有库存物品，用于下拉选择
$sql_items = "SELECT id, name, quantity FROM inventory WHERE quantity > 0 ORDER BY name ASC";
$result_items = $conn->query($sql_items);
if ($result_items && $result_items->num_rows > 0) {
    while ($row = $result_items->fetch_assoc()) {
        $inventory_items_for_dropdown[] = $row;
    }
}

// 处理发货表单提交
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $inventory_item_id = $_POST['inventory_item_id'];
    $shipping_number = $_POST['shipping_number'];
    $quantity_shipped = $_POST['quantity_shipped'];
    $shipping_date = $_POST['shipping_date'];
    $recipient_name = $_POST['recipient_name'];    // 获取收件人名称
    $recipient_phone = $_POST['recipient_phone'];  // 获取收件人电话
    $recipient_address = $_POST['recipient_address'];// 获取收件人地址
    $remarks = $_POST['remarks'];

    // 1. 验证输入
    if (!is_numeric($inventory_item_id) || $inventory_item_id <= 0) {
        $error = "请选择一个有效的物品。";
    } elseif (empty($shipping_number)) {
        $error = "发货单号不能为空。";
    } elseif (!is_numeric($quantity_shipped) || $quantity_shipped <= 0) {
        $error = "发货数量必须是大于0的数字。";
    } elseif (empty($shipping_date)) {
        $error = "发货日期不能为空。";
    } else {
        // 2. 检查库存中是否有足够的数量
        $stmt_check_quantity = $conn->prepare("SELECT quantity FROM inventory WHERE id = ?");
        $stmt_check_quantity->bind_param("i", $inventory_item_id);
        $stmt_check_quantity->execute();
        $stmt_check_quantity->bind_result($current_quantity);
        $stmt_check_quantity->fetch();
        $stmt_check_quantity->close();

        if ($quantity_shipped > $current_quantity) {
            $error = "发货数量超出当前库存量。当前库存: " . $current_quantity;
        } else {
            // 3. 插入发货记录
            $conn->begin_transaction(); // 启动事务

            try {
                $stmt_insert_shipment = $conn->prepare("INSERT INTO shipments (inventory_item_id, shipping_number, quantity_shipped, shipping_date, recipient_name, recipient_phone, recipient_address, remarks) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                if (!$stmt_insert_shipment) {
                    throw new Exception("SQL 预处理失败: " . $conn->error);
                }
                // 注意绑定参数的类型，新增了三个 sss (string)
                $stmt_insert_shipment->bind_param("isississ", $inventory_item_id, $shipping_number, $quantity_shipped, $shipping_date, $recipient_name, $recipient_phone, $recipient_address, $remarks);
                
                if (!$stmt_insert_shipment->execute()) {
                    throw new Exception("插入发货记录失败: " . $stmt_insert_shipment->error);
                }
                $stmt_insert_shipment->close();

                // 4. 更新库存数量
                $stmt_update_inventory = $conn->prepare("UPDATE inventory SET quantity = quantity - ? WHERE id = ?");
                if (!$stmt_update_inventory) {
                    throw new Exception("SQL 预处理失败: " . $conn->error);
                }
                $stmt_update_inventory->bind_param("ii", $quantity_shipped, $inventory_item_id);
                if (!$stmt_update_inventory->execute()) {
                    throw new Exception("更新库存数量失败: " . $stmt_update_inventory->error);
                }
                $stmt_update_inventory->close();

                $conn->commit(); // 提交事务
                $message = "发货记录添加成功，库存已更新！";
                // 清空表单字段以便添加下一个
                $_POST = array();
            } catch (Exception $e) {
                $conn->rollback(); // 回滚事务
                $error = "发货失败: " . $e->getMessage();
            }
        }
    }
}
$conn->close();
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>添加发货记录</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f0f2f5;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            padding: 2rem;
        }
    </style>
</head>
<body class="bg-gray-100">
    <nav class="bg-blue-600 p-4 shadow-md">
        <div class="container flex justify-between items-center">
            <h1 class="text-white text-2xl font-bold">库存管理系统</h1>
            <div class="flex items-center space-x-4">
                <a href="dashboard.php" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg transition duration-150 ease-in-out">
                    返回仪表盘
                </a>
                <a href="shipments_list.php" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg transition duration-150 ease-in-out">
                    发货列表
                </a>
                <a href="logout.php" class="bg-red-500 hover:bg-red-700 text-white font-bold py-2 px-4 rounded-lg transition duration-150 ease-in-out">
                    注销
                </a>
            </div>
        </div>
    </nav>

    <div class="container mt-8">
        <h2 class="text-3xl font-bold text-gray-800 mb-6">添加发货记录</h2>

        <?php if (!empty($message)): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">
                <span class="block sm:inline"><?php echo $message; ?></span>
            </div>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
                <span class="block sm:inline"><?php echo $error; ?></span>
            </div>
        <?php endif; ?>

        <div class="bg-white p-8 rounded-lg shadow-lg">
            <form action="add_shipment.php" method="post">
                <div class="mb-4">
                    <label for="inventory_item_id" class="block text-gray-700 text-sm font-bold mb-2">选择物品:</label>
                    <select id="inventory_item_id" name="inventory_item_id" required
                            class="shadow appearance-none border rounded-lg w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        <option value="">请选择物品</option>
                        <?php foreach ($inventory_items_for_dropdown as $item): ?>
                            <option value="<?php echo htmlspecialchars($item['id']); ?>"
                                <?php echo (isset($_POST['inventory_item_id']) && $_POST['inventory_item_id'] == $item['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($item['name']); ?> (库存: <?php echo htmlspecialchars($item['quantity']); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mb-4">
                    <label for="shipping_number" class="block text-gray-700 text-sm font-bold mb-2">发货单号:</label>
                    <input type="text" id="shipping_number" name="shipping_number" required
                           class="shadow appearance-none border rounded-lg w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                           value="<?php echo htmlspecialchars($_POST['shipping_number'] ?? ''); ?>">
                </div>

                <div class="mb-4">
                    <label for="quantity_shipped" class="block text-gray-700 text-sm font-bold mb-2">发货数量:</label>
                    <input type="number" id="quantity_shipped" name="quantity_shipped" required min="1"
                           class="shadow appearance-none border rounded-lg w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                           value="<?php echo htmlspecialchars($_POST['quantity_shipped'] ?? ''); ?>">
                </div>

                <div class="mb-4">
                    <label for="shipping_date" class="block text-gray-700 text-sm font-bold mb-2">发货日期:</label>
                    <input type="date" id="shipping_date" name="shipping_date" required
                           class="shadow appearance-none border rounded-lg w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                           value="<?php echo htmlspecialchars($_POST['shipping_date'] ?? date('Y-m-d')); ?>">
                </div>

                <div class="mb-4">
                    <label for="recipient_name" class="block text-gray-700 text-sm font-bold mb-2">收件人名称:</label>
                    <input type="text" id="recipient_name" name="recipient_name"
                           class="shadow appearance-none border rounded-lg w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                           value="<?php echo htmlspecialchars($_POST['recipient_name'] ?? ''); ?>">
                </div>

                <div class="mb-4">
                    <label for="recipient_phone" class="block text-gray-700 text-sm font-bold mb-2">收件人电话:</label>
                    <input type="text" id="recipient_phone" name="recipient_phone"
                           class="shadow appearance-none border rounded-lg w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                           value="<?php echo htmlspecialchars($_POST['recipient_phone'] ?? ''); ?>">
                </div>

                <div class="mb-6">
                    <label for="recipient_address" class="block text-gray-700 text-sm font-bold mb-2">收件人地址:</label>
                    <textarea id="recipient_address" name="recipient_address" rows="3"
                              class="shadow appearance-none border rounded-lg w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"><?php echo htmlspecialchars($_POST['recipient_address'] ?? ''); ?></textarea>
                </div>

                <div class="mb-6">
                    <label for="remarks" class="block text-gray-700 text-sm font-bold mb-2">备注 (发货):</label>
                    <textarea id="remarks" name="remarks" rows="4"
                              class="shadow appearance-none border rounded-lg w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"><?php echo htmlspecialchars($_POST['remarks'] ?? ''); ?></textarea>
                </div>

                <div class="flex items-center justify-between">
                    <button type="submit"
                            class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg focus:outline-none focus:shadow-outline w-full transition duration-150 ease-in-out">
                        记录发货
                    </button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>