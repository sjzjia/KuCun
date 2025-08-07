<?php
// dashboard.php - 库存主页，需要登录才能访问

session_start(); // 启动会话

// 检查用户是否已登录，否则重定向到登录页面
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: login.php");
    exit;
}

require_once 'db_connect.php'; // 引入数据库连接文件

// 从数据库获取预设国家列表
$countries = [];
$sql_countries = "SELECT name FROM preset_countries ORDER BY name ASC";
$result_countries = $conn->query($sql_countries);
if ($result_countries && $result_countries->num_rows > 0) {
    while ($row = $result_countries->fetch_assoc()) {
        $countries[] = $row['name'];
    }
}

// 从数据库获取预设品牌列表
$brands = [];
$sql_brands = "SELECT name FROM preset_brands ORDER BY name ASC";
$result_brands = $conn->query($sql_brands);
if ($result_brands && $result_brands->num_rows > 0) {
    while ($row = $result_brands->fetch_assoc()) {
        $brands[] = $row['name'];
    }
}

// --- 获取用于筛选的唯一值 ---
$unique_names = [];
$unique_specifications = [];
$unique_remarks = [];

// 获取所有唯一的名称
$sql_unique_names = "SELECT DISTINCT name FROM inventory ORDER BY name ASC";
$result_names = $conn->query($sql_unique_names);
if ($result_names && $result_names->num_rows > 0) {
    while ($row = $result_names->fetch_assoc()) {
        $unique_names[] = $row['name'];
    }
}

// 获取所有唯一的规格
$sql_unique_specifications = "SELECT DISTINCT specifications FROM inventory ORDER BY specifications ASC";
$result_specifications = $conn->query($sql_unique_specifications);
if ($result_specifications && $result_specifications->num_rows > 0) {
    while ($row = $result_specifications->fetch_assoc()) {
        if (!empty($row['specifications'])) { // 排除空值
            $unique_specifications[] = $row['specifications'];
        }
    }
}

// 获取所有唯一的备注
$sql_unique_remarks = "SELECT DISTINCT remarks FROM inventory ORDER BY remarks ASC";
$result_remarks = $conn->query($sql_unique_remarks);
if ($result_remarks && $result_remarks->num_rows > 0) {
    while ($row = $result_remarks->fetch_assoc()) {
        if (!empty($row['remarks'])) { // 排除空值
            $unique_remarks[] = $row['remarks'];
        }
    }
}

// 获取排序参数
$sort_by = $_GET['sort_by'] ?? 'created_at'; // 默认按创建日期排序
$sort_order = $_GET['sort_order'] ?? 'DESC'; // 默认降序

// 确保排序字段是合法的，防止 SQL 注入
$allowed_sort_columns = ['name', 'country', 'production_date', 'expiration_date', 'specifications', 'remarks', 'quantity', 'brand', 'created_at'];
if (!in_array($sort_by, $allowed_sort_columns)) {
    $sort_by = 'created_at'; // 如果不合法，则使用默认排序
}

// 确保排序方向是合法的
$sort_order = strtoupper($sort_order); // 转换为大写
if (!in_array($sort_order, ['ASC', 'DESC'])) {
    $sort_order = 'DESC'; // 如果不合法，则使用默认降序
}

$inventory_items = [];
// 查询语句中包含所有字段，并应用排序条件 (客户端筛选将在此基础上进行)
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
            max-width: 1400px; /* 增加最大宽度 */
            margin: 0 auto;
            padding: 2rem;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            padding: 0.75rem;
            border-bottom: 1px solid #e2e8f0;
            text-align: center; /* 居中所有表格单元格的内容 */
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
            justify-content: center; /* 居中排序链接内容 */
            gap: 0.25rem;
            color: #4a5568;
            text-decoration: none;
            cursor: pointer;
            width: 100%; /* 确保排序链接填充整个宽度以便居中 */
        }
        .sort-link:hover {
            color: #2b6cb0; /* blue-700 */
        }
        .sort-arrow {
            font-size: 0.75em;
            line-height: 1;
        }
        /* 统一筛选输入框和下拉菜单的样式 */
        .filter-control {
            margin-top: 0.5rem; /* Space between sort link and filter input/select */
            padding: 0.375rem 0.5rem; /* py-1.5 px-2 */
            font-size: 0.875rem; /* text-sm */
            line-height: 1.25rem; /* leading-5 */
            border-radius: 0.375rem; /* rounded-md */
            border: 1px solid #d2d6dc; /* border-gray-300 */
            width: 100%; /* 确保填充父元素宽度 */
            box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05); /* shadow-sm */
            outline: none;
            text-align: center; /* 筛选控件内部文本居中 */
        }
        .filter-control:focus {
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
                <a href="manage_presets.php" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg transition duration-150 ease-in-out">
                    管理预选项
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
                            <!-- 品牌 -->
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider" data-column="brand" style="min-width: 180px;">
                                <div class="flex flex-col items-center">
                                    <a href="<?php echo getSortLink('brand', $sort_by, $sort_order); ?>" class="sort-link w-full">
                                        品牌
                                        <?php if ($sort_by === 'brand'): ?>
                                            <span class="sort-arrow"><?php echo ($sort_order === 'ASC') ? '▲' : '▼'; ?></span>
                                        <?php endif; ?>
                                    </a>
                                    <select data-filter-column="brand" class="filter-control">
                                        <option value="">所有品牌</option>
                                        <?php foreach ($brands as $b): ?>
                                            <option value="<?php echo htmlspecialchars($b); ?>"><?php echo htmlspecialchars($b); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </th>
                            <!-- 名称 -->
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider" data-column="name" style="min-width: 200px;">
                                <div class="flex flex-col items-center">
                                    <a href="<?php echo getSortLink('name', $sort_by, $sort_order); ?>" class="sort-link w-full">
                                        名称
                                        <?php if ($sort_by === 'name'): ?>
                                            <span class="sort-arrow"><?php echo ($sort_order === 'ASC') ? '▲' : '▼'; ?></span>
                                        <?php endif; ?>
                                    </a>
                                    <select data-filter-column="name" class="filter-control">
                                        <option value="">所有名称</option>
                                        <?php foreach ($unique_names as $n): ?>
                                            <option value="<?php echo htmlspecialchars($n); ?>"><?php echo htmlspecialchars($n); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </th>
                            <!-- 规格 -->
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider" data-column="specifications" style="min-width: 200px;">
                                <div class="flex flex-col items-center">
                                    <a href="<?php echo getSortLink('specifications', $sort_by, $sort_order); ?>" class="sort-link w-full">
                                        规格
                                        <?php if ($sort_by === 'specifications'): ?>
                                            <span class="sort-arrow"><?php echo ($sort_order === 'ASC') ? '▲' : '▼'; ?></span>
                                        <?php endif; ?>
                                    </a>
                                    <select data-filter-column="specifications" class="filter-control">
                                        <option value="">所有规格</option>
                                        <?php foreach ($unique_specifications as $s): ?>
                                            <option value="<?php echo htmlspecialchars($s); ?>"><?php echo htmlspecialchars($s); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </th>
                            <!-- 国家 -->
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider" data-column="country" style="min-width: 180px;">
                                <div class="flex flex-col items-center">
                                    <a href="<?php echo getSortLink('country', $sort_by, $sort_order); ?>" class="sort-link w-full">
                                        国家
                                        <?php if ($sort_by === 'country'): ?>
                                            <span class="sort-arrow"><?php echo ($sort_order === 'ASC') ? '▲' : '▼'; ?></span>
                                        <?php endif; ?>
                                    </a>
                                    <select data-filter-column="country" class="filter-control">
                                        <option value="">所有国家</option>
                                        <?php foreach ($countries as $c): ?>
                                            <option value="<?php echo htmlspecialchars($c); ?>"><?php echo htmlspecialchars($c); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </th>
                            <!-- 生产日期 -->
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider" data-column="production_date">
                                <div class="flex flex-col items-center">
                                    <a href="<?php echo getSortLink('production_date', $sort_by, $sort_order); ?>" class="sort-link w-full">
                                        生产日期
                                        <?php if ($sort_by === 'production_date'): ?>
                                            <span class="sort-arrow"><?php echo ($sort_order === 'ASC') ? '▲' : '▼'; ?></span>
                                        <?php endif; ?>
                                    </a>
                                    <input type="date" data-filter-column="production_date" placeholder="筛选日期..." class="filter-control">
                                </div>
                            </th>
                            <!-- 到期日期 -->
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider" data-column="expiration_date">
                                <div class="flex flex-col items-center">
                                    <a href="<?php echo getSortLink('expiration_date', $sort_by, $sort_order); ?>" class="sort-link">
                                        到期日期
                                        <?php if ($sort_by === 'expiration_date'): ?>
                                            <span class="sort-arrow"><?php echo ($sort_order === 'ASC') ? '▲' : '▼'; ?></span>
                                        <?php endif; ?>
                                    </a>
                                    <input type="date" data-filter-column="expiration_date" placeholder="筛选日期..." class="filter-control">
                                </div>
                            </th>
                            <!-- 数量 -->
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider" data-column="quantity">
                                <div class="flex flex-col items-center">
                                    <a href="<?php echo getSortLink('quantity', $sort_by, $sort_order); ?>" class="sort-link">
                                        数量
                                        <?php if ($sort_by === 'quantity'): ?>
                                            <span class="sort-arrow"><?php echo ($sort_order === 'ASC') ? '▲' : '▼'; ?></span>
                                        <?php endif; ?>
                                    </a>
                                    <input type="text" data-filter-column="quantity" placeholder="筛选数量..." class="filter-control">
                                </div>
                            </th>
                            <!-- 备注 -->
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider" data-column="remarks" style="min-width: 250px;">
                                <div class="flex flex-col items-center">
                                    <a href="<?php echo getSortLink('remarks', $sort_by, $sort_order); ?>" class="sort-link">
                                        备注
                                        <?php if ($sort_by === 'remarks'): ?>
                                            <span class="sort-arrow"><?php echo ($sort_order === 'ASC') ? '▲' : '▼'; ?></span>
                                        <?php endif; ?>
                                    </a>
                                    <select data-filter-column="remarks" class="filter-control">
                                        <option value="">所有备注</option>
                                        <?php foreach ($unique_remarks as $r): ?>
                                            <option value="<?php echo htmlspecialchars($r); ?>"><?php echo htmlspecialchars($r); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </th>
                        </tr>
                    </thead>
                    <tbody id="inventoryTableBody" class="bg-white divide-y divide-gray-200">
                        <?php foreach ($inventory_items as $item): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($item['brand']); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($item['name']); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($item['specifications']); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($item['country']); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($item['production_date']); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($item['expiration_date']); ?></td>
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

            const filterControls = table.querySelectorAll('.filter-control'); // 统一选择器
            const rows = table.querySelectorAll('#inventoryTableBody tr');

            filterControls.forEach(control => {
                // 根据元素类型绑定不同的事件
                if (control.tagName === 'SELECT' || control.type === 'date') { // 针对 select 和 date input
                    control.addEventListener('change', applyFilters);
                } else { // input[type="text"] or textarea
                    control.addEventListener('keyup', applyFilters);
                }
            });

            function applyFilters() {
                const activeFilters = {};

                // 收集所有活动的筛选值
                filterControls.forEach(control => {
                    const filterColumn = control.dataset.filterColumn;
                    const filterValue = control.value.toLowerCase().trim();
                    if (filterValue !== '') {
                        activeFilters[filterColumn] = filterValue;
                    }
                });

                rows.forEach(row => {
                    let rowMatchesAllFilters = true;

                    // 检查当前行是否匹配所有筛选条件
                    for (const filterColumn in activeFilters) {
                        const filterValue = activeFilters[filterColumn];
                        
                        // 找到对应列的索引
                        let columnIndex = -1;
                        const headerCells = row.closest('table').querySelectorAll('thead th');
                        headerCells.forEach((th, index) => {
                            if (th.dataset.column === filterColumn) {
                                columnIndex = index;
                            }
                        });

                        if (columnIndex !== -1) {
                            const cell = row.children[columnIndex];
                            if (cell) {
                                const cellText = cell.textContent.toLowerCase();
                                // 对于日期，需要精确匹配，或者根据需要实现日期范围筛选
                                // 对于文本（名称、规格、备注），使用 includes
                                if (filterColumn === 'production_date' || filterColumn === 'expiration_date') {
                                    if (cellText !== filterValue) {
                                        rowMatchesAllFilters = false;
                                        break;
                                    }
                                } else {
                                    if (!cellText.includes(filterValue)) {
                                        rowMatchesAllFilters = false;
                                        break;
                                    }
                                }
                            } else {
                                rowMatchesAllFilters = false; // 如果单元格不存在，也视为不匹配
                                break;
                            }
                        }
                    }

                    if (rowMatchesAllFilters) {
                        row.style.display = ''; // 显示行
                    } else {
                        row.style.display = 'none'; // 隐藏行
                    }
                });
            }
        });
    </script>
</body>
</html>
