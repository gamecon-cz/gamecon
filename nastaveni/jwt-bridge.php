<?php

/**
 * JWT Bridge for sharing authentication between this (legacy app) and other applications (like C# store)
 *
 * This file provides functions to generate JWT tokens for logged-in users.
 */

use App\Kernel;
use App\Service\JwtService;
use Gamecon\SystemoveNastaveni\SystemoveNastaveni;

/**
 * Generate JWT token for current user
 * Call this function when user logs in or when needed for C# app integration
 */
function generateJwtForUser(
    Uzivatel $uzivatel,
    ?SystemoveNastaveni $systemoveNastaveni = null,
): string {
    // Use existing Symfony kernel from SystemoveNastaveni
    $systemoveNastaveni ??= SystemoveNastaveni::zGlobals();
    $kernel = $systemoveNastaveni->kernel();

    /** @var JwtService $jwtService */
    $jwtService = $kernel->getContainer()->get(JwtService::class);

    // Extract user data and generate token
    $userData = $jwtService->extractUserData($uzivatel);
    $token    = $jwtService->generateJwtToken($userData);

    // Store token for other apps
    $jwtService->storeToken($token, $uzivatel->id());

    return $token;
}

/**
 * Get JWT token for user (if exists and valid)
 */
function getJwtForUser(
    Uzivatel $uzivatel,
): string {
    $systemoveNastaveni = SystemoveNastaveni::zGlobals();
    $kernel = $systemoveNastaveni->kernel();

    /** @var JwtService $jwtService */
    $jwtService = $kernel->getContainer()->get(JwtService::class);

    return $jwtService->getToken($uzivatel->id());
}

/**
 * Delete JWT token for user (called on logout)
 */
function deleteJwtForUser(Uzivatel $uzivatel): void
{
    try {
        $systemoveNastaveni = SystemoveNastaveni::zGlobals();
        $kernel = $systemoveNastaveni->kernel();

        /** @var JwtService $jwtService */
        $jwtService = $kernel->getContainer()->get(JwtService::class);
        $jwtService->deleteToken($uzivatel->id());

        // Also clear the cookie if it exists
        if (isset($_COOKIE['gamecon_jwt'])) {
            setcookie(
                'gamecon_jwt',
                '',
                time() - 3600, // Expire in the past
                '/',
                $_SERVER['HTTP_HOST'] ?? 'localhost',
                isset($_SERVER['HTTPS']),
                true
            );
        }
    } catch (Exception $e) {
        error_log("JWT deletion failed: " . $e->getMessage());
    }
}

/**
 * Cleanup expired JWT tokens
 * Call this periodically (e.g., in cron job)
 */
function cleanupExpiredJwtTokens(): void
{
    try {
        $systemoveNastaveni = SystemoveNastaveni::zGlobals();
        $kernel = $systemoveNastaveni->kernel();

        /** @var JwtService $jwtService */
        $jwtService = $kernel->getContainer()->get(JwtService::class);
        $jwtService->cleanupExpiredTokens();
    } catch (Exception $e) {
        error_log("JWT cleanup failed: " . $e->getMessage());
    }
}

/**
 * Set JWT token in cookie for frontend access (optional)
 * Useful if other apps needs to access token via HTTP cookie
 */
function setJwtCookie(
    ?string $token,
    ?Uzivatel $uzivatel,
): void {
    if ($token && $uzivatel) {
        if (headers_sent()) {
            throw new RuntimeException('Cannot set JWT cookie, headers already sent');
        }
        setcookie(
            name: 'gamecon_jwt',
            value: $token,
            expires_or_options: time() + 3600, // 1 hour
            secure: isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            path: '/',
            httponly: true,
        ) ?: throw new RuntimeException('Failed to set JWT cookie');
    }
}
