<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260515092955 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE order_items (id INT AUTO_INCREMENT NOT NULL, quantity_requested INT NOT NULL, order_id INT NOT NULL, product_id INT NOT NULL, INDEX IDX_62809DB08D9F6D38 (order_id), INDEX IDX_62809DB04584665A (product_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE order_reservations (id INT AUTO_INCREMENT NOT NULL, quantity_reserved INT NOT NULL, order_item_id INT NOT NULL, warehouse_stock_id INT NOT NULL, INDEX IDX_48D3653E415FB15 (order_item_id), INDEX IDX_48D36532D8503B6 (warehouse_stock_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE orders (id INT AUTO_INCREMENT NOT NULL, status VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE products (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(255) NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE warehouse_stocks (id INT AUTO_INCREMENT NOT NULL, quantity INT NOT NULL, quantity_reserved INT NOT NULL, warehouse_id INT NOT NULL, product_id INT NOT NULL, INDEX IDX_B3A347745080ECDE (warehouse_id), INDEX IDX_B3A347744584665A (product_id), UNIQUE INDEX uniq_warehouse_product (warehouse_id, product_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE warehouses (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(255) NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE order_items ADD CONSTRAINT FK_62809DB08D9F6D38 FOREIGN KEY (order_id) REFERENCES orders (id)');
        $this->addSql('ALTER TABLE order_items ADD CONSTRAINT FK_62809DB04584665A FOREIGN KEY (product_id) REFERENCES products (id)');
        $this->addSql('ALTER TABLE order_reservations ADD CONSTRAINT FK_48D3653E415FB15 FOREIGN KEY (order_item_id) REFERENCES orders (id)');
        $this->addSql('ALTER TABLE order_reservations ADD CONSTRAINT FK_48D36532D8503B6 FOREIGN KEY (warehouse_stock_id) REFERENCES warehouse_stocks (id)');
        $this->addSql('ALTER TABLE warehouse_stocks ADD CONSTRAINT FK_B3A347745080ECDE FOREIGN KEY (warehouse_id) REFERENCES warehouses (id)');
        $this->addSql('ALTER TABLE warehouse_stocks ADD CONSTRAINT FK_B3A347744584665A FOREIGN KEY (product_id) REFERENCES products (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE order_items DROP FOREIGN KEY FK_62809DB08D9F6D38');
        $this->addSql('ALTER TABLE order_items DROP FOREIGN KEY FK_62809DB04584665A');
        $this->addSql('ALTER TABLE order_reservations DROP FOREIGN KEY FK_48D3653E415FB15');
        $this->addSql('ALTER TABLE order_reservations DROP FOREIGN KEY FK_48D36532D8503B6');
        $this->addSql('ALTER TABLE warehouse_stocks DROP FOREIGN KEY FK_B3A347745080ECDE');
        $this->addSql('ALTER TABLE warehouse_stocks DROP FOREIGN KEY FK_B3A347744584665A');
        $this->addSql('DROP TABLE order_items');
        $this->addSql('DROP TABLE order_reservations');
        $this->addSql('DROP TABLE orders');
        $this->addSql('DROP TABLE products');
        $this->addSql('DROP TABLE warehouse_stocks');
        $this->addSql('DROP TABLE warehouses');
    }
}
