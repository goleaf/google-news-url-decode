<?php

namespace App\Console\Commands;

use App\Models\Article;
use App\Models\Category;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;

class FetchNews extends Command
{
    protected $signature = 'news:fetch {--depth=1 : Recursive search depth}';

    protected $description = 'Fetch and decode news from Categories with recursive search';

    public function handle(): void
    {
        $categories = Category::whereNotNull('rss_url')->get();

        foreach ($categories as $category) {
            $this->info("Processing Category: {$category->name}");

            if (! file_exists(base_path('node_modules'))) {
                $this->error('Node modules not found.');

                return;
            }

            // 1. Generate Exclude File (Global)
            $excludeFile = storage_path('app/exclude_cmd_global.json');
            $existing = Article::select('guid', 'original_url')->get();
            $set = $existing->pluck('guid')->merge($existing->pluck('original_url'))->filter()->unique()->values()->toArray();
            file_put_contents($excludeFile, json_encode($set));

            $this->info('Scanning RSS against '.count($set).' globally known items.');

            $command = 'node decoder.js '.escapeshellarg((string) $category->rss_url).' --exclude '.escapeshellarg($excludeFile);
            $buffer = '';

            // Increase timeout as resolving multiple links takes time
            Process::path(base_path())
                ->timeout(1800) // 30 mins
                ->run($command, function (string $type, string $output) use (&$buffer, $category): void {
                    $buffer .= $output;

                    while (($pos = strpos($buffer, "\n")) !== false) {
                        $line = substr($buffer, 0, $pos);
                        $buffer = substr($buffer, $pos + 1);

                        if (trim($line) === '') {
                            continue;
                        }

                        $data = json_decode($line, true);
                        if ($data) {
                            if (isset($data['event']) && $data['event'] === 'status') {
                                $this->info($data['message']);

                                continue;
                            }

                            if (isset($data['main'])) {
                                $service = new \App\Services\NewsService;
                                $depth = (int) $this->option('depth');
                                $service->saveArticleCluster([$category->id], $data, $depth, 0, $this->output);
                            }
                        }
                    }
                });

            @unlink($excludeFile);
        }

        $this->info('Done.');
    }
}
