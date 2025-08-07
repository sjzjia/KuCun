-- 创建数据库 (如果尚未创建)
-- CREATE DATABASE IF NOT EXISTS inventory_db;
-- USE inventory_db;

-- 用户表
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL -- 存储哈希后的密码
);

-- 库存物品表
CREATE TABLE IF NOT EXISTS inventory (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    country VARCHAR(100),
    production_date DATE,
    expiration_date DATE,
    remarks TEXT,
    specifications VARCHAR(255),
    quantity INT DEFAULT 0, -- 物品数量字段
    brand VARCHAR(255), -- 品牌字段
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

-- 如果 inventory 表已存在，但缺少 quantity 或 brand 字段，请运行以下语句添加：
-- ALTER TABLE inventory ADD COLUMN quantity INT DEFAULT 0;
-- ALTER TABLE inventory ADD COLUMN brand VARCHAR(255);

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

-- 如果 shipments 表已存在，但缺少收件人信息字段，请运行以下语句添加：
-- 注意：这些 ALTER TABLE 语句现在放在 CREATE TABLE 之后
ALTER TABLE shipments ADD COLUMN recipient_name VARCHAR(255);
ALTER TABLE shipments ADD COLUMN recipient_phone VARCHAR(50);
ALTER TABLE shipments ADD COLUMN recipient_address TEXT;
