CREATE TABLE cart (
    id SERIAL PRIMARY KEY,
    customer_id INTEGER NOT NULL,
    product_id INTEGER NOT NULL,
    quantity NUMERIC(10,1) NOT NULL DEFAULT 1 CHECK (quantity > 0),
    added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_cart_customer FOREIGN KEY (customer_id)
        REFERENCES customer(id) ON DELETE CASCADE,

    CONSTRAINT fk_cart_product FOREIGN KEY (product_id)
        REFERENCES product(id) ON DELETE CASCADE
);