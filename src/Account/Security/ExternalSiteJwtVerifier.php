<?php

declare(strict_types=1);

namespace App\Account\Security;

use Symfony\Component\Security\Core\Exception\BadCredentialsException;

final readonly class ExternalSiteJwtVerifier
{
    public function __construct(
        private string $issuer,
        private string $audience,
        private string $subject,
        private string $publicKey,
    ) {
    }

    /** @return array<string, mixed> */
    public function verify(string $token): array
    {
        $parts = explode('.', $token);
        if (3 !== count($parts)) { throw new BadCredentialsException('JWT mal formé.'); }
        [$headerPart, $payloadPart, $signaturePart] = $parts;
        $header = $this->decodeJson($headerPart);
        $claims = $this->decodeJson($payloadPart);
        if ('RS256' !== ($header['alg'] ?? null)) { throw new BadCredentialsException('Algorithme JWT invalide.'); }
        $key = str_starts_with($this->publicKey, '-----BEGIN') ? $this->publicKey : @file_get_contents($this->publicKey);
        if (!is_string($key) || '' === $key || 1 !== openssl_verify($headerPart.'.'.$payloadPart, $this->decode($signaturePart), $key, OPENSSL_ALGO_SHA256)) {
            throw new BadCredentialsException('Signature JWT invalide.');
        }
        foreach (['iss', 'aud', 'sub', 'iat', 'exp', 'jti'] as $required) {
            if (!array_key_exists($required, $claims)) { throw new BadCredentialsException('Claim JWT manquant.'); }
        }
        $audiences = is_array($claims['aud']) ? $claims['aud'] : [$claims['aud']];
        $now = time();
        if ($claims['iss'] !== $this->issuer || !in_array($this->audience, $audiences, true) || $claims['sub'] !== $this->subject) {
            throw new BadCredentialsException('Identité JWT invalide.');
        }
        if (!is_int($claims['iat']) || !is_int($claims['exp']) || $claims['iat'] > $now + 30 || $claims['exp'] <= $now || !is_string($claims['jti']) || '' === trim($claims['jti'])) {
            throw new BadCredentialsException('Claims JWT invalides.');
        }

        return $claims;
    }

    /** @return array<string, mixed> */
    private function decodeJson(string $part): array
    {
        try { $value = json_decode($this->decode($part), true, 32, JSON_THROW_ON_ERROR); } catch (\Throwable) { throw new BadCredentialsException('JWT mal formé.'); }
        if (!is_array($value)) { throw new BadCredentialsException('JWT mal formé.'); }
        return $value;
    }

    private function decode(string $value): string
    {
        $padding = (4 - strlen($value) % 4) % 4;
        $decoded = base64_decode(strtr($value.str_repeat('=', $padding), '-_', '+/'), true);
        if (false === $decoded) { throw new BadCredentialsException('JWT mal formé.'); }
        return $decoded;
    }
}
