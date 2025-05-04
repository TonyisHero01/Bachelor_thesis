CREATE TABLE category_translation (
    id SERIAL PRIMARY KEY,
    category_id INTEGER NOT NULL,
    locale VARCHAR(10) NOT NULL,
    name VARCHAR(255) NOT NULL,
    UNIQUE (category_id, locale),
    FOREIGN KEY (category_id) REFERENCES category(id) ON DELETE CASCADE
);