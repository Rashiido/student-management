<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260107210000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add start_time and end_time columns to attendance.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE attendance ADD start_time TIME DEFAULT NULL, ADD end_time TIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE attendance DROP start_time, DROP end_time');
    }
}
