import os
import json
import psycopg2
from psycopg2.extras import Json
from dotenv import load_dotenv


load_dotenv()


class UserStudyRepository:
    def __init__(self):
        self.database_url = os.getenv("DATABASE_URL")

        if not self.database_url:
            raise RuntimeError("DATABASE_URL is not set")

        self.ensure_table_exists()

    def get_connection(self):
        return psycopg2.connect(self.database_url)

    def ensure_table_exists(self):
        sql = """
        CREATE TABLE IF NOT EXISTS user_studies (
            id SERIAL PRIMARY KEY,
            session_id VARCHAR(255),
            customer_id INTEGER NULL,
            form_name VARCHAR(255) NOT NULL,
            page_type VARCHAR(255),
            source VARCHAR(255),
            answers JSONB NOT NULL,
            user_agent TEXT,
            ip_address VARCHAR(100),
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        );

        CREATE INDEX IF NOT EXISTS idx_user_studies_form_name
            ON user_studies(form_name);

        CREATE INDEX IF NOT EXISTS idx_user_studies_customer_id
            ON user_studies(customer_id);

        CREATE INDEX IF NOT EXISTS idx_user_studies_created_at
            ON user_studies(created_at);
        """

        with self.get_connection() as conn:
            with conn.cursor() as cursor:
                cursor.execute(sql)
            conn.commit()

    def save_study(
        self,
        form_name,
        answers,
        session_id=None,
        customer_id=None,
        page_type=None,
        source=None,
        user_agent=None,
        ip_address=None,
    ):
        if isinstance(answers, str):
            answers = json.loads(answers)

        sql = """
        INSERT INTO user_studies (
            session_id,
            customer_id,
            form_name,
            page_type,
            source,
            answers,
            user_agent,
            ip_address
        )
        VALUES (%s, %s, %s, %s, %s, %s, %s, %s)
        RETURNING id;
        """

        with self.get_connection() as conn:
            with conn.cursor() as cursor:
                cursor.execute(
                    sql,
                    (
                        session_id,
                        customer_id,
                        form_name,
                        page_type,
                        source,
                        Json(answers),
                        user_agent,
                        ip_address,
                    ),
                )

                new_id = cursor.fetchone()[0]

            conn.commit()

        return new_id