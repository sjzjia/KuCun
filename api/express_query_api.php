<?php
// api/express_query_api.php - 快递查询API接口

// 开启错误报告，方便调试。在生产环境请禁用或限制。
error_reporting(E_ALL);
ini_set('display_errors', 1); // 开发环境下显示错误
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/express_query_api_errors.log'); // 指定日志文件路径

header('Content-Type: application/json'); // 默认设置响应头为 JSON 格式

$response = [
    'success' => false,
    'message' => '未知错误',
    'result' => null
];

// !!! 请将这里替换为您的阿里云 AppCode !!!
// 获取您的 AppCode: 登录阿里云控制台 -> 云市场 -> 购买的服务 -> 管理 -> API网关
$appcode = "aec4d9b593a44a9086e5d4fa0843db24"; 
$is_placeholder_appcode = ($appcode === "你自己的AppCode" || empty($appcode) || $appcode === "YOUR_APP_CODE_HERE");

// For verbose cURL logging - 将详细的 cURL 调试信息输出到单独的日志文件
$curl_verbose_log_file = __DIR__ . '/logs/curl_debug.log';
// 尝试以追加模式打开文件，如果文件不存在则创建。
// 'a+' 模式在文件不存在时创建，在存在时打开并定位到文件末尾。
$fp = fopen($curl_verbose_log_file, 'a+'); 

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $tracking_number = $_POST['tracking_number'] ?? '';

    if (empty($tracking_number)) {
        $response['message'] = "请输入快递单号。";
        echo json_encode($response);
        exit;
    } elseif ($is_placeholder_appcode) {
        $response['message'] = "请在 api/express_query_api.php 中配置您的阿里云 AppCode，当前 AppCode 为占位符或为空。";
        echo json_encode($response);
        exit; 
    } else {
        // --- API 请求参数 ---
        $host = "https://sfpush.market.alicloudapi.com";
        $path = "/expresspush";
        $method = "GET";
        
        // 构建查询参数
        $querys = "no=" . urlencode($tracking_number); 
        $querys .= "&url=url"; // API 要求此参数

        $url = $host . $path . "?" . $querys;

        $headers = array();
        array_push($headers, "Authorization:APPCODE " . $appcode);

        // --- cURL 请求 ---
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($curl, CURLOPT_FAILONERROR, false);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HEADER, false); // 不返回响应头，只返回内容
        curl_setopt($curl, CURLOPT_TIMEOUT, 10); // 设置超时时间为 10 秒
        
        // 启用 cURL 详细日志输出到文件
        curl_setopt($curl, CURLOPT_VERBOSE, true); // 开启详细模式
        curl_setopt($curl, CURLOPT_STDERR, $fp);   // 将详细信息输出到指定的文件句柄

        // 禁用 SSL 证书验证，方便测试，但不安全！
        if (strpos($host, "https://") === 0) {
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        }

        $response_body = curl_exec($curl);
        $curl_error = curl_error($curl);
        $http_status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        fclose($fp); // 关闭 cURL 调试日志文件文件句柄

        if ($response_body === false) {
            $response['message'] = "cURL 请求失败: " . $curl_error;
            error_log("express_query_api.php: cURL 请求失败。错误: " . $curl_error);
        } else {
            // ✨ 优化：先检查响应体是否为空
            if (empty($response_body)) {
                $response['message'] = "未找到该快递单号的物流信息，或API返回空数据。";
                $response['raw_response'] = $response_body; // Still include empty raw response for full context
                error_log("express_query_api.php: API 返回空响应体。单号: " . $tracking_number);
            } else {
                // 尝试解析 JSON 响应体
                $json_data = json_decode($response_body, true);

                if ($json_data === null && json_last_error() !== JSON_ERROR_NONE) {
                    $response['message'] = "API响应格式错误或非JSON格式。 (JSON错误: " . json_last_error_msg() . ")";
                    $response['raw_response'] = $response_body; // For debugging, include raw response
                    error_log("express_query_api.php: JSON 解析失败。原始响应体:\n" . $response_body . "\nJSON错误: " . json_last_error_msg());
                } else {
                    if ($json_data['success'] ?? false) {
                        $response['success'] = true;
                        $response['message'] = '查询成功';
                        $response['result'] = $json_data['result'] ?? ['nu' => $tracking_number, 'company' => '无', 'status' => '无', 'data' => []];
                        // 确保 nu 字段为查询的单号，以防API返回不一致
                    } else {
                        $api_msg = $json_data['msg'] ?? '未知错误';
                        $response['message'] = "查询失败: " . $api_msg;
                    }
                }
            }
        }
    }
} else {
    $response['message'] = '无效的请求方法。';
}

echo json_encode($response);
?>