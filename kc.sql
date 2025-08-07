-- 创建数据库 (如果尚未创建)
-- CREATE DATABASE IF NOT EXISTS inventory_db;
-- USE inventory_db;

-- 用户表
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL, -- 存储哈希后的密码
    current_session_id VARCHAR(255) NULL -- 新增的列，用于存储当前活跃的会话ID
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

-- 新增系统设置表，用于存储 SSO 启用状态
CREATE TABLE IF NOT EXISTS system_settings (
    setting_name VARCHAR(50) PRIMARY KEY,
    setting_value VARCHAR(255) NOT NULL
);

-- 插入 SSO 启用状态的默认值 (默认启用)
INSERT IGNORE INTO system_settings (setting_name, setting_value) VALUES
('sso_enabled', 'true');


DELETE FROM users WHERE username = 'admin';
INSERT INTO users (username, password) VALUES ('admin', '$2y$10$i/D3zmHflxWrDLjUEqvopewnZA0A1Q9igW6VJKqmpzGxwaUqMtjmm'); -- 'password123' 的新哈希值 (来自您的系统生成)