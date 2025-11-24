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
 \ \        / _ \   |  ____||  ____|
  \ \  /\  / / \ \  | |____ | |__
   \ \/  \/ /___\ \ |____  ||  __|
    \  /\  /_____\ \ ____| || |
     \/  \/       \_\______||_|
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
    // SPINNER (compatible with Windows)
    // ===================================================

    private function spinner(string $text, callable $callback)
    {
        $frames = ['-', '\\', '|', '/'];
        $i = 0;

        echo "{$this->yellow}{$text}... {$this->reset}";

        // Run the callback in blocking mode (no pcntl)
        while (true) {
            echo "\r{$this->yellow}{$text} {$frames[$i]}{$this->reset}";
            $i = ($i + 1) % count($frames);

            // Run callback once
            $callback();
            break;
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

        if (!is_resource($proc)) return 1;

        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        $percent = 0;
        $start = microtime(true);

        $this->renderProgress(0);

        while (true) {
            $status = proc_get_status($proc);

            echo stream_get_contents($pipes[1]);
            echo stream_get_contents($pipes[2]);

            if (!$status["running"]) {
                $this->renderProgress(100);
                break;
            }

            // Time-based progress
            $elapsed = microtime(true) - $start;
            if ($percent < 90) {
                $percent = min(90, $elapsed * 8); // slower ramp
            }

            $this->renderProgress((int)$percent);
            usleep(100000);
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
        echo "\n";
        echo "{$this->cyan}→ Finishing setup...{$this->reset}\n";

        // Generate WASF_KEY
        $this->spinner("Generating WASF_KEY", function() use ($project) {
            $env = file_get_contents("$project/.env");
            $key = "WASF_KEY=" . base64_encode(random_bytes(32));
            $env = preg_replace("/WASF_KEY=.*/", $key, $env);
            file_put_contents("$project/.env", $env);
        });

        // chmod
        if (DIRECTORY_SEPARATOR === "/") {
            $this->spinner("Setting permissions", function() use ($project) {
                exec("chmod -R 777 $project/storage");
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
