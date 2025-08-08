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

// 获取所有库存物品，用于下拉选择，现在包含了品牌、名称、规格、国家和到期日期
// 排序方式改为：到期日期（升序），NULL值排在最后；然后按名称升序
$sql_items = "SELECT id, name, brand, specifications, country, expiration_date, quantity FROM inventory WHERE quantity > 0 ORDER BY expiration_date IS NULL ASC, expiration_date ASC, name ASC";
$result_items = $conn->query($sql_items);
if ($result_items && $result_items->num_rows > 0) {
    while ($row = $result_items->fetch_assoc()) {
        $inventory_items_for_dropdown[] = $row;
    }
}

// 处理发货表单提交
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $shipping_number = $_POST['shipping_number'];
    $shipping_date = $_POST['shipping_date'];
    $recipient_name = $_POST['recipient_name'];
    $recipient_phone = $_POST['recipient_phone'];
    $recipient_address = $_POST['recipient_address'];
    $remarks = $_POST['remarks'];
    $items_to_ship = $_POST['items'] ?? []; // 获取要发货的物品数组

    // 1. 验证通用输入字段
    if (empty($shipping_number)) {
        $error = "发货单号不能为空。";
    } elseif (empty($shipping_date)) {
        $error = "发货日期不能为空。";
    } elseif (empty($items_to_ship)) {
        $error = "请选择至少一个物品进行发货。";
    } else {
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
            $message = "发货记录添加成功，库存已更新！";
            // 清空表单字段以便添加下一个
            $_POST = array(); // 清空所有 POST 数据
        } catch (Exception $e) {
            $conn->rollback(); // 回滚事务
            $error = "发货失败: " . $e->getMessage();
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
        .item-row {
            display: flex;
            flex-wrap: wrap; /* Allow wrapping on smaller screens */
            gap: 1rem;
            align-items: flex-end; /* Align items at the bottom */
            margin-bottom: 1rem;
            padding: 1rem;
            border: 1px solid #e2e8f0;
            border-radius: 0.5rem;
            background-color: #f8fafc;
        }
        .item-row > div {
            flex: 1; /* Distribute space evenly */
            min-width: 150px; /* Minimum width for each column */
        }
        .item-row .full-width {
            flex-basis: 100%; /* Take full width for item details */
        }
        .item-row label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: bold;
            color: #4a5568;
        }
        .item-row select, .item-row input[type="number"] {
            width: 100%;
            padding: 0.5rem;
            border: 1px solid #d2d6dc;
            border-radius: 0.375rem;
            box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            outline: none;
        }
        .item-row select:focus, .item-row input[type="number"]:focus {
            border-color: #3b82f6;
            box-shadow: 0 0 0 1px #3b82f6;
        }
        .remove-item-btn {
            background-color: #ef4444; /* red-500 */
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 0.5rem;
            cursor: pointer;
            transition: background-color 0.15s ease-in-out;
            white-space: nowrap; /* Prevent button text from wrapping */
        }
        .remove-item-btn:hover {
            background-color: #dc2626; /* red-600 */
        }
        .add-item-btn {
            background-color: #22c55e; /* green-500 */
            color: white;
            padding: 0.75rem 1.5rem;
            border-radius: 0.5rem;
            cursor: pointer;
            transition: background-color 0.15s ease-in-out;
        }
        .add-item-btn:hover {
            background-color: #16a34a; /* green-600 */
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
                <h3 class="text-2xl font-bold text-gray-800 mb-4">发货物品列表</h3>
                <div id="itemsContainer">
                    <!-- 动态添加的物品行将在这里显示 -->
                </div>
                <button type="button" id="addItemButton" class="add-item-btn mt-4">
                    + 添加物品
                </button>

                <hr class="my-8 border-t-2 border-gray-200">

                <!-- 通用发货信息 -->
                <div class="mb-6">
                    <label for="shipping_number" class="block text-gray-700 text-sm font-bold mb-2">发货单号:</label>
                    <input type="text" id="shipping_number" name="shipping_number" required
                           class="shadow appearance-none border rounded-lg w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                           value="<?php echo htmlspecialchars($_POST['shipping_number'] ?? ''); ?>">
                </div>

                <div class="mb-6">
                    <label for="shipping_date" class="block text-gray-700 text-sm font-bold mb-2">发货日期:</label>
                    <input type="date" id="shipping_date" name="shipping_date" required
                           class="shadow appearance-none border rounded-lg w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                           value="<?php echo htmlspecialchars($_POST['shipping_date'] ?? date('Y-m-d')); ?>">
                </div>

                <hr class="my-8 border-t-2 border-gray-200">

                <div class="mb-6">
                    <label for="recipient_name" class="block text-gray-700 text-sm font-bold mb-2">收件人名称:</label>
                    <input type="text" id="recipient_name" name="recipient_name"
                           class="shadow appearance-none border rounded-lg w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                           value="<?php echo htmlspecialchars($_POST['recipient_name'] ?? ''); ?>">
                </div>

                <div class="mb-6">
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

                <div class="flex items-center justify-between mt-8">
                    <button type="submit"
                            class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg focus:outline-none focus:shadow-outline w-full transition duration-150 ease-in-out">
                        记录发货
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const itemsContainer = document.getElementById('itemsContainer');
            const addItemButton = document.getElementById('addItemButton');
            let itemIndex = 0; // 用于为每个物品行生成唯一的名称和ID

            // PHP 传递的库存物品数据
            const inventoryItems = <?php echo json_encode($inventory_items_for_dropdown); ?>;

            function addItemRow() {
                const rowDiv = document.createElement('div');
                rowDiv.classList.add('item-row');
                rowDiv.dataset.index = itemIndex; // Store index for easy reference

                rowDiv.innerHTML = `
                    <div>
                        <label for="item_id_${itemIndex}">选择物品:</label>
                        <select id="item_id_${itemIndex}" name="items[${itemIndex}][id]" required
                                class="shadow appearance-none border rounded-lg w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent item-select">
                            <option value="">请选择物品</option>
                            <?php foreach ($inventory_items_for_dropdown as $item): ?>
                                <option value="<?php echo htmlspecialchars($item['id']); ?>"
                                    data-brand="<?php echo htmlspecialchars($item['brand'] ?? ''); ?>"
                                    data-name="<?php echo htmlspecialchars($item['name'] ?? ''); ?>"
                                    data-specifications="<?php echo htmlspecialchars($item['specifications'] ?? ''); ?>"
                                    data-country="<?php echo htmlspecialchars($item['country'] ?? ''); ?>"
                                    data-expiration_date="<?php echo htmlspecialchars($item['expiration_date'] ?? 'N/A'); ?>"
                                    data-quantity="<?php echo htmlspecialchars($item['quantity'] ?? 0); ?>">
                                    <?php
                                        echo htmlspecialchars($item['brand'] ?? '') . ' - ';
                                        echo htmlspecialchars($item['name'] ?? '') . ' - ';
                                        echo htmlspecialchars($item['specifications'] ?? '') . ' - ';
                                        echo htmlspecialchars($item['country'] ?? '') . ' - ';
                                        echo '到期: ' . htmlspecialchars($item['expiration_date'] ?? 'N/A') . ' - ';
                                        echo '(库存: ' . htmlspecialchars($item['quantity'] ?? 0) . ')';
                                    ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="quantity_shipped_${itemIndex}">发货数量:</label>
                        <input type="number" id="quantity_shipped_${itemIndex}" name="items[${itemIndex}][quantity]" required min="1"
                               class="shadow appearance-none border rounded-lg w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent quantity-input">
                    </div>
                    <div class="full-width text-sm text-gray-600 mt-2 item-details">
                        <!-- 物品详细信息将在这里显示 -->
                    </div>
                    <div>
                        <button type="button" class="remove-item-btn">移除</button>
                    </div>
                `;

                itemsContainer.appendChild(rowDiv);

                // 添加事件监听器
                const selectElement = rowDiv.querySelector('.item-select');
                const quantityInput = rowDiv.querySelector('.quantity-input');
                const itemDetailsDiv = rowDiv.querySelector('.item-details');
                const removeButton = rowDiv.querySelector('.remove-item-btn');

                selectElement.addEventListener('change', function() {
                    const selectedOption = this.options[this.selectedIndex];
                    const currentStock = parseInt(selectedOption.dataset.quantity || 0, 10);
                    
                    // 更新物品详细信息
                    if (selectedOption.value) {
                        itemDetailsDiv.innerHTML = `
                            品牌: <strong>${selectedOption.dataset.brand}</strong> |
                            名称: <strong>${selectedOption.dataset.name}</strong> |
                            规格: <strong>${selectedOption.dataset.specifications}</strong> |
                            国家: <strong>${selectedOption.dataset.country}</strong> |
                            到期日期: <strong>${selectedOption.dataset.expiration_date}</strong> |
                            当前库存: <strong>${currentStock}</strong>
                        `;
                        quantityInput.max = currentStock; // 设置数量输入框的最大值
                        quantityInput.placeholder = `最大: ${currentStock}`;
                        quantityInput.value = ''; // 清空数量，让用户重新输入
                    } else {
                        itemDetailsDiv.innerHTML = '';
                        quantityInput.max = ''; // 移除最大值限制
                        quantityInput.placeholder = '';
                        quantityInput.value = '';
                    }
                });

                // 移除按钮事件
                removeButton.addEventListener('click', function() {
                    rowDiv.remove();
                });

                itemIndex++; // 递增索引
            }

            // 页面加载时添加一个初始物品行
            addItemRow();

            // "添加物品"按钮事件
            addItemButton.addEventListener('click', addItemRow);
        });
    </script>
</body>
</html>