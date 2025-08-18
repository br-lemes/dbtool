<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class PostTags extends AbstractMigration
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
        $this->table('post_tags', ['id' => false])
            ->addColumn('post_id', 'biginteger')
            ->addColumn('tag_id', 'integer')
            ->addTimestamps(false, 'refresh_at')
            ->addIndex(['post_id', 'tag_id'], ['unique' => true])
            ->create();
    }
}
