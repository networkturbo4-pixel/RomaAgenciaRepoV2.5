CREATE TABLE IF NOT EXISTS post_comments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    post_id INT NOT NULL,
    comment_text TEXT,
    image_link VARCHAR(255),
    status VARCHAR(50) DEFAULT 'Pendiente',
    phase VARCHAR(100) DEFAULT 'Parrilla Final',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
