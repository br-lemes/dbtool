-- Schema: public
SET search_path TO public;

-- Create table: users
CREATE TABLE public.users (
    id BIGSERIAL PRIMARY KEY,
    email VARCHAR(255) NOT NULL UNIQUE,
    name VARCHAR(100) NOT NULL,
    password_hash TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP
);

-- Create table: posts
CREATE TABLE public.posts (
    id BIGSERIAL PRIMARY KEY,
    user_id BIGINT NOT NULL,
    content TEXT,
    publish_date DATE,
    title VARCHAR(200) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Create table: products
CREATE TABLE public.products (
    id SERIAL PRIMARY KEY,
    description_long TEXT,
    description_medium TEXT,
    description_tiny TEXT,
    ean VARCHAR(100) NOT NULL,
    name VARCHAR(255) NOT NULL,
    price DECIMAL(10, 2) DEFAULT 0.00,
    sku VARCHAR(100) NOT NULL,
    status VARCHAR(50) DEFAULT 'active',
    stock INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    refresh_at TIMESTAMP,
    UNIQUE (ean, sku)
);

CREATE INDEX posts_user_id ON public.posts (user_id);

-- Insert data into users
INSERT INTO public.users (email, name, password_hash, created_at, updated_at) VALUES
('john.doe@example.com', 'John Doe', '$2y$10$abc123hashedPassword', '2025-07-24 09:00:00', NULL),
('jane.smith@example.com', 'Jane Smith', '$2y$10$xyz789hashedPassword', '2025-07-24 09:15:00', '2025-07-24 10:00:00'),
('alice.brown@example.com', 'Alice Brown', '$2y$10$def456hashedPassword', '2025-07-24 09:30:00', NULL);

-- Insert data into posts
INSERT INTO public.posts (user_id, content, publish_date, title, created_at) VALUES
(1, 'This is the content of my first post.', '2025-07-24', 'My First Post', '2025-07-24 09:10:00'),
(1, 'More content here.', NULL, 'Another Post', '2025-07-24 09:20:00'),
(2, 'Jane shares her thoughts.', '2025-07-25', 'Jane''s Blog', '2025-07-24 09:25:00');
