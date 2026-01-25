<?php

namespace App\Console\Commands;

use App\Models\Article;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MergeDuplicates extends Command
{
    protected $signature = 'news:merge-duplicates';

    protected $description = 'Find and merge articles with duplicate URLs';

    public function handle()
    {
        $this->info('Scanning for duplicates based on original_url...');

        $duplicates = Article::select('original_url')
            ->groupBy('original_url')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('original_url');

        if ($duplicates->isEmpty()) {
            $this->info('No duplicates found.');

            return;
        }

        $this->info('Found '.$duplicates->count().' URLs with duplicates. Merging...');

        foreach ($duplicates as $url) {
            $records = Article::where('original_url', $url)
                ->orderByDesc('guid')
                ->orderByDesc('decoded_url')
                ->orderBy('created_at')
                ->get();

            $master = $records->shift();

            // Get master categories
            $masterCategoryIds = DB::table('article_category')
                ->where('article_id', $master->id)
                ->pluck('category_id')
                ->toArray();

            foreach ($records as $duplicate) {
                // 1. Get duplicate categories
                $duplicateCategoryIds = DB::table('article_category')
                    ->where('article_id', $duplicate->id)
                    ->pluck('category_id')
                    ->toArray();

                // 2. Attach missing categories to master
                $newCategories = array_diff($duplicateCategoryIds, $masterCategoryIds);
                foreach ($newCategories as $catId) {
                    DB::table('article_category')->insert([
                        'article_id' => $master->id,
                        'category_id' => $catId,
                    ]);
                    $masterCategoryIds[] = $catId;
                }

                // 3. Move children
                Article::where('parent_id', $duplicate->id)
                    ->update(['parent_id' => $master->id]);

                // 4. Delete the duplicate article record
                // (article_category records should be deleted by cascading if set,
                // but let's do it manually if not sure)
                DB::table('article_category')->where('article_id', $duplicate->id)->delete();
                $duplicate->delete();

                $this->warn(" -> Merged ID: {$duplicate->id} into Master ID: {$master->id}");
            }
        }

        $this->info('Deduplication complete!');
    }
}
