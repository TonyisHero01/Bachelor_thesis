CREATE TABLE Category (
    id SERIAL PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    parent_id INTEGER,
    CONSTRAINT fk_category_parent FOREIGN KEY (parent_id)
        REFERENCES category(id) ON DELETE CASCADE
);