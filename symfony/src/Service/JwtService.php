<?php

declare(strict_types=1);

namespace App\Service;

use App\Service\Exception\JwtTokenException;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

readonly class JwtService
{
    public function __construct(
        private string $secret,
        private string $legacyCacheDir,
        private string $algorithm = 'HS256',
        private int $expirationInSeconds = 3600,
    ) {
    }

    /**
     * @param array<string, mixed> $userData
     */
    public function generateJwtToken(array $userData): string
    {
        $payload = [
            'iss'  => 'gamecon-php', // Issuer
            'aud'  => 'gamecon-csharp', // Audience
            'iat'  => time(), // Issued at
            'exp'  => time() + $this->expirationInSeconds, // Expiration
            'user' => $userData,
        ];

        return JWT::encode($payload, $this->secret, $this->algorithm);
    }

    /**
     * Decode and validate JWT token
     *
     * @return array<string, mixed>|null Returns payload array or null if invalid
     */
    public function decodeJwtToken(string $token): ?array
    {
        try {
            $decoded = JWT::decode($token, new Key($this->secret, $this->algorithm));

            return (array) $decoded;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Extract minimal user data for sharing
     *
     * @return array{
     *     id: int|null,
     *     login: string,
     *     jmeno: string,
     *     email: string|null,
     *     logged_at: int,
     * }
     */
    public function extractUserData(\Uzivatel $uzivatel): array
    {
        return [
            'id'        => $uzivatel->id(),
            'login'     => $uzivatel->login(),
            'jmeno'     => $uzivatel->jmenoNick(),
            'email'     => $uzivatel->mail(),
            'logged_at' => time(),
        ];
    }

    /**
     * Store JWT token in a shared location (file or cache)
     */
    public function storeToken(
        string $token,
        int $userId,
    ): void {
        $tokenFile = $this->getTokenFilePath($userId);
        if (@file_put_contents($tokenFile, $token) === false) {
            throw new JwtTokenException(sprintf('Can not write to file %s', var_export($tokenFile, true)));
        }
    }

    /**
     * Get JWT token from shared location
     */
    public function getToken(int $userId): ?string
    {
        $tokenFile = $this->getTokenFilePath($userId);
        if (file_exists($tokenFile)) {
            $jwt = @file_get_contents($tokenFile);
            if ($jwt === false) {
                throw new JwtTokenException(sprintf('Can not read JWT token content from file %s', var_export($tokenFile, true)));
            }

            return $jwt;
        }

        return null;
    }

    /**
     * Delete JWT token for user (called on logout)
     */
    public function deleteToken(int $userId): void
    {
        $tokenFile = $this->getTokenFilePath($userId);
        if (file_exists($tokenFile)) {
            if (! unlink($tokenFile) && file_exists($tokenFile)) {
                throw new JwtTokenException(sprintf('Can not delete JWT token file %s', var_export($tokenFile, true)));
            }
        }
    }

    /**
     * Clean up expired tokens
     */
    public function cleanupExpiredTokens(): void
    {
        $tokenDir = $this->getTokenDirectory();
        if (! is_dir($tokenDir)) {
            return;
        }

        $glob = $tokenDir . '/jwt_*.token';
        $tokenFiles = glob($glob);
        if ($tokenFiles === false) {
            throw new JwtTokenException(sprintf('Can not read JWT files by pattern %s', var_export($glob, true)));
        }
        foreach ($tokenFiles as $tokenFile) {
            if (time() - filemtime($tokenFile) > $this->expirationInSeconds) {
                if (! unlink($tokenFile) && file_exists($tokenFile)) {
                    throw new JwtTokenException(sprintf('Can not delete JWT token file %s', var_export($tokenFile, true)));
                }
            }
        }
    }

    private function getTokenFilePath(int $userId): string
    {
        $tokenDir = $this->getTokenDirectory();
        if (! is_dir($tokenDir)) {
            mkdir($tokenDir, 0755, true);
        }

        return $tokenDir . "/jwt_{$userId}.token";
    }

    private function getTokenDirectory(): string
    {
        return $this->legacyCacheDir . '/jwt_tokens';
    }
}
