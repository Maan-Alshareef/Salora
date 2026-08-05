<?php

namespace App\Services;

use App\Models\DeviceToken;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

class PushNotificationService
{
    private const OAUTH_SCOPE =
        'https://www.googleapis.com/auth/firebase.messaging';

    public function isConfigured(): bool
    {
        try {
            return $this->credentials() !== null;
        } catch (\Throwable $exception) {
            report($exception);

            return false;
        }
    }


    public function sendToUser(
        int $userId,
        string $title,
        ?string $body = null,
        array $data = [],
    ): int {
        $credentials = $this->credentials();

        if ($credentials === null) {
            return 0;
        }

        $devices = DeviceToken::query()
            ->where('user_id', $userId)
            ->whereNotNull('token')
            ->get();

        $sent = 0;

        foreach ($devices as $device) {
            if (
                $this->sendToDevice(
                    $device,
                    $title,
                    $body,
                    $data,
                    $credentials,
                )
            ) {
                $sent++;
            }
        }

        return $sent;
    }

    private function sendToDevice(
        DeviceToken $device,
        string $title,
        ?string $body,
        array $data,
        array $credentials,
    ): bool {
        $projectId = trim(
            (string) (
                config('firebase.project_id') ?:
                ($credentials['project_id'] ?? '')
            ),
        );

        if ($projectId === '') {
            Log::warning(
                'Firebase push skipped because project_id is missing.',
            );

            return false;
        }

        $message = [
            'token' => $device->token,
            'notification' => [
                'title' => $title,
                'body' => (string) ($body ?? ''),
            ],
            'data' => $this->stringData($data),
            'android' => [
                'priority' => 'high',
                'notification' => [
                    'channel_id' => (string) config(
                        'firebase.android_channel_id',
                        'salora_high_importance',
                    ),
                    'sound' => 'default',
                    'visibility' => 'PUBLIC',
                ],
            ],
            'apns' => [
                'headers' => [
                    'apns-priority' => '10',
                ],
                'payload' => [
                    'aps' => [
                        'sound' => 'default',
                        'content-available' => 1,
                    ],
                ],
            ],
        ];

        $endpoint =
            "https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send";

        $response = $this->postMessage(
            $endpoint,
            $message,
            $credentials,
            false,
        );

        if ($response->status() === 401) {
            $response = $this->postMessage(
                $endpoint,
                $message,
                $credentials,
                true,
            );
        }

        if ($response->successful()) {
            $device->forceFill([
                'last_seen_at' => now(),
            ])->save();

            return true;
        }

        if ($this->isInvalidTokenResponse($response->json())) {
            $device->delete();
        }

        Log::warning('Firebase push request failed.', [
            'user_id' => $device->user_id,
            'device_token_id' => $device->id,
            'status' => $response->status(),
            'response' => $response->json() ?: $response->body(),
        ]);

        return false;
    }

    private function postMessage(
        string $endpoint,
        array $message,
        array $credentials,
        bool $refreshToken,
    ) {
        return Http::acceptJson()
            ->asJson()
            ->withToken(
                $this->accessToken(
                    $credentials,
                    $refreshToken,
                ),
            )
            ->timeout(20)
            ->post(
                $endpoint,
                ['message' => $message],
            );
    }

    private function accessToken(
        array $credentials,
        bool $refresh = false,
    ): string {
        $clientEmail = trim(
            (string) ($credentials['client_email'] ?? ''),
        );
        $privateKey = (string) (
            $credentials['private_key'] ?? ''
        );
        $tokenUri = trim(
            (string) (
                $credentials['token_uri'] ??
                'https://oauth2.googleapis.com/token'
            ),
        );

        if ($clientEmail === '' || $privateKey === '') {
            throw new RuntimeException(
                'Firebase service-account JSON is missing client_email or private_key.',
            );
        }

        $cacheKey =
            'salora_firebase_access_token_'.sha1($clientEmail);

        if ($refresh) {
            Cache::forget($cacheKey);
        }

        return Cache::remember(
            $cacheKey,
            now()->addMinutes(50),
            function () use (
                $clientEmail,
                $privateKey,
                $tokenUri,
            ) {
                $now = time();

                $header = $this->base64Url(
                    json_encode(
                        [
                            'alg' => 'RS256',
                            'typ' => 'JWT',
                        ],
                        JSON_UNESCAPED_SLASHES |
                            JSON_THROW_ON_ERROR,
                    ),
                );

                $claims = $this->base64Url(
                    json_encode(
                        [
                            'iss' => $clientEmail,
                            'scope' => self::OAUTH_SCOPE,
                            'aud' => $tokenUri,
                            'iat' => $now,
                            'exp' => $now + 3600,
                        ],
                        JSON_UNESCAPED_SLASHES |
                            JSON_THROW_ON_ERROR,
                    ),
                );

                $unsigned = $header.'.'.$claims;
                $key = openssl_pkey_get_private($privateKey);

                if ($key === false) {
                    throw new RuntimeException(
                        'Unable to read Firebase private key.',
                    );
                }

                $signature = '';

                if (
                    !openssl_sign(
                        $unsigned,
                        $signature,
                        $key,
                        OPENSSL_ALGO_SHA256,
                    )
                ) {
                    throw new RuntimeException(
                        'Unable to sign Firebase OAuth assertion.',
                    );
                }

                $assertion =
                    $unsigned.'.'.$this->base64Url($signature);

                $response = Http::asForm()
                    ->timeout(20)
                    ->post(
                        $tokenUri,
                        [
                            'grant_type' =>
                                'urn:ietf:params:oauth:grant-type:jwt-bearer',
                            'assertion' => $assertion,
                        ],
                    );

                if (!$response->successful()) {
                    throw new RuntimeException(
                        'Unable to obtain Firebase OAuth token: '.
                        $response->body(),
                    );
                }

                $accessToken = trim(
                    (string) $response->json('access_token'),
                );

                if ($accessToken === '') {
                    throw new RuntimeException(
                        'Firebase OAuth response did not contain an access token.',
                    );
                }

                return $accessToken;
            },
        );
    }

    private function credentials(): ?array
    {
        $configuredPath = trim(
            (string) config('firebase.credentials'),
        );

        if ($configuredPath === '') {
            return null;
        }

        $path = $this->absolutePath($configuredPath);

        if (!is_file($path)) {
            return null;
        }

        $decoded = json_decode(
            (string) file_get_contents($path),
            true,
        );

        if (!is_array($decoded)) {
            throw new RuntimeException(
                'Firebase service-account file is not valid JSON.',
            );
        }

        return $decoded;
    }

    private function absolutePath(string $path): string
    {
        if (
            Str::startsWith($path, ['/', '\\']) ||
            preg_match('/^[A-Za-z]:[\\\\\/]/', $path) === 1
        ) {
            return $path;
        }

        return base_path($path);
    }

    private function stringData(array $data): array
    {
        $normalized = [];

        foreach ($data as $key => $value) {
            if ($value === null) {
                continue;
            }

            if (is_bool($value)) {
                $normalized[(string) $key] =
                    $value ? 'true' : 'false';
            } elseif (is_scalar($value)) {
                $normalized[(string) $key] =
                    (string) $value;
            } else {
                $normalized[(string) $key] =
                    json_encode(
                        $value,
                        JSON_UNESCAPED_UNICODE |
                            JSON_UNESCAPED_SLASHES,
                    ) ?: '';
            }
        }

        return $normalized;
    }

    private function isInvalidTokenResponse(
        mixed $payload,
    ): bool {
        if (!is_array($payload)) {
            return false;
        }

        $status = strtoupper(
            (string) data_get(
                $payload,
                'error.status',
                '',
            ),
        );

        if ($status === 'UNREGISTERED') {
            return true;
        }

        foreach (
            (array) data_get(
                $payload,
                'error.details',
                [],
            ) as $detail
        ) {
            if (
                strtoupper(
                    (string) data_get(
                        $detail,
                        'errorCode',
                        '',
                    ),
                ) === 'UNREGISTERED'
            ) {
                return true;
            }
        }

        return false;
    }

    private function base64Url(
        string $value,
    ): string {
        return rtrim(
            strtr(
                base64_encode($value),
                '+/',
                '-_',
            ),
            '=',
        );
    }
}