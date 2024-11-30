<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

class TinkerWatch extends Command
{
  protected $signature = 'tinker:watch {--interval=2 : Check interval in seconds}';
  protected $description = 'Start Tinker with file watching capability (Windows optimized)';

  protected $watchDirectories = [
    'app',
    'config',
    'database/factories',
    'database/seeders',
  ];

  protected $fileCache = [];
  protected $process = null;

  public function handle()
  {
    $this->info('Starting Tinker with watch mode...');

    // Initial cache build
    $this->buildInitialCache();

    // Start initial Tinker process
    $this->startTinker();

    $interval = $this->option('interval');

    while (true) {
      if ($this->hasChanges()) {
        $this->info('Changes detected, restarting Tinker...');

        // Terminate the existing process properly
        if ($this->process) {
          // On Windows, we need to make sure the process is terminated
          $pid = $this->process->getPid();
          exec("taskkill /F /T /PID $pid 2>&1");
          $this->process->stop();
        }

        // Small delay to ensure proper cleanup
        usleep(500000); // 0.5 seconds

        // Start new process
        $this->startTinker();
      }

      // Use sleep instead of usleep for better Windows performance
      sleep($interval);
    }
  }

  protected function startTinker()
  {
    $command = 'php artisan tinker';

    $this->process = new Process(
      explode(' ', $command),
      base_path(),
      ['COMPOSER_MEMORY_LIMIT' => '-1']
    );

    // Enable PTY if not on Windows
    if (PHP_OS !== 'WINNT') {
      $this->process->setTty(true);
    }

    $this->process->start();
  }

  protected function buildInitialCache()
  {
    foreach ($this->watchDirectories as $dir) {
      $this->cacheDirectory($dir);
    }
  }

  protected function cacheDirectory($dir)
  {
    $path = base_path($dir);
    if (!file_exists($path)) {
      return;
    }

    $directory = new \RecursiveDirectoryIterator(
      $path,
      \RecursiveDirectoryIterator::SKIP_DOTS
    );
    $iterator = new \RecursiveIteratorIterator($directory);

    foreach ($iterator as $file) {
      if ($file->isFile() && $this->isWatchableFile($file)) {
        $this->fileCache[$file->getPathname()] = $file->getMTime();
      }
    }
  }

  protected function hasChanges()
  {
    $hasChanges = false;

    foreach ($this->watchDirectories as $dir) {
      $path = base_path($dir);
      if (!file_exists($path)) {
        continue;
      }

      $directory = new \RecursiveDirectoryIterator(
        $path,
        \RecursiveDirectoryIterator::SKIP_DOTS
      );
      $iterator = new \RecursiveIteratorIterator($directory);

      foreach ($iterator as $file) {
        if (!$file->isFile() || !$this->isWatchableFile($file)) {
          continue;
        }

        $pathname = $file->getPathname();
        $currentMTime = $file->getMTime();

        if (!isset($this->fileCache[$pathname])) {
          // New file
          $this->fileCache[$pathname] = $currentMTime;
          $hasChanges = true;
        } elseif ($this->fileCache[$pathname] !== $currentMTime) {
          // Modified file
          $this->fileCache[$pathname] = $currentMTime;
          $hasChanges = true;
        }
      }
    }

    return $hasChanges;
  }

  protected function isWatchableFile($file)
  {
    return pathinfo($file->getPathname(), PATHINFO_EXTENSION) === 'php';
  }
}
