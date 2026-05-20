<?php

namespace App\Service;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class GoogleTokenVerifierService
{
    private const GOOGLE_CERTS_URL = 'https://www.googleapis.com/oauth2/v3/certs';
    private const GOOGLE_TOKENINFO_URL = 'https://www.googleapis.com/oauth2/v3/tokeninfo';

    private ?array $googlePublicKeys = null;

    public function __construct(
        private HttpClientInterface $httpClient,
        private ?LoggerInterface $logger = null,
    ) {
    }

    /**
     * Verify Google ID token and return decoded token data.
     *
     * @return array<string, mixed>|null
     */
    public function verifyToken(string $token, ?string $expectedAudience = null): ?array
    {
        $token = trim($token);

        if ($token === '' || substr_count($token, '.') !== 2) {
            return null;
        }

        try {
            $tokenData = $this->verifyTokenLocally($token);

            if ($tokenData === null) {
                $tokenData = $this->verifyTokenWithGoogle($token);
            }

            if ($tokenData === null || !isset($tokenData['sub'])) {
                return null;
            }

            if ($expectedAudience !== null && $expectedAudience !== '') {
                $tokenAudience = $tokenData['aud'] ?? null;
                if ($tokenAudience !== $expectedAudience) {
                    $this->logger?->warning('Google token audience mismatch', [
                        'expected' => $expectedAudience,
                        'actual' => $tokenAudience,
                    ]);

                    return null;
                }
            }

            return $tokenData;
        } catch (\Exception $e) {
            $this->logger?->error('Google token verification failed', ['error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function verifyTokenLocally(string $token): ?array
    {
        try {
            $publicKeys = $this->getGooglePublicKeys();

            if ($publicKeys === []) {
                return null;
            }

            $parts = explode('.', $token);
            $header = json_decode($this->base64UrlDecode($parts[0]), true);

            if (!is_array($header)) {
                return null;
            }

            $kid = $header['kid'] ?? null;

            if (!$kid || !isset($publicKeys[$kid])) {
                return null;
            }

            $decoded = JWT::decode($token, new Key($publicKeys[$kid], 'RS256'));

            return json_decode(json_encode($decoded), true);
        } catch (\Exception $e) {
            $this->logger?->debug('Local Google token verification failed', ['error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function verifyTokenWithGoogle(string $token): ?array
    {
        try {
            $response = $this->httpClient->request('GET', self::GOOGLE_TOKENINFO_URL, [
                'query' => ['id_token' => $token],
                'timeout' => 10,
            ]);

            if ($response->getStatusCode() !== 200) {
                return null;
            }

            $data = $response->toArray();

            if (!is_array($data) || isset($data['error'])) {
                return null;
            }

            return $data;
        } catch (\Exception $e) {
            $this->logger?->debug('Google tokeninfo verification failed', ['error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * @return array<string, string>
     */
    private function getGooglePublicKeys(): array
    {
        if ($this->googlePublicKeys !== null) {
            return $this->googlePublicKeys;
        }

        try {
            $response = $this->httpClient->request('GET', self::GOOGLE_CERTS_URL, [
                'timeout' => 10,
            ]);

            if ($response->getStatusCode() !== 200) {
                return [];
            }

            $keys = $response->toArray();
            $this->googlePublicKeys = is_array($keys) ? $keys : [];

        } catch (\Exception $e) {
            $this->logger?->warning('Failed to fetch Google public keys', ['error' => $e->getMessage()]);
            $this->googlePublicKeys = [];
        }

        return $this->googlePublicKeys;
    }

    private function base64UrlDecode(string $data): string
    {
        $remainder = strlen($data) % 4;
        if ($remainder > 0) {
            $data .= str_repeat('=', 4 - $remainder);
        }

        return base64_decode(strtr($data, '-_', '+/'), true) ?: '';
    }
}
