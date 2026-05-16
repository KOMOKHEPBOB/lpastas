<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260516091015 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE order_item_reservations (id INT AUTO_INCREMENT NOT NULL, quantity_reserved INT NOT NULL, order_item_id INT NOT NULL, warehouse_location_id INT NOT NULL, INDEX IDX_DB7415A3E415FB15 (order_item_id), INDEX IDX_DB7415A3F474C0FB (warehouse_location_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE order_items (id INT AUTO_INCREMENT NOT NULL, quantity_requested INT NOT NULL, order_id INT NOT NULL, product_id INT NOT NULL, INDEX IDX_62809DB08D9F6D38 (order_id), INDEX IDX_62809DB04584665A (product_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE orders (id INT AUTO_INCREMENT NOT NULL, status VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE products (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(255) NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE warehouse_locations (id INT AUTO_INCREMENT NOT NULL, warehouse_id INT NOT NULL, product_id INT NOT NULL, location_code VARCHAR(16) NOT NULL, quantity INT NOT NULL, quantity_reserved INT NOT NULL, INDEX IDX_287304055080ECDE (warehouse_id), INDEX idx_product_id (product_id), UNIQUE INDEX uniq_warehouse_location_code (warehouse_id, location_code), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE warehouses (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(255) NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE order_item_reservations ADD CONSTRAINT FK_DB7415A3E415FB15 FOREIGN KEY (order_item_id) REFERENCES order_items (id)');
        $this->addSql('ALTER TABLE order_item_reservations ADD CONSTRAINT FK_DB7415A3F474C0FB FOREIGN KEY (warehouse_location_id) REFERENCES warehouse_locations (id)');
        $this->addSql('ALTER TABLE order_items ADD CONSTRAINT FK_62809DB08D9F6D38 FOREIGN KEY (order_id) REFERENCES orders (id)');
        $this->addSql('ALTER TABLE order_items ADD CONSTRAINT FK_62809DB04584665A FOREIGN KEY (product_id) REFERENCES products (id)');
        $this->addSql('ALTER TABLE warehouse_locations ADD CONSTRAINT FK_287304055080ECDE FOREIGN KEY (warehouse_id) REFERENCES warehouses (id)');
        $this->addSql('ALTER TABLE warehouse_locations ADD CONSTRAINT FK_287304054584665A FOREIGN KEY (product_id) REFERENCES products (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE order_item_reservations DROP FOREIGN KEY FK_DB7415A3E415FB15');
        $this->addSql('ALTER TABLE order_item_reservations DROP FOREIGN KEY FK_DB7415A3F474C0FB');
        $this->addSql('ALTER TABLE order_items DROP FOREIGN KEY FK_62809DB08D9F6D38');
        $this->addSql('ALTER TABLE order_items DROP FOREIGN KEY FK_62809DB04584665A');
        $this->addSql('ALTER TABLE warehouse_locations DROP FOREIGN KEY FK_287304055080ECDE');
        $this->addSql('ALTER TABLE warehouse_locations DROP FOREIGN KEY FK_287304054584665A');
        $this->addSql('DROP TABLE order_item_reservations');
        $this->addSql('DROP TABLE order_items');
        $this->addSql('DROP TABLE orders');
        $this->addSql('DROP TABLE products');
        $this->addSql('DROP TABLE warehouse_locations');
        $this->addSql('DROP TABLE warehouses');
    }
}
