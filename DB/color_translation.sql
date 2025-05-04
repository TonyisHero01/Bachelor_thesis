CREATE TABLE color_translation (
    id SERIAL PRIMARY KEY,
    color_id INTEGER NOT NULL,
    locale VARCHAR(10) NOT NULL,
    name VARCHAR(255) NOT NULL,
    
    CONSTRAINT fk_color_translation_color FOREIGN KEY (color_id)
        REFERENCES productcolor(id) ON DELETE CASCADE,

    CONSTRAINT unique_color_locale UNIQUE (color_id, locale)
);