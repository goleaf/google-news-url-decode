<?php

namespace App\Console\Commands;

use App\Models\Article;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class ResolvePendingArticles extends Command
{
    protected $signature = 'news:resolve-pending';

    protected $description = 'Try to resolve all articles that are missing a decoded URL';

    public function handle()
    {
        $pending = Article::whereNull('decoded_url')->get();

        if ($pending->isEmpty()) {
            $this->info('No pending articles to resolve.');

            return;
        }

        $this->info("Found {$pending->count()} articles to resolve...");

        $inputData = $pending->map(fn ($a) => [
            'id' => $a->id,
            'url' => $a->original_url,
        ])->values()->toArray();

        $tempFile = storage_path('app/pending_resolve_'.time().'.json');
        File::put($tempFile, json_encode($inputData));

        $scriptPath = base_path('resolver.js');
        $nodePath = trim(exec('which node')) ?: 'node';

        $command = escapeshellarg($nodePath).' '.escapeshellarg($scriptPath).' '.escapeshellarg($tempFile);

        $this->info("Executing: $command");

        $handle = popen($command, 'r');

        while (! feof($handle)) {
            $line = fgets($handle);
            if (empty(trim($line))) {
                continue;
            }

            $data = json_decode($line, true);
            if ($data && ! empty($data['decoded_url'])) {
                $article = Article::find($data['id']);
                if ($article) {
                    $cleanUrl = $this->cleanUrl($data['decoded_url']);
                    if ($this->isActuallyDecoded($article->original_url, $cleanUrl)) {
                        $article->update(['decoded_url' => $cleanUrl]);
                        $this->line('<fg=green>Resolved:</> '.$article->title);
                    }
                }
            }
        }

        pclose($handle);
        @unlink($tempFile);

        $this->info('Finished resolving pending articles!');
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
        if ($original === $decoded) {
            return false;
        }
        if (Str::contains($decoded, ['news.google.com/rss/articles', 'news.google.com/articles', 'consent.google.com'])) {
            return false;
        }

        return true;
    }
}
