<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260107190000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Make attendance.schedule nullable for teacher attendance flow.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE attendance MODIFY schedule_id INT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE attendance MODIFY schedule_id INT NOT NULL');
    }
}
