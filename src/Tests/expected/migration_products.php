<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class Products extends AbstractMigration
{
    /**
     * Change Method.
     *
     * Write your reversible migrations using this method.
     *
     * More information on writing migrations is available here:
     * https://book.cakephp.org/phinx/0/en/migrations.html#the-change-method
     *
     * Remember to call "create()" or "update()" and NOT "save()" when working
     * with the Table class.
     */
    public function change(): void
    {
        $this->table('products')
            ->addColumn('description_long', 'text', ['limit' => 'TEXT_LONG', 'null' => true])
            ->addColumn('description_medium', 'text', ['limit' => 'TEXT_MEDIUM', 'null' => true])
            ->addColumn('description_tiny', 'text', ['limit' => 'TEXT_TINY', 'null' => true])
            ->addColumn('ean', 'string', ['limit' => 100])
            ->addColumn('name', 'string', ['limit' => 255])
            ->addColumn('price', 'decimal', ['default' => 0.0, 'null' => true])
            ->addColumn('sku', 'string', ['limit' => 100])
            ->addColumn('status', 'string', ['limit' => 50, 'default' => 'active', 'null' => true])
            ->addColumn('stock', 'integer', ['default' => 0, 'null' => true])
            ->addTimestamps('created_at', 'refresh_at')
            ->addIndex(['ean', 'sku'], ['unique' => true])
            ->create();
    }
}
