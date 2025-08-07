<?php
// dashboard.php - 库存主页，需要登录才能访问

session_start(); // 启动会话

// 检查用户是否已登录，否则重定向到登录页面
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: login.php");
    exit;
}

require_once 'db_connect.php'; // 引入数据库连接文件

// 获取排序参数
$sort_by = $_GET['sort_by'] ?? 'created_at'; // 默认按创建日期排序
$sort_order = $_GET['sort_order'] ?? 'DESC'; // 默认降序

// 确保排序字段是合法的，防止 SQL 注入
$allowed_sort_columns = ['name', 'country', 'production_date', 'expiration_date', 'specifications', 'remarks', 'quantity', 'brand', 'created_at']; // 增加 brand
if (!in_array($sort_by, $allowed_sort_columns)) {
    $sort_by = 'created_at'; // 如果不合法，则使用默认排序
}

// 确保排序方向是合法的
$sort_order = strtoupper($sort_order); // 转换为大写
if (!in_array($sort_order, ['ASC', 'DESC'])) {
    $sort_order = 'DESC'; // 如果不合法，则使用默认降序
}

$inventory_items = [];
// 查询语句中包含 specifications, quantity 和 brand 字段，并应用排序条件 (客户端筛选将在此基础上进行)
$sql = "SELECT id, name, country, production_date, expiration_date, remarks, specifications, quantity, brand FROM inventory ORDER BY " . $sort_by . " " . $sort_order;

$result = $conn->query($sql);

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $inventory_items[] = $row;
    }
}

// --- 统计数据查询 (保持对整个库存的统计，不受列表筛选影响) ---
$total_items = 0;
$expired_items = 0;
$expiring_soon = 0; // 即将过期 (例如，未来30天内)

// 查询总物品数量
$sql_total = "SELECT SUM(quantity) AS total_items FROM inventory"; // 统计总数量
$result_total = $conn->query($sql_total);
if ($result_total && $result_total->num_rows > 0) {
    $row_total = $result_total->fetch_assoc();
    $total_items = $row_total['total_items'] ?? 0; // 如果没有物品，则为0
}

// 查询已过期物品数量
$sql_expired = "SELECT SUM(quantity) AS expired_items FROM inventory WHERE expiration_date < CURDATE() AND expiration_date IS NOT NULL";
$result_expired = $conn->query($sql_expired);
if ($result_expired && $result_expired->num_rows > 0) {
    $row_expired = $result_expired->fetch_assoc();
    $expired_items = $row_expired['expired_items'] ?? 0;
}

// 查询即将过期物品数量 (未来30天内)
$sql_expiring_soon = "SELECT SUM(quantity) AS expiring_soon FROM inventory WHERE expiration_date >= CURDATE() AND expiration_date <= CURDATE() + INTERVAL 30 DAY AND expiration_date IS NOT NULL";
$result_expiring_soon = $conn->query($sql_expiring_soon);
if ($result_expiring_soon && $result_expiring_soon->num_rows > 0) {
    $row_expiring_soon = $result_expiring_soon->fetch_assoc();
    $expiring_soon = $row_expiring_soon['expiring_soon'] ?? 0;
}

$conn->close();

// 辅助函数：生成排序链接 (现在不再包含筛选参数，因为筛选在客户端完成)
function getSortLink($column, $current_sort_by, $current_sort_order) {
    $new_sort_order = ($current_sort_by === $column && $current_sort_order === 'ASC') ? 'DESC' : 'ASC';
    $query_params = http_build_query([
        'sort_by' => $column,
        'sort_order' => $new_sort_order
    ]);
    return "dashboard.php?" . $query_params;
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>库存管理系统 - 仪表盘</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f0f2f5;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            padding: 0.75rem;
            text-align: left;
            border-bottom: 1px solid #e2e8f0;
        }
        th {
            background-color: #f8fafc;
            font-weight: 600;
            color: #4a5568;
        }
        tr:hover {
            background-color: #f0f4f8;
        }
        .sort-link {
            display: flex;
            align-items: center;
            gap: 0.25rem;
            color: #4a5568;
            text-decoration: none;
            cursor: pointer;
        }
        .sort-link:hover {
            color: #2b6cb0; /* blue-700 */
        }
        .sort-arrow {
            font-size: 0.75em;
            line-height: 1;
        }
        .filter-input {
            margin-top: 0.5rem; /* Space between sort link and filter input */
            padding: 0.375rem 0.5rem; /* py-1.5 px-2 */
            font-size: 0.875rem; /* text-sm */
            line-height: 1.25rem; /* leading-5 */
            border-radius: 0.375rem; /* rounded-md */
            border: 1px solid #d2d6dc; /* border-gray-300 */
            width: 100%;
            box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05); /* shadow-sm */
            outline: none;
        }
        .filter-input:focus {
            border-color: #3b82f6; /* focus:border-blue-500 */
            box-shadow: 0 0 0 1px #3b82f6; /* focus:ring-1 focus:ring-blue-500 */
        }
    </style>
</head>
<body class="bg-gray-100">
    <nav class="bg-blue-600 p-4 shadow-md">
        <div class="container flex justify-between items-center">
            <h1 class="text-white text-2xl font-bold">库存管理系统</h1>
            <div class="flex items-center space-x-4">
                <span class="text-white">欢迎, <?php echo htmlspecialchars($_SESSION['username']); ?>!</span>
                <a href="add_item.php" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg transition duration-150 ease-in-out">
                    添加新物品
                </a>
                <a href="add_shipment.php" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg transition duration-150 ease-in-out">
                    添加发货
                </a>
                <a href="shipments_list.php" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg transition duration-150 ease-in-out">
                    发货列表
                </a>
                <a href="change_password.php" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg transition duration-150 ease-in-out">
                    修改密码
                </a>
                <a href="logout.php" class="bg-red-500 hover:bg-red-700 text-white font-bold py-2 px-4 rounded-lg transition duration-150 ease-in-out">
                    注销
                </a>
            </div>
        </div>
    </nav>

    <div class="container mt-8">
        <h2 class="text-3xl font-bold text-gray-800 mb-6">库存概览</h2>

        <!-- 统计数据卡片 -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <div class="bg-white p-6 rounded-lg shadow-lg text-center">
                <p class="text-gray-500 text-sm font-medium uppercase">总物品数量</p>
                <p class="text-4xl font-bold text-blue-600 mt-2"><?php echo $total_items; ?></p>
            </div>
            <div class="bg-white p-6 rounded-lg shadow-lg text-center">
                <p class="text-gray-500 text-sm font-medium uppercase">已过期物品数量</p>
                <p class="text-4xl font-bold text-red-600 mt-2"><?php echo $expired_items; ?></p>
            </div>
            <div class="bg-white p-6 rounded-lg shadow-lg text-center">
                <p class="text-gray-500 text-sm font-medium uppercase">即将过期物品数量 (30天内)</p>
                <p class="text-4xl font-bold text-yellow-600 mt-2"><?php echo $expiring_soon; ?></p>
            </div>
        </div>

        <h2 class="text-3xl font-bold text-gray-800 mb-6">库存列表</h2>

        <?php if (empty($inventory_items)): ?>
            <div class="bg-yellow-100 border border-yellow-400 text-yellow-700 px-4 py-3 rounded relative" role="alert">
                <span class="block sm:inline">目前没有库存物品。请添加一些！</span>
            </div>
        <?php else: ?>
            <div class="bg-white shadow-lg rounded-lg overflow-hidden">
                <table id="inventoryTable" class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider" data-column="name">
                                <div class="flex flex-col">
                                    <a href="<?php echo getSortLink('name', $sort_by, $sort_order); ?>" class="sort-link">
                                        名称
                                        <?php if ($sort_by === 'name'): ?>
                                            <span class="sort-arrow"><?php echo ($sort_order === 'ASC') ? '▲' : '▼'; ?></span>
                                        <?php endif; ?>
                                    </a>
                                    <input type="text" data-filter-column="name" placeholder="筛选名称..." class="filter-input">
                                </div>
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider" data-column="country">
                                <div class="flex flex-col">
                                    <a href="<?php echo getSortLink('country', $sort_by, $sort_order); ?>" class="sort-link">
                                        国家
                                        <?php if ($sort_by === 'country'): ?>
                                            <span class="sort-arrow"><?php echo ($sort_order === 'ASC') ? '▲' : '▼'; ?></span>
                                        <?php endif; ?>
                                    </a>
                                    <input type="text" data-filter-column="country" placeholder="筛选国家..." class="filter-input">
                                </div>
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider" data-column="production_date">
                                <div class="flex flex-col">
                                    <a href="<?php echo getSortLink('production_date', $sort_by, $sort_order); ?>" class="sort-link">
                                        生产日期
                                        <?php if ($sort_by === 'production_date'): ?>
                                            <span class="sort-arrow"><?php echo ($sort_order === 'ASC') ? '▲' : '▼'; ?></span>
                                        <?php endif; ?>
                                    </a>
                                    <input type="text" data-filter-column="production_date" placeholder="筛选日期..." class="filter-input">
                                </div>
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider" data-column="expiration_date">
                                <div class="flex flex-col">
                                    <a href="<?php echo getSortLink('expiration_date', $sort_by, $sort_order); ?>" class="sort-link">
                                        到期日期
                                        <?php if ($sort_by === 'expiration_date'): ?>
                                            <span class="sort-arrow"><?php echo ($sort_order === 'ASC') ? '▲' : '▼'; ?></span>
                                        <?php endif; ?>
                                    </a>
                                    <input type="text" data-filter-column="expiration_date" placeholder="筛选日期..." class="filter-input">
                                </div>
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider" data-column="specifications">
                                <div class="flex flex-col">
                                    <a href="<?php echo getSortLink('specifications', $sort_by, $sort_order); ?>" class="sort-link">
                                        规格
                                        <?php if ($sort_by === 'specifications'): ?>
                                            <span class="sort-arrow"><?php echo ($sort_order === 'ASC') ? '▲' : '▼'; ?></span>
                                        <?php endif; ?>
                                    </a>
                                    <input type="text" data-filter-column="specifications" placeholder="筛选规格..." class="filter-input">
                                </div>
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider" data-column="brand">
                                <div class="flex flex-col">
                                    <a href="<?php echo getSortLink('brand', $sort_by, $sort_order); ?>" class="sort-link">
                                        品牌
                                        <?php if ($sort_by === 'brand'): ?>
                                            <span class="sort-arrow"><?php echo ($sort_order === 'ASC') ? '▲' : '▼'; ?></span>
                                        <?php endif; ?>
                                    </a>
                                    <input type="text" data-filter-column="brand" placeholder="筛选品牌..." class="filter-input">
                                </div>
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider" data-column="quantity">
                                <div class="flex flex-col">
                                    <a href="<?php echo getSortLink('quantity', $sort_by, $sort_order); ?>" class="sort-link">
                                        数量
                                        <?php if ($sort_by === 'quantity'): ?>
                                            <span class="sort-arrow"><?php echo ($sort_order === 'ASC') ? '▲' : '▼'; ?></span>
                                        <?php endif; ?>
                                    </a>
                                    <input type="text" data-filter-column="quantity" placeholder="筛选数量..." class="filter-input">
                                </div>
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider" data-column="remarks">
                                <div class="flex flex-col">
                                    <a href="<?php echo getSortLink('remarks', $sort_by, $sort_order); ?>" class="sort-link">
                                        备注
                                        <?php if ($sort_by === 'remarks'): ?>
                                            <span class="sort-arrow"><?php echo ($sort_order === 'ASC') ? '▲' : '▼'; ?></span>
                                        <?php endif; ?>
                                    </a>
                                    <textarea data-filter-column="remarks" placeholder="筛选备注..." class="filter-input h-auto"></textarea>
                                </div>
                            </th>
                        </tr>
                    </thead>
                    <tbody id="inventoryTableBody" class="bg-white divide-y divide-gray-200">
                        <?php foreach ($inventory_items as $item): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($item['name']); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($item['country']); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($item['production_date']); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($item['expiration_date']); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($item['specifications']); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($item['brand']); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($item['quantity']); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($item['remarks']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const table = document.getElementById('inventoryTable');
            if (!table) return; // Exit if table not found

            const filterInputs = table.querySelectorAll('.filter-input');
            const rows = table.querySelectorAll('#inventoryTableBody tr');

            filterInputs.forEach(input => {
                input.addEventListener('keyup', function() {
                    const filterValue = this.value.toLowerCase();
                    const filterColumn = this.dataset.filterColumn;
                    const columnIndex = Array.from(this.closest('th').parentNode.children).indexOf(this.closest('th'));

                    rows.forEach(row => {
                        const cell = row.children[columnIndex];
                        if (cell) {
                            const cellText = cell.textContent.toLowerCase();
                            if (cellText.includes(filterValue)) {
                                row.style.display = ''; // Show row
                            } else {
                                row.style.display = 'none'; // Hide row
                            }
                        }
                    });
                });
            });
        });
    </script>
</body>
</html>