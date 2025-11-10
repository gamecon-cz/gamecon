<?php

declare(strict_types=1);

namespace Gamecon\Tests\Symfony\Service;

use App\Service\Exception\JwtTokenException;
use App\Service\JwtService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

class JwtServiceTest extends TestCase
{
    private string $testCacheDir;
    private JwtService $jwtService;
    private const TEST_SECRET = 'test-secret-key-for-jwt';
    private const TEST_USER_ID = 42;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a temporary directory for testing
        $this->testCacheDir = SPEC . '/jwt_service_test_' . uniqid();
        mkdir($this->testCacheDir, 0755, true);

        $this->jwtService = new JwtService(
            secret: self::TEST_SECRET,
            legacyCacheDir: $this->testCacheDir,
            algorithm: 'HS256',
            expirationInSeconds: 3600,
        );
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        // Clean up test directory
        $tokenDir = $this->testCacheDir . '/jwt_tokens';
        if (is_dir($tokenDir)) {
            $files = glob($tokenDir . '/*');
            if ($files !== false) {
                foreach ($files as $file) {
                    if (is_file($file)) {
                        unlink($file);
                    }
                }
            }
            rmdir($tokenDir);
        }
        if (is_dir($this->testCacheDir)) {
            rmdir($this->testCacheDir);
        }
    }

    public function testGenerateJwtTokenCreatesValidToken(): void
    {
        $userData = [
            'id'    => 123,
            'login' => 'testuser',
            'email' => 'test@example.com',
        ];

        $token = $this->jwtService->generateJwtToken($userData);

        self::assertIsString($token);
        self::assertNotEmpty($token);
        self::assertStringContainsString('.', $token); // JWT format has dots
    }

    public function testDecodeJwtTokenReturnsValidPayload(): void
    {
        $userData = [
            'id'    => 123,
            'login' => 'testuser',
            'email' => 'test@example.com',
        ];

        $token = $this->jwtService->generateJwtToken($userData);
        $decoded = $this->jwtService->decodeJwtToken($token);

        self::assertIsArray($decoded);
        self::assertArrayHasKey('iss', $decoded);
        self::assertArrayHasKey('aud', $decoded);
        self::assertArrayHasKey('iat', $decoded);
        self::assertArrayHasKey('exp', $decoded);
        self::assertArrayHasKey('user', $decoded);
        self::assertEquals('gamecon-php', $decoded['iss']);
        self::assertEquals('gamecon-csharp', $decoded['aud']);

        // Verify user data is properly embedded
        self::assertEquals($userData['id'], $decoded['user']->id);
        self::assertEquals($userData['login'], $decoded['user']->login);
        self::assertEquals($userData['email'], $decoded['user']->email);
    }

    public function testDecodeJwtTokenReturnsNullForInvalidToken(): void
    {
        $invalidToken = 'invalid.jwt.token';

        $decoded = $this->jwtService->decodeJwtToken($invalidToken);

        self::assertNull($decoded);
    }

    public function testDecodeJwtTokenReturnsNullForTokenWithWrongSecret(): void
    {
        // Create token with different service
        $differentService = new JwtService(
            secret: 'different-secret',
            legacyCacheDir: $this->testCacheDir,
        );
        $token = $differentService->generateJwtToken([
            'id' => 1,
        ]);

        // Try to decode with original service
        $decoded = $this->jwtService->decodeJwtToken($token);

        self::assertNull($decoded);
    }

    public function testDecodeJwtTokenReturnsNullForExpiredToken(): void
    {
        // Create service with very short expiration
        $shortExpirationService = new JwtService(
            secret: self::TEST_SECRET,
            legacyCacheDir: $this->testCacheDir,
            expirationInSeconds: -1, // Already expired
        );

        $token = $shortExpirationService->generateJwtToken([
            'id' => 1,
        ]);

        // Token should be expired and fail validation
        $decoded = $this->jwtService->decodeJwtToken($token);

        self::assertNull($decoded);
    }

    public function testExtractUserDataReturnsCorrectStructure(): void
    {
        $uzivatel = $this->createMock(\Uzivatel::class);
        $uzivatel->method('id')->willReturn(123);
        $uzivatel->method('login')->willReturn('testuser');
        $uzivatel->method('jmenoNick')->willReturn('Test User');
        $uzivatel->method('mail')->willReturn('test@example.com');

        $userData = $this->jwtService->extractUserData($uzivatel);

        self::assertIsArray($userData);
        self::assertArrayHasKey('id', $userData);
        self::assertArrayHasKey('login', $userData);
        self::assertArrayHasKey('jmeno', $userData);
        self::assertArrayHasKey('email', $userData);
        self::assertArrayHasKey('logged_at', $userData);

        self::assertEquals(123, $userData['id']);
        self::assertEquals('testuser', $userData['login']);
        self::assertEquals('Test User', $userData['jmeno']);
        self::assertEquals('test@example.com', $userData['email']);
        self::assertIsInt($userData['logged_at']);
        self::assertGreaterThan(0, $userData['logged_at']);
    }

    public function testExtractUserDataWithNullValues(): void
    {
        $uzivatel = $this->createMock(\Uzivatel::class);
        $uzivatel->method('id')->willReturn(null);
        $uzivatel->method('login')->willReturn('testuser');
        $uzivatel->method('jmenoNick')->willReturn('Test User');
        $uzivatel->method('mail')->willReturn(null);

        $userData = $this->jwtService->extractUserData($uzivatel);

        self::assertNull($userData['id']);
        self::assertNull($userData['email']);
        self::assertEquals('testuser', $userData['login']);
        self::assertEquals('Test User', $userData['jmeno']);
    }

    public function testStoreTokenCreatesFile(): void
    {
        $token = $this->jwtService->generateJwtToken([
            'id' => 1,
        ]);

        $this->jwtService->storeToken($token, self::TEST_USER_ID);

        $tokenFile = $this->testCacheDir . '/jwt_tokens/jwt_' . self::TEST_USER_ID . '.token';
        self::assertFileExists($tokenFile);
        self::assertEquals($token, file_get_contents($tokenFile));
    }

    public function testStoreTokenCreatesDirectoryIfNotExists(): void
    {
        $tokenDir = $this->testCacheDir . '/jwt_tokens';
        self::assertDirectoryDoesNotExist($tokenDir);

        $token = $this->jwtService->generateJwtToken([
            'id' => 1,
        ]);
        $this->jwtService->storeToken($token, self::TEST_USER_ID);

        self::assertDirectoryExists($tokenDir);
    }

    public function testGetTokenReturnsStoredToken(): void
    {
        $token = $this->jwtService->generateJwtToken([
            'id' => 1,
        ]);
        $this->jwtService->storeToken($token, self::TEST_USER_ID);

        $retrievedToken = $this->jwtService->getToken(self::TEST_USER_ID);

        self::assertEquals($token, $retrievedToken);
    }

    public function testGetTokenReturnsNullWhenFileDoesNotExist(): void
    {
        $retrievedToken = $this->jwtService->getToken(999);

        self::assertNull($retrievedToken);
    }

    public function testDeleteTokenRemovesFile(): void
    {
        $token = $this->jwtService->generateJwtToken([
            'id' => 1,
        ]);
        $this->jwtService->storeToken($token, self::TEST_USER_ID);

        $tokenFile = $this->testCacheDir . '/jwt_tokens/jwt_' . self::TEST_USER_ID . '.token';
        self::assertFileExists($tokenFile);

        $this->jwtService->deleteToken(self::TEST_USER_ID);

        self::assertFileDoesNotExist($tokenFile);
    }

    public function testDeleteTokenDoesNotThrowExceptionWhenFileDoesNotExist(): void
    {
        // Should not throw exception
        $this->jwtService->deleteToken(999);

        // If we got here, the test passes
        self::assertTrue(true);
    }

    public function testCleanupExpiredTokensRemovesOldFiles(): void
    {
        // Create service with short expiration
        $shortExpirationService = new JwtService(
            secret: self::TEST_SECRET,
            legacyCacheDir: $this->testCacheDir,
            expirationInSeconds: 1,
        );

        // Store a token
        $token = $shortExpirationService->generateJwtToken([
            'id' => 1,
        ]);
        $shortExpirationService->storeToken($token, self::TEST_USER_ID);

        $tokenFile = $this->testCacheDir . '/jwt_tokens/jwt_' . self::TEST_USER_ID . '.token';
        self::assertFileExists($tokenFile);

        // Make the file appear old by touching it with old timestamp
        touch($tokenFile, time() - 10);

        // Run cleanup
        $shortExpirationService->cleanupExpiredTokens();

        // File should be deleted
        self::assertFileDoesNotExist($tokenFile);
    }

    public function testCleanupExpiredTokensKeepsRecentFiles(): void
    {
        $token = $this->jwtService->generateJwtToken([
            'id' => 1,
        ]);
        $this->jwtService->storeToken($token, self::TEST_USER_ID);

        $tokenFile = $this->testCacheDir . '/jwt_tokens/jwt_' . self::TEST_USER_ID . '.token';
        self::assertFileExists($tokenFile);

        // Run cleanup - file is recent, should not be deleted
        $this->jwtService->cleanupExpiredTokens();

        self::assertFileExists($tokenFile);
    }

    public function testCleanupExpiredTokensDoesNotThrowExceptionWhenDirectoryDoesNotExist(): void
    {
        // Create service with non-existent directory
        $nonExistentService = new JwtService(
            secret: self::TEST_SECRET,
            legacyCacheDir: SPEC . '/non_existent_' . uniqid(),
        );

        // Should not throw exception
        $nonExistentService->cleanupExpiredTokens();

        self::assertTrue(true);
    }

    public function testStoreTokenThrowsExceptionWhenCannotWriteFile(): void
    {
        // Create a read-only directory to force write failure
        $readOnlyDir = SPEC . '/jwt_readonly_test_' . uniqid();
        mkdir($readOnlyDir, 0755, true);
        $tokenDir = $readOnlyDir . '/jwt_tokens';
        mkdir($tokenDir, 0755, true);

        $readOnlyService = new JwtService(
            secret: self::TEST_SECRET,
            legacyCacheDir: $readOnlyDir,
        );

        // Make directory read-only
        self::assertTrue(chmod($tokenDir, 0555), 'Can not change permissions to dir ' . $tokenDir);

        try {
            $this->expectException(JwtTokenException::class);
            $this->expectExceptionMessage('Can not write to file');

            $token = $readOnlyService->generateJwtToken([
                'id' => 1,
            ]);
            $readOnlyService->storeToken($token, self::TEST_USER_ID);
        } finally {
            // Cleanup
            chmod($tokenDir, 0755);
            if (is_dir($tokenDir)) {
                (new Filesystem())->remove($tokenDir);
            }
            if (is_dir($readOnlyDir)) {
                (new Filesystem())->remove($tokenDir);
            }
        }
    }

    public function testMultipleUsersCanHaveTokensStored(): void
    {
        $token1 = $this->jwtService->generateJwtToken([
            'id' => 1,
        ]);
        $token2 = $this->jwtService->generateJwtToken([
            'id' => 2,
        ]);
        $token3 = $this->jwtService->generateJwtToken([
            'id' => 3,
        ]);

        $this->jwtService->storeToken($token1, 100);
        $this->jwtService->storeToken($token2, 200);
        $this->jwtService->storeToken($token3, 300);

        self::assertEquals($token1, $this->jwtService->getToken(100));
        self::assertEquals($token2, $this->jwtService->getToken(200));
        self::assertEquals($token3, $this->jwtService->getToken(300));
    }

    public function testTokenRoundTripWithUserData(): void
    {
        // Create a mock user
        $uzivatel = $this->createMock(\Uzivatel::class);
        $uzivatel->method('id')->willReturn(123);
        $uzivatel->method('login')->willReturn('testuser');
        $uzivatel->method('jmenoNick')->willReturn('Test User');
        $uzivatel->method('mail')->willReturn('test@example.com');

        // Extract user data
        $userData = $this->jwtService->extractUserData($uzivatel);

        // Generate token
        $token = $this->jwtService->generateJwtToken($userData);

        // Store token
        $this->jwtService->storeToken($token, 123);

        // Retrieve token
        $retrievedToken = $this->jwtService->getToken(123);

        // Decode token
        $decoded = $this->jwtService->decodeJwtToken($retrievedToken);

        // Verify data integrity
        self::assertEquals(123, $decoded['user']->id);
        self::assertEquals('testuser', $decoded['user']->login);
        self::assertEquals('Test User', $decoded['user']->jmeno);
        self::assertEquals('test@example.com', $decoded['user']->email);
    }
}
