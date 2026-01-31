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

    public function mount(): void
    {
        Category::syncFromConfig();

        $this->categories = Category::whereNotNull('rss_url')
            ->where('rss_url', '!=', '')
            ->get()
            ->map(fn ($c): array => ['id' => $c->id, 'name' => $c->name, 'rss_url' => $c->rss_url])
            ->toArray();

        // Check for existing state
        $state = \Illuminate\Support\Facades\Cache::get($this->getPersistentStateKey());
        if ($state && $this->isRunning($state['pid'] ?? null)) {
            $this->isProcessing = true;
            $this->status = $state['status'] ?? 'processing';
            $this->pid = $state['pid'];
            $this->logFilePath = $state['logFilePath'];
            $this->excludeFilePath = $state['excludeFilePath'];
            $this->currentCategoryIndex = $state['currentCategoryIndex'] ?? 0;
            $this->processedCount = $state['processedCount'] ?? 0;
            $this->savedCount = $state['savedCount'] ?? 0;
            $this->logOffset = $state['logOffset'] ?? 0;
            $this->logs = $state['logs'] ?? [];
        }
    }

    protected function getPersistentStateKey(): string
    {
        return 'news_processor_state';
    }

    protected function saveState()
    {
        \Illuminate\Support\Facades\Cache::put($this->getPersistentStateKey(), [
            'isProcessing' => $this->isProcessing,
            'status' => $this->status,
            'pid' => $this->pid,
            'logFilePath' => $this->logFilePath,
            'excludeFilePath' => $this->excludeFilePath,
            'currentCategoryIndex' => $this->currentCategoryIndex,
            'processedCount' => $this->processedCount,
            'savedCount' => $this->savedCount,
            'logOffset' => $this->logOffset,
            'logs' => $this->logs,
        ], 3600); // 1 hour TTL
    }

    protected function clearState()
    {
        \Illuminate\Support\Facades\Cache::forget($this->getPersistentStateKey());
    }

    public $status = 'idle'; // idle, processing

    public $pid;

    public $logFilePath;

    public $excludeFilePath;

    public $logOffset = 0;

    public function startProcessing(): void
    {
        \Illuminate\Support\Facades\Log::info('NewsProcessor: Start processing requested');

        if (empty($this->categories)) {
            \Illuminate\Support\Facades\Log::info('NewsProcessor: Categories empty, reloading');
            $this->mount();
        }

        $this->isProcessing = true;
        $this->status = 'starting';
        $this->currentCategoryIndex = 0;
        $this->processedCount = 0;
        $this->savedCount = 0;
        $this->logs = [];
        $this->logOffset = 0;

        $this->processNextCategory();
    }

    public function processNextCategory(): void
    {
        if ($this->currentCategoryIndex >= count($this->categories)) {
            $this->isProcessing = false;
            $this->status = 'idle';
            $this->log('All categories processed.', 'success');
            $this->clearState();

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

        // Use 'node' directly if available in PATH, otherwise try full path
        $nodePath = 'node';
        $testNode = exec('node -v 2>&1');
        if (str_contains($testNode, 'command not found')) {
            $nodePath = trim(exec('which node')) ?: 'node';
        }

        $nodeCommand = escapeshellarg($nodePath).' '.escapeshellarg($scriptPath).' '.escapeshellarg((string) $rssUrl).' --exclude '.escapeshellarg($this->excludeFilePath);

        $fullCommand = "{$nodeCommand} > ".escapeshellarg($this->logFilePath).' 2>&1';
        $command = 'nohup sh -c '.escapeshellarg($fullCommand).' > /dev/null 2>&1 & echo $!';

        \Illuminate\Support\Facades\Log::info('NewsProcessor: Executing command', ['command' => $command]);

        $output = [];
        $resultCode = 0;
        $pid = exec($command, $output, $resultCode);
        $this->pid = (int) $pid;
        \Illuminate\Support\Facades\Log::info('NewsProcessor: Process started', [
            'pid' => $this->pid,
            'output' => $output,
            'resultCode' => $resultCode,
        ]);
        $this->status = 'processing';
        $this->saveState();
    }

    public function checkProgress(): void
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
                                $this->log('Saved: '.mb_substr((string) $article->title, 0, 50).'...', 'success');
                            }
                        }
                    }
                } elseif (trim($line) !== '' && trim($line) !== '0') {
                    // Raw log (stderr or debug)
                    $this->log($line, 'gray');
                }
            }
            $this->saveState();
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

        // Use kill -0 to check if process exists
        $result = shell_exec("kill -0 $pid 2>&1");

        return $result === null || $result === '';
    }

    protected function log($message, $type = 'info')
    {
        \Illuminate\Support\Facades\Log::info("NewsProcessor LOG [{$type}]: {$message}");
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

        $this->logs[] = $html;
        $this->saveState();

        if (method_exists($this, 'stream')) {
            try {
                $this->stream('logs', $html, false);
            } catch (\Exception) {
                // Ignore if streaming is not available
            }
        }
    }
}
