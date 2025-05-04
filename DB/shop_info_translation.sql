CREATE TABLE shop_info_translation (
    id SERIAL PRIMARY KEY,
    shop_info_id INTEGER NOT NULL REFERENCES shop_info(id) ON DELETE CASCADE,
    locale VARCHAR(10) NOT NULL,
    about_us TEXT,
    how_to_order TEXT,
    business_conditions TEXT,
    privacy_policy TEXT,
    shipping_info TEXT,
    payment TEXT,
    refund TEXT,
    UNIQUE (shop_info_id, locale)
);