<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240817111436 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Update Employee and Product tables for PostgreSQL compatibility';
    }

    public function up(Schema $schema): void
    {
        // 删除无用表
        $this->addSql('DROP TABLE IF EXISTS Product_Document_Vector');

        // 只在 Employee 表没有 id 列时添加它
        $this->addSql('DO $$ 
            BEGIN
                IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = \'employee\' AND column_name = \'id\') THEN
                    ALTER TABLE Employee ADD COLUMN id SERIAL PRIMARY KEY;
                END IF;
            END$$;');

        // 更新 Employee 表字段类型和唯一索引
        $this->addSql('ALTER TABLE Employee ALTER COLUMN username TYPE VARCHAR(180)');
        $this->addSql('ALTER TABLE Employee ALTER COLUMN password TYPE VARCHAR(255)');
        $this->addSql('ALTER TABLE Employee ALTER COLUMN email TYPE VARCHAR(255)');
        $this->addSql('ALTER TABLE Employee ALTER COLUMN phone_number TYPE VARCHAR(255)');
        $this->addSql('ALTER TABLE Employee ALTER COLUMN surname TYPE VARCHAR(180)');
        $this->addSql('ALTER TABLE Employee ALTER COLUMN name TYPE VARCHAR(180)');

        // 仅对 email 和 username 创建唯一索引
        $this->addSql('CREATE UNIQUE INDEX IF NOT EXISTS UNIQ_5D9F75A1E7927C74 ON Employee (email)');
        $this->addSql('CREATE UNIQUE INDEX IF NOT EXISTS UNIQ_5D9F75A1F85E0677 ON Employee (username)');

        // 更新 Product 表字段类型
        $this->addSql('ALTER TABLE Product ALTER COLUMN name TYPE VARCHAR(255)');
        $this->addSql('ALTER TABLE Product ALTER COLUMN kategory TYPE VARCHAR(255)');
        $this->addSql('ALTER TABLE Product ALTER COLUMN description TYPE VARCHAR(255)');
        $this->addSql('ALTER TABLE Product ALTER COLUMN image_urls TYPE JSON');
        $this->addSql('ALTER TABLE Product ALTER COLUMN add_time TYPE VARCHAR(255)');
        $this->addSql('ALTER TABLE Product ALTER COLUMN material TYPE VARCHAR(255)');
        $this->addSql('ALTER TABLE Product ALTER COLUMN color TYPE VARCHAR(255)');
        
        // 修改 hidden 列，改为 BOOLEAN 类型
        $this->addSql('ALTER TABLE Product ALTER COLUMN hidden TYPE BOOLEAN');
    }

    public function down(Schema $schema): void
    {
        // 还原 Product_Document_Vector 表
        $this->addSql('CREATE TABLE IF NOT EXISTS Product_Document_Vector (
            id SERIAL PRIMARY KEY,
            document TEXT,
            vector TEXT
        )');

        // Employee表：还原字段类型和主键约束
        $this->addSql('ALTER TABLE Employee DROP COLUMN id');
        $this->addSql('ALTER TABLE Employee DROP CONSTRAINT IF EXISTS UNIQ_5D9F75A1E7769B0F');
        $this->addSql('ALTER TABLE Employee DROP CONSTRAINT IF EXISTS UNIQ_5D9F75A15E237E06');
        $this->addSql('ALTER TABLE Employee DROP CONSTRAINT IF EXISTS UNIQ_5D9F75A1F85E0677');
        $this->addSql('ALTER TABLE Employee DROP CONSTRAINT IF EXISTS Employee_pkey');
        $this->addSql('ALTER TABLE Employee ALTER COLUMN surname TYPE VARCHAR(50)');
        $this->addSql('ALTER TABLE Employee ALTER COLUMN name TYPE VARCHAR(50)');
        $this->addSql('ALTER TABLE Employee ALTER COLUMN username TYPE VARCHAR(50)');
        $this->addSql('ALTER TABLE Employee ALTER COLUMN roles TYPE JSON USING roles::JSON');
        $this->addSql('ALTER TABLE Employee ALTER COLUMN password TYPE VARCHAR(50)');
        $this->addSql('ALTER TABLE Employee ALTER COLUMN email TYPE VARCHAR(50)');
        $this->addSql('ALTER TABLE Employee ALTER COLUMN phone_number TYPE VARCHAR(50)');
        $this->addSql('ALTER TABLE Employee ADD PRIMARY KEY (username)');

        // Product表：还原字段类型
        $this->addSql('ALTER TABLE Product ALTER COLUMN name TYPE VARCHAR(50)');
        $this->addSql('ALTER TABLE Product ALTER COLUMN kategory TYPE VARCHAR(50)');
        $this->addSql('ALTER TABLE Product ALTER COLUMN description TYPE TEXT');
        $this->addSql('ALTER TABLE Product ALTER COLUMN image_url TYPE VARCHAR(50)');
        $this->addSql('ALTER TABLE Product ALTER COLUMN add_time TYPE VARCHAR(50)');
        $this->addSql('ALTER TABLE Product ALTER COLUMN material TYPE VARCHAR(50)');
        $this->addSql('ALTER TABLE Product ALTER COLUMN color TYPE VARCHAR(50)');
    }
}