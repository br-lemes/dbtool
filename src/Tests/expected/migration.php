<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class Posts extends AbstractMigration
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
        $this->table('posts')
            ->addColumn('user_id', 'biginteger')
            ->addColumn('content', 'text', ['null' => true])
            ->addColumn('publish_date', 'date', ['null' => true])
            ->addColumn('title', 'string', ['limit' => 200])
            ->addIndex(['user_id', 'title'])
            ->create();
    }
}
