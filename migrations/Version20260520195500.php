<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260520195500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Link budget tracker entries to bookings.';
    }

    public function up(Schema $schema): void
    {
        $platform = $this->connection->getDatabasePlatform()->getName();
        $this->abortIf(!in_array($platform, ['mysql', 'mariadb'], true), 'Migration only supported on MySQL/MariaDB.');

        $schemaManager = $this->connection->createSchemaManager();
        $columns = $schemaManager->listTableColumns('budget_tracker');

        if (!isset($columns['booking_id'])) {
            $this->addSql('ALTER TABLE budget_tracker ADD booking_id INT DEFAULT NULL');
            $this->addSql('ALTER TABLE budget_tracker ADD CONSTRAINT FK_2269A43D330C47D0 FOREIGN KEY (booking_id) REFERENCES booking (id) ON DELETE SET NULL');
            $this->addSql('CREATE INDEX IDX_2269A43D330C47D0 ON budget_tracker (booking_id)');
        }
    }

    public function down(Schema $schema): void
    {
        $platform = $this->connection->getDatabasePlatform()->getName();
        $this->abortIf(!in_array($platform, ['mysql', 'mariadb'], true), 'Migration only supported on MySQL/MariaDB.');

        $schemaManager = $this->connection->createSchemaManager();
        $columns = $schemaManager->listTableColumns('budget_tracker');

        if (isset($columns['booking_id'])) {
            $this->addSql('ALTER TABLE budget_tracker DROP FOREIGN KEY FK_2269A43D330C47D0');
            $this->addSql('DROP INDEX IDX_2269A43D330C47D0 ON budget_tracker');
            $this->addSql('ALTER TABLE budget_tracker DROP COLUMN booking_id');
        }
    }
}
