<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MigrationsGeneratorIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_sqlite_schema_export_can_be_replayed_without_changing_the_migration_log(): void
    {
        $directory = storage_path('framework/testing/schema-export-'.bin2hex(random_bytes(8)));
        $migrationLog = DB::table('migrations')->get()->toArray();
        Schema::create('test_generator_records', function (Blueprint $table): void {
            $table->id();
            $table->string('slug')->unique();
            $table->text('description')->nullable();
        });

        try {
            $this->artisan('migrate:generate', [
                '--connection' => 'sqlite',
                '--tables' => 'test_generator_records',
                '--path' => $directory,
                '--skip-log' => true,
                '--no-interaction' => true,
            ])->assertSuccessful();

            $files = File::glob($directory.'/*_create_test_generator_records_table.php');
            $this->assertCount(1, $files);
            $this->assertEquals($migrationLog, DB::table('migrations')->get()->toArray());

            Schema::drop('test_generator_records');
            $migration = require $files[0];
            $migration->up();

            $this->assertTrue(Schema::hasColumns('test_generator_records', ['id', 'slug', 'description']));
            $this->assertTrue(Schema::hasIndex('test_generator_records', ['slug'], 'unique'));
            DB::table('test_generator_records')->insert(['slug' => 'export-test']);
            $this->assertNull(DB::table('test_generator_records')->value('description'));

            $migration->down();
            $this->assertFalse(Schema::hasTable('test_generator_records'));
            $this->assertEquals($migrationLog, DB::table('migrations')->get()->toArray());
        } finally {
            Schema::dropIfExists('test_generator_records');
            File::deleteDirectory($directory);
        }
    }
}
