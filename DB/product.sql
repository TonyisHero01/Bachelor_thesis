CREATE TABLE product (
    id SERIAL PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    number_in_stock INTEGER NOT NULL,
    image_urls JSON,
    add_time VARCHAR(255) NOT NULL,
    width DOUBLE PRECISION,
    height DOUBLE PRECISION,
    length DOUBLE PRECISION,
    weight DOUBLE PRECISION,
    material VARCHAR(255),
    price DOUBLE PRECISION,
    hidden BOOLEAN NOT NULL,
    discount DOUBLE PRECISION NOT NULL DEFAULT 100,
    attributes JSONB DEFAULT '{}'::jsonb,
    version INTEGER NOT NULL DEFAULT 1,
    sku VARCHAR(255) NOT NULL DEFAULT 'UNKNOWN',
    currency_id INTEGER NOT NULL,
    tax_rate DOUBLE PRECISION NOT NULL DEFAULT 21,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    category_id INTEGER,
    color_id INTEGER,
    size_id INTEGER,

    CONSTRAINT fk_product_currency FOREIGN KEY (currency_id)
        REFERENCES currency(id) ON UPDATE CASCADE ON DELETE RESTRICT,

    CONSTRAINT fk_product_size FOREIGN KEY (size_id)
        REFERENCES size(id) ON DELETE SET NULL,

    CONSTRAINT fk_product_category FOREIGN KEY (category_id)
        REFERENCES category(id) ON DELETE SET NULL,

    CONSTRAINT fk_product_color FOREIGN KEY (color_id)
        REFERENCES productcolor(id) ON DELETE SET NULL
);

CREATE INDEX idx_product_sku ON product(sku);