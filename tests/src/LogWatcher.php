<?php

declare(strict_types=1);

namespace OpenConext\DevSsp\Tests;

/**
 * Reads the lines that were appended to the devssp error log (Apache, SimpleSAMLphp and PHP errors)
 * since mark() was called. In the container this log is written by tee, see docker/conf/000-default.conf
 */
final class LogWatcher
{
    /** Time to wait for log lines to be written before reading them (the log goes through a pipe) */
    private const FLUSH_DELAY_US = 200_000;

    /** Lines that indicate an error */
    private const ERROR_PATTERN = '/ simplesamlphp (ERROR|CRITICAL|ALERT|EMERGENCY) |\[php:(error|crit|alert|emerg)\]|\[critical\]|PHP (Fatal|Parse) error|Fatal error/';

    private int $offset = 0;

    public function __construct(private readonly string $file)
    {
    }

    /**
     * Create a LogWatcher for the file in the DEVSSP_LOG_FILE environment variable, or null when the
     * variable is empty (log checking disabled)
     */
    public static function fromEnvironment(): ?self
    {
        $file = (string) getenv('DEVSSP_LOG_FILE');
        if ($file === '') {
            return null;
        }
        if (!is_readable($file)) {
            throw new \RuntimeException(
                "Cannot read the log file '$file' (DEVSSP_LOG_FILE). Set DEVSSP_LOG_FILE to the error log of " .
                "the devssp, or set it to an empty value to disable checking the log."
            );
        }
        return new self($file);
    }

    /** Start watching from the current end of the log */
    public function mark(): void
    {
        clearstatcache(true, $this->file);
        $this->offset = (int) filesize($this->file);
    }

    /**
     * Lines written since mark()
     *
     * @return list<string>
     */
    public function linesSinceMark(): array
    {
        usleep(self::FLUSH_DELAY_US);
        clearstatcache(true, $this->file);
        $size = (int) filesize($this->file);
        if ($size < $this->offset) {
            $this->offset = 0;  // log was truncated or replaced
        }
        $content = (string) file_get_contents($this->file, false, null, $this->offset);
        return array_values(array_filter(explode("\n", $content), static fn (string $l): bool => trim($l) !== ''));
    }

    /**
     * Error lines written since mark(), except those allowed by $allowedPatterns. A SimpleSAMLphp log
     * message spans several lines (backtrace) that share a track ID; when one line of a message is
     * allowed, all lines with the same track ID are allowed.
     *
     * @param list<string> $allowedPatterns Regular expressions
     * @return list<string>
     */
    public function errorsSinceMark(array $allowedPatterns = []): array
    {
        $lines = $this->linesSinceMark();

        $allowedTrackIds = [];
        foreach ($lines as $line) {
            foreach ($allowedPatterns as $pattern) {
                if (preg_match($pattern, $line) && preg_match('/ \[([0-9a-zA-Z]{10})\] /', $line, $m)) {
                    $allowedTrackIds[$m[1]] = true;
                }
            }
        }

        $errors = [];
        foreach ($lines as $line) {
            if (!preg_match(self::ERROR_PATTERN, $line)) {
                continue;
            }
            if (preg_match('/ \[([0-9a-zA-Z]{10})\] /', $line, $m) && isset($allowedTrackIds[$m[1]])) {
                continue;
            }
            foreach ($allowedPatterns as $pattern) {
                if (preg_match($pattern, $line)) {
                    continue 2;
                }
            }
            $errors[] = $line;
        }
        return $errors;
    }
}
