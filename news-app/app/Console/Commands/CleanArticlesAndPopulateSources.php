<?php

namespace App\Console\Commands;

use App\Models\Article;
use App\Models\Source;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class CleanArticlesAndPopulateSources extends Command
{
    protected $signature = 'news:clean-and-populate';

    protected $description = 'Clean article titles by removing source names and populate sources table';

    public function handle()
    {
        $articles = Article::all();
        $this->info("Processing {$articles->count()} articles...");

        $bar = $this->output->createProgressBar($articles->count());
        $bar->start();

        foreach ($articles as $article) {
            // 1. Handle Source
            if ($article->source_name) {
                $domain = ! empty($article->source_url) ? $this->extractDomain($article->source_url) : null;
                \Illuminate\Support\Facades\Log::info("Cleaning article {$article->id}: URL={$article->source_url}, Domain={$domain}");

                $source = Source::updateOrCreate(
                    ['name' => $article->source_name],
                    [
                        'url' => $article->source_url,
                        'domain' => $domain,
                    ]
                );

                // 2. Clean Title
                // Google News titles are usually "Title - Source Name"
                $sourceSuffix = ' - '.$article->source_name;
                $updateData = [
                    'source_id' => $source->id,
                ];

                if (Str::endsWith($article->title, $sourceSuffix)) {
                    $updateData['title'] = Str::replaceLast($sourceSuffix, '', $article->title);
                }

                if ($domain) {
                    $updateData['source_domain'] = $domain;
                }

                // 3. Inherit parent GUID if empty and has parent
                if (empty($article->guid) && $article->parent_id) {
                    $parent = Article::find($article->parent_id);
                    if ($parent && ! empty($parent->guid)) {
                        $updateData['guid'] = $parent->guid;
                    }
                }

                if (! empty($updateData)) {
                    $article->update($updateData);
                }
            }
            $bar->advance();
        }

        $bar->finish();
        $this->newline();
        $this->info('Done! Created '.Source::count().' sources.');
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
