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

-- Insert data into users
INSERT INTO users (name, email, password_hash, created_at, updated_at) VALUES
('John Doe', 'john.doe@example.com', '$2y$10$abc123hashedPassword', '2025-07-24 09:00:00', NULL),
('Jane Smith', 'jane.smith@example.com', '$2y$10$xyz789hashedPassword', '2025-07-24 09:15:00', '2025-07-24 10:00:00'),
('Alice Brown', 'alice.brown@example.com', '$2y$10$def456hashedPassword', '2025-07-24 09:30:00', NULL);
