<?php

namespace App\Service;

use App\Service\Exception\JwtTokenException;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class JwtService
{
    private string $secret;
    private string $algorithm  = 'HS256';
    private int    $expiration = 3600; // 1 hour

    public function __construct()
    {
        // Use the same secret as Symfony app
        $this->secret = $_ENV['APP_SECRET'] ?? throw new \RuntimeException('APP_SECRET is not set in environment variables.');
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
            'exp'  => time() + $this->expiration, // Expiration
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

            return (array)$decoded;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Extract minimal user data for sharing
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
        int    $userId,
    ): void {
        $tokenFile = $this->getTokenFilePath($userId);
        file_put_contents($tokenFile, $token);
    }

    /**
     * Get JWT token from shared location
     */
    public function getToken(int $userId): ?string
    {
        $tokenFile = $this->getTokenFilePath($userId);
        if (file_exists($tokenFile)) {
            $jwt = file_get_contents($tokenFile);
            if ($jwt === false) {
                throw new JwtTokenException(
                    sprintf('Can not read JWT token content from file %s', var_export($tokenFile, true)),
                );
            }
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
            if (!unlink($tokenFile) && file_exists($tokenFile)) {
                throw new JwtTokenException(
                    sprintf('Can not delete JWT token file %s', var_export($tokenFile, true)),
                );
            }
        }
    }

    /**
     * Clean up expired tokens
     */
    public function cleanupExpiredTokens(): void
    {
        $tokenDir = $this->getTokenDirectory();
        if (!is_dir($tokenDir)) {
            return;
        }

        $tokenFiles = glob($tokenDir . '/jwt_*.token');
        foreach ($tokenFiles as $tokenFile) {
            if (time() - filemtime($tokenFile) > $this->expiration) {
                if (!unlink($tokenFile) && file_exists($tokenFile)) {
                    throw new JwtTokenException(
                        sprintf('Can not delete JWT token file %s', var_export($tokenFile, true)),
                    );
                }
            }
        }
    }

    private function getTokenFilePath(int $userId): string
    {
        $tokenDir = $this->getTokenDirectory();
        if (!is_dir($tokenDir)) {
            mkdir($tokenDir, 0755, true);
        }

        return $tokenDir . "/jwt_{$userId}.token";
    }

    private function getTokenDirectory(): string
    {
        // Use cache directory that both apps can access
        return SPEC . '/jwt_tokens';
    }
}
