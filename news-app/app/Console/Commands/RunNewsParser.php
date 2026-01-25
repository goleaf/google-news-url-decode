<?php

namespace App\Console\Commands;

use App\Models\Article;
use App\Models\Category;
use App\Models\Source;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class RunNewsParser extends Command
{
    protected $signature = 'news:parse';

    protected $description = 'Rerun the news parser for all categories';

    public function handle()
    {
        $categories = Category::whereNotNull('rss_url')
            ->where('rss_url', '!=', '')
            ->get();

        if ($categories->isEmpty()) {
            $this->error('No categories with RSS URLs found.');

            return;
        }

        $this->info('Starting news parser for '.$categories->count().' categories...');

        foreach ($categories as $category) {
            $this->info("Processing Category: {$category->name}...");

            $rssUrl = $category->rss_url;
            $excludeFilePath = storage_path('app/news_exclude_temp.json');

            // Prepare Exclude List
            $existing = Article::select('original_url', 'guid')->get();
            $excludeSet = $existing->pluck('original_url')
                ->merge($existing->pluck('guid'))
                ->filter()
                ->unique()
                ->values()
                ->toArray();

            File::put($excludeFilePath, json_encode($excludeSet));

            $scriptPath = base_path('decoder.js');
            $nodePath = trim(exec('which node')) ?: 'node';

            $command = escapeshellarg($nodePath).' '.escapeshellarg($scriptPath).' '.escapeshellarg($rssUrl).' --exclude '.escapeshellarg($excludeFilePath);

            $this->info("Executing: $command");

            $handle = popen($command, 'r');

            while (! feof($handle)) {
                $line = fgets($handle);
                if (empty(trim($line))) {
                    continue;
                }

                $data = json_decode($line, true);
                if ($data) {
                    if (isset($data['event']) && $data['event'] === 'status') {
                        $this->line('<info>[Status]</info> '.$data['message']);
                    } elseif (isset($data['main'])) {
                        $this->savePacket($category->id, $data);
                    }
                } else {
                    $this->line('<comment>[Log]</comment> '.trim($line));
                }
            }

            pclose($handle);
            @unlink($excludeFilePath);
        }

        $this->info('All categories processed!');

        $this->info('Starting resolution of pending articles...');
        $this->call('news:resolve-pending');

        $this->info('Merging any remaining duplicates...');
        $this->call('news:merge-duplicates');
    }

    protected function savePacket($categoryId, $packet)
    {
        $publishedAt = ! empty($packet['pubDate']) ? \Illuminate\Support\Carbon::parse($packet['pubDate']) : null;

        $mainArticle = null;

        // Save Main Article
        if (! empty($packet['main']) && empty($packet['main']['skipped']) && $this->isActuallyDecoded($packet['main']['original_url'], $packet['main']['decoded_url'])) {
            $mainArticle = $this->saveArticle($categoryId, [
                'guid' => $packet['guid'] ?? null,
                'title' => $packet['main']['title'] ?? 'Untitled',
                'original_url' => $packet['main']['original_url'],
                'decoded_url' => $packet['main']['decoded_url'],
                'source_name' => $packet['main']['source'] ?? null,
                'source_url' => $packet['main']['source_url'] ?? null,
                'published_at' => $publishedAt,
            ]);
        }

        // Save Related Articles
        if (! empty($packet['related'])) {
            foreach ($packet['related'] as $related) {
                if (empty($related['skipped']) && $this->isActuallyDecoded($related['original_url'], $related['decoded_url'])) {
                    $this->saveArticle($categoryId, [
                        'guid' => $related['guid'] ?? $packet['guid'] ?? null, // Inherit parent guid if empty
                        'parent_id' => $mainArticle?->id, // Set parent relationship
                        'title' => $related['title'] ?? 'Untitled',
                        'original_url' => $related['original_url'],
                        'decoded_url' => $related['decoded_url'],
                        'source_name' => $related['source'] ?? null,
                        'source_url' => $related['source_url'] ?? null,
                        'published_at' => $publishedAt,
                    ]);
                }
            }
        }
    }

    protected function saveArticle($categoryId, $data)
    {
        // 1. Clean Title (Remove " - Source Name")
        if (! empty($data['source_name'])) {
            $sourceSuffix = ' - '.$data['source_name'];
            if (Str::endsWith($data['title'], $sourceSuffix)) {
                $data['title'] = Str::replaceLast($sourceSuffix, '', $data['title']);
            }

            $domain = ! empty($data['source_url']) ? $this->extractDomain($data['source_url']) : null;
            $data['source_domain'] = $domain;

            // 2. Update/Create Source
            Source::updateOrCreate(
                ['name' => $data['source_name']],
                [
                    'url' => $this->cleanUrl($data['source_url'] ?? null),
                    'domain' => $domain,
                ]
            );
        }

        $decodedUrl = $this->cleanUrl($data['decoded_url']);
        $originalUrl = $data['original_url'];

        // Double-check existence
        // We prioritize URL lookups because GUID can be shared across a cluster (inherited)
        // We only use GUID for lookup if we don't have a URL match and the GUID is unique enough
        $article = Article::where('original_url', $originalUrl)
            ->when($decodedUrl, fn ($q) => $q->orWhere('decoded_url', $decodedUrl))
            ->first();

        if ($article) {
            $updateData = [];

            // Only update decoded_url if we got a valid new one and current is empty
            if (empty($article->decoded_url) && $this->isActuallyDecoded($originalUrl, $data['decoded_url'])) {
                $updateData['decoded_url'] = $decodedUrl;
            }

            if (! empty($data['source_domain']) && empty($article->source_domain)) {
                $updateData['source_domain'] = $data['source_domain'];
            }

            if (! empty($data['parent_id']) && empty($article->parent_id)) {
                $updateData['parent_id'] = $data['parent_id'];
            }

            if (! empty($updateData)) {
                $article->update($updateData);
            }
        } else {
            try {
                $article = Article::create([
                    'parent_id' => $data['parent_id'] ?? null,
                    'original_url' => $originalUrl,
                    'title' => $data['title'],
                    'decoded_url' => $decodedUrl,
                    'source_name' => $data['source_name'] ?? null,
                    'source_url' => $this->cleanUrl($data['source_url'] ?? null),
                    'source_domain' => $data['source_domain'] ?? null,
                    'published_at' => $data['published_at'] ?? null,
                    'guid' => $data['guid'] ?? null,
                ]);

                if ($article->wasRecentlyCreated) {
                    $this->line('<fg=green>Saved article:</> '.$data['title']);
                }
            } catch (\Illuminate\Database\QueryException $e) {
                // If it fails due to unique constraint, another worker probably saved it
                // in the millisecond between our check and create.
                $article = Article::where('original_url', $originalUrl)->first();
                if ($article) {
                    // Update what we can
                    $updateData = [];
                    if (empty($article->decoded_url) && $this->isActuallyDecoded($originalUrl, $data['decoded_url'])) {
                        $updateData['decoded_url'] = $decodedUrl;
                    }
                    if (! empty($updateData)) {
                        $article->update($updateData);
                    }
                }
            }
        }

        // Attach category
        $article->categories()->syncWithoutDetaching([$categoryId]);

        return $article;
    }

    protected function cleanUrl($url)
    {
        if (empty($url)) {
            return null;
        }

        $urlData = parse_url($url);
        if (! isset($urlData['host'])) {
            return $url;
        }

        $path = $urlData['path'] ?? '';
        $cleanUrl = ($urlData['scheme'] ?? 'https').'://'.$urlData['host'].$path;

        if (isset($urlData['query'])) {
            parse_str($urlData['query'], $params);
            $keep = ['v', 'id', 'p'];
            $filteredParams = array_intersect_key($params, array_flip($keep));
            if (! empty($filteredParams)) {
                $cleanUrl .= '?'.http_build_query($filteredParams);
            }
        }

        return $cleanUrl;
    }

    protected function isActuallyDecoded($original, $decoded)
    {
        if (empty($decoded)) {
            return false;
        }

        $cleanDecoded = $this->cleanUrl($decoded);
        $cleanOriginal = $this->cleanUrl($original);

        // If they are exactly the same after cleaning, it's not decoded
        if ($cleanOriginal === $cleanDecoded) {
            return false;
        }

        // If the "decoded" URL still contains Google News patterns, it's a failure
        if (Str::contains($decoded, [
            'news.google.com/rss/articles',
            'news.google.com/articles',
            'consent.google.com',
        ])) {
            return false;
        }

        return true;
    }

    protected function extractDomain($url)
    {
        $host = parse_url($url, PHP_URL_HOST);
        if (! $host) {
            return null;
        }

        return preg_replace('/^www\./', '', $host);
    }
}
