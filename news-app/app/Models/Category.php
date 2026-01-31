<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Category extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'rss_url'];

    public static function syncFromConfig(): void
    {
        $configCategories = config('news.categories', []);

        foreach ($configCategories as $name => $url) {
            $rssUrl = str_replace('/topics/', '/rss/topics/', $url);
            static::updateOrCreate(
                ['name' => $name],
                ['rss_url' => $rssUrl]
            );
        }
    }

    public function parentCategories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'category_related', 'category_id', 'parent_id')->withTimestamps();
    }

    public function subCategories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'category_related', 'parent_id', 'category_id')->withTimestamps();
    }

    public function articles(): BelongsToMany
    {
        return $this->belongsToMany(Article::class);
    }
}
