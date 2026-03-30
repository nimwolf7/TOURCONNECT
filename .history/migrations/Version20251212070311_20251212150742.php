<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251212070311 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        // $this->addSql('ALTER TABLE booking ADD service_id INT DEFAULT NULL'); // Already exists, skip to avoid error
        // $this->addSql('ALTER TABLE booking ADD CONSTRAINT FK_E00CEDDEED5CA9E6 FOREIGN KEY (service_id) REFERENCES service (id)'); // Already exists, skip to avoid error
        // $this->addSql('CREATE INDEX IDX_E00CEDDEED5CA9E6 ON booking (service_id)'); // Already exists, skip to avoid error
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE booking DROP FOREIGN KEY FK_E00CEDDEED5CA9E6');
        $this->addSql('DROP INDEX IDX_E00CEDDEED5CA9E6 ON booking');
        $this->addSql('ALTER TABLE booking DROP service_id');
    }
}
