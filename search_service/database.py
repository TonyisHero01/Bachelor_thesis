import logging
from contextlib import contextmanager
from typing import Generator

import psycopg2
from psycopg2.extensions import connection
from psycopg2.extras import DictCursor

from config import settings


logger = logging.getLogger(__name__)


@contextmanager
def get_connection() -> Generator[connection, None, None]:
    conn = None

    try:
        conn = psycopg2.connect(
            settings.database_url,
            cursor_factory=DictCursor,
        )
        yield conn
    except Exception:
        logger.exception("Database connection or query failed")
        raise
    finally:
        if conn is not None:
            conn.close()