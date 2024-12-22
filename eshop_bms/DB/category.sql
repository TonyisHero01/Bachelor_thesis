CREATE TABLE Category (
    id SERIAL PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    parent_id INT DEFAULT NULL,
    FOREIGN KEY (parent_id) REFERENCES Category (id) ON DELETE CASCADE
);

WITH RECURSIVE CategoryHierarchy AS (
    SELECT id, name, parent_id, 1 AS level
    FROM Category
    WHERE parent_id IS NULL

    UNION ALL

    SELECT c.id, c.name, c.parent_id, ch.level + 1
    FROM Category c
    INNER JOIN CategoryHierarchy ch ON c.parent_id = ch.id
)
SELECT * FROM CategoryHierarchy
ORDER BY level, parent_id, id;