CREATE TABLE Employee (
    id SERIAL PRIMARY KEY,
    username VARCHAR(180) NOT NULL,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    phone_number VARCHAR(255) NOT NULL,
    surname VARCHAR(180) NOT NULL,
    name VARCHAR(180) NOT NULL,
    roles JSONB NOT NULL
);