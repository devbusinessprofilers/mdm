<?php

declare(strict_types=1);

namespace App\Tests\Support;

use PHPUnit\Framework\Assert;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/**
 * Client de l'API externe (/api/v1) dans les tests : clé RSA jetable posée
 * dans EXTERNAL_SITE_JWT_PUBLIC_KEY, jeton signé avec tous les scopes, en-têtes
 * Bearer. Appeler installerCleJwt() dans setUp() et retirerCleJwt() dans tearDown().
 */
trait ClientApiExterne
{
    /** @var \OpenSSLAsymmetricKey */
    private $clePrivee;
    private string $fichierClePublique = '';

    private function installerCleJwt(): void
    {
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        Assert::assertInstanceOf(\OpenSSLAsymmetricKey::class, $key);
        $details = openssl_pkey_get_details($key);
        Assert::assertIsArray($details);
        $path = tempnam(sys_get_temp_dir(), 'mdm-api-public-');
        Assert::assertIsString($path);
        file_put_contents($path, $details['key']);
        $this->clePrivee = $key;
        $this->fichierClePublique = $path;
        putenv('EXTERNAL_SITE_JWT_PUBLIC_KEY='.$path);
        $_ENV['EXTERNAL_SITE_JWT_PUBLIC_KEY'] = $path;
        $_SERVER['EXTERNAL_SITE_JWT_PUBLIC_KEY'] = $path;
    }

    private function retirerCleJwt(): void
    {
        if ('' !== $this->fichierClePublique) {
            @unlink($this->fichierClePublique);
        }
        putenv('EXTERNAL_SITE_JWT_PUBLIC_KEY');
        unset($_ENV['EXTERNAL_SITE_JWT_PUBLIC_KEY'], $_SERVER['EXTERNAL_SITE_JWT_PUBLIC_KEY']);
    }

    /**
     * @param array<string, string> $extra
     *
     * @return array<string, string>
     */
    private function entetesApi(array $extra = []): array
    {
        return $extra + ['HTTP_AUTHORIZATION' => 'Bearer '.$this->jetonApi(), 'HTTP_ACCEPT' => 'application/json'];
    }

    private function jetonApi(string $scope = 'fiches:write medias:write documents:read documents:write documents:private documents:publish'): string
    {
        $encode = static fn (array $value): string => rtrim(strtr(base64_encode(json_encode($value, JSON_THROW_ON_ERROR)), '+/', '-_'), '=');
        $header = $encode(['alg' => 'RS256', 'typ' => 'JWT']);
        $now = time();
        $payload = $encode(['iss' => 'external-site', 'aud' => 'mdm', 'sub' => 'external-site', 'iat' => $now, 'exp' => $now + 300, 'jti' => bin2hex(random_bytes(8)), 'scope' => $scope]);
        Assert::assertTrue(openssl_sign($header.'.'.$payload, $signature, $this->clePrivee, OPENSSL_ALGO_SHA256));

        return $header.'.'.$payload.'.'.rtrim(strtr(base64_encode($signature), '+/', '-_'), '=');
    }

    /** @return array<array-key, mixed> */
    private function jsonApi(KernelBrowser $client): array
    {
        $data = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        Assert::assertIsArray($data, 'Réponse JSON attendue.');

        return $data;
    }

    /** PNG valide (RGB) aux dimensions données, sans dépendance GD. */
    private function pngApi(int $width, int $height): string
    {
        $chunk = static fn (string $type, string $data): string => pack('N', strlen($data)).$type.$data.pack('N', crc32($type.$data));
        $compressed = gzcompress(str_repeat("\0".str_repeat("\0", $width * 3), $height), 9);
        Assert::assertIsString($compressed);

        return "\x89PNG\r\n\x1a\n"
            .$chunk('IHDR', pack('NNCCCCC', $width, $height, 8, 2, 0, 0, 0))
            .$chunk('IDAT', $compressed)
            .$chunk('IEND', '');
    }
}
