<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SavedArticle extends Model
{
    protected $fillable = [
        'user_id',
        'article_id',
        'headline',
        'summary',
        'source',
        'url',
        'image',
        'category',
        'published_at',
    ];
}
