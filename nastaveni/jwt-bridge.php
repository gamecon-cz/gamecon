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
    $userId = $uzivatel->id();
    if ($userId === null) {
        throw new \RuntimeException('Can not store JWT token because user ID is empty');
    }

    // Use existing Symfony kernel from SystemoveNastaveni
    $systemoveNastaveni ??= SystemoveNastaveni::zGlobals();
    $kernel             = $systemoveNastaveni->kernel();

    /** @var JwtService $jwtService */
    $jwtService = $kernel->getContainer()->get(JwtService::class);

    // Extract user data and generate token
    $userData = $jwtService->extractUserData($uzivatel);
    $token    = $jwtService->generateJwtToken($userData);

    // Store token for other apps
    $jwtService->storeToken($token, $userId);

    return $token;
}

/**
 * Get JWT token for the user (if exists and valid)
 */
function getJwtForUser(
    Uzivatel $uzivatel,
): ?string {
    $userId = $uzivatel->id();
    if ($userId === null) {
        throw new \RuntimeException('Can not get JWT token because user ID is empty');
    }

    $systemoveNastaveni = SystemoveNastaveni::zGlobals();
    $kernel             = $systemoveNastaveni->kernel();

    /** @var JwtService $jwtService */
    $jwtService = $kernel->getContainer()->get(JwtService::class);

    return $jwtService->getToken($userId);
}

/**
 * Delete JWT token for the user (called on logout)
 */
function deleteJwtForUser(
    Uzivatel $uzivatel,
): void {
    try {
        $userId = $uzivatel->id();
        if ($userId === null) {
            throw new \RuntimeException('Can not delete JWT token because user ID is empty');
        }

        $systemoveNastaveni = SystemoveNastaveni::zGlobals();
        $kernel             = $systemoveNastaveni->kernel();

        /** @var JwtService $jwtService */
        $jwtService = $kernel->getContainer()->get(JwtService::class);
        $jwtService->deleteToken($userId);

        // Also clear the cookie if it exists
        if (isset($_COOKIE['gamecon_jwt'])) {
            $result = setcookie(
                name: 'gamecon_jwt',
                value: '',
                expires_or_options: time() - 3600, // Expire in the past
                path: '/',
                domain: $_SERVER['HTTP_HOST'] ?? 'localhost',
                secure: isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
                httponly: true,
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
    string $token,
): void {
    if (headers_sent()) {
        throw new RuntimeException('Can not set JWT cookie, headers already sent');
    }
    $result = setcookie(
        name: 'gamecon_jwt',
        value: $token,
        expires_or_options: time() + 3600, // 1 hour
        secure: isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        domain: $_SERVER['HTTP_HOST'] ?? 'localhost',
        path: '/',
        httponly: true,
    );
    if ($result === false) {
        throw new RuntimeException('Failed to set JWT cookie: ' . var_export(error_get_last(), true));
    }
}
