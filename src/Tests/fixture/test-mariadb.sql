CREATE USER IF NOT EXISTS 'fail'@'%' IDENTIFIED BY 'fail';
REVOKE ALL PRIVILEGES, GRANT OPTION FROM 'fail'@'%';
GRANT CREATE ON test_db.* TO 'fail'@'%';

-- Create table: users
CREATE TABLE users (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100),
    email VARCHAR(255) NOT NULL,
    password_hash TEXT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT NULL ON UPDATE current_timestamp(),
    UNIQUE KEY email (email)
);

GRANT SELECT ON test_db.users TO 'fail'@'%';
FLUSH PRIVILEGES;

-- Create table: products
CREATE TABLE products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    description_long LONGTEXT,
    description_medium MEDIUMTEXT,
    description_tiny TINYTEXT,
    ean VARCHAR(100) NOT NULL,
    name VARCHAR(255) NOT NULL,
    price DECIMAL(10, 2) DEFAULT 0.00,
    sku VARCHAR(100) NOT NULL,
    status VARCHAR(50) DEFAULT 'active',
    stock INT DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    refresh_at TIMESTAMP DEFAULT NULL ON UPDATE current_timestamp(),
    UNIQUE KEY ean_sku (ean, sku)
);

-- Create table: user_groups
CREATE TABLE user_groups (
    id INT NOT NULL,
    user_id BIGINT NOT NULL,
    updated_at TIMESTAMP DEFAULT NULL ON UPDATE current_timestamp(),
    key_id INT,
    PRIMARY KEY (id, user_id),
    KEY key_id (key_id)
);

-- Create table: tags
CREATE TABLE tags (
    id INT,
    description TEXT,
    name VARCHAR(100)
);

-- Create table: post_tags
CREATE TABLE post_tags (
    post_id BIGINT NOT NULL,
    tag_id INT NOT NULL,
    refresh_at TIMESTAMP DEFAULT NULL ON UPDATE current_timestamp(),
    UNIQUE KEY post_tag (post_id, tag_id)
);

-- Insert data into users
INSERT INTO users (name, email, password_hash, created_at, updated_at) VALUES
('John Doe', 'john.doe@example.com', '$2y$10$abc123hashedPassword', '2025-07-24 09:00:00', NULL),
('Jane Smith', 'jane.smith@example.com', '$2y$10$xyz789hashedPassword', '2025-07-24 09:15:00', '2025-07-24 10:00:00'),
('Alice Brown', 'alice.brown@example.com', '$2y$10$def456hashedPassword', '2025-07-24 09:30:00', NULL);

-- Insert data into products
INSERT INTO products (description_long, description_medium, description_tiny, ean, name, price, sku, status, stock, created_at, refresh_at) VALUES
('Long Desc A', 'Medium Desc A', 'Tiny Desc A', 'EAN-A-001', 'Product A', 19.99, 'SKU-A-001', 'active', 100, NOW(), NOW()),
('Long Desc B', 'Medium Desc B', 'Tiny Desc B', 'EAN-B-002', 'Product B', 29.99, 'SKU-B-002', 'active', 250, NOW(), NOW()),
('Long Desc C', 'Medium Desc C', 'Tiny Desc C', 'EAN-C-003', 'Product C', 9.99, 'SKU-C-003', 'active', 50, NOW(), NOW());

-- Insert data into user_groups
INSERT INTO user_groups (id, user_id) VALUES (3, 2), (1, 1), (2, 1), (1, 2);

-- Insert data into tags
INSERT INTO tags (id, name, description) VALUES
(1, 'Electronics', 'Devices and gadgets'),
(2, 'Books', 'Printed and digital books'),
(3, 'Clothing', 'Apparel and accessories');

-- Insert data into post_tags
INSERT INTO post_tags (post_id, tag_id) VALUES (3, 2), (1, 1), (2, 1), (1, 2);
