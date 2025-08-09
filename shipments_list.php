<?php
// shipments_list.php - 发货列表页面

session_start(); // 启动会话

// 检查用户是否已登录，否则重定向到登录页面
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: login.php");
    exit;
}

require_once 'db_connect.php'; // 引入数据库连接文件

$shipments = [];
// 查询发货记录，并联接 inventory 表以获取物品名称、品牌、规格、国家，并包含新的收件人信息
$sql = "SELECT s.id, i.name AS item_name, i.brand, i.specifications, i.country, 
               s.shipping_number, s.quantity_shipped, s.shipping_date,
               s.recipient_name, s.recipient_phone, s.recipient_address, s.remarks, s.created_at
        FROM shipments s
        JOIN inventory i ON s.inventory_item_id = i.id
        ORDER BY s.created_at DESC";
$result = $conn->query($sql);

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $shipments[] = $row;
    }
}
$conn->close();
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>发货列表</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f0f2f5;
        }
        .container {
            max-width: 1800px;
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
            white-space: nowrap; /* 防止文本换行 */
        }
        th {
            background-color: #f8fafc;
            font-weight: 600;
            color: #4a5568;
            text-align: center; /* 居中显示 */
        }
        td { 
             text-align: center; /* 也可以让数据居中，如果需要的话 */
        }
        tr:hover {
            background-color: #f0f4f8;
        }
        /* 使表格在小屏幕上可水平滚动 */
        .overflow-x-auto {
            overflow-x: auto;
        }

        /* Modal styles */
        .modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.6); /* 半透明背景 */
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 1000;
            transition: opacity 0.3s ease-in-out;
            opacity: 0;
            visibility: hidden;
        }
        .modal.show {
            opacity: 1;
            visibility: visible;
        }
        .modal-content {
            background-color: #ffffff;
            padding: 2.5rem;
            border-radius: 1.5rem; /* 大圆角 */
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.25); /* 更明显的阴影 */
            max-width: 600px;
            width: 90%;
            position: relative;
            transform: translateY(20px);
            transition: transform 0.3s ease-in-out;
        }
        .modal.show .modal-content {
            transform: translateY(0);
        }
        .close-button {
            position: absolute;
            top: 1rem;
            right: 1.2rem;
            font-size: 2rem;
            cursor: pointer;
            color: #7f8c8d;
            transition: color 0.2s;
        }
        .close-button:hover {
            color: #34495e;
        }
        .tracking-timeline {
            border-left: 3px solid #007bff; /* 更粗的蓝色时间线 */
            padding-left: 1.5rem; /* 更多左边距 */
            margin-left: 1.5rem; /* 更多左外边距 */
            position: relative;
        }
        .timeline-item {
            position: relative;
            padding-bottom: 2rem; /* 增加行间距 */
        }
        .timeline-item:last-child {
            padding-bottom: 0;
        }
        .timeline-dot {
            position: absolute;
            left: -2.3rem; /* 调整点的位置 */
            top: 0;
            width: 1.2rem; /* 增大点的大小 */
            height: 1.2rem;
            background-color: #007bff; /* 蓝色 */
            border-radius: 50%;
            border: 3px solid #ffffff; /* 白色边框 */
            box-shadow: 0 0 0 4px rgba(0, 123, 255, 0.3); /* 点的光晕效果 */
            transition: all 0.2s ease-in-out;
        }
        .timeline-item:hover .timeline-dot {
            background-color: #0056b3; /* 悬停时颜色变深 */
            transform: scale(1.1); /* 悬停时放大 */
            box-shadow: 0 0 0 6px rgba(0, 123, 255, 0.5);
        }
        .timeline-item p {
            margin-bottom: 0.25rem;
        }
        .timeline-item .text-sm {
            color: #7f8c8d; /* 时间的颜色 */
            font-size: 0.9rem;
        }
        .timeline-item .text-base {
            color: #34495e; /* 内容的颜色 */
            font-weight: 500;
        }
        .loading-spinner {
            border: 4px solid #f3f3f3;
            border-top: 4px solid #3498db;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            margin: 20px auto;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
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
                <a href="add_shipment.php" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg transition duration-150 ease-in-out">
                    添加发货
                </a>
                <a href="logout.php" class="bg-red-500 hover:bg-red-700 text-white font-bold py-2 px-4 rounded-lg transition duration-150 ease-in-out">
                    注销
                </a>
            </div>
        </div>
    </nav>

    <div class="container mt-8">
        <h2 class="text-3xl font-bold text-gray-800 mb-6">发货列表</h2>

        <?php if (empty($shipments)): ?>
            <div class="bg-yellow-100 border border-yellow-400 text-yellow-700 px-4 py-3 rounded relative" role="alert">
                <span class="block sm:inline">目前没有发货记录。</span>
            </div>
        <?php else: ?>
            <div class="bg-white shadow-lg rounded-lg overflow-hidden">
                <div class="overflow-x-auto"> <!-- Added for horizontal scrolling on small screens -->
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">物品名称</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">品牌</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">规格</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">国家</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">发货单号</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">发货数量</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">发货日期</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">收件人名称</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">收件人电话</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">收件人地址</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">备注</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">记录时间</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">操作</th>
                                <!-- ✨ 新增：快递查询列 -->
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">快递查询</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($shipments as $shipment): ?>
                                <tr>
                                    <td class="px-6 py-4 text-sm text-gray-900"><?php echo htmlspecialchars($shipment['item_name'] ?? ''); ?></td>
                                    <td class="px-6 py-4 text-sm text-gray-900"><?php echo htmlspecialchars($shipment['brand'] ?? ''); ?></td>
                                    <td class="px-6 py-4 text-sm text-gray-900"><?php echo htmlspecialchars($shipment['specifications'] ?? ''); ?></td>
                                    <td class="px-6 py-4 text-sm text-gray-900"><?php echo htmlspecialchars($shipment['country'] ?? ''); ?></td>
                                    <td class="px-6 py-4 text-sm text-gray-900"><?php echo htmlspecialchars($shipment['shipping_number'] ?? ''); ?></td>
                                    <td class="px-6 py-4 text-sm text-gray-900"><?php echo htmlspecialchars($shipment['quantity_shipped'] ?? ''); ?></td>
                                    <td class="px-6 py-4 text-sm text-gray-900"><?php echo htmlspecialchars($shipment['shipping_date'] ?? ''); ?></td>
                                    <td class="px-6 py-4 text-sm text-gray-900"><?php echo htmlspecialchars($shipment['recipient_name'] ?? ''); ?></td>
                                    <td class="px-6 py-4 text-sm text-gray-900"><?php echo htmlspecialchars($shipment['recipient_phone'] ?? ''); ?></td>
                                    <td class="px-6 py-4 text-sm text-gray-900"><?php echo htmlspecialchars($shipment['recipient_address'] ?? ''); ?></td>
                                    <td class="px-6 py-4 text-sm text-gray-900"><?php echo htmlspecialchars($shipment['remarks'] ?? ''); ?></td>
                                    <td class="px-6 py-4 text-sm text-gray-900"><?php echo htmlspecialchars($shipment['created_at'] ?? ''); ?></td>
                                    <td class="px-6 py-4 text-sm font-medium">
                                        <a href="edit_shipment.php?id=<?php echo htmlspecialchars($shipment['id']); ?>" 
                                           class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-1 px-3 rounded-lg transition duration-150 ease-in-out">
                                            编辑
                                        </a>
                                    </td>
                                    <!-- ✨ 新增：快递查询按钮 -->
                                    <td class="px-6 py-4 text-sm font-medium">
                                        <button class="query-express-button bg-blue-500 hover:bg-blue-700 text-white font-bold py-1 px-3 rounded-lg transition duration-150 ease-in-out"
                                                data-shipping-number="<?php echo htmlspecialchars($shipment['shipping_number'] ?? ''); ?>">
                                            快递查询
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

    <!-- 快递查询模态框 -->
    <div id="expressQueryModal" class="modal">
        <div class="modal-content">
            <span class="close-button" id="closeExpressModalButton">&times;</span>
            <h3 class="text-2xl font-bold text-gray-800 mb-4 text-center">📦 快递详情 🚚</h3>
            <div id="modalLoading" class="loading-spinner hidden"></div>
            <div id="modalContentDisplay">
                <div class="mb-4">
                    <p class="text-gray-700"><span class="font-semibold">快递单号:</span> <span id="modalTrackingNumber"></span></p>
                    <p class="text-gray-700"><span class="font-semibold">快递公司:</span> <span id="modalCompany"></span></p>
                    <p class="text-gray-700"><span class="font-semibold">当前状态:</span> <span id="modalStatus"></span></p>
                </div>
                <h4 class="text-xl font-bold text-gray-800 mb-3">📍 物流详情</h4>
                <div id="modalTimeline" class="tracking-timeline">
                    <!-- 物流详情将在此处加载 -->
                </div>
                <div id="modalErrorMessage" class="text-red-600 text-center mt-4 hidden"></div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const expressQueryModal = document.getElementById('expressQueryModal');
            const closeExpressModalButton = document.getElementById('closeExpressModalButton');
            const modalLoading = document.getElementById('modalLoading');
            const modalContentDisplay = document.getElementById('modalContentDisplay');
            const modalTrackingNumber = document.getElementById('modalTrackingNumber');
            const modalCompany = document.getElementById('modalCompany');
            const modalStatus = document.getElementById('modalStatus');
            const modalTimeline = document.getElementById('modalTimeline');
            const modalErrorMessage = document.getElementById('modalErrorMessage');

            // 显示模态框
            function showModal() {
                expressQueryModal.classList.add('show');
            }

            // 隐藏模态框
            function hideModal() {
                expressQueryModal.classList.remove('show');
                // 清理模态框内容
                modalTrackingNumber.textContent = '';
                modalCompany.textContent = '';
                modalStatus.textContent = '';
                modalTimeline.innerHTML = '';
                modalErrorMessage.textContent = '';
                modalErrorMessage.classList.add('hidden');
                modalContentDisplay.classList.remove('hidden'); // Ensure content is visible next time
                modalLoading.classList.add('hidden'); // Hide loading spinner
            }

            // 关闭按钮事件
            closeExpressModalButton.addEventListener('click', hideModal);

            // 点击模态框外部区域关闭
            expressQueryModal.addEventListener('click', function(e) {
                if (e.target === expressQueryModal) {
                    hideModal();
                }
            });

            // 快递查询按钮点击事件
            document.querySelectorAll('.query-express-button').forEach(button => {
                button.addEventListener('click', function() {
                    const shippingNumber = this.dataset.shippingNumber;
                    showModal();
                    modalLoading.classList.remove('hidden'); // Show loading spinner
                    modalContentDisplay.classList.add('hidden'); // Hide content while loading
                    modalErrorMessage.classList.add('hidden'); // Hide error message initially

                    fetch('api/express_query_api.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: 'tracking_number=' + encodeURIComponent(shippingNumber)
                    })
                    .then(response => response.json())
                    .then(data => {
                        modalLoading.classList.add('hidden'); // Hide loading spinner
                        modalContentDisplay.classList.remove('hidden'); // Show content

                        if (data.success) {
                            const result = data.result || {};
                            modalTrackingNumber.textContent = result.nu || shippingNumber;
                            modalCompany.textContent = result.company || '未知';
                            modalStatus.textContent = result.status || '未知';

                            modalTimeline.innerHTML = ''; // 清空旧的详情
                            if (result.data && result.data.length > 0) {
                                result.data.forEach(item => {
                                    const timelineItem = document.createElement('div');
                                    timelineItem.classList.add('timeline-item');
                                    timelineItem.innerHTML = `
                                        <div class="timeline-dot"></div>
                                        <p class="text-gray-600 text-sm mb-1">${htmlspecialchars(item.time || '')}</p>
                                        <p class="text-gray-800 text-base">${htmlspecialchars(item.context || '')}</p>
                                    `;
                                    modalTimeline.appendChild(timelineItem);
                                });
                            } else {
                                modalTimeline.innerHTML = '<p class="text-gray-600">暂无物流详情。</p>';
                            }
                        } else {
                            modalErrorMessage.textContent = data.message || '查询失败，请稍后重试。';
                            modalErrorMessage.classList.remove('hidden');
                            modalContentDisplay.classList.add('hidden'); // Hide normal content on error
                            // For debugging, if raw_response is provided:
                            // if (data.raw_response) {
                            //     console.error("Raw API response:", data.raw_response);
                            // }
                        }
                    })
                    .catch(error => {
                        modalLoading.classList.add('hidden'); // Hide loading spinner
                        modalErrorMessage.textContent = '网络请求失败，请检查网络连接。';
                        modalErrorMessage.classList.remove('hidden');
                        modalContentDisplay.classList.add('hidden'); // Hide normal content on error
                        console.error('Fetch error:', error);
                    });
                });
            });

            // Helper function for HTML escaping (basic)
            function htmlspecialchars(str) {
                if (typeof str !== 'string') return str;
                return str.replace(/&/g, '&amp;')
                          .replace(/</g, '&lt;')
                          .replace(/>/g, '&gt;')
                          .replace(/"/g, '&quot;')
                          .replace(/'/g, '&#039;');
            }
        });
    </script>
</body>
</html>