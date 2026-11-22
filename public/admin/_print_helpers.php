<?php

function printDuration(int $minutes, string $language): string
{
    $isFrench = strtolower(substr($language, 0, 2)) === 'fr';
    if ($minutes > 0 && $minutes % 1440 === 0) {
        $value = intdiv($minutes, 1440);
        $unit = $isFrench ? ($value === 1 ? 'jour' : 'jours') : ($value === 1 ? 'day' : 'days');
    } elseif ($minutes > 0 && $minutes % 60 === 0) {
        $value = intdiv($minutes, 60);
        $unit = $isFrench ? ($value === 1 ? 'heure' : 'heures') : ($value === 1 ? 'hour' : 'hours');
    } else {
        $value = $minutes;
        $unit = $isFrench ? 'min' : 'min';
    }

    return ($isFrench ? 'Valable ' : 'Valid for ') . $value . ' ' . $unit;
}

function printRateMbps($kilobitsPerSecond): ?string
{
    if (!is_numeric($kilobitsPerSecond) || (float)$kilobitsPerSecond <= 0) {
        return null;
    }

    return number_format((float)$kilobitsPerSecond / 1000, 2, '.', '') . ' Mbps';
}

function printDataLimit(array $voucher, ?int $fallbackMegabytes, string $language): string
{
    $limit = null;
    foreach (['qos_usage_quota', 'megabytes'] as $key) {
        if (isset($voucher[$key]) && is_numeric($voucher[$key]) && (float)$voucher[$key] > 0) {
            $limit = (float)$voucher[$key];
            break;
        }
    }

    if ($limit === null && $fallbackMegabytes !== null && $fallbackMegabytes > 0) {
        $limit = (float)$fallbackMegabytes;
    }

    if ($limit === null) {
        return strtolower(substr($language, 0, 2)) === 'fr' ? 'Illimité' : 'Unlimited';
    }

    if ($limit >= 1024) {
        return number_format($limit / 1024, 2, '.', '') . ' GB';
    }
    return number_format($limit, 0, '.', '') . ' MB';
}

function printVoucherRates(array $voucher): array
{
    $upload = $voucher['qos_rate_max_up'] ?? $voucher['up'] ?? $voucher['rate_max_up'] ?? null;
    $download = $voucher['qos_rate_max_down'] ?? $voucher['down'] ?? $voucher['rate_max_down'] ?? null;
    return [$upload, $download];
}

function printRateLabel($kilobitsPerSecond, string $language): string
{
    return printRateMbps($kilobitsPerSecond)
        ?? (strtolower(substr($language, 0, 2)) === 'fr' ? 'Illimité' : 'Unlimited');
}
