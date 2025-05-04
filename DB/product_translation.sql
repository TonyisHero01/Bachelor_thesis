CREATE TABLE product_translation (
    id SERIAL PRIMARY KEY,
    product_id INTEGER NOT NULL,
    locale VARCHAR(10) NOT NULL,
    name TEXT,
    description TEXT,
    material TEXT,
    attributes JSONB DEFAULT '{}'::jsonb,

    CONSTRAINT fk_translation_product FOREIGN KEY (product_id)
        REFERENCES product(id) ON DELETE CASCADE,
    
    CONSTRAINT unq_product_locale UNIQUE (product_id, locale)
);