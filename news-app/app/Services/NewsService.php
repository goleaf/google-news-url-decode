<?php

namespace App\Services;

use App\Models\Article;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class NewsService
{
    /**
     * Save or update an article cluster (main article and related articles).
     *
     * @return array Array of saved article instances.
     */
    public function saveArticleCluster(int $categoryId, array $packet): array
    {
        $savedArticles = [];
        $publishedAt = ! empty($packet['pubDate']) ? Carbon::parse($packet['pubDate']) : null;

        // 1. Save Main Article
        if (! empty($packet['main']) && empty($packet['main']['skipped']) && ! empty($packet['main']['decoded_url'])) {
            $sourceUrl = $packet['main']['source_url'] ?? null;
            $mainArticle = $this->saveArticle($categoryId, [
                'guid' => $packet['guid'] ?? null,
                'title' => $packet['main']['title'] ?? 'Untitled',
                'original_url' => $packet['main']['original_url'],
                'decoded_url' => $packet['main']['decoded_url'],
                'source_name' => $packet['main']['source'] ?? null,
                'source_url' => $sourceUrl,
                'source_domain' => $this->extractDomain($sourceUrl ?: $packet['main']['decoded_url']),
                'published_at' => $publishedAt,
            ]);

            if ($mainArticle) {
                $savedArticles[] = $mainArticle;

                // 2. Save Related Articles
                if (! empty($packet['related'])) {
                    foreach ($packet['related'] as $related) {
                        if (empty($related['skipped']) && ! empty($related['decoded_url'])) {
                            $child = $this->saveArticle($categoryId, [
                                'parent_id' => $mainArticle->id,
                                'title' => $related['title'] ?? 'Untitled',
                                'original_url' => $related['original_url'],
                                'decoded_url' => $related['decoded_url'],
                                'source_name' => $related['source'] ?? null,
                                'source_domain' => $this->extractDomain($related['decoded_url']),
                                'published_at' => $publishedAt,
                            ]);

                            if ($child) {
                                $savedArticles[] = $child;
                            }
                        }
                    }
                }
            }
        }

        return $savedArticles;
    }

    /**
     * Save or update a single article.
     */
    public function saveArticle(int $categoryId, array $data): ?Article
    {
        $uniqueKey = ! empty($data['guid']) ? ['guid' => $data['guid']] : ['original_url' => $data['original_url']];

        // Clean Title
        $title = $data['title'];
        if (! empty($data['source_name'])) {
            $sourceSuffix = ' - '.$data['source_name'];
            if (\Illuminate\Support\Str::endsWith($title, $sourceSuffix)) {
                $title = \Illuminate\Support\Str::replaceLast($sourceSuffix, '', $title);
            }
        }

        // Handle Source
        $sourceId = null;
        if (! empty($data['source_name'])) {
            $source = \App\Models\Source::updateOrCreate(
                ['name' => $data['source_name']],
                [
                    'url' => $data['source_url'] ?? null,
                    'domain' => $data['source_domain'] ?? $this->extractDomain($data['decoded_url'] ?? null),
                ]
            );
            $sourceId = $source->id;
        }

        try {
            $article = Article::updateOrCreate(
                $uniqueKey,
                [
                    'parent_id' => $data['parent_id'] ?? null,
                    'original_url' => $data['original_url'],
                    'title' => $title,
                    'decoded_url' => $data['decoded_url'],
                    'source_id' => $sourceId,
                    'source_name' => $data['source_name'] ?? null,
                    'source_url' => $data['source_url'] ?? null,
                    'source_domain' => $data['source_domain'] ?? $this->extractDomain($data['decoded_url'] ?? null),
                    'published_at' => $data['published_at'] ?? null,
                    'guid' => $data['guid'] ?? null,
                ]
            );

            $article->categories()->syncWithoutDetaching([$categoryId]);

            return $article;
        } catch (\Exception $e) {
            Log::error('Failed to save article: '.$e->getMessage());

            return null;
        }
    }

    /**
     * Extract domain from URL.
     */
    public function extractDomain(?string $url): ?string
    {
        if (! $url) {
            return null;
        }

        return parse_url($url, PHP_URL_HOST);
    }
}
