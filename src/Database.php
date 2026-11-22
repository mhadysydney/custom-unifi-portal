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
        $this->pdo->exec("
            DELETE FROM mac_sessions
            WHERE id NOT IN (
                SELECT id FROM (
                    SELECT id, ROW_NUMBER() OVER (
                        PARTITION BY site_key, mac
                        ORDER BY authorized_at DESC, id DESC
                    ) AS row_num
                    FROM mac_sessions
                )
                WHERE row_num = 1
            )
        ");
        $this->pdo->exec("
            CREATE UNIQUE INDEX IF NOT EXISTS ux_mac_sessions_site_mac
            ON mac_sessions(site_key, mac)
        ");

        $this->pdo->exec("
        CREATE TABLE IF NOT EXISTS voucher_redemptions (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            site_key TEXT NOT NULL,
            voucher_code TEXT NOT NULL,
            voucher_id TEXT,
            mac TEXT NOT NULL,
            ap_mac TEXT,
            note TEXT,
            duration_min INTEGER,
            quota INTEGER NOT NULL DEFAULT 1,
            qos_rate_max_up INTEGER,
            qos_rate_max_down INTEGER,
            data_limit_mb INTEGER,
            voucher_created_at INTEGER,
            revoked_at INTEGER,
            revocation_status TEXT NOT NULL DEFAULT 'not_required',
            revocation_attempts INTEGER NOT NULL DEFAULT 0,
            revocation_error TEXT,
            authorized_at INTEGER NOT NULL,
            expires_at INTEGER,
            UNIQUE(site_key, voucher_code, mac)
        )
    ");
    $columns = array_column(
        $this->pdo->query("PRAGMA table_info(voucher_redemptions)")->fetchAll(\PDO::FETCH_ASSOC),
        'name'
    );
    $newColumns = [
        'quota' => 'INTEGER NOT NULL DEFAULT 1',
        'qos_rate_max_up' => 'INTEGER',
        'qos_rate_max_down' => 'INTEGER',
        'data_limit_mb' => 'INTEGER',
        'voucher_created_at' => 'INTEGER',
        'revoked_at' => 'INTEGER',
        'revocation_status' => "TEXT NOT NULL DEFAULT 'not_required'",
        'revocation_attempts' => 'INTEGER NOT NULL DEFAULT 0',
        'revocation_error' => 'TEXT',
    ];
    foreach ($newColumns as $column => $definition) {
        if (!in_array($column, $columns, true)) {
            $this->pdo->exec("ALTER TABLE voucher_redemptions ADD COLUMN {$column} {$definition}");
        }
    }
    $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_redemption_site_code ON voucher_redemptions(site_key, voucher_code)");
    $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_redemption_site_time ON voucher_redemptions(site_key, authorized_at)");

    }

    public function saveSession(string $siteKey, string $mac, string $voucherCode, int $minutes, string $cookieToken): void
    {
        $now = time();
        $stmt = $this->pdo->prepare("
            INSERT INTO mac_sessions (site_key, mac, voucher_code, minutes, authorized_at, expires_at, cookie_token)
            VALUES (?, ?, ?, ?, ?, ?, ?)
            ON CONFLICT(site_key, mac) DO UPDATE SET
                voucher_code = excluded.voucher_code,
                minutes = excluded.minutes,
                authorized_at = excluded.authorized_at,
                expires_at = excluded.expires_at,
                cookie_token = excluded.cookie_token
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
        $newMac = strtolower($newMac);
        $sessionStmt = $this->pdo->prepare(
            "SELECT site_key, mac, voucher_code FROM mac_sessions WHERE cookie_token = ? LIMIT 1"
        );
        $sessionStmt->execute([$cookieToken]);
        $session = $sessionStmt->fetch(\PDO::FETCH_ASSOC);

        $updateSession = $this->pdo->prepare(
            "UPDATE mac_sessions SET mac = ? WHERE cookie_token = ?"
        );
        $updateSession->execute([$newMac, $cookieToken]);

        if ($session) {
            $code = preg_replace('/\D/', '', (string)($session['voucher_code'] ?? ''));
            $updateRedemption = $this->pdo->prepare(
                "UPDATE voucher_redemptions
                 SET mac = :new_mac, ap_mac = COALESCE(ap_mac, ap_mac)
                 WHERE site_key = :site
                   AND voucher_code = :code
                   AND mac = :old_mac"
            );
            $updateRedemption->execute([
                ':new_mac' => $newMac,
                ':site' => $session['site_key'],
                ':code' => $code,
                ':old_mac' => strtolower($session['mac']),
            ]);
        }
    }

    public function saveRedemption(
    string $siteKey,
    string $code,
    string $mac,
    ?string $apMac = null,
    ?string $voucherId = null,
    ?string $note = null,
    ?int $durationMin = null,
    ?int $expiresAt = null
): void {
    $code = preg_replace('/\D/', '', $code);
    $mac  = strtolower($mac);
    $now  = time();

    $stmt = $this->pdo->prepare("
        INSERT INTO voucher_redemptions
            (site_key, voucher_code, voucher_id, mac, ap_mac, note, duration_min, authorized_at, expires_at)
        VALUES
            (:site, :code, :vid, :mac, :ap, :note, :dur, :at, :exp)
        ON CONFLICT(site_key, voucher_code, mac) DO UPDATE SET
            authorized_at = excluded.authorized_at,
            expires_at    = excluded.expires_at,
            ap_mac        = COALESCE(excluded.ap_mac, voucher_redemptions.ap_mac),
            note          = COALESCE(excluded.note, voucher_redemptions.note)
    ");
    $stmt->execute([
        ':site' => $siteKey,
        ':code' => $code,
        ':vid'  => $voucherId,
        ':mac'  => $mac,
        ':ap'   => $apMac ? strtolower($apMac) : null,
        ':note' => $note,
        ':dur'  => $durationMin,
        ':at'   => $now,
        ':exp'  => $expiresAt ?? ($durationMin ? $now + ($durationMin * 60) : null),
    ]);
}

/** Codes déjà redeemés pour un site (code normalisé => liste de redemptions) */
public function getRedemptionsBySite(string $siteKey): array
{
    $stmt = $this->pdo->prepare("
        SELECT * FROM voucher_redemptions
        WHERE site_key = :site
        ORDER BY authorized_at DESC
    ");
    $stmt->execute([':site' => $siteKey]);
    $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

    $byCode = [];
    foreach ($rows as $r) {
        $c = preg_replace('/\D/', '', $r['voucher_code']);
        $byCode[$c][] = $r;
    }
    return $byCode;
}

/** Stats d'un code */
public function getRedemptionsForCode(string $siteKey, string $code): array
{
    $code = preg_replace('/\D/', '', $code);
    $stmt = $this->pdo->prepare("
        SELECT * FROM voucher_redemptions
        WHERE site_key = :site AND voucher_code = :code
        ORDER BY authorized_at DESC
    ");
    $stmt->execute([':site' => $siteKey, ':code' => $code]);
    return $stmt->fetchAll(\PDO::FETCH_ASSOC);
}

    public function reserveVoucher(
        string $siteKey,
        string $code,
        string $mac,
        int $quota,
        ?string $apMac = null,
        ?string $voucherId = null,
        ?string $note = null,
        ?int $durationMin = null,
        ?int $uploadKbps = null,
        ?int $downloadKbps = null,
        ?int $dataLimitMb = null,
        ?int $voucherCreatedAt = null
    ): bool {
        $code = preg_replace('/\D/', '', $code);
        $mac = strtolower($mac);
        $now = time();

        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare(
                "SELECT COUNT(DISTINCT mac) FROM voucher_redemptions
                 WHERE site_key = :site AND voucher_code = :code"
            );
            $stmt->execute([':site' => $siteKey, ':code' => $code]);
            $usedDevices = (int)$stmt->fetchColumn();

            $deviceStmt = $this->pdo->prepare(
                "SELECT 1 FROM voucher_redemptions
                 WHERE site_key = :site AND voucher_code = :code AND mac = :mac
                 LIMIT 1"
            );
            $deviceStmt->execute([':site' => $siteKey, ':code' => $code, ':mac' => $mac]);
            $alreadyUsedByDevice = (bool)$deviceStmt->fetchColumn();

            if ($alreadyUsedByDevice) {
                $this->pdo->rollBack();
                return false;
            }
            if ($quota > 0 && $usedDevices >= $quota) {
                $this->pdo->rollBack();
                return false;
            }

            $expiresAt = $durationMin ? $now + ($durationMin * 60) : null;
            $insert = $this->pdo->prepare("
                INSERT INTO voucher_redemptions
                    (site_key, voucher_code, voucher_id, mac, ap_mac, note, duration_min, quota,
                     qos_rate_max_up, qos_rate_max_down, data_limit_mb, voucher_created_at,
                     authorized_at, expires_at)
                VALUES
                    (:site, :code, :vid, :mac, :ap, :note, :dur, :quota,
                     :up, :down, :data_limit, :created, :at, :exp)
            ");
            $insert->execute([
                ':site' => $siteKey,
                ':code' => $code,
                ':vid' => $voucherId,
                ':mac' => $mac,
                ':ap' => $apMac ? strtolower($apMac) : null,
                ':note' => $note,
                ':dur' => $durationMin,
                ':quota' => $quota,
                ':up' => $uploadKbps,
                ':down' => $downloadKbps,
                ':data_limit' => $dataLimitMb,
                ':created' => $voucherCreatedAt,
                ':at' => $now,
                ':exp' => $expiresAt,
            ]);
            $this->pdo->commit();
            return true;
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    public function releaseVoucherReservation(string $siteKey, string $code, string $mac): void
    {
        $stmt = $this->pdo->prepare(
            "DELETE FROM voucher_redemptions
             WHERE site_key = :site AND voucher_code = :code AND mac = :mac"
        );
        $stmt->execute([
            ':site' => $siteKey,
            ':code' => preg_replace('/\D/', '', $code),
            ':mac' => strtolower($mac),
        ]);
    }

    public function markVoucherRevoked(string $siteKey, string $code): void
    {
        $stmt = $this->pdo->prepare(
            "UPDATE voucher_redemptions
             SET revoked_at = :revoked_at,
                 revocation_status = 'revoked',
                 revocation_error = NULL
             WHERE site_key = :site AND voucher_code = :code"
        );
        $stmt->execute([
            ':revoked_at' => time(),
            ':site' => $siteKey,
            ':code' => preg_replace('/\D/', '', $code),
        ]);
    }

    public function markVoucherRevocationPending(string $siteKey, string $code): void
    {
        $stmt = $this->pdo->prepare(
            "UPDATE voucher_redemptions
             SET revocation_status = 'pending',
                 revocation_attempts = revocation_attempts + 1,
                 revocation_error = NULL
             WHERE site_key = :site AND voucher_code = :code"
        );
        $stmt->execute([
            ':site' => $siteKey,
            ':code' => preg_replace('/\D/', '', $code),
        ]);
    }

    public function markVoucherRevocationFailed(string $siteKey, string $code, string $error): void
    {
        $stmt = $this->pdo->prepare(
            "UPDATE voucher_redemptions
             SET revocation_status = 'pending',
                 revocation_error = :error
             WHERE site_key = :site AND voucher_code = :code"
        );
        $stmt->execute([
            ':error' => substr($error, 0, 1000),
            ':site' => $siteKey,
            ':code' => preg_replace('/\D/', '', $code),
        ]);
    }
}