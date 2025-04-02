CREATE TABLE return_requests (
    id SERIAL PRIMARY KEY,
    user_email VARCHAR(255) NOT NULL,
    user_phone VARCHAR(50) NOT NULL,
    user_name VARCHAR(255) NOT NULL,
    order_id INT NOT NULL,
    product_skus TEXT NOT NULL,
    return_reason TEXT DEFAULT NULL,
    user_message TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status VARCHAR(20) DEFAULT 'PENDING',
    CONSTRAINT fk_return_requests_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
);

ALTER TABLE return_requests ADD COLUMN request_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP;