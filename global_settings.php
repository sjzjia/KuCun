<?php
// global_settings.php - 全局配置页面

require_once 'check_session.php'; // 引入会话验证文件

$message = '';
$error = '';

// --- 获取当前的 SSO 状态 ---
$sso_enabled = false;
$sql_get_sso = "SELECT setting_value FROM system_settings WHERE setting_name = 'sso_enabled'";
$result_sso = $conn->query($sql_get_sso);
if ($result_sso && $result_sso->num_rows > 0) {
    $row_sso = $result_sso->fetch_assoc();
    $sso_enabled = ($row_sso['setting_value'] === 'true');
} else {
    // 如果设置不存在，则创建默认值
    $stmt_insert_default = $conn->prepare("INSERT IGNORE INTO system_settings (setting_name, setting_value) VALUES ('sso_enabled', 'true')");
    $stmt_insert_default->execute();
    $stmt_insert_default->close();
    $sso_enabled = true; // 默认启用
}

// --- 处理表单提交 (SSO 和预设) ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // 处理 SSO 状态切换
    if (isset($_POST['toggle_sso'])) {
        $new_sso_status = $sso_enabled ? 'false' : 'true'; // 切换状态

        $stmt = $conn->prepare("UPDATE system_settings SET setting_value = ? WHERE setting_name = 'sso_enabled'");
        $stmt->bind_param("s", $new_sso_status);

        if ($stmt->execute()) {
            $message = "单点登录功能已成功切换为 " . ($new_sso_status === 'true' ? '启用' : '禁用') . "。";
            $sso_enabled = !$sso_enabled; // 更新页面显示的状态
        } else {
            $error = "切换单点登录功能失败: " . $stmt->error;
        }
        $stmt->close();
    }
    // 处理添加国家
    elseif (isset($_POST['add_country']) && !empty($_POST['country_name'])) {
        $name_to_add = trim($_POST['country_name']);
        try {
            $stmt = $conn->prepare("INSERT INTO preset_countries (name) VALUES (?)");
            $stmt->bind_param("s", $name_to_add);
            if ($stmt->execute()) {
                $message = "国家 '" . htmlspecialchars($name_to_add) . "' 添加成功！";
            } else {
                $error = "添加国家失败: " . $stmt->error;
            }
            $stmt->close();
        } catch (mysqli_sql_exception $e) {
            if ($e->getCode() == 1062) {
                $error = "添加国家失败: 国家 '" . htmlspecialchars($name_to_add) . "' 已存在。";
            } else {
                $error = "添加国家失败: " . $e->getMessage();
            }
        }
    }
    // 处理添加品牌
    elseif (isset($_POST['add_brand']) && !empty($_POST['brand_name'])) {
        $name_to_add = trim($_POST['brand_name']);
        try {
            $stmt = $conn->prepare("INSERT INTO preset_brands (name) VALUES (?)");
            $stmt->bind_param("s", $name_to_add);
            if ($stmt->execute()) {
                $message = "品牌 '" . htmlspecialchars($name_to_add) . "' 添加成功！";
            } else {
                $error = "添加品牌失败: " . $stmt->error;
            }
            $stmt->close();
        } catch (mysqli_sql_exception $e) {
            if ($e->getCode() == 1062) {
                $error = "添加品牌失败: 品牌 '" . htmlspecialchars($name_to_add) . "' 已存在。";
            } else {
                $error = "添加品牌失败: " . $e->getMessage();
            }
        }
    }
    // 处理添加名称
    elseif (isset($_POST['add_name']) && !empty($_POST['item_name'])) {
        $name_to_add = trim($_POST['item_name']);
        try {
            $stmt = $conn->prepare("INSERT INTO preset_names (name) VALUES (?)");
            $stmt->bind_param("s", $name_to_add);
            if ($stmt->execute()) {
                $message = "名称 '" . htmlspecialchars($name_to_add) . "' 添加成功！";
            } else {
                $error = "添加名称失败: " . $stmt->error;
            }
            $stmt->close();
        } catch (mysqli_sql_exception $e) {
            if ($e->getCode() == 1062) {
                $error = "添加名称失败: 名称 '" . htmlspecialchars($name_to_add) . "' 已存在。";
            } else {
                $error = "添加名称失败: " . $e->getMessage();
            }
        }
    }
    // 处理添加规格
    elseif (isset($_POST['add_specification']) && !empty($_POST['spec_name'])) {
        $name_to_add = trim($_POST['spec_name']);
        try {
            $stmt = $conn->prepare("INSERT INTO preset_specifications (name) VALUES (?)");
            $stmt->bind_param("s", $name_to_add);
            if ($stmt->execute()) {
                $message = "规格 '" . htmlspecialchars($name_to_add) . "' 添加成功！";
            } else {
                $error = "添加规格失败: " . $stmt->error;
            }
            $stmt->close();
        } catch (mysqli_sql_exception $e) {
            if ($e->getCode() == 1062) {
                $error = "添加规格失败: 规格 '" . htmlspecialchars($name_to_add) . "' 已存在。";
            } else {
                $error = "添加规格失败: " . $e->getMessage();
            }
        }
    }
    // 清空输入框
    $_POST = array();
}

// --- 处理删除预设 (GET 请求) ---
if (isset($_GET['action']) && $_GET['action'] == 'delete') {
    $id = (int)$_GET['id'];
    $type = $_GET['type'] ?? '';
    $table_name = '';
    $item_type_display = '';

    switch ($type) {
        case 'country':
            $table_name = 'preset_countries';
            $item_type_display = '国家';
            break;
        case 'brand':
            $table_name = 'preset_brands';
            $item_type_display = '品牌';
            break;
        case 'name':
            $table_name = 'preset_names';
            $item_type_display = '名称';
            break;
        case 'specification':
            $table_name = 'preset_specifications';
            $item_type_display = '规格';
            break;
        default:
            $error = "无效的删除类型。";
            header("Location: global_settings.php"); // 重定向以清除 GET 参数
            exit;
    }

    if (!empty($table_name)) {
        // 获取要删除的名称，用于确认消息
        $get_name_sql = "SELECT name FROM " . $table_name . " WHERE id = ?";
        $get_name_stmt = $conn->prepare($get_name_sql);
        $get_name_stmt->bind_param("i", $id);
        $get_name_stmt->execute();
        $get_name_stmt->bind_result($item_name_to_delete);
        $get_name_stmt->fetch();
        $get_name_stmt->close();

        try {
            $stmt = $conn->prepare("DELETE FROM " . $table_name . " WHERE id = ?");
            $stmt->bind_param("i", $id);
            if ($stmt->execute()) {
                $message = $item_type_display . " '" . htmlspecialchars($item_name_to_delete) . "' 删除成功！";
            } else {
                $error = "删除" . $item_type_display . "失败: " . $stmt->error;
            }
            $stmt->close();
        } catch (mysqli_sql_exception $e) {
            if ($e->getCode() == 1451) { // 外键约束失败错误代码
                $error = "删除" . $item_type_display . "失败: 该" . $item_type_display . "有库存物品关联，请先删除相关物品。";
            } else {
                $error = "删除" . $item_type_display . "失败: " . $e->getMessage();
            }
        }
    }
    // 重定向以清除 GET 参数，防止重复删除
    header("Location: global_settings.php");
    exit;
}


// --- 获取当前预设列表以显示 ---
$countries = [];
$sql_countries = "SELECT id, name FROM preset_countries ORDER BY name ASC";
$result_countries = $conn->query($sql_countries);
if ($result_countries && $result_countries->num_rows > 0) {
    while ($row = $result_countries->fetch_assoc()) {
        $countries[] = $row;
    }
}

$brands = [];
$sql_brands = "SELECT id, name FROM preset_brands ORDER BY name ASC";
$result_brands = $conn->query($sql_brands);
if ($result_brands && $result_brands->num_rows > 0) {
    while ($row = $result_brands->fetch_assoc()) {
        $brands[] = $row;
    }
}

$names = [];
$sql_names = "SELECT id, name FROM preset_names ORDER BY name ASC";
$result_names = $conn->query($sql_names);
if ($result_names && $result_names->num_rows > 0) {
    while ($row = $result_names->fetch_assoc()) {
        $names[] = $row;
    }
}

$specifications = [];
$sql_specifications = "SELECT id, name FROM preset_specifications ORDER BY name ASC";
$result_specifications = $conn->query($sql_specifications);
if ($result_specifications && $result_specifications->num_rows > 0) {
    while ($row = $result_specifications->fetch_assoc()) {
        $specifications[] = $row;
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>全局配置</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f0f2f5;
        }
        .container {
            max-width: 1200px; /* 增加容器宽度以容纳更多管理部分 */
            margin: 0 auto;
            padding: 2rem;
        }
        .preset-list {
            max-height: 250px; /* 调整高度以适应更多列表 */
            overflow-y: auto;
            border: 1px solid #e2e8f0;
            border-radius: 0.375rem;
            margin-top: 1rem;
        }
        .preset-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem 1rem;
            border-bottom: 1px solid #e2e8f0;
        }
        .preset-item:last-child {
            border-bottom: none;
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
                <a href="logout.php" class="bg-red-500 hover:bg-red-700 text-white font-bold py-2 px-4 rounded-lg transition duration-150 ease-in-out">
                    注销
                </a>
            </div>
        </div>
    </nav>

    <div class="container mt-8">
        <h2 class="text-3xl font-bold text-gray-800 mb-6">全局配置</h2>

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

        <div class="bg-white p-8 rounded-lg shadow-lg mb-8">
            <h3 class="text-2xl font-bold text-gray-700 mb-4 text-center">单点登录 (SSO) 配置</h3>
            <p class="text-gray-700 text-xl mb-4 text-center">当前单点登录状态：
                <span class="font-bold <?php echo $sso_enabled ? 'text-green-600' : 'text-red-600'; ?>">
                    <?php echo $sso_enabled ? '已启用' : '已禁用'; ?>
                </span>
            </p>
            <form action="global_settings.php" method="post" class="text-center">
                <button type="submit" name="toggle_sso"
                        class="px-6 py-3 rounded-lg font-bold text-white transition duration-150 ease-in-out
                               <?php echo $sso_enabled ? 'bg-red-600 hover:bg-red-700' : 'bg-green-600 hover:bg-green-700'; ?>">
                    <?php echo $sso_enabled ? '禁用单点登录' : '启用单点登录'; ?>
                </button>
            </form>
            <p class="text-gray-500 text-sm mt-4 text-center">
                <?php echo $sso_enabled ? '启用状态下，同一账号在其他地方登录会导致当前会话失效。' : '禁用状态下，同一账号可在多个地方同时登录。'; ?>
            </p>
        </div>

        <h3 class="text-3xl font-bold text-gray-800 mb-6">预设选项管理</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-2 gap-8">
            <!-- 国家管理部分 -->
            <div class="bg-white p-6 rounded-lg shadow-lg">
                <h3 class="text-2xl font-bold text-gray-700 mb-4">管理国家</h3>
                <form action="global_settings.php" method="post" class="flex items-center space-x-2 mb-4">
                    <input type="text" name="country_name" placeholder="新国家名称" required
                           class="shadow appearance-none border rounded-lg py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent flex-grow">
                    <button type="submit" name="add_country"
                            class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg transition duration-150 ease-in-out">
                        添加国家
                    </button>
                </form>
                <div class="preset-list bg-gray-50">
                    <?php if (empty($countries)): ?>
                        <p class="p-4 text-gray-600">目前没有预设国家。</p>
                    <?php else: ?>
                        <?php foreach ($countries as $country): ?>
                            <div class="preset-item">
                                <span class="text-gray-800"><?php echo htmlspecialchars($country['name']); ?></span>
                                <a href="global_settings.php?action=delete&type=country&id=<?php echo $country['id']; ?>"
                                   onclick="return confirm('确定要删除国家 <?php echo htmlspecialchars($country['name']); ?> 吗？');"
                                   class="bg-red-500 hover:bg-red-700 text-white text-sm font-bold py-1 px-3 rounded-lg transition duration-150 ease-in-out">
                                    删除
                                </a>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- 品牌管理部分 -->
            <div class="bg-white p-6 rounded-lg shadow-lg">
                <h3 class="text-2xl font-bold text-gray-700 mb-4">管理品牌</h3>
                <form action="global_settings.php" method="post" class="flex items-center space-x-2 mb-4">
                    <input type="text" name="brand_name" placeholder="新品牌名称" required
                           class="shadow appearance-none border rounded-lg py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent flex-grow">
                    <button type="submit" name="add_brand"
                            class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg transition duration-150 ease-in-out">
                        添加品牌
                    </button>
                </form>
                <div class="preset-list bg-gray-50">
                    <?php if (empty($brands)): ?>
                        <p class="p-4 text-gray-600">目前没有预设品牌。</p>
                    <?php else: ?>
                        <?php foreach ($brands as $brand): ?>
                            <div class="preset-item">
                                <span class="text-gray-800"><?php echo htmlspecialchars($brand['name']); ?></span>
                                <a href="global_settings.php?action=delete&type=brand&id=<?php echo $brand['id']; ?>"
                                   onclick="return confirm('确定要删除品牌 <?php echo htmlspecialchars($brand['name']); ?> 吗？');"
                                   class="bg-red-500 hover:bg-red-700 text-white text-sm font-bold py-1 px-3 rounded-lg transition duration-150 ease-in-out">
                                    删除
                                </a>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- 名称管理部分 -->
            <div class="bg-white p-6 rounded-lg shadow-lg">
                <h3 class="text-2xl font-bold text-gray-700 mb-4">管理名称</h3>
                <form action="global_settings.php" method="post" class="flex items-center space-x-2 mb-4">
                    <input type="text" name="item_name" placeholder="新物品名称" required
                           class="shadow appearance-none border rounded-lg py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent flex-grow">
                    <button type="submit" name="add_name"
                            class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg transition duration-150 ease-in-out">
                        添加名称
                    </button>
                </form>
                <div class="preset-list bg-gray-50">
                    <?php if (empty($names)): ?>
                        <p class="p-4 text-gray-600">目前没有预设名称。</p>
                    <?php else: ?>
                        <?php foreach ($names as $name_item): ?>
                            <div class="preset-item">
                                <span class="text-gray-800"><?php echo htmlspecialchars($name_item['name']); ?></span>
                                <a href="global_settings.php?action=delete&type=name&id=<?php echo $name_item['id']; ?>"
                                   onclick="return confirm('确定要删除名称 <?php echo htmlspecialchars($name_item['name']); ?> 吗？');"
                                   class="bg-red-500 hover:bg-red-700 text-white text-sm font-bold py-1 px-3 rounded-lg transition duration-150 ease-in-out">
                                    删除
                                </a>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- 规格管理部分 -->
            <div class="bg-white p-6 rounded-lg shadow-lg">
                <h3 class="text-2xl font-bold text-gray-700 mb-4">管理规格</h3>
                <form action="global_settings.php" method="post" class="flex items-center space-x-2 mb-4">
                    <input type="text" name="spec_name" placeholder="新规格名称" required
                           class="shadow appearance-none border rounded-lg py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent flex-grow">
                    <button type="submit" name="add_specification"
                            class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg transition duration-150 ease-in-out">
                        添加规格
                    </button>
                </form>
                <div class="preset-list bg-gray-50">
                    <?php if (empty($specifications)): ?>
                        <p class="p-4 text-gray-600">目前没有预设规格。</p>
                    <?php else: ?>
                        <?php foreach ($specifications as $spec_item): ?>
                            <div class="preset-item">
                                <span class="text-gray-800"><?php echo htmlspecialchars($spec_item['name']); ?></span>
                                <a href="global_settings.php?action=delete&type=specification&id=<?php echo $spec_item['id']; ?>"
                                   onclick="return confirm('确定要删除规格 <?php echo htmlspecialchars($spec_item['name']); ?> 吗？');"
                                   class="bg-red-500 hover:bg-red-700 text-white text-sm font-bold py-1 px-3 rounded-lg transition duration-150 ease-in-out">
                                    删除
                                </a>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html>