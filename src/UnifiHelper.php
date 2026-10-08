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
}