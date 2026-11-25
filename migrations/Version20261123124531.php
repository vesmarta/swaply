<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261123124531 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE purchase (id INT AUTO_INCREMENT NOT NULL, price_millimes INT NOT NULL, message LONGTEXT DEFAULT NULL, status VARCHAR(20) NOT NULL, created_at DATETIME NOT NULL, responded_at DATETIME DEFAULT NULL, buyer_confirmed_at DATETIME DEFAULT NULL, seller_confirmed_at DATETIME DEFAULT NULL, listing_id INT NOT NULL, buyer_id INT NOT NULL, seller_id INT NOT NULL, INDEX IDX_PURCHASE_LISTING_STATUS (listing_id, status), INDEX IDX_6117D13BD4619D1A (listing_id), INDEX IDX_6117D13B6C755722 (buyer_id), INDEX IDX_6117D13B8DE820D9 (seller_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE purchase ADD CONSTRAINT FK_6117D13BD4619D1A FOREIGN KEY (listing_id) REFERENCES listing (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE purchase ADD CONSTRAINT FK_6117D13B6C755722 FOREIGN KEY (buyer_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE purchase ADD CONSTRAINT FK_6117D13B8DE820D9 FOREIGN KEY (seller_id) REFERENCES user (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE purchase DROP FOREIGN KEY FK_6117D13BD4619D1A');
        $this->addSql('ALTER TABLE purchase DROP FOREIGN KEY FK_6117D13B6C755722');
        $this->addSql('ALTER TABLE purchase DROP FOREIGN KEY FK_6117D13B8DE820D9');
        $this->addSql('DROP TABLE purchase');
    }
}
