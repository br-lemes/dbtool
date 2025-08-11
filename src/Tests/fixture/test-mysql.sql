-- Create table: users
CREATE TABLE users (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL,
    name VARCHAR(100) NOT NULL,
    password_hash TEXT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT NULL ON UPDATE current_timestamp(),
    UNIQUE KEY email (email)
);

-- Create table: posts
CREATE TABLE posts (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT NOT NULL,
    content TEXT,
    publish_date DATE,
    title VARCHAR(200) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY user_id (user_id)
);

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
    UNIQUE KEY `ean_sku` (`ean`, `sku`)
);

-- Create table: phinxlog
CREATE TABLE phinxlog (
    version BIGINT NOT NULL PRIMARY KEY,
    migration_name VARCHAR(100) DEFAULT NULL,
    start_time TIMESTAMP NULL DEFAULT NULL,
    end_time TIMESTAMP NULL DEFAULT NULL,
    breakpoint TINYINT NOT NULL DEFAULT 0
);

-- Insert data into users
INSERT INTO users (email, name, password_hash, created_at, updated_at) VALUES
('john.doe@example.com', 'John Doe', '$2y$10$abc123hashedPassword', '2025-07-24 09:00:00', NULL),
('jane.smith@example.com', 'Jane Smith', '$2y$10$xyz789hashedPassword', '2025-07-24 09:15:00', '2025-07-24 10:00:00'),
('alice.brown@example.com', 'Alice Brown', '$2y$10$def456hashedPassword', '2025-07-24 09:30:00', NULL);

-- Insert data into posts
INSERT INTO posts (user_id, content, publish_date, title, created_at) VALUES
(1, 'This is the content of my first post.', '2025-07-24', 'My First Post', '2025-07-24 09:10:00'),
(1, 'More content here.', NULL, 'Another Post', '2025-07-24 09:20:00'),
(2, 'Jane shares her thoughts.', '2025-07-25', 'Jane''s Blog', '2025-07-24 09:25:00');

-- Insert data into products
INSERT INTO products (description_long, description_medium, description_tiny, ean, name, price, sku, status, stock, created_at, refresh_at) VALUES
('Long Desc A', 'Medium Desc A', 'Tiny Desc A', 'EAN-A-001', 'Product A', 19.99, 'SKU-A-001', 'active', 100, NOW(), NOW()),
('Long Desc B', 'Medium Desc B', 'Tiny Desc B', 'EAN-B-002', 'Product B', 29.99, 'SKU-B-002', 'active', 250, NOW(), NOW()),
('Long Desc C', 'Medium Desc C', 'Tiny Desc C', 'EAN-C-003', 'Product C', 9.99, 'SKU-C-003', 'active', 50, NOW(), NOW());

-- Insert data into phinxlog
INSERT INTO phinxlog (version, migration_name, start_time, end_time, breakpoint) VALUES
(20250807015230, 'Users', '2025-08-11 02:33:47', '2025-08-11 02:33:47', 0),
(20250807015231, 'Posts', '2025-08-11 02:33:47', '2025-08-11 02:33:47', 0),
(20250807015232, 'Products', '2025-08-11 02:33:47', '2025-08-11 02:33:47', 0);
