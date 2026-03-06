<?php

namespace Allyson\SafeMode\Support;

class LocalIpDetector
{
    /**
     * Blocos CIDR privados (RFC 1918 + loopback + IPv6 loopback + link-local)
     */
    private const PRIVATE_PREFIXES = [
        '10.',
        '192.168.',
        '172.16.', '172.17.', '172.18.', '172.19.', '172.20.',
        '172.21.', '172.22.', '172.23.', '172.24.', '172.25.',
        '172.26.', '172.27.', '172.28.', '172.29.', '172.30.',
        '172.31.',
        '169.254.', // link-local
    ];

    private const LOCAL_EXACT = [
        '127.0.0.1',
        'localhost',
        '::1',
        '0.0.0.0',
    ];

    /**
     * Retorna true se o host fornecido for considerado local/privado.
     */
    public static function isLocal(string $host): bool
    {
        $host = strtolower(trim($host));

        return !empty($host) && self::matchesLocalRules($host);
    }

    /**
     * Avalia as regras de localidade (exato ou prefixo).
     */
    private static function matchesLocalRules(string $host): bool
    {
        return self::isExactLocal($host)
            || self::hasPrivatePrefix($host)
            || self::isResolvedLocal($host);
    }

    private static function isExactLocal(string $host): bool
    {
        return in_array($host, self::LOCAL_EXACT, true);
    }

    private static function hasPrivatePrefix(string $host): bool
    {
        return array_reduce(
            self::PRIVATE_PREFIXES,
            static fn (bool $carry, string $prefix) => $carry || str_starts_with($host, $prefix),
            false
        );
    }

    private static function isResolvedLocal(string $host): bool
    {
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return false;
        }

        // gethostbynamel retorna todos os IPs IPv4 do hostname (ou false em falha).
        // Isso cobre service names do Docker como "mysql", "postgres", "db", etc.,
        // que resolvem para endereços privados da rede interna do container.
        $addresses = @gethostbynamel($host);

        if (! is_array($addresses) || empty($addresses)) {
            return false;
        }

        foreach ($addresses as $resolved) {
            if (self::isExactLocal($resolved) || self::hasPrivatePrefix($resolved)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Retorna true se o host estiver na whitelist de IPs configurada.
     */
    public static function isWhitelisted(string $host, array $allowedIps): bool
    {
        $host = strtolower(trim($host));

        foreach ($allowedIps as $allowed) {
            if (strtolower(trim($allowed)) === $host) {
                return true;
            }
        }

        return false;
    }
}
