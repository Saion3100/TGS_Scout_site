<?php
declare(strict_types=1);

final class JsonRepository
{
    public function __construct(private readonly string $filePath)
    {
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
}
