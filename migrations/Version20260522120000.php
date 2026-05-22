<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260522120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Rename booking status Complete to Confirmed.';
    }

    public function up(Schema $schema): void
    {
        $platform = $this->connection->getDatabasePlatform()->getName();
        $this->abortIf(!in_array($platform, ['mysql', 'mariadb'], true), 'Migration only supported on MySQL/MariaDB.');

        $this->addSql("UPDATE booking SET status = 'Confirmed' WHERE status = 'Complete'");
    }

    public function down(Schema $schema): void
    {
        $platform = $this->connection->getDatabasePlatform()->getName();
        $this->abortIf(!in_array($platform, ['mysql', 'mariadb'], true), 'Migration only supported on MySQL/MariaDB.');

        $this->addSql("UPDATE booking SET status = 'Complete' WHERE status = 'Confirmed'");
    }
}
