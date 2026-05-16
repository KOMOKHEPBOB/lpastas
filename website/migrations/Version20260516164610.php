<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260516164610 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add MySQL CHECK constraints on quantity & quantity_reserved fields';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE warehouse_locations
            ADD CONSTRAINT chk_quantity_non_negative
                CHECK (quantity >= 0),
            ADD CONSTRAINT chk_quantity_reserved_non_negative
                CHECK (quantity_reserved >= 0),
            ADD CONSTRAINT chk_reserved_not_exceeds_quantity
                CHECK (quantity_reserved <= quantity)
        ');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE warehouse_locations
            DROP CONSTRAINT chk_reserved_not_exceeds_quantity,
            DROP CONSTRAINT chk_quantity_reserved_non_negative,
            DROP CONSTRAINT chk_quantity_non_negative
        ');
    }
}
