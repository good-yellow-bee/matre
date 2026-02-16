<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Security;

use App\Service\Security\CredentialEncryptionService;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class CredentialEncryptionServiceTest extends TestCase
{
    private CredentialEncryptionService $service;

    protected function setUp(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $this->service = new CredentialEncryptionService('test-secret-key-for-testing', $logger);
    }

    public function testEncryptDecryptRoundTrip(): void
    {
        $plaintext = 'my-secret-password';

        $encrypted = $this->service->encrypt($plaintext);
        $decrypted = $this->service->decrypt($encrypted);

        $this->assertSame($plaintext, $decrypted);
        $this->assertNotSame($plaintext, $encrypted);
    }

    public function testEncryptEmptyStringPassthrough(): void
    {
        $this->assertSame('', $this->service->encrypt(''));
    }

    public function testDecryptEmptyStringPassthrough(): void
    {
        $this->assertSame('', $this->service->decrypt(''));
    }

    public function testDecryptTamperedDataThrowsException(): void
    {
        $encrypted = $this->service->encrypt('secret-data');
        $decoded = base64_decode($encrypted, true);
        $tampered = substr($decoded, 0, -1) . 'X';

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Decryption failed');

        $this->service->decrypt(base64_encode($tampered));
    }

    public function testDecryptInvalidBase64ThrowsException(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Invalid encrypted data format');

        $this->service->decrypt('not-valid-base64!!!');
    }

    public function testDecryptTooShortDataThrowsException(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Encrypted data too short');

        $this->service->decrypt(base64_encode('short'));
    }

    public function testIsEncryptedReturnsFalseForEmptyString(): void
    {
        $this->assertFalse($this->service->isEncrypted(''));
    }

    public function testIsEncryptedReturnsFalseForNonBase64(): void
    {
        $this->assertFalse($this->service->isEncrypted('not-valid-base64!!!'));
    }

    public function testIsEncryptedReturnsTrueForEncryptedData(): void
    {
        $encrypted = $this->service->encrypt('test-value');

        $this->assertTrue($this->service->isEncrypted($encrypted));
    }

    public function testEncryptIfNeededSkipsAlreadyEncrypted(): void
    {
        $encrypted = $this->service->encrypt('my-password');

        $result = $this->service->encryptIfNeeded($encrypted);

        $this->assertSame($encrypted, $result);
    }

    public function testEncryptIfNeededEncryptsPlainValues(): void
    {
        $plaintext = 'my-password';

        $result = $this->service->encryptIfNeeded($plaintext);

        $this->assertNotSame($plaintext, $result);
        $this->assertTrue($this->service->isEncrypted($result));
        $this->assertSame($plaintext, $this->service->decrypt($result));
    }

    public function testDecryptSafeReturnsOriginalOnFailure(): void
    {
        $this->assertSame('not-encrypted', $this->service->decryptSafe('not-encrypted'));
    }

    public function testDecryptSafeReturnsOriginalForBase64LikeLegacyPlaintext(): void
    {
        $base64LikePlaintext = base64_encode(str_repeat('A', 40));

        $this->assertTrue($this->service->isEncrypted($base64LikePlaintext));
        $this->assertSame($base64LikePlaintext, $this->service->decryptSafe($base64LikePlaintext));
    }

    public function testDifferentAppSecretsProduceDifferentCiphertexts(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $other = new CredentialEncryptionService('different-secret-key', $logger);
        $plaintext = 'same-password';

        $encrypted1 = $this->service->encrypt($plaintext);
        $encrypted2 = $other->encrypt($plaintext);

        $this->assertNotSame($encrypted1, $encrypted2);

        $this->expectException(\RuntimeException::class);
        $other->decrypt($encrypted1);
    }

    public function testEachEncryptCallProducesUniqueCiphertext(): void
    {
        $plaintext = 'same-password';

        $encrypted1 = $this->service->encrypt($plaintext);
        $encrypted2 = $this->service->encrypt($plaintext);

        $this->assertNotSame($encrypted1, $encrypted2);
        $this->assertSame($plaintext, $this->service->decrypt($encrypted1));
        $this->assertSame($plaintext, $this->service->decrypt($encrypted2));
    }
}
