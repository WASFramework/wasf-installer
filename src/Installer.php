<?php

namespace WASFInstaller;

class Installer
{
    // Colors (safe for Win10+)
    protected string $reset   = "\033[0m";
    protected string $red     = "\033[31m";
    protected string $green   = "\033[32m";
    protected string $yellow  = "\033[33m";
    protected string $cyan    = "\033[36m";
    protected string $bold    = "\033[1m";
    protected string $blue    = "\033[34m"; // FIXED: missing color

    public function run(array $argv)
    {
        $this->clear();
        $this->banner();

        if (!isset($argv[1]) || $argv[1] !== 'new') {
            $this->usage();
            exit(1);
        }

        if (!isset($argv[2])) {
            echo $this->red . "Error: Missing project name\n" . $this->reset;
            $this->usage();
            exit(1);
        }

        $project = $argv[2];

        if (is_dir($project)) {
            echo $this->red . "Error: Directory '$project' already exists.\n" . $this->reset;
            exit(1);
        }

        echo "{$this->cyan}→ Creating project folder: {$this->reset}{$project}\n";
        echo "{$this->cyan}→ Downloading WASF Skeleton...\n\n{$this->reset}";

        $cmd = "composer create-project wasframework/wasf-app " . escapeshellarg($project);
        $exit = $this->progressComposer($cmd);

        if ($exit !== 0) {
            echo $this->red . "\nInstallation failed.\n" . $this->reset;
            exit(1);
        }

        $this->postInstall($project);
    }

    private function banner()
    {
        echo $this->cyan . $this->bold . "
 __          ___     ______  ______
 \\ \\        / _ \\   |  ____||  ____|
  \\ \\  /\\  / / \\ \\  | |____ | |__
   \\ \\/  \\/ /___\\ \\ |____  ||  __|
    \\  /\\  /_____\\ \\ ____| || |
     \\/  \\/       \\_\\______||_|
" . $this->reset;

        echo $this->green . $this->bold . "     🔥 WASF Framework Installer\n" . $this->reset;
        echo $this->cyan  . "============================================\n\n" . $this->reset;
    }

    private function usage()
    {
        echo $this->yellow . "Usage:\n" . $this->reset;
        echo "  wasf new {$this->green}project-name{$this->reset}\n\n";
    }

    private function clear()
    {
        echo "\033[2J\033[;H";
    }

    // ===================================================
    // SPINNER (Improved)
    // ===================================================

    private function spinner(string $text, callable $callback)
    {
        $frames = ['-', '\\', '|', '/'];
        $i = 0;

        echo "{$this->yellow}{$text}... {$this->reset}";

        // Execute callback now while spinner runs for effect
        $start = microtime(true);
        $callback();

        // Spinner animation after callback for better UX
        while (microtime(true) - $start < 0.7) {
            echo "\r{$this->yellow}{$text} {$frames[$i]}{$this->reset}";
            $i = ($i + 1) % count($frames);
            usleep(90000);
        }

        echo "\r{$this->green}✔ {$text}{$this->reset}\n";
    }

    // ===================================================
    // PROGRESS BAR (Composer)
    // ===================================================

    private function progressComposer(string $cmd): int
    {
        $pipes = [];
        $proc = proc_open($cmd, [
            1 => ["pipe", "w"],
            2 => ["pipe", "w"],
        ], $pipes);

        if (!is_resource($proc)) {
            echo $this->red . "Failed to start composer process.\n" . $this->reset;
            return 1;
        }

        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        $percent = 0;
        $start = microtime(true);

        $this->renderProgress(0);

        while (true) {
            $status = proc_get_status($proc);

            // Print output without breaking progress bar
            $out = stream_get_contents($pipes[1]);
            if ($out) echo "\n" . trim($out);

            $err = stream_get_contents($pipes[2]);
            if ($err) echo "\n" . trim($err);

            if (!$status["running"]) {
                $this->renderProgress(100);
                break;
            }

            // Very smooth time-based progress
            $elapsed = microtime(true) - $start;
            if ($percent < 90) {
                $percent = min(90, $elapsed * 9);
            }

            $this->renderProgress((int)$percent);
            usleep(120000);
        }

        fclose($pipes[1]);
        fclose($pipes[2]);

        return proc_close($proc);
    }

    private function renderProgress(int $percent)
    {
        $barWidth = 40;
        $filled = (int)($barWidth * $percent / 100);
        $bar = "[" . str_repeat("█", $filled) . str_repeat("░", $barWidth - $filled) . "]";
        echo "\r{$this->cyan}{$this->bold}{$bar} {$percent}%{$this->reset}";
        if ($percent >= 100) echo PHP_EOL;
    }

    // ===================================================
    // POST INSTALL
    // ===================================================

    private function postInstall(string $project)
    {
        echo "\n{$this->cyan}→ Finishing setup...{$this->reset}\n";

        // Generate WASF_KEY safely
        $this->spinner("Generating WASF_KEY", function() use ($project) {

            $envFile = "$project/.env";

            if (!file_exists($envFile)) {
                file_put_contents($envFile, "WASF_KEY=\n");
            }

            $env = file_get_contents($envFile);

            $key = "WASF_KEY=" . base64_encode(random_bytes(32));

            if (preg_match("/WASF_KEY=/", $env)) {
                $env = preg_replace("/WASF_KEY=.*/", $key, $env);
            } else {
                $env .= "\n" . $key;
            }

            file_put_contents($envFile, $env);
        });

        // chmod (Linux only)
        if (DIRECTORY_SEPARATOR === "/") {
            $this->spinner("Setting permissions", function() use ($project) {
                $target = escapeshellarg("$project/storage");
                exec("chmod -R 777 $target");
            });
        }

        $this->done($project);
    }

    private function done(string $project)
    {
        echo "\n{$this->cyan}{$this->bold}============================================\n";
        echo "          🎉 WASF Installed Successfully!\n";
        echo "============================================{$this->reset}\n\n";

        echo "{$this->yellow}Next steps:{$this->reset}\n";
        echo "{$this->green}  cd {$project}\n";
        echo "  php wasf serve{$this->reset}\n\n";

        echo $this->blue.$this->bold."Happy coding with WASF Framework 🚀\n\n".$this->reset;
    }
}
