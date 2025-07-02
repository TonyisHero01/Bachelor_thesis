CREATE TABLE public.admin_code (
    id SERIAL PRIMARY KEY,
    code_hash TEXT NOT NULL,
    created_at TIMESTAMP WITHOUT TIME ZONE NOT NULL DEFAULT now()
);