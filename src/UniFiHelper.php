<?php
namespace Portal;

use UniFi_API\Client;

class UniFiHelper
{
    private array $config;
    private array $siteConfig;
    private string $siteKey;

    public function __construct(array $globalConfig, string $siteKey)
    {
        $this->config = $globalConfig;
        $this->siteKey = $siteKey;
        if (!isset($globalConfig['sites'][$siteKey])) {
            throw new \InvalidArgumentException("Site inconnu: $siteKey");
        }
        $this->siteConfig = $globalConfig['sites'][$siteKey];
    }

    public function getClient(): Client
{
    $sc = $this->siteConfig;
    $method = $sc['method'] ?? 'site_manager';

    $this->log('getClient start', [
        'siteKey'    => $this->siteKey ?? null,
        'method'     => $method,
        'console_id' => $sc['console_id'] ?? null,
        'site_id'    => $sc['site_id'] ?? 'default',
        'has_sm_key' => !empty($this->config['site_manager_api_key']),
        'has_api_key'=> !empty($sc['api_key']),
        'has_user'   => !empty($sc['user']),
        'url'        => $sc['url'] ?? null,
    ]);

    try {
        if ($method === 'site_manager') {
            if (empty($this->config['site_manager_api_key']) || empty($sc['console_id'])) {
                throw new \RuntimeException('Site Manager: console_id ou site_manager_api_key manquant');
            }

            $client = Client::connect_via_site_manager(
                $sc['console_id'],
                $sc['api_key'],
                $sc['site_id'] ?? 'default'
            );

            $this->log('getClient OK via site_manager');
            return $client;
        }

        // Méthode directe
        if (empty($sc['url'])) {
            throw new \RuntimeException('Direct: url manquante');
        }

        $client = new Client(
            $sc['user'] ?? '',
            $sc['pass'] ?? '',
            $sc['url'],
            $sc['site_id'] ?? 'default'
        );

        if (!empty($sc['api_key'])) {
            $client->set_api_key($sc['api_key']);
            $this->log('getClient OK via api_key (direct)');
        } else {
            if (empty($sc['user']) || empty($sc['pass'])) {
                throw new \RuntimeException('Direct: ni api_key ni user/pass');
            }
            $login = $client->login();
            $this->log('getClient login result', ['login' => $login]);
        }

        return $client;

    } catch (\Throwable $e) {
        $this->log('getClient FAIL', [
            'error' => $e->getMessage(),
            'class' => get_class($e),
        ]);
        throw $e;
    }
}

public function log(string $msg, array $ctx = []): void
{
    $line = date('c') . ' [' . ($this->siteKey ?? '-') . '] ' . $msg;
    if ($ctx) {
        $line .= ' ' . json_encode($ctx, JSON_UNESCAPED_UNICODE);
    }
    $line .= PHP_EOL;

    $dir = dirname(__DIR__) . '/data';
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    @file_put_contents($dir . '/portal_debug.log', $line, FILE_APPEND | LOCK_EX);
}

    /**
     * Cherche un voucher par code et retourne ses infos
     */
    public function findVoucher(string $code): ?array
    {
        $client = $this->getClient();
        $vouchers = $client->stat_voucher(); // tous les vouchers

        $code = preg_replace('/[^0-9]/', '', $code); // nettoie espaces / tirets

        foreach ($vouchers as $v) {
            $vcode = preg_replace('/[^0-9]/', '', $v->code ?? '');
            if ($vcode === $code) {
                // Vérifie s’il est encore utilisable
                $used = (int)($v->used ?? 0);
                $quota = (int)($v->quota ?? 0); // 0 = multi illimité, 1 = single, n = n fois
                if ($quota > 0 && $used >= $quota) {
                    return null; // épuisé
                }
                return (array)$v;
            }
        }
        return null;
    }

    /**
     * Autorise le guest
     */
    public function authorize(string $mac, int $minutes, ?string $apMac = null): bool
    {
        try{
        $client = $this->getClient();
        return (bool)$client->authorize_guest(
            strtolower($mac),
            $minutes,
            null, // up
            null, // down
            null, // bytes
            $apMac
         );
        }catch (\Throwable $e) {
        $this->log('getClient FAIL', [
            'error' => $e->getMessage(),
            'class' => get_class($e),
        ]);
        throw $e;
    }
    }

    public function getSiteConfig(): array
    {
        return $this->siteConfig;
    }

    public function createVoucher(
        int $minutes,
        int $count = 1,
        int $quota = 1,
        string $note = '',
        ?int $up = null,
        ?int $down = null,
        ?int $megabytes = null
    ): array {
        $client = $this->getClient();
        $result = $client->create_voucher($minutes, $count, $quota, $note, $up, $down, $megabytes);

        // Récupère create_time
        $createTime = null;
        if (is_array($result) && isset($result[0])) {
            $createTime = is_object($result[0])
                ? ($result[0]->create_time ?? null)
                : ($result[0]['create_time'] ?? null);
        } elseif (is_object($result)) {
            $createTime = $result->create_time ?? null;
        }

        if (!$createTime) {
            return [];
        }

        $list = $client->stat_voucher((int)$createTime) ?: [];
        return array_map(fn($v) => (array)$v, $list);
    }

    /**
     * Liste tous les vouchers (ou ceux d'un create_time)
     */
    public function listVouchers(?int $createTime = null): array
    {
        $list = $this->getClient()->stat_voucher($createTime) ?: [];
        return array_map(fn($v) => (array)$v, $list);
    }

    /**
     * Révoque un voucher par son _id
     */
    public function revokeVoucher(string $voucherId): bool
    {
        return (bool)$this->getClient()->revoke_voucher($voucherId);
    }

    /**
     * Stats filtrées par période + statut
     *
     * @return array{all: array, used: array, remaining: array, expired_unused: array}
     */
    public function getVoucherStats(?int $fromTs = null, ?int $toTs = null): array
    {
        $all = $this->listVouchers();
        $now = time();

        $used = $remaining = $expiredUnused = [];

        foreach ($all as $v) {
            $created = (int)($v['create_time'] ?? 0);

            if ($fromTs !== null && $created < $fromTs) {
                continue;
            }
            if ($toTs !== null && $created > $toTs) {
                continue;
            }

            $quota = (int)($v['quota'] ?? 0);
            $usedCount = (int)($v['used'] ?? 0);
            // duration = minutes après activation ; create_time = création
            // Un voucher non utilisé n'expire pas vraiment côté UniFi tant qu'il n'est pas activé
            // On considère "épuisé" si quota atteint

            if ($quota > 0 && $usedCount >= $quota) {
                $used[] = $v;
            } else {
                $remaining[] = $v;
            }
        }

        return [
            'all'            => $all,
            'used'           => $used,
            'remaining'      => $remaining,
            'count_all'      => count($all),
            'count_used'     => count($used),
            'count_remaining'=> count($remaining),
            'from'           => $fromTs,
            'to'             => $toTs,
        ];
    }
}