-- Create table: users
CREATE TABLE users (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100),
    email VARCHAR(255) NOT NULL,
    password_hash TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT NULL ON UPDATE current_timestamp(),
    UNIQUE KEY email (email)
);

-- Create table: products
CREATE TABLE products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description_tiny TINYTEXT,
    description_medium MEDIUMTEXT,
    description_long LONGTEXT,
    sku VARCHAR(100) NOT NULL,
    ean VARCHAR(100) NOT NULL,
    stock INT DEFAULT 0,
    price DECIMAL(10, 2) DEFAULT 0.00,
    status VARCHAR(50) DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    refresh_at TIMESTAMP DEFAULT NULL ON UPDATE current_timestamp(),
    UNIQUE KEY `sku_ean` (`sku`, `ean`)
);

-- Insert data into users
INSERT INTO users (name, email, password_hash, created_at, updated_at) VALUES
('John Doe', 'john.doe@example.com', '$2y$10$abc123hashedPassword', '2025-07-24 09:00:00', NULL),
('Jane Smith', 'jane.smith@example.com', '$2y$10$xyz789hashedPassword', '2025-07-24 09:15:00', '2025-07-24 10:00:00'),
('Alice Brown', 'alice.brown@example.com', '$2y$10$def456hashedPassword', '2025-07-24 09:30:00', NULL);

-- Insert data into products
INSERT INTO products (name, description_tiny, description_medium, description_long, sku, ean, stock, price, refresh_at) VALUES
('Product A', 'Tiny Desc A', 'Medium Desc A', 'Long Desc A', 'SKU-A-001', 'EAN-A-001', 100, 19.99, NOW()),
('Product B', 'Tiny Desc B', 'Medium Desc B', 'Long Desc B', 'SKU-B-002', 'EAN-B-002', 250, 29.99, NOW()),
('Product C', 'Tiny Desc C', 'Medium Desc C', 'Long Desc C', 'SKU-C-003', 'EAN-C-003', 50, 9.99, NOW());
