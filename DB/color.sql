CREATE TABLE ProductColor (
    id SERIAL PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    hex VARCHAR(7) NOT NULL DEFAULT '#FFFFFF',
    CONSTRAINT hex_format_check CHECK (hex ~ '^#([A-Fa-f0-9]{6})$')
);