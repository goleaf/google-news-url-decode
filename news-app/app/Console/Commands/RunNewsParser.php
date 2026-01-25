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
    protected $signature = 'news:parse {--category-id= : Process only a specific category}';

    protected $description = 'Rerun the news parser for categories';

    public function handle()
    {
        $categoryId = $this->option('category-id');

        $categories = Category::whereNotNull('rss_url')
            ->where('rss_url', '!=', '')
            ->when($categoryId, fn($q) => $q->where('id', $categoryId))
            ->get();

        if ($categories->isEmpty()) {
            $this->error('No categories with RSS URLs found.');

            return;
        }

        $this->info('Starting news parser for ' . $categories->count() . ' categories...');

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

            $command = escapeshellarg($nodePath) . ' ' . escapeshellarg($scriptPath) . ' ' . escapeshellarg($rssUrl) . ' --exclude ' . escapeshellarg($excludeFilePath);

            $this->info("Executing: $command");

            $handle = popen($command, 'r');

            while (!feof($handle)) {
                $line = fgets($handle);
                if (empty(trim($line))) {
                    continue;
                }

                $data = json_decode($line, true);
                if ($data) {
                    if (isset($data['event']) && $data['event'] === 'status') {
                        $this->line('<info>[Status]</info> ' . $data['message']);
                    } elseif (isset($data['main'])) {
                        $this->savePacket($category->id, $data);
                    }
                } else {
                    $this->line('<comment>[Log]</comment> ' . trim($line));
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
        $service = app(\App\Services\NewsService::class);
        $service->saveArticleCluster($categoryId, $packet);
    }


    protected function cleanUrl($url)
    {
        if (empty($url)) {
            return null;
        }

        $urlData = parse_url($url);
        if (!isset($urlData['host'])) {
            return $url;
        }

        $path = $urlData['path'] ?? '';
        $cleanUrl = ($urlData['scheme'] ?? 'https') . '://' . $urlData['host'] . $path;

        if (isset($urlData['query'])) {
            parse_str($urlData['query'], $params);
            $keep = ['v', 'id', 'p'];
            $filteredParams = array_intersect_key($params, array_flip($keep));
            if (!empty($filteredParams)) {
                $cleanUrl .= '?' . http_build_query($filteredParams);
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
        if (
            Str::contains($decoded, [
                'news.google.com/rss/articles',
                'news.google.com/articles',
                'consent.google.com',
            ])
        ) {
            return false;
        }

        return true;
    }

    protected function extractDomain($url)
    {
        $host = parse_url($url, PHP_URL_HOST);
        if (!$host) {
            return null;
        }

        return preg_replace('/^www\./', '', $host);
    }
}
