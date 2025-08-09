<?php
// edit_shipment.php - 编辑发货记录页面

session_start(); // 启动会话

// 检查用户是否已登录，否则重定向到登录页面
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: login.php");
    exit;
}

require_once 'db_connect.php'; // 引入数据库连接文件

$message = '';
$error = '';
$shipment = null; // 用于存储要编辑的发货记录数据
$inventory_items_for_dropdown = []; // 用于物品选择下拉框

// 获取所有库存物品，用于下拉选择，包括品牌、名称、规格、国家和到期日期
// 排序方式改为：到期日期（升序），NULL值排在最后；然后按名称升序
$sql_items = "SELECT id, name, brand, specifications, country, expiration_date, quantity FROM inventory ORDER BY expiration_date IS NULL ASC, expiration_date ASC, name ASC";
$result_items = $conn->query($sql_items);
if ($result_items && $result_items->num_rows > 0) {
    while ($row = $result_items->fetch_assoc()) {
        $inventory_items_for_dropdown[] = $row;
    }
}

// 获取要编辑的发货记录ID
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $shipment_id = (int)$_GET['id'];

    // 查询指定的发货记录及其关联的物品信息
    $sql_shipment = "SELECT s.id, s.inventory_item_id, s.shipping_number, s.quantity_shipped, s.shipping_date,
                            s.recipient_name, s.recipient_phone, s.recipient_address, s.remarks, s.created_at,
                            i.name AS item_name, i.brand AS item_brand, i.specifications AS item_specifications, i.country AS item_country, i.quantity AS item_current_stock
                     FROM shipments s
                     JOIN inventory i ON s.inventory_item_id = i.id
                     WHERE s.id = ?";
    
    if ($stmt = $conn->prepare($sql_shipment)) {
        $stmt->bind_param("i", $shipment_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows == 1) {
            $shipment = $result->fetch_assoc();
            // 为下拉框预选中当前物品
            foreach ($inventory_items_for_dropdown as $key => $item) {
                if ($item['id'] == $shipment['inventory_item_id']) {
                    $shipment['selected_inventory_item_index'] = $key;
                    break;
                }
            }
        } else {
            $error = "未找到指定ID的发货记录。";
        }
        $stmt->close();
    } else {
        $error = "数据库查询准备失败: " . $conn->error;
    }
} else {
    $error = "缺少发货记录ID。";
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>编辑发货记录</title>
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
        .item-details-display {
            background-color: #f5f5f5;
            padding: 1rem;
            border-radius: 0.5rem;
            font-size: 0.9rem;
            color: #666;
            line-height: 1.5;
            margin-top: 0.5rem;
            border: 1px dashed #ccc;
        }
        .item-details-display strong {
            color: #333;
        }
    </style>
</head>
<body class="bg-gray-100">
    <nav class="bg-blue-600 p-4 shadow-md">
        <div class="container flex justify-between items-center">
            <h1 class="text-white text-2xl font-bold">库存管理系统</h1>
            <div class="flex items-center space-x-4">
                <a href="shipments_list.php" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg transition duration-150 ease-in-out">
                    返回发货列表
                </a>
                <a href="logout.php" class="bg-red-500 hover:bg-red-700 text-white font-bold py-2 px-4 rounded-lg transition duration-150 ease-in-out">
                    注销
                </a>
            </div>
        </div>
    </nav>

    <div class="container mt-8">
        <h2 class="text-3xl font-bold text-gray-800 mb-6">编辑发货记录</h2>

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

        <?php if ($shipment): ?>
            <div class="bg-white p-8 rounded-lg shadow-lg">
                <form action="update_shipment.php" method="post">
                    <input type="hidden" name="id" value="<?php echo htmlspecialchars($shipment['id']); ?>">
                    <input type="hidden" name="original_inventory_item_id" value="<?php echo htmlspecialchars($shipment['inventory_item_id']); ?>">
                    <input type="hidden" name="original_quantity_shipped" value="<?php echo htmlspecialchars($shipment['quantity_shipped']); ?>">

                    <div class="mb-6">
                        <label for="inventory_item_id" class="block text-gray-700 text-sm font-bold mb-2">发货物品:</label>
                        <select id="inventory_item_id" name="inventory_item_id" required
                                class="shadow appearance-none border rounded-lg w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            <option value="">请选择物品</option>
                            <?php foreach ($inventory_items_for_dropdown as $item): ?>
                                <option value="<?php echo htmlspecialchars($item['id']); ?>"
                                    <?php echo ($item['id'] == $shipment['inventory_item_id']) ? 'selected' : ''; ?>
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
                        <div class="item-details-display mt-2" id="selectedItemDetails">
                            <?php if ($shipment): ?>
                                品牌: <strong><?php echo htmlspecialchars($shipment['item_brand'] ?? ''); ?></strong> |
                                名称: <strong><?php echo htmlspecialchars($shipment['item_name'] ?? ''); ?></strong> |
                                规格: <strong><?php echo htmlspecialchars($shipment['item_specifications'] ?? ''); ?></strong> |
                                国家: <strong><?php echo htmlspecialchars($shipment['item_country'] ?? ''); ?></strong> |
                                当前库存: <strong><?php echo htmlspecialchars($shipment['item_current_stock'] ?? ''); ?></strong>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="mb-6">
                        <label for="quantity_shipped" class="block text-gray-700 text-sm font-bold mb-2">发货数量:</label>
                        <input type="number" id="quantity_shipped" name="quantity_shipped" required min="1"
                               class="shadow appearance-none border rounded-lg w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                               value="<?php echo htmlspecialchars($shipment['quantity_shipped']); ?>">
                    </div>

                    <div class="mb-6">
                        <label for="shipping_number" class="block text-gray-700 text-sm font-bold mb-2">发货单号:</label>
                        <input type="text" id="shipping_number" name="shipping_number" required
                               class="shadow appearance-none border rounded-lg w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                               value="<?php echo htmlspecialchars($shipment['shipping_number']); ?>">
                    </div>

                    <div class="mb-6">
                        <label for="shipping_date" class="block text-gray-700 text-sm font-bold mb-2">发货日期:</label>
                        <input type="date" id="shipping_date" name="shipping_date" required
                               class="shadow appearance-none border rounded-lg w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                               value="<?php echo htmlspecialchars($shipment['shipping_date']); ?>">
                    </div>

                    <div class="mb-6">
                        <label for="recipient_name" class="block text-gray-700 text-sm font-bold mb-2">收件人名称:</label>
                        <input type="text" id="recipient_name" name="recipient_name"
                               class="shadow appearance-none border rounded-lg w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                               value="<?php echo htmlspecialchars($shipment['recipient_name']); ?>">
                    </div>

                    <div class="mb-6">
                        <label for="recipient_phone" class="block text-gray-700 text-sm font-bold mb-2">收件人电话:</label>
                        <input type="text" id="recipient_phone" name="recipient_phone"
                               class="shadow appearance-none border rounded-lg w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                               value="<?php echo htmlspecialchars($shipment['recipient_phone']); ?>">
                    </div>

                    <div class="mb-6">
                        <label for="recipient_address" class="block text-gray-700 text-sm font-bold mb-2">收件人地址:</label>
                        <textarea id="recipient_address" name="recipient_address" rows="3"
                                  class="shadow appearance-none border rounded-lg w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"><?php echo htmlspecialchars($shipment['recipient_address']); ?></textarea>
                    </div>

                    <div class="mb-6">
                        <label for="remarks" class="block text-gray-700 text-sm font-bold mb-2">备注 (发货):</label>
                        <textarea id="remarks" name="remarks" rows="4"
                                  class="shadow appearance-none border rounded-lg w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"><?php echo htmlspecialchars($shipment['remarks']); ?></textarea>
                    </div>

                    <div class="flex items-center justify-between mt-8">
                        <button type="submit"
                                class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg focus:outline-none focus:shadow-outline w-full transition duration-150 ease-in-out">
                            保存修改
                        </button>
                    </div>
                </form>
                <div id="modalMessage" class="mt-4 hidden" style="display: none;"></div>
            </div>
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    const itemSelect = document.getElementById('inventory_item_id');
                    const quantityInput = document.getElementById('quantity_shipped');
                    const itemDetailsDiv = document.getElementById('selectedItemDetails');
                    const inventoryItems = <?php echo json_encode($inventory_items_for_dropdown); ?>;
                    
                    const editItemForm = document.querySelector('#editShipmentForm'); // Ensure this is the correct ID/selector for your form
                    const modalMessage = document.getElementById('modalMessage');
                    const editShipmentModal = document.getElementById('editShipmentModal'); // Assuming you have a modal wrapper for this form

                    // Function to update item details display and max quantity
                    function updateItemDetails() {
                        const selectedOption = itemSelect.options[itemSelect.selectedIndex];
                        const selectedItemId = selectedOption.value;
                        
                        let currentItemData = null;
                        if (selectedItemId) {
                            currentItemData = inventoryItems.find(item => item.id == selectedItemId);
                        }

                        if (currentItemData) {
                            itemDetailsDiv.innerHTML = `
                                品牌: <strong>${currentItemData.brand}</strong> |
                                名称: <strong>${currentItemData.name}</strong> |
                                规格: <strong>${currentItemData.specifications}</strong> |
                                国家: <strong>${currentItemData.country}</strong> |
                                当前库存: <strong>${currentItemData.quantity}</strong>
                            `;
                            // Set max quantity for the selected item.
                            // If the current shipment quantity is higher than the new max (e.g., if item changed to one with less stock),
                            // the user might need to reduce the quantity.
                            // Original quantity is added back to stock for calculation.
                            const originalShippedQuantity = parseInt(document.querySelector('input[name="original_quantity_shipped"]').value || 0, 10);
                            quantityInput.max = parseInt(currentItemData.quantity) + originalShippedQuantity; 
                        } else {
                            itemDetailsDiv.innerHTML = '请选择物品以查看详细信息';
                            quantityInput.max = '';
                        }
                    }

                    // Initial update when page loads
                    updateItemDetails();

                    // Add event listener for when the selected item changes
                    itemSelect.addEventListener('change', updateItemDetails);


                    // Handle form submission for edit
                    const form = document.querySelector('form[action="update_shipment.php"]'); // Select the form by its action
                    if (form) {
                        form.addEventListener('submit', function(e) {
                            e.preventDefault(); // Prevent default form submission

                            // Clear previous messages
                            modalMessage.textContent = '';
                            modalMessage.className = 'mt-4 hidden'; // Reset classes, hide it initially
                            modalMessage.style.display = 'none';

                            const formData = new FormData(this);

                            fetch('update_shipment.php', {
                                method: 'POST',
                                body: formData
                            })
                            .then(response => response.json())
                            .then(data => {
                                if (data.success) {
                                    modalMessage.textContent = data.message;
                                    modalMessage.className = 'mt-4 text-center text-green-700 bg-green-100 border border-green-400 px-4 py-3 rounded relative';
                                    modalMessage.style.display = 'block'; // Explicitly show
                                    
                                    // Optionally, refresh the parent shipments_list.php page or just redirect back
                                    setTimeout(() => {
                                        window.location.href = 'shipments_list.php?message=' + encodeURIComponent(data.message);
                                    }, 1500); // Display message for 1.5 seconds, then redirect
                                } else {
                                    modalMessage.textContent = data.message;
                                    modalMessage.className = 'mt-4 text-center text-red-700 bg-red-100 border border-red-400 px-4 py-3 rounded relative';
                                    modalMessage.style.display = 'block'; // Explicitly show
                                }
                            })
                            .catch(error => {
                                console.error('Fetch error:', error);
                                modalMessage.textContent = '发生网络错误，请稍后再试。';
                                modalMessage.className = 'mt-4 text-center text-red-700 bg-red-100 border border-red-400 px-4 py-3 rounded relative';
                                modalMessage.style.display = 'block'; // Explicitly show
                            });
                        });
                    }
                });
            </script>
        <?php endif; ?>
    </div>
</body>
</html>