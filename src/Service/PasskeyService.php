<?php

declare(strict_types=1);

namespace S3Gallery\Service;

use lbuchs\WebAuthn\WebAuthn;
use PDO;

final class PasskeyService
{
    private readonly WebAuthn $webAuthn;

    public function __construct(
        private readonly PDO $db,
        string $rpName = 'S3 Gallery',
        ?string $rpId = null,
    ) {
        $rpId = $rpId ?: ($_ENV['S3G_RP_ID'] ?? 'localhost');
        $this->webAuthn = new WebAuthn($rpName, $rpId, ['none']);
    }

    public function isOtpConfigured(): bool
    {
        $otp = $_ENV['S3G_REGISTRATION_OTP'] ?? '';
        return $otp !== '';
    }

    public function isOtpConsumed(): bool
    {
        $stmt = $this->db->query('SELECT consumed FROM otp_status WHERE id = 1 LIMIT 1');
        $row = $stmt->fetch();
        return $row && (bool) $row['consumed'];
    }

    public function verifyOtp(string $input): bool
    {
        $expected = $_ENV['S3G_REGISTRATION_OTP'] ?? '';
        if ($expected === '') {
            return false;
        }
        return hash_equals($expected, $input);
    }

    public function getCreateArgs(): \stdClass
    {
        $userId = random_bytes(32);
        $userName = 'gallery-admin';
        $userDisplayName = 'Gallery Administrator';

        $createArgs = $this->webAuthn->getCreateArgs(
            $userId,
            $userName,
            $userDisplayName,
            60,
            true,
            'required',
            null,
        );

        return $createArgs;
    }

    public function getChallenge(): string
    {
        return $this->webAuthn->getChallenge()->getHex();
    }

    public function processRegistration(
        string $clientDataJSON,
        string $attestationObject,
        string $challengeHex,
    ): void {
        $challenge = \lbuchs\WebAuthn\Binary\ByteBuffer::fromHex($challengeHex);

        $credential = $this->webAuthn->processCreate(
            $clientDataJSON,
            $attestationObject,
            $challenge,
            true,
            true,
            false,
        );

        $this->storeCredential($credential);
        $this->markOtpConsumed();
    }

    public function hasPasskeys(): bool
    {
        $stmt = $this->db->query('SELECT COUNT(*) FROM passkeys');
        return (int) $stmt->fetchColumn() > 0;
    }

    public function getGetArgs(): \stdClass
    {
        $credentialIds = [];
        $stmt = $this->db->query('SELECT credential_id FROM passkeys');
        while ($row = $stmt->fetch()) {
            $credentialIds[] = \lbuchs\WebAuthn\Binary\ByteBuffer::fromHex($row['credential_id']);
        }

        return $this->webAuthn->getGetArgs($credentialIds, 60);
    }

    public function processAuthentication(
        string $id,
        string $clientDataJSON,
        string $authenticatorData,
        string $signature,
        string $challengeHex,
    ): bool {
        $challenge = \lbuchs\WebAuthn\Binary\ByteBuffer::fromHex($challengeHex);

        $credentialHex = bin2hex(base64_decode(strtr($id, '-_', '+/')));

        $stmt = $this->db->prepare('SELECT public_key, counter FROM passkeys WHERE credential_id = :cid LIMIT 1');
        $stmt->execute(['cid' => $credentialHex]);
        $row = $stmt->fetch();

        if (!$row) {
            return false;
        }

        $publicKey = $row['public_key'];
        $prevCounter = (int) $row['counter'];

        $this->webAuthn->processGet(
            $clientDataJSON,
            $authenticatorData,
            $signature,
            \lbuchs\WebAuthn\Binary\ByteBuffer::fromHex($publicKey),
            $challenge,
            $prevCounter,
            true,
            true,
        );

        $newCounter = $this->webAuthn->getSignatureCounter();
        $stmt = $this->db->prepare('UPDATE passkeys SET counter = :counter WHERE credential_id = :cid');
        $stmt->execute(['counter' => $newCounter, 'cid' => $credentialHex]);

        return true;
    }

    private function storeCredential(object $credential): void
    {
        $credentialId = $credential->credentialId->getHex();
        $publicKey = $credential->credentialPublicKey->getHex();

        $stmt = $this->db->prepare(
            'INSERT INTO passkeys (credential_id, public_key, counter)
             VALUES (:cid, :pk, :counter)'
        );
        $stmt->execute([
            'cid' => $credentialId,
            'pk' => $publicKey,
            'counter' => 0,
        ]);
    }

    private function markOtpConsumed(): void
    {
        $this->db->exec('UPDATE otp_status SET consumed = 1, consumed_at = NOW() WHERE id = 1');
    }
}
