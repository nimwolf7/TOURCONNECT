<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260426120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Rename service stock column to slots.';
    }

    public function up(Schema $schema): void
    {
        $platform = $this->connection->getDatabasePlatform()->getName();
        $this->abortIf(!in_array($platform, ['mysql', 'mariadb'], true), 'Migration only supported on MySQL/MariaDB.');

        $schemaManager = $this->connection->createSchemaManager();
        $columns = $schemaManager->listTableColumns('service');

        if (!isset($columns['stock'])) {
            if (isset($columns['slots'])) {
                return;
            }
            $this->abortIf(true, 'Neither stock nor slots column exists on service table.');
        }

        if (isset($columns['slots'])) {
            return;
        }

        $this->addSql('ALTER TABLE service CHANGE stock slots INT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $platform = $this->connection->getDatabasePlatform()->getName();
        $this->abortIf(!in_array($platform, ['mysql', 'mariadb'], true), 'Migration only supported on MySQL/MariaDB.');

        $schemaManager = $this->connection->createSchemaManager();
        $columns = $schemaManager->listTableColumns('service');

        if (!isset($columns['slots'])) {
            if (isset($columns['stock'])) {
                return;
            }
            $this->abortIf(true, 'Neither slots nor stock column exists on service table.');
        }

        if (isset($columns['stock'])) {
            return;
        }

        $this->addSql('ALTER TABLE service CHANGE slots stock INT DEFAULT NULL');
    }
}
