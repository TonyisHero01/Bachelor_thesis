create table USER
(
    USERNAME VARCHAR(50) NOT NULL,
    PASSWORD VARCHAR(50) NOT NULL,
    POSITION VARCHAR(50) NOT NULL,
    IS_CLIENT BOOL NOT NULL,
    PRIMARY KEY(USERNAME)
)ENGINE=InnoDB DEFAULT CHARSET=utf8;