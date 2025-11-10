<?php

/**
 * JWT Bridge for sharing authentication between this (legacy app) and other applications (like C# store)
 *
 * This file provides functions to generate JWT tokens for logged-in users.
 */

use App\Service\JwtService;
use Gamecon\SystemoveNastaveni\SystemoveNastaveni;

/**
 * Generate JWT token for the current user
 * Call this function when the user logs in or when needed for C# app integration
 */
function generateJwtForUser(
    Uzivatel            $uzivatel,
    ?SystemoveNastaveni $systemoveNastaveni = null,
): string {
    // Use existing Symfony kernel from SystemoveNastaveni
    $systemoveNastaveni ??= SystemoveNastaveni::zGlobals();
    $kernel             = $systemoveNastaveni->kernel();

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
 * Get JWT token for the user (if exists and valid)
 */
function getJwtForUser(
    Uzivatel $uzivatel,
): ?string {
    $systemoveNastaveni = SystemoveNastaveni::zGlobals();
    $kernel             = $systemoveNastaveni->kernel();

    /** @var JwtService $jwtService */
    $jwtService = $kernel->getContainer()->get(JwtService::class);

    return $jwtService->getToken($uzivatel->id());
}

/**
 * Delete JWT token for the user (called on logout)
 */
function deleteJwtForUser(
    Uzivatel $uzivatel,
): void {
    try {
        $systemoveNastaveni = SystemoveNastaveni::zGlobals();
        $kernel             = $systemoveNastaveni->kernel();

        /** @var JwtService $jwtService */
        $jwtService = $kernel->getContainer()->get(JwtService::class);
        $jwtService->deleteToken($uzivatel->id());

        // Also clear the cookie if it exists
        if (isset($_COOKIE['gamecon_jwt'])) {
            $result = setcookie(
                'gamecon_jwt',
                '',
                time() - 3600, // Expire in the past
                '/',
                $_SERVER['HTTP_HOST'] ?? 'localhost',
                isset($_SERVER['HTTPS']),
                true,
            );
            if ($result === false) {
                throw new RuntimeException(
                    "Can not delete cookie 'gamecon_jwt': " . var_export(error_get_last(), true),
                );
            }
        }
    } catch (Exception $e) {
        error_log("JWT deletion failed: " . $e->getMessage());
    }
}

/**
 * Clean-up expired JWT tokens
 * Call this periodically (e.g., in a cron job)
 */
function cleanupExpiredJwtTokens(): void
{
    try {
        $systemoveNastaveni = SystemoveNastaveni::zGlobals();
        $kernel             = $systemoveNastaveni->kernel();

        /** @var JwtService $jwtService */
        $jwtService = $kernel->getContainer()->get(JwtService::class);
        $jwtService->cleanupExpiredTokens();
    } catch (Exception $e) {
        error_log("JWT cleanup failed: " . $e->getMessage());
    }
}

/**
 * Set JWT token in the cookie for frontend access (optional)
 * Useful if other apps need to access token via HTTP cookie
 */
function setJwtCookie(
    string   $token,
): void {
    if (headers_sent()) {
        throw new RuntimeException('Cannot set JWT cookie, headers already sent');
    }
    $result = setcookie(
        name: 'gamecon_jwt',
        value: $token,
        expires_or_options: time() + 3600, // 1 hour
        secure: isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        path: '/',
        httponly: true,
    );
    if ($result === false) {
        throw new RuntimeException('Failed to set JWT cookie: ' . var_export(error_get_last(), true));
    }
}
