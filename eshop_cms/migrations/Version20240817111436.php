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
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE user_permissions');
        $this->addSql('DROP TABLE Product_Document_Vector');
        $this->addSql('ALTER TABLE Employee ADD id INT AUTO_INCREMENT NOT NULL, CHANGE USERNAME username VARCHAR(180) NOT NULL, CHANGE PASSWORD password VARCHAR(255) NOT NULL, CHANGE email email VARCHAR(255) NOT NULL, CHANGE phone_number phone_number VARCHAR(255) NOT NULL, CHANGE surname surname VARCHAR(180) NOT NULL, CHANGE name name VARCHAR(180) NOT NULL, CHANGE ROLES roles JSON NOT NULL, DROP PRIMARY KEY, ADD PRIMARY KEY (id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_5D9F75A1E7769B0F ON Employee (surname)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_5D9F75A15E237E06 ON Employee (name)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_5D9F75A1F85E0677 ON Employee (username)');
        $this->addSql('ALTER TABLE Product CHANGE NAME name VARCHAR(255) NOT NULL, CHANGE KATEGORY kategory VARCHAR(255) DEFAULT NULL, CHANGE DESCRIPTION description VARCHAR(255) DEFAULT NULL, CHANGE IMAGE_URL image_url VARCHAR(255) DEFAULT NULL, CHANGE ADD_TIME add_time VARCHAR(255) NOT NULL, CHANGE MATERIAL material VARCHAR(255) DEFAULT NULL, CHANGE COLOR color VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE user_permissions (id INT AUTO_INCREMENT NOT NULL, username VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_0900_ai_ci`, can_create_worker_accounts TINYINT(1) DEFAULT 0, can_view_workers_customers TINYINT(1) DEFAULT 0, can_delete_worker_accounts TINYINT(1) DEFAULT 0, can_manage_admin_account TINYINT(1) DEFAULT 0, can_view_dashboard TINYINT(1) DEFAULT 0, can_view_stored_products TINYINT(1) DEFAULT 0, can_edit_stock_quantity TINYINT(1) DEFAULT 0, can_dispatch_goods TINYINT(1) DEFAULT 0, can_view_customer_info TINYINT(1) DEFAULT 0, can_create_products TINYINT(1) DEFAULT 0, can_set_product_visibility TINYINT(1) DEFAULT 0, can_delete_products TINYINT(1) DEFAULT 0, can_create_product_categories TINYINT(1) DEFAULT 0, can_translate_products TINYINT(1) DEFAULT 0, can_add_product_discounts TINYINT(1) DEFAULT 0, can_create_invoices TINYINT(1) DEFAULT 0, can_handle_claims TINYINT(1) DEFAULT 0, can_purchase_or_add_to_cart TINYINT(1) DEFAULT 0, can_edit_cart TINYINT(1) DEFAULT 0, gets_discount_as_registered_customer TINYINT(1) DEFAULT 0, does_not_need_to_reenter_contact_details TINYINT(1) DEFAULT 0, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_0900_ai_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE Product_Document_Vector (id INT NOT NULL, document TEXT CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_0900_ai_ci`, vector TEXT CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_0900_ai_ci`, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_0900_ai_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('ALTER TABLE employee MODIFY id INT NOT NULL');
        $this->addSql('DROP INDEX UNIQ_5D9F75A1E7769B0F ON employee');
        $this->addSql('DROP INDEX UNIQ_5D9F75A15E237E06 ON employee');
        $this->addSql('DROP INDEX UNIQ_5D9F75A1F85E0677 ON employee');
        $this->addSql('DROP INDEX `PRIMARY` ON employee');
        $this->addSql('ALTER TABLE employee DROP id, CHANGE surname surname VARCHAR(50) NOT NULL, CHANGE name name VARCHAR(50) NOT NULL, CHANGE username USERNAME VARCHAR(50) NOT NULL, CHANGE roles ROLES JSON DEFAULT NULL, CHANGE password PASSWORD VARCHAR(50) NOT NULL, CHANGE email email VARCHAR(50) NOT NULL, CHANGE phone_number phone_number VARCHAR(50) NOT NULL');
        $this->addSql('ALTER TABLE employee ADD PRIMARY KEY (USERNAME)');
        $this->addSql('ALTER TABLE product CHANGE name NAME VARCHAR(50) NOT NULL, CHANGE kategory KATEGORY VARCHAR(50) DEFAULT NULL, CHANGE description DESCRIPTION LONGTEXT DEFAULT NULL, CHANGE image_url IMAGE_URL VARCHAR(50) DEFAULT NULL, CHANGE add_time ADD_TIME VARCHAR(50) DEFAULT NULL, CHANGE material MATERIAL VARCHAR(50) DEFAULT NULL, CHANGE color COLOR VARCHAR(50) DEFAULT NULL');
    }
}
