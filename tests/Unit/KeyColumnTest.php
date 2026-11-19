<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\Translatable\Tests\Unit;

use Pharaonic\Laravel\Translatable\Console\SchemaGenerator;

$types = (static function (): array {
    return [
        'mysql bigIncrements (5.7)' => ['bigint(20) unsigned', 'mysql', "\$table->unsignedBigInteger('post_id')"],
        'mysql bigIncrements (8.0)' => ['bigint unsigned', 'mysql', "\$table->unsignedBigInteger('post_id')"],
        'mysql increments' => ['int(10) unsigned', 'mysql', "\$table->unsignedInteger('post_id')"],
        'mysql signed int' => ['int', 'mysql', "\$table->integer('post_id')"],
        'mysql smallIncrements' => ['smallint unsigned', 'mariadb', "\$table->unsignedSmallInteger('post_id')"],
        'mysql mediumIncrements' => ['mediumint(8) unsigned', 'mysql', "\$table->unsignedMediumInteger('post_id')"],
        'mysql uuid' => ['char(36)', 'mysql', "\$table->char('post_id', 36)"],
        'mysql ulid' => ['char(26)', 'mysql', "\$table->char('post_id', 26)"],
        'mysql string key' => ['varchar(191)', 'mysql', "\$table->string('post_id', 191)"],
        'pgsql increments' => ['integer', 'pgsql', "\$table->integer('post_id')"],
        'pgsql bigIncrements' => ['bigint', 'pgsql', "\$table->bigInteger('post_id')"],
        'pgsql uuid' => ['uuid', 'pgsql', "\$table->uuid('post_id')"],
        'pgsql string key' => ['character varying(255)', 'pgsql', "\$table->string('post_id', 255)"],
        'pgsql char key' => ['character(26)', 'pgsql', "\$table->char('post_id', 26)"],
        'sqlsrv uuid' => ['uniqueidentifier', 'sqlsrv', "\$table->uuid('post_id')"],
        'sqlsrv string key' => ['nvarchar(255)', 'sqlsrv', "\$table->string('post_id', 255)"],
        'sqlite increments' => ['integer', 'sqlite', "\$table->unsignedBigInteger('post_id')"],
        'sqlite string key' => ['varchar', 'sqlite', "\$table->string('post_id')"],
        'unknown' => ['binary(16)', 'mysql', null],
    ];
})();

test('the foreign key column matches the model key type', function (string $type, string $driver, ?string $expected) {
    $this->assertSame($expected, SchemaGenerator::keyColumnFor($type, 'post_id', $driver));
})->with($types);
