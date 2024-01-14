create table `Product` 
( 
    `ID` INT AUTO_INCREMENT, 
    `NAME` VARCHAR(50) not null, 
    `KATEGORY` VARCHAR(50), 
    `DESCRIPTION` LONGTEXT, 
    `NUMBER_IN_STOCK` INT not null,
    `IMAGE_URL` VARCHAR(50), 
    `ADD_TIME` TIMESTAMP not null, 
    `WIDTH` INT, 
    `HEIGHT` INT, 
    `LENGTH` INT, 
    `WEIGHT` INT, 
    `MATERIAL` VARCHAR(50), 
    `COLOR` VARCHAR(50), 
    `PRICE` INT not null, 
    PRIMARY KEY(`ID`) 
)ENGINE=InnoDB DEFAULT CHARSET=utf8;