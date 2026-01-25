<?php

namespace App\Filament\Pages;

use App\Models\Article;
use App\Models\Category;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Process;

class NewsProcessor extends Page
{
    public static function canAccess(): bool
    {
        return true;
    }

    protected static ?string $navigationIcon = 'heroicon-o-cpu-chip';

    protected static string $view = 'filament.pages.news-processor';

    protected static ?string $slug = 'news-processor';

    protected static bool $shouldRegisterNavigation = false;

    public $categories = [];

    public $logs = [];

    public $isProcessing = false;

    public $currentCategoryIndex = 0;

    public $processedCount = 0;

    public $savedCount = 0;

    public function mount()
    {
        $this->categories = Category::whereNotNull('rss_url')
            ->where('rss_url', '!=', '')
            ->get()
            ->map(fn ($c) => ['id' => $c->id, 'name' => $c->name, 'rss_url' => $c->rss_url])
            ->toArray();
    }

    public $status = 'idle'; // idle, processing

    public $pid = null;

    public $logFilePath = null;

    public $excludeFilePath = null;

    public $logOffset = 0;

    public function startProcessing()
    {
        $this->isProcessing = true;
        $this->status = 'starting';
        $this->currentCategoryIndex = 0;
        $this->processedCount = 0;
        $this->savedCount = 0;
        $this->logs = [];
        $this->logOffset = 0;

        $this->processNextCategory();
    }

    public function processNextCategory()
    {
        if ($this->currentCategoryIndex >= count($this->categories)) {
            $this->isProcessing = false;
            $this->status = 'idle';
            $this->log('All categories processed.', 'success');

            return;
        }

        $categoryData = $this->categories[$this->currentCategoryIndex];
        $this->log("Starting Category: {$categoryData['name']}...", 'info');

        $rssUrl = $categoryData['rss_url'];

        if (empty($rssUrl)) {
            $this->log("Skipping {$categoryData['name']}: No RSS URL.", 'warning');
            $this->currentCategoryIndex++;
            $this->processNextCategory();

            return;
        }

        // 1. Prepare Log File
        $this->logFilePath = storage_path("app/news_process_{$categoryData['id']}_".time().'.log');
        $this->logOffset = 0;
        touch($this->logFilePath);

        // 2. Prepare Exclude List (Global check)
        $this->excludeFilePath = storage_path('app/news_exclude_global_'.time().'.json');

        // Use global check to prevent scanning ANYTHING ever scanned before
        $existing = Article::select('original_url', 'guid')->get();

        $excludeSet = $existing->pluck('original_url')
            ->merge($existing->pluck('guid'))
            ->filter()
            ->unique()
            ->values()
            ->toArray();

        file_put_contents($this->excludeFilePath, json_encode($excludeSet));
        $this->log('Scanning RSS against '.count($excludeSet).' globally known items...', 'gray');

        // 3. Build Command (Concurrency increased in decoder.js)
        $basePath = base_path();
        $scriptPath = $basePath.'/decoder.js';

        $nodePath = trim(exec('which node')) ?: 'node';
        if (empty($nodePath) || ! is_executable($nodePath)) {
            $nodePath = '/usr/local/bin/node';
            if (! file_exists($nodePath)) {
                $nodePath = 'node';
            }
        }

        $nodeCommand = "$nodePath ".escapeshellarg($scriptPath).' '.escapeshellarg($rssUrl).' --exclude '.escapeshellarg($this->excludeFilePath);

        // Run in background
        $command = "nohup $nodeCommand > ".escapeshellarg($this->logFilePath).' 2>&1 & echo $!';

        $pid = exec($command);
        $this->pid = (int) $pid;
        $this->status = 'processing';
    }

    public function checkProgress()
    {
        if ($this->status !== 'processing' || ! $this->logFilePath) {
            return;
        }

        if (! file_exists($this->logFilePath)) {
            return;
        }

        // Read new content
        clearstatcache(false, $this->logFilePath);
        $fileSize = filesize($this->logFilePath);

        if ($fileSize > $this->logOffset) {
            $content = file_get_contents($this->logFilePath, false, null, $this->logOffset);
            $this->logOffset = $fileSize;

            $lines = explode("\n", $content);
            foreach ($lines as $line) {
                if (trim($line) === '') {
                    continue;
                }

                $data = json_decode($line, true);

                if ($data) {
                    if (isset($data['event']) && $data['event'] === 'status') {
                        $this->log($data['message'], 'info');
                    } elseif (isset($data['main'])) {
                        $service = new \App\Services\NewsService;
                        $saved = $service->saveArticleCluster($this->categories[$this->currentCategoryIndex]['id'], $data);

                        foreach ($saved as $article) {
                            if ($article->wasRecentlyCreated) {
                                $this->savedCount++;
                                $this->log('Saved: '.mb_substr($article->title, 0, 50).'...', 'success');
                            }
                        }
                    }
                } else {
                    // Raw log (stderr or debug)
                    if (trim($line)) {
                        $this->log($line, 'gray');
                    }
                }
            }
        }

        // Check if process is still running
        if (! $this->isRunning($this->pid)) {
            $this->status = 'finishing';

            // Cleanup
            @unlink($this->logFilePath);
            @unlink($this->excludeFilePath);

            $this->log("Finished Category: {$this->categories[$this->currentCategoryIndex]['name']}", 'info');

            $this->currentCategoryIndex++;
            $this->processedCount++;

            $this->processNextCategory();
        }
    }

    protected function isRunning($pid)
    {
        if (! $pid) {
            return false;
        }
        $result = exec("ps -p $pid -o pid=");

        return trim($result) == $pid;
    }

    protected function log($message, $type = 'info')
    {
        $color = match ($type) {
            'success' => 'text-green-500',
            'error' => 'text-red-500',
            'warning' => 'text-yellow-500',
            'gray' => 'text-gray-400',
            default => 'text-gray-900 dark:text-gray-100',
        };

        $timestamp = now()->format('H:i:s');

        // Append log using stream() (replace = false)
        $html = "<div class='{$color} font-mono text-sm'>[{$timestamp}] {$message}</div>";

        if (method_exists($this, 'stream')) {
            try {
                $this->stream('logs', $html, false);
            } catch (\Exception $e) {
                // Ignore if streaming is not available
            }
        }
    }
}
