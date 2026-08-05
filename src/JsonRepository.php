<?php
declare(strict_types=1);

final class JsonRepository
{
    /** @var string */
    private $filePath;

    public function __construct(string $filePath)
    {
        $this->filePath = $filePath;
    }

    /** @return list<array<string, mixed>> */
    public function all(): array
    {
        if (!is_readable($this->filePath)) {
            throw new RuntimeException('JSON data file is not readable.');
        }

        $json = file_get_contents($this->filePath);
        if ($json === false) {
            throw new RuntimeException('Could not read JSON data.');
        }

        $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($data)) {
            throw new RuntimeException('JSON data must contain an array.');
        }

        return $data;
    }

    /** @param list<array<string, mixed>> $items */
    public function replaceAll(array $items): void
    {
        $directory = dirname($this->filePath);
        if (!is_dir($directory) || !is_writable($directory)) {
            throw new RuntimeException('JSON data directory is not writable.');
        }

        $json = json_encode(array_values($items), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        $temporary = tempnam($directory, '.json-');
        if ($temporary === false) {
            throw new RuntimeException('Could not create a temporary data file.');
        }

        try {
            if (file_put_contents($temporary, $json . PHP_EOL, LOCK_EX) === false) {
                throw new RuntimeException('Could not write JSON data.');
            }
            if (!rename($temporary, $this->filePath)) {
                throw new RuntimeException('Could not replace JSON data.');
            }
        } finally {
            if (is_file($temporary)) {
                unlink($temporary);
            }
        }
    }

    /** @param array<string, mixed> $item */
    public function saveById(array $item, bool $isNew): void
    {
        $id = trim((string) ($item['id'] ?? ''));
        if ($id === '') {
            throw new InvalidArgumentException('id is required.');
        }

        $items = $this->all();
        $found = false;
        foreach ($items as $index => $existing) {
            if ((string) ($existing['id'] ?? '') !== $id) {
                continue;
            }
            if ($isNew) {
                throw new InvalidArgumentException('The specified id already exists.');
            }
            $items[$index] = $item;
            $found = true;
            break;
        }
        if (!$found) {
            if (!$isNew) {
                throw new InvalidArgumentException('The record to update was not found.');
            }
            $items[] = $item;
        }
        $this->replaceAll($items);
    }
}
