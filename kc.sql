-- 创建数据库 (如果尚未创建)
-- CREATE DATABASE IF NOT EXISTS inventory_db;
-- USE inventory_db;

-- 用户表
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL -- 存储哈希后的密码
    -- ✨ 移除 current_session_id 字段
    -- current_session_id VARCHAR(255) NULL
);

-- 如果 users 表已存在且有 current_session_id 字段，请运行以下语句移除它：
ALTER TABLE users DROP COLUMN IF EXISTS current_session_id;

-- ✨ 新增 user_sessions 表，用于管理多设备登录会话
CREATE TABLE IF NOT EXISTS user_sessions (
    session_token VARCHAR(255) PRIMARY KEY, -- 存储会话令牌，作为主键
    user_id INT NOT NULL,                  -- 关联到 users 表的用户ID
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, -- 会话创建时间
    expires_at TIMESTAMP NULL,             -- 会话过期时间 (可选，可用于自动清理过期会话)
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE -- 用户删除时，关联会话也删除
);


-- 库存物品表 (保持不变)
CREATE TABLE IF NOT EXISTS inventory (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    country VARCHAR(100),
    production_date DATE,
    expiration_date DATE,
    remarks TEXT,
    specifications VARCHAR(255),
    quantity INT DEFAULT 0,
    brand VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 插入一个示例用户 (密码是 'password123' 的哈希值)
-- !!! 重要：如果你之前已经插入过 'admin' 用户，请先运行下面的 DELETE 语句删除它，
-- !!! 或者运行 UPDATE 语句更新它的密码。
-- !!! 否则，你可能会有多个 'admin' 用户或旧的密码哈希值。
DELETE FROM users WHERE username = 'admin';
INSERT INTO users (username, password) VALUES ('admin', '$2y$10$i/D3zmHflxWrDLjUEqvopewnZA0A1Q9igW6VJKqmpzGxwaUqMtjmm'); -- 'password123' 的新哈希值 (来自您的系统生成)
-- 或者，如果你想更新现有用户的密码（推荐，如果 'admin' 已存在）：
-- UPDATE users SET password = '$2y$10$i/D3zmHflxWrDLjUEqvopewnZA0A1Q9igW6VJKqmpzGxwaUqMtjmm' WHERE username = 'admin';


-- 新增发货记录表 (确保在 ALTER TABLE 之前创建)
CREATE TABLE IF NOT EXISTS shipments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    inventory_item_id INT NOT NULL, -- 关联到 inventory 表的物品ID
    shipping_number VARCHAR(100) NOT NULL, -- 发货单号
    quantity_shipped INT NOT NULL, -- 发货数量
    shipping_date DATE NOT NULL, -- 发货日期
    recipient_name VARCHAR(255),    -- 收件人名称
    recipient_phone VARCHAR(50),    -- 收件人电话
    recipient_address TEXT,         -- 收件人地址
    remarks TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (inventory_item_id) REFERENCES inventory(id) ON DELETE RESTRICT -- 限制删除，除非先删除发货记录
);

-- 新增预设国家表 (保持不变)
CREATE TABLE IF NOT EXISTS preset_countries (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE
);

-- 新增预设品牌表 (保持不变)
CREATE TABLE IF NOT EXISTS preset_brands (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL UNIQUE
);

-- 新增预设名称表
CREATE TABLE IF NOT EXISTS preset_names (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL UNIQUE
);

-- 新增预设规格表
CREATE TABLE IF NOT EXISTS preset_specifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL UNIQUE
);


-- 新增系统设置表，用于存储 SSO 启用状态 (保持不变)
CREATE TABLE IF NOT EXISTS system_settings (
    setting_name VARCHAR(50) PRIMARY KEY,
    setting_value VARCHAR(255) NOT NULL
);

-- 插入 SSO 启用状态的默认值 (默认启用)
INSERT IGNORE INTO system_settings (setting_name, setting_value) VALUES
('sso_enabled', 'true');

-- 插入初始预设国家数据 (如果表为空)
INSERT IGNORE INTO preset_countries (name) VALUES
('中国'), ('美国'), ('日本'), ('德国'), ('法国'),
('英国'), ('加拿大'), ('澳大利亚'), ('印度'), ('韩国'),
('巴西'), ('俄罗斯'), ('意大利'), ('西班牙'), ('墨西哥');

-- 插入初始预设品牌数据 (如果表为空)
INSERT IGNORE INTO preset_brands (name) VALUES
('Apple'), ('Samsung'), ('Huawei'), ('Xiaomi'), ('Sony'),
('LG'), ('Philips'), ('Bosch'), ('Siemens'), ('Haier'),
('Dell'), ('HP'), ('Lenovo'), ('Microsoft'), ('Logitech');

-- 插入初始预设名称数据 (示例，可根据需要修改或删除)
INSERT IGNORE INTO preset_names (name) VALUES
('智能手机'), ('笔记本电脑'), ('耳机'), ('充电宝'), ('智能手表'),
('显示器'), ('键盘'), ('鼠标'), ('打印机'), ('路由器');

-- 插入初始预设规格数据 (示例，可根据需要修改或删除)
INSERT IGNORE INTO preset_specifications (name) VALUES
('128GB 存储'), ('256GB 存储'), ('512GB 存储'), ('1TB 存储'),
('8GB RAM'), ('16GB RAM'), ('32GB RAM'),
('Intel Core i5'), ('Intel Core i7'), ('AMD Ryzen 5'), ('AMD Ryzen 7'),
('13英寸'), ('15英寸'), ('17英寸'), ('24英寸'), ('27英寸');
