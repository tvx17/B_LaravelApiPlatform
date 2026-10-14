<?php

namespace Tests\Fixtures;

use Cviebrock\EloquentSluggable\Sluggable;
use Illuminate\Database\Eloquent\Model;

class SluggableArticle extends Model
{
    use Sluggable;

    protected $table = 'test_sluggable_articles';

    protected $fillable = ['title'];

    public function sluggable(): array
    {
        return ['slug' => ['source' => 'title', 'maxLength' => 255]];
    }
}
