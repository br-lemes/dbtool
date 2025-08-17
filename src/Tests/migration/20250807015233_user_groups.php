<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class UserGroups extends AbstractMigration
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
        $this->table('user_groups', ['id' => false, 'primary_key' => ['id', 'user_id']])
            ->addColumn('id', 'integer')
            ->addColumn('user_id', 'biginteger')
            ->addTimestamps(false, 'updated_at')
            ->addColumn('key_id', 'integer', ['null' => true])
            ->addIndex('key_id')
            ->create();
    }
}
