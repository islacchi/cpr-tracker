<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\CprParser;

/**
 * Invoked by CprController::parseFiles() for each file when running in
 * parallel mode. Writes a single JSON object to $outputPath and exits.
 *
 * Register in app/Console/Kernel.php:
 *   protected $commands = [
 *       \App\Console\Commands\ParseCprFile::class,
 *   ];
 *
 * Or in Laravel 11+, it auto-discovers via the Commands directory.
 */
class ParseCprFile extends Command
{
    protected $signature   = 'cpr:parse-file {filePath} {outputPath}';
    protected $description = 'Parse a single CPR PDF and write JSON result to a temp file.';

    public function handle(): int
    {
        $filePath   = $this->argument('filePath');
        $outputPath = $this->argument('outputPath');

        if (!file_exists($filePath) || !is_readable($filePath)) {
            // Write null so the controller knows this file failed cleanly.
            file_put_contents($outputPath, json_encode(null));
            return self::FAILURE;
        }

        $parser = new CprParser();
        $result = $parser->parse($filePath);

        file_put_contents($outputPath, json_encode($result));
        return self::SUCCESS;
    }
}