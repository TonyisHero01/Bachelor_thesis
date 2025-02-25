CREATE TABLE orders (
    id SERIAL PRIMARY KEY,
    customer_id INT NOT NULL,
    total_price DECIMAL(10,2) NOT NULL,
    address TEXT NOT NULL,
    order_created_at TIMESTAMP DEFAULT NOW(),
    pickup_or_delivery_at TIMESTAMP,
    is_completed BOOLEAN DEFAULT FALSE,
    payment_status VARCHAR(50) DEFAULT 'PENDING',
    payment_method VARCHAR(50),
    delivery_status VARCHAR(50) DEFAULT 'PENDING',
    notes TEXT,
    discount DECIMAL(10,2) DEFAULT 0.00,
    CONSTRAINT fk_orders_customer FOREIGN KEY (customer_id) REFERENCES customer(id) ON DELETE CASCADE
);