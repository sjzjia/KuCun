<?php
// dashboard.php - 库存主页，需要登录才能访问

require_once 'check_session.php'; // 引入会话验证文件

// db_connect.php 已经在 check_session.php 中引入，无需再次引入
// require_once 'db_connect.php';

// --- 处理数据导出请求 ---
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    // 设置 HTTP 头，强制浏览器下载文件
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="inventory_export_' . date('Ymd_His') . '.csv"');

    // 创建一个文件指针连接到输出
    $output = fopen('php://output', 'w');

    // 设置 CSV 文件的 BOM (Byte Order Mark) 以确保 Excel 等软件正确识别 UTF-8 中文
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

    // CSV 表头
    $header = [
        '品牌', '名称', '规格', '国家', '生产日期',
        '到期日期', '数量', '备注'
    ];
    fputcsv($output, $header);

    // 从数据库获取所有库存数据
    $sql_export = "SELECT brand, name, specifications, country, production_date, expiration_date, quantity, remarks FROM inventory ORDER BY created_at DESC";
    $result_export = $conn->query($sql_export);

    if ($result_export && $result_export->num_rows > 0) {
        while ($row = $result_export->fetch_assoc()) {
            fputcsv($output, $row);
        }
    }

    fclose($output);
    $conn->close(); // 在导出完成后关闭数据库连接
    exit; // 导出完成后终止脚本执行
}


// 预设国家列表 (从数据库获取)
$countries = [];
$sql_countries = "SELECT name FROM preset_countries ORDER BY name ASC";
$result_countries = $conn->query($sql_countries); // $conn 来自 db_connect.php
if ($result_countries && $result_countries->num_rows > 0) {
    while ($row = $result_countries->fetch_assoc()) {
        $countries[] = $row['name'];
    }
}

// 预设品牌列表 (从数据库获取)
$brands = [];
$sql_brands = "SELECT name FROM preset_brands ORDER BY name ASC";
$result_brands = $conn->query($sql_brands); // $conn 来自 db_connect.php
if ($result_brands && $result_brands->num_rows > 0) {
    while ($row = $result_brands->fetch_assoc()) {
        $brands[] = $row['name'];
    }
}

// 预设名称列表 (从数据库获取)
$names = [];
$sql_names = "SELECT name FROM preset_names ORDER BY name ASC";
$result_names_preset = $conn->query($sql_names); // 避免与 $result_names 冲突
if ($result_names_preset && $result_names_preset->num_rows > 0) {
    while ($row = $result_names_preset->fetch_assoc()) {
        $names[] = $row['name'];
    }
}

// 预设规格列表 (从数据库获取)
$specifications = [];
$sql_specifications = "SELECT name FROM preset_specifications ORDER BY name ASC";
$result_specifications_preset = $conn->query($sql_specifications); // 避免与 $result_specifications 冲突
if ($result_specifications_preset && $result_specifications_preset->num_rows > 0) {
    while ($row = $result_specifications_preset->fetch_assoc()) {
        $specifications[] = $row['name'];
    }
}


// --- 获取用于筛选的唯一值 (这些现在应该从预设表获取，而不是从 inventory 表获取，以保持一致性) ---
// 由于现在名称和规格都有了预设表，我们直接使用预设的列表
$unique_names = $names;
$unique_specifications = $specifications;
// 备注仍然从 inventory 表获取，因为备注可能非常多样，不适合预设
$unique_remarks = [];
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

$conn->close(); // 在这里关闭连接，因为它是页面的最后一次数据库操作

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
        /* 调整容器的最大宽度和内边距，使其在小屏幕上更适应 */
        .container {
            max-width: 1850px; /* 保持大屏幕的最大宽度 */
            margin: 0 auto;
            padding: 1rem; /* 默认较小内边距 */
        }
        @media (min-width: 640px) { /* Small screens and up */
            .container {
                padding: 1.5rem;
            }
        }
        @media (min-width: 1024px) { /* Large screens and up */
            .container {
                padding: 2rem;
            }
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

        /* Modal styles */
        .modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 1000;
        }
        .modal-content {
            background-color: white;
            padding: 2rem;
            border-radius: 0.5rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            max-width: 600px;
            width: 90%;
            position: relative;
        }
        .close-button {
            position: absolute;
            top: 1rem;
            right: 1rem;
            font-size: 1.5rem;
            cursor: pointer;
            color: #4a5568;
        }
        .cursor-pointer {
            cursor: pointer;
        }

        /* 导航栏在小屏幕上自动换行 */
        .nav-links {
            flex-wrap: wrap;
            justify-content: center; /* 按钮居中 */
            gap: 0.5rem; /* 减小按钮间距 */
        }
        @media (min-width: 768px) { /* Medium screens and up */
            .nav-links {
                flex-wrap: nowrap; /* 在中等屏幕及以上不换行 */
                justify-content: flex-end; /* 按钮靠右 */
                gap: 1rem; /* 恢复正常间距 */
            }
        }
    </style>
</head>
<body class="bg-gray-100">
    <nav class="bg-blue-600 p-4 shadow-md">
        <div class="container flex flex-col md:flex-row justify-between items-center">
            <h1 class="text-white text-2xl font-bold mb-2 md:mb-0"></h1>
            <div class="flex items-center space-x-2 md:space-x-4 nav-links">
                <span class="text-white">欢迎, <?php echo htmlspecialchars($_SESSION['username']); ?>!</span>
                <a href="add_item.php" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg text-sm transition duration-150 ease-in-out">
                    添加新物品
                </a>
                <a href="add_shipment.php" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg text-sm transition duration-150 ease-in-out">
                    添加发货
                </a>
                <a href="shipments_list.php" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg text-sm transition duration-150 ease-in-out">
                    发货列表
                </a>
                <a href="global_settings.php" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg text-sm transition duration-150 ease-in-out">
                    全局配置
                </a>
                <a href="change_password.php" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg text-sm transition duration-150 ease-in-out">
                    修改密码
                </a>
                <a href="logout.php" class="bg-red-500 hover:bg-red-700 text-white font-bold py-2 px-4 rounded-lg text-sm transition duration-150 ease-in-out">
                    注销
                </a>
            </div>
        </div>
    </nav>

    <div class="container mt-8">
        <!-- <h2 class="text-3xl font-bold text-gray-800 mb-6 text-center md:text-left">库存概览</h2> -->

        <!-- 统计数据卡片 -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <div class="bg-white p-6 rounded-lg shadow-lg text-center">
                <p class="text-gray-500 text-sm font-medium uppercase">总物品数量</p>
                <p id="filteredTotalItems" class="text-4xl font-bold text-blue-600 mt-2"><?php echo $total_items; ?></p>
            </div>
            <div class="bg-white p-6 rounded-lg shadow-lg text-center cursor-pointer" data-filter-type="expired">
                <p class="text-gray-500 text-sm font-medium uppercase">已过期物品数量</p>
                <p id="filteredExpiredItems" class="text-4xl font-bold text-red-600 mt-2"><?php echo $expired_items; ?></p>
            </div>
            <div class="bg-white p-6 rounded-lg shadow-lg text-center cursor-pointer" data-filter-type="expiring_soon">
                <p id="expiringSoonLabel" class="text-gray-500 text-sm font-medium uppercase">即将过期物品数量 (30天内)</p>
                <p id="filteredExpiringSoonItems" class="text-4xl font-bold text-yellow-600 mt-2"><?php echo $expiring_soon; ?></p>
            </div>
        </div>

        <!-- <h2 class="text-3xl font-bold text-gray-800 mb-6 text-center md:text-left">库存列表</h2> -->

        <div class="flex flex-col md:flex-row justify-end space-y-2 md:space-y-0 md:space-x-4 mb-4">
            <button id="showAllButton" class="bg-gray-600 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded-lg transition duration-150 ease-in-out">
                显示全部
            </button>
            <button id="bulkEditButton" class="bg-purple-600 hover:bg-purple-700 text-white font-bold py-2 px-4 rounded-lg transition duration-150 ease-in-out">
                批量修改
            </button>
            <a href="dashboard.php?export=csv" class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded-lg transition duration-150 ease-in-out text-center">
                导出数据 (CSV)
            </a>
        </div>

        <?php if (empty($inventory_items)): ?>
            <div class="bg-yellow-100 border border-yellow-400 text-yellow-700 px-4 py-3 rounded relative" role="alert">
                <span class="block sm:inline">目前没有库存物品。请添加一些！</span>
            </div>
        <?php else: ?>
            <div class="bg-white shadow-lg rounded-lg overflow-hidden">
                <!-- 使表格在小屏幕上可水平滚动 -->
                <div class="overflow-x-auto">
                    <table id="inventoryTable" class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <!-- 全选复选框 -->
                                <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    <input type="checkbox" id="selectAllItems" class="form-checkbox h-4 w-4 text-blue-600 rounded">
                                </th>
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
                                        <div class="flex space-x-1">
                                            <select id="production_year" data-filter-column="production_year" class="filter-control w-1/3">
                                                <option value="">年</option>
                                                <?php
                                                $currentYear = date('Y');
                                                for ($y = $currentYear - 5; $y <= $currentYear + 10; $y++) {
                                                    echo "<option value=\"{$y}\">{$y}</option>";
                                                }
                                                ?>
                                            </select>
                                            <select id="production_month" data-filter-column="production_month" class="filter-control w-1/3">
                                                <option value="">月</option>
                                                <?php
                                                for ($m = 1; $m <= 12; $m++) {
                                                    $monthPadded = str_pad($m, 2, '0', STR_PAD_LEFT);
                                                    echo "<option value=\"{$monthPadded}\">{$monthPadded}</option>";
                                                }
                                                ?>
                                            </select>
                                            <select id="production_day" data-filter-column="production_day" class="filter-control w-1/3">
                                                <option value="">日</option>
                                                <?php
                                                for ($d = 1; $d <= 31; $d++) {
                                                    $dayPadded = str_pad($d, 2, '0', STR_PAD_LEFT);
                                                    echo "<option value=\"{$dayPadded}\">{$dayPadded}</option>";
                                                }
                                                ?>
                                            </select>
                                        </div>
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
                                        <div class="flex space-x-1">
                                            <select id="expiration_year" data-filter-column="expiration_year" class="filter-control w-1/3">
                                                <option value="">年</option>
                                                <?php
                                                $currentYear = date('Y');
                                                for ($y = $currentYear - 5; $y <= $currentYear + 10; $y++) {
                                                    echo "<option value=\"{$y}\">{$y}</option>";
                                                }
                                                ?>
                                            </select>
                                            <select id="expiration_month" data-filter-column="expiration_month" class="filter-control w-1/3">
                                                <option value="">月</option>
                                                <?php
                                                for ($m = 1; $m <= 12; $m++) {
                                                    $monthPadded = str_pad($m, 2, '0', STR_PAD_LEFT);
                                                    echo "<option value=\"{$monthPadded}\">{$monthPadded}</option>";
                                                }
                                                ?>
                                            </select>
                                            <select id="expiration_day" data-filter-column="expiration_day" class="filter-control w-1/3">
                                                <option value="">日</option>
                                                <?php
                                                for ($d = 1; $d <= 31; $d++) {
                                                    $dayPadded = str_pad($d, 2, '0', STR_PAD_LEFT);
                                                    echo "<option value=\"{$dayPadded}\">{$dayPadded}</option>";
                                                }
                                                ?>
                                            </select>
                                        </div>
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
                                        <!-- Removed data-filter-column="quantity" to disable filtering -->
                                        <input type="text" placeholder="筛选数量..." class="filter-control">
                                    </div>
                                </th>
                                <!-- 备注 -->
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider" data-column="remarks">
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
                                <!-- 新增操作列 -->
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">操作</th>
                            </tr>
                        </thead>
                        <tbody id="inventoryTableBody" class="bg-white divide-y divide-gray-200">
                            <?php foreach ($inventory_items as $item): ?>
                                <tr data-item-id="<?php echo htmlspecialchars($item['id']); ?>"> <!-- Added data-item-id to row -->
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <input type="checkbox" class="item-checkbox form-checkbox h-4 w-4 text-blue-600 rounded" data-id="<?php echo htmlspecialchars($item['id']); ?>">
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($item['brand']); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($item['name']); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($item['specifications']); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($item['country']); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($item['production_date']); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($item['expiration_date']); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($item['quantity']); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($item['remarks']); ?></td>
                                    <!-- 操作按钮 -->
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <button class="edit-button bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-1 px-3 rounded-lg transition duration-150 ease-in-out"
                                                data-id="<?php echo htmlspecialchars($item['id']); ?>"
                                                data-name="<?php echo htmlspecialchars($item['name']); ?>"
                                                data-country="<?php echo htmlspecialchars($item['country']); ?>"
                                                data-production_date="<?php echo htmlspecialchars($item['production_date']); ?>"
                                                data-expiration_date="<?php echo htmlspecialchars($item['expiration_date']); ?>"
                                                data-specifications="<?php echo htmlspecialchars($item['specifications']); ?>"
                                                data-quantity="<?php echo htmlspecialchars($item['quantity']); ?>"
                                                data-brand="<?php echo htmlspecialchars($item['brand']); ?>"
                                                data-remarks="<?php echo htmlspecialchars($item['remarks']); ?>">
                                            编辑
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Edit Item Modal (for single item edit) -->
    <div id="editItemModal" class="modal hidden" style="display: none;">
        <div class="modal-content">
            <span class="close-button" id="closeModalButton">&times;</span>
            <h3 class="text-2xl font-bold text-gray-800 mb-6 text-center">编辑物品信息</h3>
            <form id="editItemForm" action="update_item.php" method="post">
                <input type="hidden" id="editItemId" name="id">
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-4">
                    <div>
                        <label for="editName" class="block text-gray-700 text-sm font-bold mb-2">名称:</label>
                        <select id="editName" name="name" required
                                class="shadow appearance-none border rounded-lg w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            <option value="">请选择名称</option>
                            <?php foreach ($names as $n): ?>
                                <option value="<?php echo htmlspecialchars($n); ?>"><?php htmlspecialchars($n); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="editCountry" class="block text-gray-700 text-sm font-bold mb-2">国家:</label>
                        <select id="editCountry" name="country"
                                class="shadow appearance-none border rounded-lg w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            <option value="">请选择国家</option>
                            <?php foreach ($countries as $c): ?>
                                <option value="<?php echo htmlspecialchars($c); ?>"><?php echo htmlspecialchars($c); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-4">
                    <div>
                        <label for="editProductionDate" class="block text-gray-700 text-sm font-bold mb-2">生产日期:</label>
                        <input type="date" id="editProductionDate" name="production_date"
                               class="shadow appearance-none border rounded-lg w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    </div>
                    <div>
                        <label for="editExpirationDate" class="block text-gray-700 text-sm font-bold mb-2">到期日期:</label>
                        <input type="date" id="editExpirationDate" name="expiration_date"
                               class="shadow appearance-none border rounded-lg w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    </div>
                </div>

                <div class="mb-4">
                    <label for="editSpecifications" class="block text-gray-700 text-sm font-bold mb-2">规格:</label>
                    <select id="editSpecifications" name="specifications"
                            class="shadow appearance-none border rounded-lg w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        <option value="">请选择规格</option>
                        <?php foreach ($specifications as $s): ?>
                            <option value="<?php echo htmlspecialchars($s); ?>"><?php echo htmlspecialchars($s); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mb-4">
                    <label for="editBrand" class="block text-gray-700 text-sm font-bold mb-2">品牌:</label>
                    <select id="editBrand" name="brand"
                            class="shadow appearance-none border rounded-lg w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        <option value="">请选择品牌</option>
                        <?php foreach ($brands as $b): ?>
                            <option value="<?php echo htmlspecialchars($b); ?>"><?php echo htmlspecialchars($b); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mb-4">
                    <label for="editQuantity" class="block text-gray-700 text-sm font-bold mb-2">数量:</label>
                    <input type="number" id="editQuantity" name="quantity" required min="0"
                           class="shadow appearance-none border rounded-lg w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                </div>

                <div class="mb-6">
                    <label for="editRemarks" class="block text-gray-700 text-sm font-bold mb-2">备注:</label>
                    <textarea id="editRemarks" name="remarks" rows="3"
                              class="shadow appearance-none border rounded-lg w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"></textarea>
                </div>

                <div class="flex items-center justify-between">
                    <button type="submit"
                            class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg focus:outline-none focus:shadow-outline w-full transition duration-150 ease-in-out">
                        保存修改
                    </button>
                </div>
            </form>
            <div id="modalMessage" class="mt-4 text-center hidden"></div>
        </div>
    </div>

    <!-- Bulk Edit Modal -->
    <div id="bulkEditModal" class="modal hidden" style="display: none;">
        <div class="modal-content">
            <span class="close-button" id="closeBulkModalButton">&times;</span>
            <h3 class="text-2xl font-bold text-gray-800 mb-6 text-center">批量修改物品信息</h3>
            <form id="bulkEditForm">
                <p class="text-gray-700 mb-4">选择您要批量修改的字段，并输入新值：</p>

                <div class="mb-4">
                    <label class="inline-flex items-center">
                        <input type="checkbox" class="form-checkbox h-5 w-5 text-blue-600" id="enableBulkBrand">
                        <span class="ml-2 text-gray-700 font-bold">品牌:</span>
                    </label>
                    <select id="bulkBrand" name="brand" disabled
                            class="mt-1 shadow appearance-none border rounded-lg w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        <option value="">请选择品牌</option>
                        <?php foreach ($brands as $b): ?>
                            <option value="<?php echo htmlspecialchars($b); ?>"><?php echo htmlspecialchars($b); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mb-4">
                    <label class="inline-flex items-center">
                        <input type="checkbox" class="form-checkbox h-5 w-5 text-blue-600" id="enableBulkName">
                        <span class="ml-2 text-gray-700 font-bold">名称:</span>
                    </label>
                    <select id="bulkName" name="name" disabled
                            class="mt-1 shadow appearance-none border rounded-lg w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        <option value="">请选择名称</option>
                        <?php foreach ($names as $n): ?>
                            <option value="<?php echo htmlspecialchars($n); ?>"><?php echo htmlspecialchars($n); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mb-4">
                    <label class="inline-flex items-center">
                        <input type="checkbox" class="form-checkbox h-5 w-5 text-blue-600" id="enableBulkSpecifications">
                        <span class="ml-2 text-gray-700 font-bold">规格:</span>
                    </label>
                    <select id="bulkSpecifications" name="specifications" disabled
                            class="mt-1 shadow appearance-none border rounded-lg w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        <option value="">请选择规格</option>
                        <?php foreach ($specifications as $s): ?>
                            <option value="<?php echo htmlspecialchars($s); ?>"><?php echo htmlspecialchars($s); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mb-4">
                    <label class="inline-flex items-center">
                        <input type="checkbox" class="form-checkbox h-5 w-5 text-blue-600" id="enableBulkCountry">
                        <span class="ml-2 text-gray-700 font-bold">国家:</span>
                    </label>
                    <select id="bulkCountry" name="country" disabled
                            class="mt-1 shadow appearance-none border rounded-lg w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        <option value="">请选择国家</option>
                        <?php foreach ($countries as $c): ?>
                            <option value="<?php echo htmlspecialchars($c); ?>"><?php echo htmlspecialchars($c); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mb-4">
                    <label class="inline-flex items-center">
                        <input type="checkbox" class="form-checkbox h-5 w-5 text-blue-600" id="enableBulkProductionDate">
                        <span class="ml-2 text-gray-700 font-bold">生产日期:</span>
                    </label>
                    <input type="date" id="bulkProductionDate" name="production_date" disabled
                           class="mt-1 shadow appearance-none border rounded-lg w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                </div>

                <div class="mb-4">
                    <label class="inline-flex items-center">
                        <input type="checkbox" class="form-checkbox h-5 w-5 text-blue-600" id="enableBulkExpirationDate">
                        <span class="ml-2 text-gray-700 font-bold">到期日期:</span>
                    </label>
                    <input type="date" id="bulkExpirationDate" name="expiration_date" disabled
                           class="mt-1 shadow appearance-none border rounded-lg w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                </div>

                <div class="mb-6">
                    <label class="inline-flex items-center">
                        <input type="checkbox" class="form-checkbox h-5 w-5 text-blue-600" id="enableBulkRemarks">
                        <span class="ml-2 text-gray-700 font-bold">备注:</span>
                    </label>
                    <textarea id="bulkRemarks" name="remarks" rows="3" disabled
                              class="mt-1 shadow appearance-none border rounded-lg w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"></textarea>
                </div>

                <div class="flex items-center justify-between">
                    <button type="submit" id="submitBulkEdit"
                            class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg focus:outline-none focus:shadow-outline w-full transition duration-150 ease-in-out">
                        应用批量修改
                    </button>
                </div>
            </form>
            <div id="bulkModalMessage" class="mt-4 text-center hidden"></div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            console.log('DOMContentLoaded 已触发。'); // Debug log

            const table = document.getElementById('inventoryTable');
            if (!table) {
                console.log('未找到库存表格。'); // Debug log
                return;
            }

            const filterControls = table.querySelectorAll('.filter-control');
            let rows = table.querySelectorAll('#inventoryTableBody tr'); // Use 'let' as rows might be updated

            const filteredTotalItemsDisplay = document.getElementById('filteredTotalItems');
            const filteredExpiredItemsDisplay = document.getElementById('filteredExpiredItems');
            const filteredExpiringSoonItemsDisplay = document.getElementById('filteredExpiringSoonItems');

            // Single Edit Modal elements
            const editItemModal = document.getElementById('editItemModal');
            const closeModalButton = document.getElementById('closeModalButton');
            const editItemForm = document.getElementById('editItemForm');
            const modalMessage = document.getElementById('modalMessage');

            // Bulk Edit Modal elements
            const bulkEditButton = document.getElementById('bulkEditButton');
            const bulkEditModal = document.getElementById('bulkEditModal');
            const closeBulkModalButton = document.getElementById('closeBulkModalButton');
            const bulkEditForm = document.getElementById('bulkEditForm');
            const bulkModalMessage = document.getElementById('bulkModalMessage');
            const selectAllItemsCheckbox = document.getElementById('selectAllItems');
            // itemCheckboxes needs to be dynamic as rows are updated
            let itemCheckboxes = document.querySelectorAll('.item-checkbox'); 

            // New "Show All" button
            const showAllButton = document.getElementById('showAllButton');


            // Bulk edit field toggles
            const bulkEditFields = {
                'brand': { checkbox: document.getElementById('enableBulkBrand'), input: document.getElementById('bulkBrand') },
                'name': { checkbox: document.getElementById('enableBulkName'), input: document.getElementById('bulkName') },
                'specifications': { checkbox: document.getElementById('enableBulkSpecifications'), input: document.getElementById('bulkSpecifications') },
                'country': { checkbox: document.getElementById('enableBulkCountry'), input: document.getElementById('bulkCountry') },
                'production_date': { checkbox: document.getElementById('enableBulkProductionDate'), input: document.getElementById('bulkProductionDate') },
                'expiration_date': { checkbox: document.getElementById('enableBulkExpirationDate'), input: document.getElementById('bulkExpirationDate') },
                'remarks': { checkbox: document.getElementById('enableBulkRemarks'), input: document.getElementById('bulkRemarks') }
            };

            // Initialize bulk edit fields state
            for (const key in bulkEditFields) {
                const field = bulkEditFields[key];
                field.checkbox.addEventListener('change', function() {
                    field.input.disabled = !this.checked;
                    if (!this.checked) {
                        field.input.value = ''; // Clear value when disabled
                    }
                });
            }


            // 初始计算所有统计数量
            applyFilters(); // 调用 applyFilters 也会触发 updateFilteredStatistics

            // Event listeners for filters
            filterControls.forEach(control => {
                // Modified: Use an anonymous function to call applyFilters() without arguments
                // Only add event listener if data-filter-column exists (to exclude quantity)
                if (control.dataset.filterColumn) {
                    if (control.type === 'text' || control.tagName === 'SELECT') { // Listen to change for selects, keyup for text inputs
                        control.addEventListener('change', function() { applyFilters(); }); // Change event for selects and date parts
                        if (control.type === 'text') { // Keyup for text inputs (like quantity, though it's now disabled for filtering)
                             // No need for keyup on quantity as it's not a filter anymore
                        }
                    }
                }
            });

            // Event listeners for single edit buttons
            document.querySelectorAll('.edit-button').forEach(button => {
                button.addEventListener('click', function() {
                    console.log('点击了编辑按钮，物品ID:', this.dataset.id); // Debug log
                    const itemId = this.dataset.id;
                    const itemName = this.dataset.name;
                    const itemCountry = this.dataset.country;
                    const itemProductionDate = this.dataset.production_date;
                    const itemExpirationDate = this.dataset.expiration_date;
                    const itemSpecifications = this.dataset.specifications;
                    const itemQuantity = this.dataset.quantity;
                    const itemBrand = this.dataset.brand;
                    const itemRemarks = this.dataset.remarks;

                    // Populate modal fields
                    document.getElementById('editItemId').value = itemId;
                    document.getElementById('editName').value = itemName;
                    document.getElementById('editCountry').value = itemCountry;
                    document.getElementById('editProductionDate').value = itemProductionDate;
                    document.getElementById('editExpirationDate').value = itemExpirationDate;
                    document.getElementById('editSpecifications').value = itemSpecifications;
                    document.getElementById('editQuantity').value = itemQuantity;
                    document.getElementById('editBrand').value = itemBrand;
                    document.getElementById('editRemarks').value = itemRemarks;

                    modalMessage.textContent = ''; // Clear previous messages
                    modalMessage.className = 'mt-4 text-center hidden'; // Reset styling
                    editItemModal.style.display = 'flex'; // Show modal using flex display
                    editItemModal.classList.remove('hidden'); // Ensure hidden class is removed if present
                    console.log('模态框已显示。'); // Debug log
                });
            });

            // Close single edit modal functionality
            closeModalButton.addEventListener('click', function() {
                editItemModal.style.display = 'none'; // Hide modal
                editItemModal.classList.add('hidden'); // Add hidden class back
                console.log('单个编辑模态框已隐藏。'); // Debug log
            });

            // Close single edit modal if clicked outside content
            editItemModal.addEventListener('click', function(e) {
                if (e.target === editItemModal) {
                    editItemModal.style.display = 'none'; // Hide modal
                    editItemModal.classList.add('hidden'); // Add hidden class back
                    console.log('点击外部区域，单个编辑模态框已隐藏。'); // Debug log
                }
            });

            // Handle single edit modal form submission
            editItemForm.addEventListener('submit', function(e) {
                e.preventDefault(); // Prevent default form submission
                console.log('单个编辑表单提交。'); // Debug log

                const formData = new FormData(this); // Get form data
                const itemId = formData.get('id'); // Get the item ID

                fetch('update_item.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        modalMessage.textContent = data.message;
                        modalMessage.className = 'mt-4 text-center text-green-700 bg-green-100 border border-green-400 px-4 py-3 rounded relative';
                        console.log('物品更新成功:', data.message); // Debug log

                        // Update the specific row in the table
                        const updatedRow = document.querySelector(`tr[data-item-id="${itemId}"]`); // Find the row by data-item-id
                        if (updatedRow) {
                            // Update cell content based on form data
                            // Column indices shifted by 1 due to new checkbox column
                            updatedRow.children[1].textContent = formData.get('brand'); 
                            updatedRow.children[2].textContent = formData.get('name'); 
                            updatedRow.children[3].textContent = formData.get('specifications');
                            updatedRow.children[4].textContent = formData.get('country');
                            updatedRow.children[5].textContent = formData.get('production_date');
                            updatedRow.children[6].textContent = formData.get('expiration_date');
                            updatedRow.children[7].textContent = formData.get('quantity');
                            updatedRow.children[8].textContent = formData.get('remarks');
                            
                            // Also update the data-attributes on the button for future edits
                            const editButton = updatedRow.querySelector('.edit-button');
                            if (editButton) {
                                editButton.dataset.name = formData.get('name');
                                editButton.dataset.country = formData.get('country');
                                editButton.dataset.production_date = formData.get('production_date');
                                editButton.dataset.expiration_date = formData.get('expiration_date');
                                editButton.dataset.specifications = formData.get('specifications');
                                editButton.dataset.quantity = formData.get('quantity');
                                editButton.dataset.brand = formData.get('brand');
                                editButton.dataset.remarks = formData.get('remarks');
                            }
                        }
                        // Re-query rows and item checkboxes after successful update
                        rows = table.querySelectorAll('#inventoryTableBody tr');
                        itemCheckboxes = document.querySelectorAll('.item-checkbox');
                        applyFilters(); 
                        setTimeout(() => {
                            editItemModal.style.display = 'none'; // Hide modal after a short delay
                            editItemModal.classList.add('hidden'); // Add hidden class back
                        }, 1500);
                    } else {
                        modalMessage.textContent = data.message;
                        modalMessage.className = 'mt-4 text-center text-red-700 bg-red-100 border border-red-400 px-4 py-3 rounded relative';
                        console.error('物品更新失败:', data.message); // Debug log
                    }
                })
                .catch(error => {
                    console.error('Fetch 错误:', error); // Debug log
                    modalMessage.textContent = '发生错误，请重试。';
                    modalMessage.className = 'mt-4 text-center text-red-700 bg-red-100 border border-red-400 px-4 py-3 rounded relative';
                });
            });

            // --- Bulk Edit Logic ---

            // Select All Checkbox
            selectAllItemsCheckbox.addEventListener('change', function() {
                itemCheckboxes.forEach(checkbox => {
                    // Only check/uncheck visible checkboxes
                    if (checkbox.closest('tr').style.display !== 'none') {
                        checkbox.checked = this.checked;
                    }
                });
            });

            // Individual Item Checkboxes
            itemCheckboxes.forEach(checkbox => {
                checkbox.addEventListener('change', function() {
                    // If any visible item checkbox is unchecked, uncheck "Select All"
                    const allVisibleChecked = Array.from(itemCheckboxes).every(cb => {
                        return cb.closest('tr').style.display === 'none' || cb.checked;
                    });
                    selectAllItemsCheckbox.checked = allVisibleChecked;
                });
            });

            // Bulk Edit Button Click
            bulkEditButton.addEventListener('click', function() {
                const selectedItemIds = Array.from(itemCheckboxes)
                                            .filter(checkbox => checkbox.checked && checkbox.closest('tr').style.display !== 'none')
                                            .map(checkbox => checkbox.dataset.id);

                if (selectedItemIds.length === 0) {
                    // Using alert for simplicity, consider a custom modal
                    alert('请选择至少一个物品进行批量修改。'); 
                    return;
                }
                
                console.log('准备批量修改的物品ID:', selectedItemIds); // Debug log
                bulkModalMessage.textContent = ''; // Clear previous messages
                bulkModalMessage.className = 'mt-4 text-center hidden'; // Reset styling
                bulkEditModal.style.display = 'flex'; // Show modal
                bulkEditModal.classList.remove('hidden');
            });

            // Close Bulk Edit Modal
            closeBulkModalButton.addEventListener('click', function() {
                bulkEditModal.style.display = 'none';
                bulkEditModal.classList.add('hidden');
                // Reset bulk edit form fields and checkboxes
                bulkEditForm.reset();
                for (const key in bulkEditFields) {
                    const field = bulkEditFields[key];
                    field.input.disabled = true;
                }
                console.log('批量编辑模态框已隐藏。');
            });

            // Close Bulk Edit Modal if clicked outside content
            bulkEditModal.addEventListener('click', function(e) {
                if (e.target === bulkEditModal) {
                    bulkEditModal.style.display = 'none';
                    bulkEditModal.classList.add('hidden');
                    // Reset bulk edit form fields and checkboxes
                    bulkEditForm.reset();
                    for (const key in bulkEditFields) {
                        const field = bulkEditFields[key];
                        field.input.disabled = true;
                    }
                    console.log('点击外部区域，批量编辑模态框已隐藏。');
                }
            });

            // Handle Bulk Edit Form Submission
            bulkEditForm.addEventListener('submit', function(e) {
                e.preventDefault();
                console.log('批量编辑表单提交。');

                const selectedItemIds = Array.from(itemCheckboxes)
                                            .filter(checkbox => checkbox.checked && checkbox.closest('tr').style.display !== 'none')
                                            .map(checkbox => checkbox.dataset.id);

                if (selectedItemIds.length === 0) {
                    bulkModalMessage.textContent = '没有选中的物品。';
                    bulkModalMessage.className = 'mt-4 text-center text-red-700 bg-red-100 border border-red-400 px-4 py-3 rounded relative';
                    return;
                }

                const bulkFormData = new FormData();
                bulkFormData.append('ids', JSON.stringify(selectedItemIds)); // Send IDs as a JSON string

                let changesMade = false;
                for (const key in bulkEditFields) {
                    const field = bulkEditFields[key];
                    // Only append if the checkbox is checked AND the value is not empty (for selects/inputs)
                    // For remarks, it can be an empty string, so we only check if the checkbox is checked.
                    if (field.checkbox.checked) {
                        if (field.input.tagName === 'SELECT' || field.input.type === 'date' || field.input.type === 'text' || field.input.tagName === 'TEXTAREA') {
                            bulkFormData.append(key, field.input.value);
                            changesMade = true;
                        }
                    }
                }

                if (!changesMade) {
                    bulkModalMessage.textContent = '请选择至少一个字段进行修改。';
                    bulkModalMessage.className = 'mt-4 text-center text-red-700 bg-red-100 border border-red-400 px-4 py-3 rounded relative';
                    return;
                }

                fetch('bulk_update_items.php', { // New PHP endpoint for bulk updates
                    method: 'POST',
                    body: bulkFormData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        bulkModalMessage.textContent = data.message;
                        bulkModalMessage.className = 'mt-4 text-center text-green-700 bg-green-100 border border-green-400 px-4 py-3 rounded relative';
                        console.log('批量更新成功:', data.message);

                        // --- Update table rows with new data ---
                        if (data.updatedItems && Array.isArray(data.updatedItems)) {
                            console.log('正在更新表格行数据...');
                            data.updatedItems.forEach(updatedItem => {
                                const rowToUpdate = document.querySelector(`tr[data-item-id="${updatedItem.id}"]`);
                                if (rowToUpdate) {
                                    // Update each cell based on the returned data
                                    // Column indices are: checkbox(0), brand(1), name(2), specifications(3), country(4), production_date(5), expiration_date(6), quantity(7), remarks(8), actions(9)
                                    rowToUpdate.children[1].textContent = updatedItem.brand || '';
                                    rowToUpdate.children[2].textContent = updatedItem.name || '';
                                    rowToUpdate.children[3].textContent = updatedItem.specifications || '';
                                    rowToUpdate.children[4].textContent = updatedItem.country || '';
                                    rowToUpdate.children[5].textContent = updatedItem.production_date || '';
                                    rowToUpdate.children[6].textContent = updatedItem.expiration_date || '';
                                    rowToUpdate.children[7].textContent = updatedItem.quantity; // Quantity should always be a number, no || ''
                                    rowToUpdate.children[8].textContent = updatedItem.remarks || '';

                                    // Also update the data-attributes on the single edit button for future edits
                                    const editButton = rowToUpdate.querySelector('.edit-button');
                                    if (editButton) {
                                        editButton.dataset.name = updatedItem.name || '';
                                        editButton.dataset.country = updatedItem.country || '';
                                        editButton.dataset.production_date = updatedItem.production_date || '';
                                        editButton.dataset.expiration_date = updatedItem.expiration_date || '';
                                        editButton.dataset.specifications = updatedItem.specifications || '';
                                        editButton.dataset.quantity = updatedItem.quantity;
                                        editButton.dataset.brand = updatedItem.brand || '';
                                        editButton.dataset.remarks = updatedItem.remarks || '';
                                    }
                                }
                            });
                            // Re-query rows and item checkboxes after successful update
                            rows = table.querySelectorAll('#inventoryTableBody tr');
                            itemCheckboxes = document.querySelectorAll('.item-checkbox');
                        }

                        // Uncheck all checkboxes after successful bulk update
                        selectAllItemsCheckbox.checked = false;
                        itemCheckboxes.forEach(checkbox => {
                            checkbox.checked = false;
                        });

                        // Re-apply filters and update statistics, and refresh table data
                        applyFilters(); 

                        // Optionally, hide the modal after a delay
                        setTimeout(() => {
                            bulkEditModal.style.display = 'none';
                            bulkEditModal.classList.add('hidden');
                            bulkEditForm.reset();
                            for (const key in bulkEditFields) {
                                const field = bulkEditFields[key];
                                field.input.disabled = true;
                            }
                        }, 1500);

                    } else {
                        bulkModalMessage.textContent = data.message;
                        bulkModalMessage.className = 'mt-4 text-center text-red-700 bg-red-100 border border-red-400 px-4 py-3 rounded relative';
                        console.error('批量更新失败:', data.message);
                    }
                })
                .catch(error => {
                    console.error('Fetch 错误:', error);
                    bulkModalMessage.textContent = '发生错误，请重试。';
                    bulkModalMessage.className = 'mt-4 text-center text-red-700 bg-red-100 border border-red-400 px-4 py-3 rounded relative';
                });
            });


            function applyFilters(filterOverride = {}) { // Modified to accept filterOverride
                console.log('applyFilters 函数被调用。'); // Debug log
                console.log('filterOverride:', filterOverride); // Debug log

                const activeFilters = {};
                const dateFilters = {}; // To store year, month, day for each date column

                // If a filter override is provided, clear existing filter inputs first
                if (Object.keys(filterOverride).length > 0) {
                    console.log('检测到筛选覆盖，正在清空所有筛选输入框。'); // Debug log
                    filterControls.forEach(control => {
                        control.value = ''; // Clear all filter inputs
                    });
                    // Explicitly clear date selects if override is for expired/expiring_soon
                    if (filterOverride.expiration_date === 'expired' || filterOverride.expiration_date === 'expiring_soon') {
                        document.getElementById('expiration_year').value = '';
                        document.getElementById('expiration_month').value = '';
                        document.getElementById('expiration_day').value = '';
                    }
                }

                // Collect active filters from controls (or apply override)
                filterControls.forEach(control => {
                    const filterColumn = control.dataset.filterColumn;
                    const filterValue = control.value.toLowerCase().trim();
                    if (filterValue !== '') {
                        // Handle date components separately
                        if (filterColumn && (filterColumn.startsWith('production_') || filterColumn.startsWith('expiration_'))) {
                            const baseColumn = filterColumn.split('_')[0]; // 'production' or 'expiration'
                            const component = filterColumn.split('_')[1]; // 'year', 'month', 'day'
                            if (!dateFilters[baseColumn]) {
                                dateFilters[baseColumn] = {};
                            }
                            dateFilters[baseColumn][component] = filterValue;
                        } else if (filterColumn) { // Only add if data-filter-column exists (to exclude quantity)
                            activeFilters[filterColumn] = filterValue;
                        }
                        console.log(`从控件收集到筛选条件: ${filterColumn} = ${filterValue}`); // Debug log
                    }
                });

                // Apply filter override
                for (const key in filterOverride) {
                    activeFilters[key] = filterOverride[key];
                    console.log(`应用筛选覆盖: ${key} = ${filterOverride[key]}`); // Debug log
                }

                console.log('最终活动的筛选条件:', activeFilters); // Debug log


                let filteredQuantitySum = 0; // 筛选后总物品数量
                let expiredQuantitySum = 0; // 筛选后已过期物品数量
                let expiringSoonQuantitySum = 0; // 筛选后即将过期物品数量

                const currentDate = new Date();
                currentDate.setHours(0, 0, 0, 0); // 设置为当天开始，方便日期比较

                const thirtyDaysLater = new Date();
                thirtyDaysLater.setDate(currentDate.getDate() + 30);
                thirtyDaysLater.setHours(0, 0, 0, 0); // 设置为30天后当天开始

                rows.forEach(row => {
                    let rowMatchesAllFilters = true;
                    const itemId = row.dataset.itemId; // Get item ID for logging
                    // console.log(`--- 检查物品ID: ${itemId} ---`); // Debug log for each row

                    // Iterate through all possible filter columns to check against active filters
                    const allFilterableColumns = ['brand', 'name', 'specifications', 'country', 'remarks']; // Quantity and dates handled separately
                    
                    for (const filterColumn of allFilterableColumns) {
                        const filterValue = activeFilters[filterColumn];
                        if (!filterValue) continue; // Skip if no active filter for this column

                        let columnIndex = -1;
                        // Adjust column index for filters because of the new checkbox column
                        // Column indices are: checkbox(0), brand(1), name(2), specifications(3), country(4), production_date(5), expiration_date(6), quantity(7), remarks(8), actions(9)
                        if (filterColumn === 'brand') columnIndex = 1;
                        else if (filterColumn === 'name') columnIndex = 2;
                        else if (filterColumn === 'specifications') columnIndex = 3;
                        else if (filterColumn === 'country') columnIndex = 4;
                        else if (filterColumn === 'remarks') columnIndex = 8;
                        
                        if (columnIndex !== -1) {
                            const cell = row.children[columnIndex];
                            if (cell) {
                                const cellText = cell.textContent.toLowerCase().trim(); // Trim whitespace

                                console.log(`  物品ID ${itemId} - 检查列: ${filterColumn}, 单元格内容: "${cellText}", 筛选值: "${filterValue}"`); // Debug log
                                
                                // For text-based filters (brand, name, specifications, country, remarks)
                                // Use includes for partial matching
                                if (!cellText.includes(filterValue)) {
                                    rowMatchesAllFilters = false;
                                    console.log(`    物品ID ${itemId} - 列 "${filterColumn}" 内容 "${cellText}" 不包含 "${filterValue}"，不匹配筛选。`); // Debug log
                                    break;
                                }
                            } else {
                                rowMatchesAllFilters = false; // If cell does not exist, treat as not matching
                                console.log(`    物品ID ${itemId} - 列 "${filterColumn}" 单元格不存在，不匹配筛选。`); // Debug log
                                break;
                            }
                        }
                    }

                    // Check for production date filters
                    if (rowMatchesAllFilters && dateFilters['production'] && (dateFilters['production'].year || dateFilters['production'].month || dateFilters['production'].day)) {
                        const cellText = row.children[5].textContent.trim(); // Production date column
                        const prodYear = dateFilters['production'].year;
                        const prodMonth = dateFilters['production'].month;
                        const prodDay = dateFilters['production'].day;

                        let filterDateString = '';
                        if (prodYear) filterDateString += prodYear;
                        if (prodMonth) filterDateString += '-' + prodMonth.padStart(2, '0');
                        if (prodDay) filterDateString += '-' + prodDay.padStart(2, '0');

                        if (!cellText.startsWith(filterDateString)) {
                            rowMatchesAllFilters = false;
                            console.log(`    物品ID ${itemId} - 生产日期 (${cellText}) 不匹配筛选日期 (${filterDateString})。`);
                        }
                    }

                    // Check for expiration date filters (from year/month/day selects)
                    if (rowMatchesAllFilters && dateFilters['expiration'] && (dateFilters['expiration'].year || dateFilters['expiration'].month || dateFilters['expiration'].day)) {
                        const cellText = row.children[6].textContent.trim(); // Expiration date column
                        const expYear = dateFilters['expiration'].year;
                        const expMonth = dateFilters['expiration'].month;
                        const expDay = dateFilters['expiration'].day;

                        let filterDateString = '';
                        if (expYear) filterDateString += expYear;
                        if (expMonth) filterDateString += '-' + expMonth.padStart(2, '0');
                        if (expDay) filterDateString += '-' + expDay.padStart(2, '0');

                        if (!cellText.startsWith(filterDateString)) {
                            rowMatchesAllFilters = false;
                            console.log(`    物品ID ${itemId} - 到期日期 (${cellText}) 不匹配筛选日期 (${filterDateString})。`);
                        }
                    }

                    // Handle 'expired' and 'expiring_soon' from cards (these override specific date selects)
                    if (rowMatchesAllFilters && (activeFilters['expiration_date'] === 'expired' || activeFilters['expiration_date'] === 'expiring_soon')) {
                        const expirationDateCell = row.children[6];
                        if (expirationDateCell && expirationDateCell.textContent.trim() !== '') {
                            const dateString = expirationDateCell.textContent.trim();
                            const itemExpirationDate = new Date(dateString);

                            if (!isNaN(itemExpirationDate.getTime())) {
                                itemExpirationDate.setHours(0, 0, 0, 0);
                                if (activeFilters['expiration_date'] === 'expired') {
                                    if (itemExpirationDate >= currentDate) {
                                        rowMatchesAllFilters = false;
                                        console.log(`    物品ID ${itemId} - 过期日期 (${dateString}) 未过期，不匹配 'expired' 筛选。`);
                                    }
                                } else if (activeFilters['expiration_date'] === 'expiring_soon') {
                                    if (!(itemExpirationDate >= currentDate && itemExpirationDate <= thirtyDaysLater)) {
                                        rowMatchesAllFilters = false;
                                        console.log(`    物品ID ${itemId} - 过期日期 (${dateString}) 不在即将过期范围内，不匹配 'expiring_soon' 筛选。`);
                                    }
                                }
                            } else {
                                rowMatchesAllFilters = false; // Invalid date in cell, won't match
                                console.log(`    物品ID ${itemId} - 无效的到期日期 "${dateString}"，不匹配卡片筛选。`);
                            }
                        } else {
                            rowMatchesAllFilters = false; // No expiration date, won't match expired/expiring soon
                            console.log(`    物品ID ${itemId} - 无到期日期，不匹配卡片筛选。`);
                        }
                    }
                    
                    if (rowMatchesAllFilters) {
                        row.style.display = ''; // Show row
                        // Quantity column index is 7
                        const quantityCell = row.children[7]; 
                        // Expiration Date column index is 6
                        const expirationDateCell = row.children[6]; 

                        let quantity = 0;
                        if (quantityCell) {
                            quantity = parseInt(quantityCell.textContent.trim(), 10);
                            if (isNaN(quantity)) {
                                quantity = 0; // If not a valid number, default to 0
                            }
                        }
                        
                        filteredQuantitySum += quantity; // Accumulate to total quantity

                        // Check expiration status for statistics (even if not filtering by it)
                        if (expirationDateCell && expirationDateCell.textContent.trim() !== '') {
                            const dateString = expirationDateCell.textContent.trim();
                            const itemExpirationDate = new Date(dateString);
                            
                            // Check if date is valid
                            if (!isNaN(itemExpirationDate.getTime())) {
                                itemExpirationDate.setHours(0, 0, 0, 0); // Set to start of day

                                if (itemExpirationDate < currentDate) {
                                    expiredQuantitySum += quantity; // Accumulate to expired
                                } else if (itemExpirationDate >= currentDate && itemExpirationDate <= thirtyDaysLater) {
                                    expiringSoonQuantitySum += quantity; // Accumulate to expiring soon
                                }
                            }
                        }
                        console.log(`物品ID ${itemId} 匹配所有筛选条件。显示此行。`); // Debug log
                    } else {
                        row.style.display = 'none'; // Hide row
                        console.log(`物品ID ${itemId} 不匹配筛选条件。隐藏此行。`); // Debug log
                    }
                });

                // Update all displayed statistics
                updateFilteredStatistics(filteredQuantitySum, expiredQuantitySum, expiringSoonQuantitySum);
            }

            // Update all displayed statistics
            function updateFilteredStatistics(total, expired, expiringSoon) {
                console.log('updateFilteredStatistics 被调用。'); // Debug log
                console.log(`更新统计: 总数=${total}, 已过期=${expired}, 即将过期=${expiringSoon}`); // Debug log

                if (filteredTotalItemsDisplay) {
                    filteredTotalItemsDisplay.textContent = total;
                }
                if (filteredExpiredItemsDisplay) {
                    filteredExpiredItemsDisplay.textContent = expired;
                }
                if (filteredExpiringSoonItemsDisplay) {
                    filteredExpiringSoonItemsDisplay.textContent = expiringSoon;
                }
            }

            // Event listeners for clickable statistics cards
            const expiredCard = document.querySelector('.bg-white.p-6.rounded-lg.shadow-lg.text-center[data-filter-type="expired"]');
            const expiringSoonCard = document.querySelector('.bg-white.p-6.rounded-lg.shadow-lg.text-center[data-filter-type="expiring_soon"]');

            if (expiredCard) {
                expiredCard.addEventListener('click', function() {
                    console.log('点击了已过期物品数量卡片。触发筛选。'); // Debug log
                    applyFilters({ expiration_date: 'expired' });
                });
            }

            if (expiringSoonCard) {
                expiringSoonCard.addEventListener('click', function() {
                    console.log('点击了即将过期物品数量卡片。触发筛选。'); // Debug log
                    applyFilters({ expiration_date: 'expiring_soon' });
                });
            }

            // Event listener for "Show All" button
            if (showAllButton) {
                showAllButton.addEventListener('click', function() {
                    console.log('点击了“显示全部”按钮。清除所有筛选。'); // Debug log
                    // Clear all filter input values
                    filterControls.forEach(control => {
                        control.value = '';
                    });
                    // Re-apply filters without any specific override, which will show all
                    applyFilters({}); 
                });
            }
        });
    </script>
</body>
</html>