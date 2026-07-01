<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260701095545 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE employee ALTER roles TYPE JSON');
        $this->addSql('ALTER TABLE employee ALTER roles SET NOT NULL');
        $this->addSql('ALTER TABLE search_relevance_config ADD recommendation_enabled BOOLEAN DEFAULT true NOT NULL');
        $this->addSql('ALTER TABLE search_relevance_config ADD recommendation_logging_enabled BOOLEAN DEFAULT true NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE employee ALTER roles TYPE JSONB');
        $this->addSql('ALTER TABLE employee ALTER roles DROP NOT NULL');
        $this->addSql('ALTER TABLE search_relevance_config DROP recommendation_enabled');
        $this->addSql('ALTER TABLE search_relevance_config DROP recommendation_logging_enabled');
    }
}