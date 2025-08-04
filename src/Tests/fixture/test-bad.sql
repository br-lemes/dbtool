-- Create table: users
CREATE TABLE users (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(255) NOT NULL,
    password_hash TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT NULL ON UPDATE current_timestamp(),
    UNIQUE KEY email (email),
);

-- Create table: posts
CREATE TABLE posts (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT NOT NULL,
    title VARCHAR(200) NOT NULL,
    content TEXT,
    publish_date DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY user_id (user_id),
);

-- Insert data into users
INSERT INTO users (name, email, password_hash, created_at, updated_at) VALUES
('John Doe', 'john.doe@example.com', '$2y$10$abc123hashedPassword', '2025-07-24 09:00:00', NULL),
('Jane Smith', 'jane.smith@example.com', '$2y$10$xyz789hashedPassword', '2025-07-24 09:15:00', '2025-07-24 10:00:00'),
('Alice Brown', 'alice.brown@example.com', '$2y$10$def456hashedPassword', '2025-07-24 09:30:00', NULL);

-- Insert data into posts
INSERT INTO posts (user_id, title, content, publish_date, created_at) VALUES
(1, 'My First Post', 'This is the content of my first post.', '2025-07-24', '2025-07-24 09:10:00'),
(1, 'Another Post', 'More content here.', NULL, '2025-07-24 09:20:00'),
(2, 'Jane''s Blog', 'Jane shares her thoughts.', '2025-07-25', '2025-07-24 09:25:00');
