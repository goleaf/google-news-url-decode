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

    public function handle(): void
    {
        $articles = Article::all();
        $this->info("Processing {$articles->count()} articles...");

        $bar = $this->output->createProgressBar($articles->count());
        $bar->start();

        foreach ($articles as $article) {
            // 1. Handle Source
            if ($article->source_name) {
                $domain = empty($article->source_url) ? null : $this->extractDomain($article->source_url);
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
                $updateData = [];

                if (Str::endsWith($article->title, $sourceSuffix)) {
                    $updateData['title'] = Str::replaceLast($sourceSuffix, '', $article->title);
                }

                if ($domain) {
                    $updateData['source_domain'] = $domain;
                }

                // 3. Inherit parent GUID if empty and has parents
                if (empty($article->guid) && $article->parentArticles()->exists()) {
                    $parent = $article->parentArticles()->first();
                    if ($parent && ! empty($parent->guid)) {
                        $updateData['guid'] = $parent->guid;
                    }
                }

                if (! empty($updateData)) {
                    $article->update($updateData);
                }

                // Sync Source
                $article->sources()->syncWithoutDetaching([$source->id]);
            }
            $bar->advance();
        }

        $bar->finish();
        $this->newline();
        $this->info('Done! Created '.Source::count().' sources.');
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
