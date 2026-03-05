<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260304081847 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        // SQLite compatible version
        $platform = $this->connection->getDatabasePlatform();
        $isSqlite = strpos(get_class($platform), 'SQLite') !== false;
        
        if ($isSqlite) {
            $this->addSql('CREATE TABLE IF NOT EXISTS mapping_rule (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, pattern VARCHAR(255) NOT NULL, match_type VARCHAR(50) NOT NULL, tool_type VARCHAR(100) NOT NULL, confidence INTEGER NOT NULL, owasp_category_id INTEGER NOT NULL)');
            $this->addSql('CREATE INDEX IF NOT EXISTS IDX_869B3C3CBE064368 ON mapping_rule (owasp_category_id)');
            
            $this->addSql('CREATE TABLE IF NOT EXISTS owasp_category (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, code VARCHAR(10) NOT NULL, name VARCHAR(255) NOT NULL, description TEXT NOT NULL, examples TEXT NOT NULL)');
            
            $this->addSql('CREATE TABLE IF NOT EXISTS project (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, uuid VARCHAR(255) NOT NULL, name VARCHAR(255) NOT NULL, type VARCHAR(255) NOT NULL, git_url VARCHAR(255) DEFAULT NULL, zip_hash VARCHAR(255) DEFAULT NULL, created_at DATETIME NOT NULL, user_id_id INTEGER DEFAULT NULL)');
            $this->addSql('CREATE INDEX IF NOT EXISTS IDX_2FB3D0EE9D86650F ON project (user_id_id)');
            
            $this->addSql('CREATE TABLE IF NOT EXISTS report (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, languages TEXT NOT NULL, frameworks TEXT DEFAULT NULL, score INTEGER NOT NULL, status VARCHAR(255) NOT NULL, details TEXT NOT NULL, created_at DATETIME NOT NULL, project_id INTEGER DEFAULT NULL)');
            $this->addSql('CREATE INDEX IF NOT EXISTS IDX_C42F7784166D1F9C ON report (project_id)');
            
            $this->addSql('CREATE TABLE IF NOT EXISTS "user" (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, email VARCHAR(180) NOT NULL, roles TEXT NOT NULL, password VARCHAR(255) NOT NULL)');
            $this->addSql('CREATE UNIQUE INDEX IF NOT EXISTS UNIQ_IDENTIFIER_EMAIL ON "user" (email)');
            
            $this->addSql('CREATE TABLE IF NOT EXISTS vulnerability (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, title VARCHAR(255) NOT NULL, description TEXT NOT NULL, severity VARCHAR(100) NOT NULL, tool_name VARCHAR(100) NOT NULL, file_path VARCHAR(255) DEFAULT NULL, line_number INTEGER DEFAULT NULL, confidence_score INTEGER NOT NULL, raw_data TEXT NOT NULL, detected_at DATETIME NOT NULL, owasp_category_id INTEGER DEFAULT NULL)');
            $this->addSql('CREATE INDEX IF NOT EXISTS IDX_6C4E4047BE064368 ON vulnerability (owasp_category_id)');
            
            $this->addSql('CREATE TABLE IF NOT EXISTS messenger_messages (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, body TEXT NOT NULL, headers TEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL, available_at DATETIME NOT NULL, delivered_at DATETIME DEFAULT NULL)');
            $this->addSql('CREATE INDEX IF NOT EXISTS IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750 ON messenger_messages (queue_name, available_at, delivered_at, id)');
        } else {
            $this->addSql('CREATE TABLE mapping_rule (id INT AUTO_INCREMENT NOT NULL, pattern VARCHAR(255) NOT NULL, match_type VARCHAR(50) NOT NULL, tool_type VARCHAR(100) NOT NULL, confidence INT NOT NULL, owasp_category_id INT NOT NULL, INDEX IDX_869B3C3CBE064368 (owasp_category_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
            $this->addSql('CREATE TABLE owasp_category (id INT AUTO_INCREMENT NOT NULL, code VARCHAR(10) NOT NULL, name VARCHAR(255) NOT NULL, description LONGTEXT NOT NULL, examples JSON NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
            $this->addSql('CREATE TABLE project (id INT AUTO_INCREMENT NOT NULL, uuid VARCHAR(255) NOT NULL, name VARCHAR(255) NOT NULL, type VARCHAR(255) NOT NULL, git_url VARCHAR(255) DEFAULT NULL, zip_hash VARCHAR(255) DEFAULT NULL, created_at DATETIME NOT NULL, user_id_id INT DEFAULT NULL, INDEX IDX_2FB3D0EE9D86650F (user_id_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
            $this->addSql('CREATE TABLE report (id INT AUTO_INCREMENT NOT NULL, languages JSON NOT NULL, frameworks JSON DEFAULT NULL, score INT NOT NULL, status VARCHAR(255) NOT NULL, details JSON NOT NULL, created_at DATETIME NOT NULL, project_id INT DEFAULT NULL, INDEX IDX_C42F7784166D1F9C (project_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
            $this->addSql('CREATE TABLE `user` (id INT AUTO_INCREMENT NOT NULL, email VARCHAR(180) NOT NULL, roles JSON NOT NULL, password VARCHAR(255) NOT NULL, UNIQUE INDEX UNIQ_IDENTIFIER_EMAIL (email), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
            $this->addSql('CREATE TABLE vulnerability (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(255) NOT NULL, description LONGTEXT NOT NULL, severity VARCHAR(100) NOT NULL, tool_name VARCHAR(100) NOT NULL, file_path VARCHAR(255) DEFAULT NULL, line_number INT DEFAULT NULL, confidence_score INT NOT NULL, raw_data JSON NOT NULL, detected_at DATETIME NOT NULL, owasp_category_id INT DEFAULT NULL, INDEX IDX_6C4E4047BE064368 (owasp_category_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
            $this->addSql('CREATE TABLE messenger_messages (id BIGINT AUTO_INCREMENT NOT NULL, body LONGTEXT NOT NULL, headers LONGTEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL, available_at DATETIME NOT NULL, delivered_at DATETIME DEFAULT NULL, INDEX IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750 (queue_name, available_at, delivered_at, id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
            $this->addSql('ALTER TABLE mapping_rule ADD CONSTRAINT FK_869B3C3CBE064368 FOREIGN KEY (owasp_category_id) REFERENCES owasp_category (id) ON DELETE CASCADE');
            $this->addSql('ALTER TABLE project ADD CONSTRAINT FK_2FB3D0EE9D86650F FOREIGN KEY (user_id_id) REFERENCES `user` (id)');
            $this->addSql('ALTER TABLE report ADD CONSTRAINT FK_C42F7784166D1F9C FOREIGN KEY (project_id) REFERENCES project (id)');
            $this->addSql('ALTER TABLE vulnerability ADD CONSTRAINT FK_6C4E4047BE064368 FOREIGN KEY (owasp_category_id) REFERENCES owasp_category (id)');
        }
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE mapping_rule DROP FOREIGN KEY FK_869B3C3CBE064368');
        $this->addSql('ALTER TABLE project DROP FOREIGN KEY FK_2FB3D0EE9D86650F');
        $this->addSql('ALTER TABLE report DROP FOREIGN KEY FK_C42F7784166D1F9C');
        $this->addSql('ALTER TABLE vulnerability DROP FOREIGN KEY FK_6C4E4047BE064368');
        $this->addSql('DROP TABLE mapping_rule');
        $this->addSql('DROP TABLE owasp_category');
        $this->addSql('DROP TABLE project');
        $this->addSql('DROP TABLE report');
        $this->addSql('DROP TABLE `user`');
        $this->addSql('DROP TABLE vulnerability');
        $this->addSql('DROP TABLE messenger_messages');
    }
}
