<?php
declare(strict_types=1);
namespace SCForms;

final class Security
{
    public function __construct(private string $secret, private string $directory, private int $limit = 5, private int $window = 3600)
    {
        if (strlen($secret) < 32) { throw new \RuntimeException('Укажите секрет длиной от 32 символов'); }
        if ($limit < 1 || $window < 1) { throw new \RuntimeException('Некорректные настройки ограничения частоты'); }
    }
    public function token(string $form, ?int $now = null): string
    {
        $payload = ($now ?? time()) . '.' . bin2hex(random_bytes(16));
        return $payload . '.' . hash_hmac('sha256', $form . '.' . $payload, $this->secret);
    }
    public function verify(string $token, string $form, ?int $now = null): bool
    {
        if (!preg_match('/^(\d{10}|\d{1,9})\.([a-f0-9]{32})\.([a-f0-9]{64})$/D', $token, $matches)) { return false; }
        $age = ($now ?? time()) - (int)$matches[1];
        return $age >= 0 && $age <= 3600 && hash_equals(hash_hmac('sha256', $form . '.' . $matches[1] . '.' . $matches[2], $this->secret), $matches[3]);
    }
    public function originAllowed(string $origin, array $allowed): bool
    {
        return $origin !== '' && $origin !== 'null' && in_array($origin, $allowed, true);
    }
    public function allow(string $ip, ?int $now = null): bool
    {
        $now ??= time();
        if (!is_dir($this->directory) && !@mkdir($this->directory, 0700, true) && !is_dir($this->directory)) {
            throw new \RuntimeException('Недоступна папка ограничения частоты');
        }
        // Fixed number of buckets bounds disk use even when source addresses vary.
        $bucket = substr(hash_hmac('sha256', $ip, $this->secret), 0, 3);
        $handle = @fopen($this->directory . '/' . $bucket . '.json', 'c+');
        if (!$handle) { throw new \RuntimeException('Не удалось открыть хранилище ограничения частоты'); }
        try {
            if (!flock($handle, LOCK_EX)) { throw new \RuntimeException('Не удалось заблокировать хранилище'); }
            $rows = json_decode(stream_get_contents($handle) ?: '{}', true) ?: [];
            $rows = array_filter($rows, fn($row) => is_array($row) && ($row['start'] ?? 0) > $now - $this->window);
            $key = hash_hmac('sha256', $ip, $this->secret);
            $row = $rows[$key] ?? ['start' => $now, 'count' => 0];
            $ok = $row['count'] < $this->limit;
            if ($ok) { $row['count']++; }
            $rows[$key] = $row;
            // Bound memory/disk under address-flood attacks; fail closed for a full bucket.
            if (count($rows) > 1000) { return false; }
            rewind($handle);
            if (!ftruncate($handle, 0) || fwrite($handle, json_encode($rows, JSON_THROW_ON_ERROR)) === false || !fflush($handle)) {
                throw new \RuntimeException('Не удалось сохранить ограничение частоты');
            }
            return $ok;
        } finally { flock($handle, LOCK_UN); fclose($handle); }
    }
}
