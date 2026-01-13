<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260107145811 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE attendance (id INT AUTO_INCREMENT NOT NULL, student_id INT NOT NULL, schedule_id INT NOT NULL, student_group_id INT NOT NULL, date DATE NOT NULL, status VARCHAR(20) NOT NULL, INDEX IDX_6DE30D91CB944F1A (student_id), INDEX IDX_6DE30D91A40BC2D5 (schedule_id), INDEX IDX_6DE30D914DDF95DC (student_group_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE schedule (id INT AUTO_INCREMENT NOT NULL, student_group_id INT NOT NULL, subject VARCHAR(20) NOT NULL, day_of_week VARCHAR(20) NOT NULL, start_time TIME NOT NULL, end_time TIME NOT NULL, INDEX IDX_5A3811FB4DDF95DC (student_group_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE school (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, address LONGTEXT DEFAULT NULL, phone VARCHAR(20) DEFAULT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE student (id INT AUTO_INCREMENT NOT NULL, student_group_id INT NOT NULL, school_id INT NOT NULL, first_name VARCHAR(100) NOT NULL, last_name VARCHAR(100) NOT NULL, date_of_birth DATE DEFAULT NULL, niveau_scolaire VARCHAR(50) NOT NULL, INDEX IDX_B723AF334DDF95DC (student_group_id), INDEX IDX_B723AF33C32A47EE (school_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE student_group (id INT AUTO_INCREMENT NOT NULL, school_id INT NOT NULL, teacher_id INT DEFAULT NULL, name VARCHAR(100) NOT NULL, INDEX IDX_E5F73D58C32A47EE (school_id), INDEX IDX_E5F73D5841807E1D (teacher_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE teacher (id INT AUTO_INCREMENT NOT NULL, school_id INT NOT NULL, user_id INT NOT NULL, first_name VARCHAR(100) NOT NULL, last_name VARCHAR(100) NOT NULL, phone VARCHAR(20) DEFAULT NULL, INDEX IDX_B0F6A6D5C32A47EE (school_id), UNIQUE INDEX UNIQ_B0F6A6D5A76ED395 (user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE user (id INT AUTO_INCREMENT NOT NULL, username VARCHAR(180) NOT NULL, roles JSON NOT NULL, password VARCHAR(255) NOT NULL, plain_password VARCHAR(255) DEFAULT NULL, UNIQUE INDEX UNIQ_IDENTIFIER_USERNAME (username), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE attendance ADD CONSTRAINT FK_6DE30D91CB944F1A FOREIGN KEY (student_id) REFERENCES student (id)');
        $this->addSql('ALTER TABLE attendance ADD CONSTRAINT FK_6DE30D91A40BC2D5 FOREIGN KEY (schedule_id) REFERENCES schedule (id)');
        $this->addSql('ALTER TABLE attendance ADD CONSTRAINT FK_6DE30D914DDF95DC FOREIGN KEY (student_group_id) REFERENCES student_group (id)');
        $this->addSql('ALTER TABLE schedule ADD CONSTRAINT FK_5A3811FB4DDF95DC FOREIGN KEY (student_group_id) REFERENCES student_group (id)');
        $this->addSql('ALTER TABLE student ADD CONSTRAINT FK_B723AF334DDF95DC FOREIGN KEY (student_group_id) REFERENCES student_group (id)');
        $this->addSql('ALTER TABLE student ADD CONSTRAINT FK_B723AF33C32A47EE FOREIGN KEY (school_id) REFERENCES school (id)');
        $this->addSql('ALTER TABLE student_group ADD CONSTRAINT FK_E5F73D58C32A47EE FOREIGN KEY (school_id) REFERENCES school (id)');
        $this->addSql('ALTER TABLE student_group ADD CONSTRAINT FK_E5F73D5841807E1D FOREIGN KEY (teacher_id) REFERENCES teacher (id)');
        $this->addSql('ALTER TABLE teacher ADD CONSTRAINT FK_B0F6A6D5C32A47EE FOREIGN KEY (school_id) REFERENCES school (id)');
        $this->addSql('ALTER TABLE teacher ADD CONSTRAINT FK_B0F6A6D5A76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE attendance DROP FOREIGN KEY FK_6DE30D91CB944F1A');
        $this->addSql('ALTER TABLE attendance DROP FOREIGN KEY FK_6DE30D91A40BC2D5');
        $this->addSql('ALTER TABLE attendance DROP FOREIGN KEY FK_6DE30D914DDF95DC');
        $this->addSql('ALTER TABLE schedule DROP FOREIGN KEY FK_5A3811FB4DDF95DC');
        $this->addSql('ALTER TABLE student DROP FOREIGN KEY FK_B723AF334DDF95DC');
        $this->addSql('ALTER TABLE student DROP FOREIGN KEY FK_B723AF33C32A47EE');
        $this->addSql('ALTER TABLE student_group DROP FOREIGN KEY FK_E5F73D58C32A47EE');
        $this->addSql('ALTER TABLE student_group DROP FOREIGN KEY FK_E5F73D5841807E1D');
        $this->addSql('ALTER TABLE teacher DROP FOREIGN KEY FK_B0F6A6D5C32A47EE');
        $this->addSql('ALTER TABLE teacher DROP FOREIGN KEY FK_B0F6A6D5A76ED395');
        $this->addSql('DROP TABLE attendance');
        $this->addSql('DROP TABLE schedule');
        $this->addSql('DROP TABLE school');
        $this->addSql('DROP TABLE student');
        $this->addSql('DROP TABLE student_group');
        $this->addSql('DROP TABLE teacher');
        $this->addSql('DROP TABLE user');
    }
}
