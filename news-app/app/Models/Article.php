<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Article extends Model
{
    use HasFactory;

    protected $fillable = ['title', 'original_url', 'decoded_url', 'source_name', 'source_url', 'source_domain', 'guid', 'published_at'];

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class);
    }

    public function sources(): BelongsToMany
    {
        return $this->belongsToMany(Source::class, 'article_source');
    }

    // Many-to-many relationships
    public function parentArticles(): BelongsToMany
    {
        return $this->belongsToMany(Article::class, 'article_related', 'related_id', 'parent_id')->withTimestamps();
    }

    public function relatedArticles(): BelongsToMany
    {
        return $this->belongsToMany(Article::class, 'article_related', 'parent_id', 'related_id')->withTimestamps();
    }
}
