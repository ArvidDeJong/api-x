<?php

declare(strict_types=1);

namespace Darvis\ApiX\Support;

use RuntimeException;

/**
 * Write variables to the host application's .env file.
 *
 * Existing keys are replaced in place so their position and surrounding
 * comments survive; missing keys are appended at the end.
 */
final class EnvironmentFile
{
    public function __construct(private string $path) {}

    /**
     * Set the given variables, replacing any existing values.
     *
     * @param  array<string, string|bool>  $variables
     */
    public function set(array $variables): void
    {
        if (! is_file($this->path)) {
            throw new RuntimeException("No environment file found at {$this->path}.");
        }

        $contents = (string) file_get_contents($this->path);

        foreach ($variables as $key => $value) {
            $line = $key.'='.$this->format($value);
            $pattern = '/^'.preg_quote($key, '/').'=.*$/m';

            if (preg_match($pattern, $contents)) {
                $contents = (string) preg_replace($pattern, str_replace(['\\', '$'], ['\\\\', '\\$'], $line), $contents, 1);

                continue;
            }

            $contents = rtrim($contents, "\n")."\n".$line."\n";
        }

        file_put_contents($this->path, $contents);
    }

    /**
     * Read the raw value of a variable, or null when it is absent or empty.
     */
    public function get(string $key): ?string
    {
        if (! is_file($this->path)) {
            return null;
        }

        $pattern = '/^'.preg_quote($key, '/').'=(.*)$/m';

        if (! preg_match($pattern, (string) file_get_contents($this->path), $matches)) {
            return null;
        }

        $value = trim(trim($matches[1]), '"\'');

        return $value === '' ? null : $value;
    }

    private function format(string|bool $value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        return preg_match('/[\s#"\'$]/', $value) ? '"'.addcslashes($value, '"\\').'"' : $value;
    }
}
