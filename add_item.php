<?php
// add_item.php - 添加新库存物品页面

session_start(); // 启动会话

// 检查用户是否已登录，否则重定向到登录页面
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: login.php");
    exit;
}

require_once 'db_connect.php'; // 引入数据库连接文件

$message = '';
$error = '';

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


// 处理表单提交
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = $_POST['name'];
    $country = $_POST['country'];
    $production_date = $_POST['production_date'];
    $expiration_date = $_POST['expiration_date'];
    $remarks = $_POST['remarks'];
    $specifications = $_POST['specifications'];
    $quantity = $_POST['quantity']; // 获取新增的数量字段
    $brand = $_POST['brand']; // 获取新增的品牌字段

    // 验证数量是否为有效数字
    if (!is_numeric($quantity) || $quantity < 0) {
        $error = "数量必须是非负数字。";
    } else {
        // 准备 SQL 插入语句，包含 specifications, quantity 和 brand 字段
        $stmt = $conn->prepare("INSERT INTO inventory (name, country, production_date, expiration_date, remarks, specifications, quantity, brand) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        // 注意绑定参数的类型，新增了一个 s (string)
        $stmt->bind_param("ssssssis", $name, $country, $production_date, $expiration_date, $remarks, $specifications, $quantity, $brand);

        if ($stmt->execute()) {
            $message = "物品添加成功！";
            // 清空表单字段以便添加下一个
            $_POST = array();
        } else {
            $error = "添加物品失败: " . $stmt->error;
        }

        $stmt->close();
    }
}
$conn->close();
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>添加新物品</title>
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
                    返回库存列表
                </a>
                <a href="logout.php" class="bg-red-500 hover:bg-red-700 text-white font-bold py-2 px-4 rounded-lg transition duration-150 ease-in-out">
                    注销
                </a>
            </div>
        </div>
    </nav>

    <div class="container mt-8">
        <h2 class="text-3xl font-bold text-gray-800 mb-6">添加新库存物品</h2>

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
            <form action="add_item.php" method="post">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-4">
                    <div>
                        <label for="name" class="block text-gray-700 text-sm font-bold mb-2">名称:</label>
                        <input type="text" id="name" name="name" required
                               class="shadow appearance-none border rounded-lg w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                               value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>">
                    </div>
                    <div>
                        <label for="country" class="block text-gray-700 text-sm font-bold mb-2">国家:</label>
                        <select id="country" name="country"
                                class="shadow appearance-none border rounded-lg w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            <option value="">请选择国家</option>
                            <?php foreach ($countries as $c): ?>
                                <option value="<?php echo htmlspecialchars($c); ?>"
                                    <?php echo (isset($_POST['country']) && $_POST['country'] === $c) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($c); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-4">
                    <div>
                        <label for="production_date" class="block text-gray-700 text-sm font-bold mb-2">生产日期:</label>
                        <input type="date" id="production_date" name="production_date"
                               class="shadow appearance-none border rounded-lg w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                               value="<?php echo htmlspecialchars($_POST['production_date'] ?? ''); ?>">
                    </div>
                    <div>
                        <label for="expiration_date" class="block text-gray-700 text-sm font-bold mb-2">到期日期:</label>
                        <input type="date" id="expiration_date" name="expiration_date"
                               class="shadow appearance-none border rounded-lg w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                               value="<?php echo htmlspecialchars($_POST['expiration_date'] ?? ''); ?>">
                    </div>
                </div>

                <div class="mb-4">
                    <label for="specifications" class="block text-gray-700 text-sm font-bold mb-2">规格:</label>
                    <input type="text" id="specifications" name="specifications"
                           class="shadow appearance-none border rounded-lg w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                           value="<?php echo htmlspecialchars($_POST['specifications'] ?? ''); ?>">
                </div>

                <div class="mb-4">
                    <label for="brand" class="block text-gray-700 text-sm font-bold mb-2">品牌:</label>
                    <select id="brand" name="brand"
                            class="shadow appearance-none border rounded-lg w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        <option value="">请选择品牌</option>
                        <?php foreach ($brands as $b): ?>
                            <option value="<?php echo htmlspecialchars($b); ?>"
                                <?php echo (isset($_POST['brand']) && $_POST['brand'] === $b) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($b); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mb-4">
                    <label for="quantity" class="block text-gray-700 text-sm font-bold mb-2">数量:</label>
                    <input type="number" id="quantity" name="quantity" required min="0"
                           class="shadow appearance-none border rounded-lg w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                           value="<?php echo htmlspecialchars($_POST['quantity'] ?? ''); ?>">
                </div>

                <div class="mb-6">
                    <label for="remarks" class="block text-gray-700 text-sm font-bold mb-2">备注:</label>
                    <textarea id="remarks" name="remarks" rows="4"
                              class="shadow appearance-none border rounded-lg w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"><?php echo htmlspecialchars($_POST['remarks'] ?? ''); ?></textarea>
                </div>

                <div class="flex items-center justify-between">
                    <button type="submit"
                            class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg focus:outline-none focus:shadow-outline w-full transition duration-150 ease-in-out">
                        添加物品
                    </button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
