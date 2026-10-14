<?php

namespace Tests\Feature;

use Cviebrock\EloquentSluggable\ServiceProvider;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\Fixtures\SluggableArticle;
use Tests\TestCase;

class SluggableIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_model_events_generate_unique_slugs_and_preserve_existing_urls(): void
    {
        $this->assertNotNull($this->app->getProvider(ServiceProvider::class));
        Schema::create('test_sluggable_articles', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->timestamps();
        });
        try {
            $first = SluggableArticle::create(['title' => 'Laravel API']);
            $second = SluggableArticle::create(['title' => 'Laravel API']);
            $this->assertSame('laravel-api', $first->fresh()->slug);
            $this->assertSame('laravel-api-2', $second->fresh()->slug);
            $first->update(['title' => 'Updated API title']);
            $this->assertSame('laravel-api', $first->fresh()->slug);
        } finally {
            Schema::dropIfExists('test_sluggable_articles');
        }
    }
}
