<?php

namespace App\Console\Commands;

use App\Models\Article;
use App\Services\NewsService;
use Illuminate\Console\Command;

class DeepCrawl extends Command
{
    protected $signature = 'news:deep-crawl {--limit=50 : Number of articles to search} {--depth=1 : Search depth}';

    protected $description = 'Deeply search for related items for existing articles';

    public function handle(): void
    {
        $limit = (int) $this->option('limit');
        $depth = (int) $this->option('depth');

        $articles = Article::where('is_searched', false)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();

        if ($articles->isEmpty()) {
            $this->info('No unsearched articles found.');

            return;
        }

        $this->info("Starting deep crawl for {$articles->count()} articles...");

        $service = app(NewsService::class);

        foreach ($articles as $article) {
            $this->newLine();
            $this->info("🎯 Deep Searching for: \"{$article->title}\"");

            // crawlRelatedNews handles its own output if we pass $this->output
            $service->crawlRelatedNews($article, [], $depth, 0, $this->output);
        }

        $this->newLine();
        $this->info('Deep crawl complete!');

        $this->info('Starting resolution of pending articles...');
        $this->call('news:resolve-pending');

        $this->info('Merging any remaining duplicates...');
        $this->call('news:merge-duplicates');
    }
}
