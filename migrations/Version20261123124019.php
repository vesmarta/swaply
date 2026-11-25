<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261123124019 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE trade_offer (id INT AUTO_INCREMENT NOT NULL, cash_direction VARCHAR(20) NOT NULL, cash_amount_millimes INT NOT NULL, message LONGTEXT DEFAULT NULL, status VARCHAR(20) NOT NULL, created_at DATETIME NOT NULL, expires_at DATETIME NOT NULL, responded_at DATETIME DEFAULT NULL, requester_confirmed_at DATETIME DEFAULT NULL, owner_confirmed_at DATETIME DEFAULT NULL, listing_id INT NOT NULL, sender_id INT NOT NULL, receiver_id INT NOT NULL, offered_listing_id INT DEFAULT NULL, proposed_by_id INT NOT NULL, parent_id INT DEFAULT NULL, INDEX IDX_OFFER_LISTING_STATUS (listing_id, status), INDEX IDX_3DB57DDD4619D1A (listing_id), INDEX IDX_3DB57DDF624B39D (sender_id), INDEX IDX_3DB57DDCD53EDB6 (receiver_id), INDEX IDX_3DB57DD5633C924 (offered_listing_id), INDEX IDX_3DB57DDDAB5A938 (proposed_by_id), INDEX IDX_3DB57DD727ACA70 (parent_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE trade_offer ADD CONSTRAINT FK_3DB57DDD4619D1A FOREIGN KEY (listing_id) REFERENCES listing (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE trade_offer ADD CONSTRAINT FK_3DB57DDF624B39D FOREIGN KEY (sender_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE trade_offer ADD CONSTRAINT FK_3DB57DDCD53EDB6 FOREIGN KEY (receiver_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE trade_offer ADD CONSTRAINT FK_3DB57DD5633C924 FOREIGN KEY (offered_listing_id) REFERENCES listing (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE trade_offer ADD CONSTRAINT FK_3DB57DDDAB5A938 FOREIGN KEY (proposed_by_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE trade_offer ADD CONSTRAINT FK_3DB57DD727ACA70 FOREIGN KEY (parent_id) REFERENCES trade_offer (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE trade_offer DROP FOREIGN KEY FK_3DB57DDD4619D1A');
        $this->addSql('ALTER TABLE trade_offer DROP FOREIGN KEY FK_3DB57DDF624B39D');
        $this->addSql('ALTER TABLE trade_offer DROP FOREIGN KEY FK_3DB57DDCD53EDB6');
        $this->addSql('ALTER TABLE trade_offer DROP FOREIGN KEY FK_3DB57DD5633C924');
        $this->addSql('ALTER TABLE trade_offer DROP FOREIGN KEY FK_3DB57DDDAB5A938');
        $this->addSql('ALTER TABLE trade_offer DROP FOREIGN KEY FK_3DB57DD727ACA70');
        $this->addSql('DROP TABLE trade_offer');
    }
}
