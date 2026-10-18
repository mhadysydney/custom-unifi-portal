<?php
namespace Portal;

use UniFi_API\Client;

class UniFiHelper
{
    private array $config;
    private array $siteConfig;

    public function __construct(array $globalConfig, string $siteKey)
    {
        $this->config = $globalConfig;
        if (!isset($globalConfig['sites'][$siteKey])) {
            throw new \InvalidArgumentException("Site inconnu: $siteKey");
        }
        $this->siteConfig = $globalConfig['sites'][$siteKey];
    }

    public function getClient(): Client
    {
        $sc = $this->siteConfig;

        if (($sc['method'] ?? 'site_manager') === 'site_manager') {
            $client = Client::connect_via_site_manager(
                $sc['console_id'],
                $this->config['site_manager_api_key'],
                $sc['site_id'] ?? 'default'
            );
            return $client;
        }

        // Méthode directe
        $client = new Client(
            $sc['user'] ?? '',
            $sc['pass'] ?? '',
            $sc['url'],
            $sc['site_id'] ?? 'default'
        );

        if (!empty($sc['api_key'])) {
            $client->set_api_key($sc['api_key']);
        } else {
            $client->login();
        }

        return $client;
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
        $client = $this->getClient();
        return (bool)$client->authorize_guest(
            strtolower($mac),
            $minutes,
            null, // up
            null, // down
            null, // bytes
            $apMac
        );
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