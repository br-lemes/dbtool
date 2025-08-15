<?php
declare(strict_types=1);

namespace DBTool\Tests;

use DBTool\Database\DatabaseConnection;
use Exception;
use PHPUnit\Framework\TestCase;

class PgSQLDriverTest extends TestCase
{
    function testGetTableSchema(): void
    {
        $db = new DatabaseConnection('test-pgsql');

        $actual = $db->getTableSchema('posts');
        $expected = <<<SQL
        CREATE TABLE "public"."posts" (
            "id" bigserial NOT NULL,
            "user_id" bigint NOT NULL,
            "content" text,
            "publish_date" date,
            "title" character varying(200) NOT NULL,
            "created_at" timestamp without time zone NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT "posts_pkey" PRIMARY KEY ("id")
        );
        CREATE INDEX "posts_user_id" ON "public"."posts" ("user_id");
        SQL;
        $this->assertEquals($expected, $actual);

        $actual = $db->getTableSchema('products');
        $expected = <<<SQL
        CREATE TABLE "public"."products" (
            "id" serial NOT NULL,
            "description_long" text,
            "description_medium" text,
            "description_tiny" text,
            "ean" character varying(100) NOT NULL,
            "name" character varying(255) NOT NULL,
            "price" numeric(10, 2) DEFAULT 0.00,
            "sku" character varying(100) NOT NULL,
            "status" character varying(50) DEFAULT 'active'::character varying,
            "stock" integer DEFAULT 0,
            "created_at" timestamp without time zone NOT NULL DEFAULT CURRENT_TIMESTAMP,
            "refresh_at" timestamp without time zone,
            CONSTRAINT "products_ean_sku" UNIQUE ("ean", "sku"),
            CONSTRAINT "products_pkey" PRIMARY KEY ("id")
        );
        SQL;
        $this->assertEquals($expected, $actual);
    }

    function testInsertInto(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Error inserting into table 'users':");

        $duplicatedEmailData = [
            [
                'email' => 'john.doe@example.com',
                'name' => 'John Doe',
                'password_hash' => '$2y$10$abc123hashedPassword',
                'created_at' => '2025-07-24 09:00:00',
            ],
        ];

        $db = new DatabaseConnection('test-pgsql');
        $db->insertInto('users', $duplicatedEmailData);
    }
}
