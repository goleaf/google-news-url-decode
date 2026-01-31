<?php

namespace App\Console\Commands;

use App\Models\Article;
use App\Models\Category;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class RunNewsParser extends Command
{
    protected $signature = 'news:parse {--category-id= : Process only a specific category} {--depth=1 : Recursive search depth}';

    protected $description = 'Rerun the news parser for categories with recursive search';

    public function handle(): void
    {
        $categoryId = $this->option('category-id');

        $categories = Category::whereNotNull('rss_url')
            ->where('rss_url', '!=', '')
            ->when($categoryId, fn ($q) => $q->where('id', $categoryId))
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

            $command = escapeshellarg($nodePath).' '.escapeshellarg($scriptPath).' '.escapeshellarg((string) $rssUrl).' --exclude '.escapeshellarg($excludeFilePath);

            $this->info("Executing: $command");

            $handle = popen($command, 'r');

            while (! feof($handle)) {
                $line = fgets($handle);
                if (in_array(trim($line), ['', '0'], true)) {
                    continue;
                }

                $data = json_decode($line, true);
                if ($data) {
                    if (isset($data['event']) && $data['event'] === 'status') {
                        $this->line('<info>[Status]</info> '.$data['message']);
                    } elseif (isset($data['main'])) {
                        $depth = (int) $this->option('depth');
                        $this->savePacket([$category->id], $data, $depth, $this->output);
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

    protected function savePacket(array $categoryIds, array $packet, int $depth = 1, $output = null)
    {
        $service = app(\App\Services\NewsService::class);
        $service->saveArticleCluster($categoryIds, $packet, $depth, 0, $output);
    }

    protected function cleanUrl($url)
    {
        if (empty($url)) {
            return null;
        }

        $urlData = parse_url((string) $url);
        if (! isset($urlData['host'])) {
            return $url;
        }

        $path = $urlData['path'] ?? '';
        $cleanUrl = ($urlData['scheme'] ?? 'https').'://'.$urlData['host'].$path;

        if (isset($urlData['query'])) {
            parse_str($urlData['query'], $params);
            $keep = ['v', 'id', 'p'];
            $filteredParams = array_intersect_key($params, array_flip($keep));
            if ($filteredParams !== []) {
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
        return ! Str::contains($decoded, [
            'news.google.com/rss/articles',
            'news.google.com/articles',
            'consent.google.com',
        ]);
    }

    protected function extractDomain($url): ?string
    {
        $host = parse_url((string) $url, PHP_URL_HOST);
        if (! $host) {
            return null;
        }

        return preg_replace('/^www\./', '', $host);
    }
}
