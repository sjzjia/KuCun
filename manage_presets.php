<?php
// manage_presets.php - 预选项管理页面

session_start(); // 启动会话

// 检查用户是否已登录，否则重定向到登录页面
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: login.php");
    exit;
}

require_once 'db_connect.php'; // 引入数据库连接文件

$message = '';
$error = '';

// --- 处理添加预设 ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['add_country']) && !empty($_POST['country_name'])) {
        $country_name = trim($_POST['country_name']);
        try {
            $stmt = $conn->prepare("INSERT INTO preset_countries (name) VALUES (?)");
            $stmt->bind_param("s", $country_name);
            if ($stmt->execute()) {
                $message = "国家 '" . htmlspecialchars($country_name) . "' 添加成功！";
            } else {
                // 这种情况通常在 mysqli_report(MYSQLI_REPORT_OFF) 时发生
                $error = "添加国家失败: " . $stmt->error;
            }
            $stmt->close();
        } catch (mysqli_sql_exception $e) {
            // 捕获 Duplicate entry 错误 (错误代码 1062)
            if ($e->getCode() == 1062) {
                $error = "添加国家失败: 国家 '" . htmlspecialchars($country_name) . "' 已存在。";
            } else {
                $error = "添加国家失败: " . $e->getMessage();
            }
        }
    } elseif (isset($_POST['add_brand']) && !empty($_POST['brand_name'])) {
        $brand_name = trim($_POST['brand_name']);
        try {
            $stmt = $conn->prepare("INSERT INTO preset_brands (name) VALUES (?)");
            $stmt->bind_param("s", $brand_name);
            if ($stmt->execute()) {
                $message = "品牌 '" . htmlspecialchars($brand_name) . "' 添加成功！";
            } else {
                // 这种情况通常在 mysqli_report(MYSQLI_REPORT_OFF) 时发生
                $error = "添加品牌失败: " . $stmt->error;
            }
            $stmt->close();
        } catch (mysqli_sql_exception $e) {
            // 捕获 Duplicate entry 错误 (错误代码 1062)
            if ($e->getCode() == 1062) {
                $error = "添加品牌失败: 品牌 '" . htmlspecialchars($brand_name) . "' 已存在。";
            } else {
                $error = "添加品牌失败: " . $e->getMessage();
            }
        }
    }
    // 清空输入框
    $_POST = array();
}

// --- 处理删除预设 ---
if (isset($_GET['action']) && $_GET['action'] == 'delete') {
    if (isset($_GET['type']) && $_GET['type'] == 'country' && isset($_GET['id'])) {
        $id = (int)$_GET['id'];
        try {
            $stmt = $conn->prepare("DELETE FROM preset_countries WHERE id = ?");
            $stmt->bind_param("i", $id);
            if ($stmt->execute()) {
                $message = "国家删除成功！";
            } else {
                $error = "删除国家失败: " . $stmt->error;
            }
            $stmt->close();
        } catch (mysqli_sql_exception $e) {
            // 捕获外键约束失败错误 (错误代码 1451)
            if ($e->getCode() == 1451) {
                $error = "删除国家失败: 该国家有库存物品关联，请先删除相关物品。";
            } else {
                $error = "删除国家失败: " . $e->getMessage();
            }
        }
    } elseif (isset($_GET['type']) && $_GET['type'] == 'brand' && isset($_GET['id'])) {
        $id = (int)$_GET['id'];
        try {
            $stmt = $conn->prepare("DELETE FROM preset_brands WHERE id = ?");
            $stmt->bind_param("i", $id);
            if ($stmt->execute()) {
                $message = "品牌删除成功！";
            } else {
                $error = "删除品牌失败: " . $stmt->error;
            }
            $stmt->close();
        } catch (mysqli_sql_exception $e) {
            // 捕获外键约束失败错误 (错误代码 1451)
            if ($e->getCode() == 1451) {
                $error = "删除品牌失败: 该品牌有库存物品关联，请先删除相关物品。";
            } else {
                $error = "删除品牌失败: " . $e->getMessage();
            }
        }
    }
    // 重定向以清除 GET 参数，防止重复删除
    header("Location: manage_presets.php");
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

$conn->close();
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>管理预设选项</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f0f2f5;
        }
        .container {
            max-width: 1000px;
            margin: 0 auto;
            padding: 2rem;
        }
        .preset-list {
            max-height: 300px;
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
        <h2 class="text-3xl font-bold text-gray-800 mb-6">管理预设选项</h2>

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

        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
            <!-- 国家管理部分 -->
            <div class="bg-white p-6 rounded-lg shadow-lg">
                <h3 class="text-2xl font-bold text-gray-700 mb-4">管理国家</h3>
                <form action="manage_presets.php" method="post" class="flex items-center space-x-2 mb-4">
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
                                <a href="manage_presets.php?action=delete&type=country&id=<?php echo $country['id']; ?>"
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
                <form action="manage_presets.php" method="post" class="flex items-center space-x-2 mb-4">
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
                                <a href="manage_presets.php?action=delete&type=brand&id=<?php echo $brand['id']; ?>"
                                   onclick="return confirm('确定要删除品牌 <?php echo htmlspecialchars($brand['name']); ?> 吗？');"
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
