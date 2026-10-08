<?php
namespace Portal;
class Database
{
    private \PDO $pdo;

    public function __construct(string $path = __DIR__ . '/../data/portal.db')
    {
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $this->pdo = new \PDO('sqlite:' . $path);
        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $this->init();
    }

    private function init(): void
    {
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS mac_sessions (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                site_key TEXT NOT NULL,
                mac TEXT NOT NULL,
                voucher_code TEXT,
                minutes INTEGER,
                authorized_at INTEGER,
                expires_at INTEGER,
                cookie_token TEXT UNIQUE,
                created_at INTEGER DEFAULT (strftime('%s','now'))
            );
            CREATE INDEX IF NOT EXISTS idx_mac ON mac_sessions(mac);
            CREATE INDEX IF NOT EXISTS idx_cookie ON mac_sessions(cookie_token);
            CREATE INDEX IF NOT EXISTS idx_expires ON mac_sessions(expires_at);
        ");
    }

    public function saveSession(string $siteKey, string $mac, string $voucherCode, int $minutes, string $cookieToken): void
    {
        $now = time();
        $stmt = $this->pdo->prepare("
            INSERT INTO mac_sessions (site_key, mac, voucher_code, minutes, authorized_at, expires_at, cookie_token)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$siteKey, strtolower($mac), $voucherCode, $minutes, $now, $now + ($minutes * 60), $cookieToken]);
    }

    public function findByCookie(string $token): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM mac_sessions WHERE cookie_token = ? AND expires_at > ? ORDER BY id DESC LIMIT 1");
        $stmt->execute([$token, time()]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function findByMac(string $siteKey, string $mac): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM mac_sessions WHERE site_key = ? AND mac = ? AND expires_at > ? ORDER BY id DESC LIMIT 1");
        $stmt->execute([$siteKey, strtolower($mac), time()]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function updateMac(string $cookieToken, string $newMac): void
    {
        $stmt = $this->pdo->prepare("UPDATE mac_sessions SET mac = ? WHERE cookie_token = ?");
        $stmt->execute([strtolower($newMac), $cookieToken]);
    }
}