<?php

namespace App\Services;

use App\Models\Article;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;

class NewsService
{
    /**
     * Save or update an article cluster (main article and related articles).
     *
     * @param  array  $categoryIds  Array of category IDs to associate.
     * @return array Array of saved article instances.
     */
    public function saveArticleCluster(array $categoryIds, array $packet, int $maxDepth = 1, int $currentDepth = 0, $output = null): array
    {
        $savedArticles = [];
        $publishedAt = empty($packet['pubDate']) ? null : Carbon::parse($packet['pubDate']);

        $indent = str_repeat('  ', $currentDepth);

        // 1. Save Main Article
        if (! empty($packet['main']) && empty($packet['main']['skipped']) && ! empty($packet['main']['decoded_url'])) {
            $sourceUrl = $packet['main']['source_url'] ?? null;
            $mainArticle = $this->saveArticle($categoryIds, [
                'guid' => $packet['guid'] ?? null,
                'title' => $packet['main']['title'] ?? 'Untitled',
                'original_url' => $packet['main']['original_url'],
                'decoded_url' => $packet['main']['decoded_url'],
                'source_name' => $packet['main']['source'] ?? null,
                'source_url' => $sourceUrl,
                'source_domain' => $this->extractDomain($sourceUrl ?: $packet['main']['decoded_url']),
                'published_at' => $publishedAt,
            ], $output, $currentDepth);

            if ($mainArticle instanceof \App\Models\Article) {
                $savedArticles[] = $mainArticle;

                if ($output) {
                    $prefix = $mainArticle->wasRecentlyCreated ? '<info>✓ [NEW]</info>' : '<comment>✓ [UPD]</comment>';
                    $output->writeln("{$indent}{$prefix} ".Str::limit($mainArticle->title, 80));
                }

                // 2. Save Related Articles
                if (! empty($packet['related'])) {
                    foreach ($packet['related'] as $related) {
                        if (empty($related['skipped']) && ! empty($related['decoded_url'])) {
                            $child = $this->saveArticle($categoryIds, [
                                'title' => $related['title'] ?? 'Untitled',
                                'original_url' => $related['original_url'],
                                'decoded_url' => $related['decoded_url'],
                                'source_name' => $related['source'] ?? null,
                                'source_domain' => $this->extractDomain($related['decoded_url']),
                                'published_at' => $publishedAt,
                            ], $output, $currentDepth + 1);

                            if ($child instanceof \App\Models\Article) {
                                $mainArticle->relatedArticles()->syncWithoutDetaching([$child->id]);
                                $savedArticles[] = $child;
                                if ($output) {
                                    $cPrefix = $child->wasRecentlyCreated ? '<info>+</info>' : '<comment>~</comment>';
                                    $output->writeln("{$indent}  {$cPrefix} Related: ".Str::limit($child->title, 70));
                                }
                            }
                        }
                    }
                }

                // 3. Trigger Recursive Search if within depth limits
                if ($currentDepth < $maxDepth) {
                    $this->crawlRelatedNews($mainArticle, $categoryIds, $maxDepth, $currentDepth + 1, $output);
                }
            }
        }

        return $savedArticles;
    }

    /**
     * Search for an article's title on Google News RSS and ingest the results.
     * Inherits ALL categories from the original article.
     */
    public function crawlRelatedNews(Article $article, array $categoryIds = [], int $maxDepth = 1, int $currentDepth = 0, $output = null): void
    {
        // Don't search if already searched recently (within 24h)
        if ($article->is_searched && $article->last_searched_at && Carbon::parse($article->last_searched_at)->isAfter(now()->subDay())) {
            return;
        }

        // If no category IDs passed, inherit from the article
        if ($categoryIds === []) {
            $categoryIds = $article->categories()->pluck('categories.id')->toArray();
        }

        $indent = str_repeat('  ', $currentDepth);

        if ($output) {
            $output->writeln("{$indent}<fg=blue;options=bold>🔎 Recursive Search:</> \"{$article->title}\"");
        }

        Log::info("Recursively searching for: \"{$article->title}\" (Depth: {$currentDepth})");

        $searchQuery = urlencode($article->title);
        $rssUrl = "https://news.google.com/rss/search?q={$searchQuery}&hl=ru&gl=RU&ceid=RU%3Aru";

        $excludeFile = storage_path('app/exclude_recursive_'.time().'_'.Str::random(5).'.json');

        // Prepare global exclude list to avoid re-parsing known items
        $existing = Article::select('guid', 'original_url')->get();
        $set = $existing->pluck('guid')->merge($existing->pluck('original_url'))->filter()->unique()->values()->toArray();
        File::put($excludeFile, json_encode($set));

        $scriptPath = base_path('decoder.js');
        $nodePath = trim(exec('which node')) ?: 'node';
        $command = escapeshellarg($nodePath).' '.escapeshellarg($scriptPath).' '.escapeshellarg($rssUrl).' --exclude '.escapeshellarg($excludeFile);

        $buffer = '';
        Process::path(base_path())
            ->timeout(600) // 10 mins
            ->run($command, function (string $type, string $outputStr) use (&$buffer, $categoryIds, $maxDepth, $currentDepth, $output): void {
                $buffer .= $outputStr;
                while (($pos = strpos($buffer, "\n")) !== false) {
                    $line = substr($buffer, 0, $pos);
                    $buffer = substr($buffer, $pos + 1);

                    if (trim($line) === '') {
                        continue;
                    }

                    $data = json_decode($line, true);
                    if ($data && isset($data['main'])) {
                        $this->saveArticleCluster($categoryIds, $data, $maxDepth, $currentDepth, $output);
                    }
                }
            });

        @unlink($excludeFile);

        $article->update([
            'is_searched' => true,
            'last_searched_at' => now(),
        ]);
    }

    /**
     * Save or update a single article.
     *
     * @param  array  $categoryIds  Array of category IDs to sync.
     */
    public function saveArticle(array $categoryIds, array $data, $output = null, int $depth = 0): ?Article
    {
        $uniqueKey = empty($data['guid']) ? ['original_url' => $data['original_url']] : ['guid' => $data['guid']];

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
                    'original_url' => $data['original_url'],
                    'title' => $title,
                    'decoded_url' => $data['decoded_url'],
                    'source_name' => $data['source_name'] ?? null,
                    'source_url' => $data['source_url'] ?? null,
                    'source_domain' => $data['source_domain'] ?? $this->extractDomain($data['decoded_url'] ?? null),
                    'published_at' => $data['published_at'] ?? null,
                    'guid' => $data['guid'] ?? null,
                ]
            );

            if ($sourceId) {
                $article->sources()->syncWithoutDetaching([$sourceId]);
            }

            if ($categoryIds !== []) {
                $article->categories()->syncWithoutDetaching($categoryIds);
            }

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
