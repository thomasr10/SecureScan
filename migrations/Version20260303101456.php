<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260303101456 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE mapping_rule (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, pattern VARCHAR(255) NOT NULL, match_type VARCHAR(50) NOT NULL, tool_type VARCHAR(100) NOT NULL, confidence INTEGER NOT NULL, owasp_category_id INTEGER NOT NULL, CONSTRAINT FK_869B3C3CBE064368 FOREIGN KEY (owasp_category_id) REFERENCES owasp_category (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_869B3C3CBE064368 ON mapping_rule (owasp_category_id)');
        $this->addSql('CREATE TABLE owasp_category (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, code VARCHAR(10) NOT NULL, name VARCHAR(255) NOT NULL, description CLOB NOT NULL, examples CLOB NOT NULL)');
        $this->addSql('CREATE TABLE vulnerability (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, title VARCHAR(255) NOT NULL, description CLOB NOT NULL, severity VARCHAR(100) NOT NULL, tool_name VARCHAR(100) NOT NULL, file_path VARCHAR(255) DEFAULT NULL, line_number INTEGER DEFAULT NULL, confidence_score INTEGER NOT NULL, raw_data CLOB NOT NULL, detected_at DATETIME NOT NULL, owasp_category_id INTEGER DEFAULT NULL, CONSTRAINT FK_6C4E4047BE064368 FOREIGN KEY (owasp_category_id) REFERENCES owasp_category (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_6C4E4047BE064368 ON vulnerability (owasp_category_id)');
        $this->addSql('CREATE TABLE messenger_messages (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, body CLOB NOT NULL, headers CLOB NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL, available_at DATETIME NOT NULL, delivered_at DATETIME DEFAULT NULL)');
        $this->addSql('CREATE INDEX IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750 ON messenger_messages (queue_name, available_at, delivered_at, id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE mapping_rule');
        $this->addSql('DROP TABLE owasp_category');
        $this->addSql('DROP TABLE vulnerability');
        $this->addSql('DROP TABLE messenger_messages');
    }
}
