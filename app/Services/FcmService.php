<?php

namespace App\Services;

use App\Exceptions\InvalidFcmTokenException;
use Google\Auth\Credentials\ServiceAccountCredentials;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class FcmService
{
    protected function fetchAccessToken(string $credentialsPath): string
    {
        $credentials = new ServiceAccountCredentials(
            ['https://www.googleapis.com/auth/firebase.messaging'],
            $credentialsPath
        );

        $authToken = $credentials->fetchAuthToken();

        if (empty($authToken['access_token'])) {
            throw new RuntimeException(
                'Unable to obtain Google access token.'
            );
        }

        return $authToken['access_token'];
    }

    public function send(string $deviceToken, array $data): array
    {
        $credentialsPath = config('services.firebase.credentials');
        $projectId = config('services.firebase.project_id');

        if (! $credentialsPath || ! is_file($credentialsPath)) {
            throw new RuntimeException(
                'Firebase credentials file is missing.'
            );
        }

        if (! $projectId) {
            throw new RuntimeException(
                'Firebase project ID is not configured.'
            );
        }

        $accessToken = $this->fetchAccessToken($credentialsPath);

        $response = Http::withToken($accessToken)
            ->acceptJson()
            ->post(
                "https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send",
                [
                    'message' => [
                        'token' => $deviceToken,
                        'notification' => [
                            'title' => (string) (
                                $data['title'] ?? 'Notifikasi DNA Tour'
                            ),
                            'body' => (string) ($data['body'] ?? ''),
                        ],
                        'data' => array_map(
                            static fn ($value) => (string) $value,
                            $data['data'] ?? []
                        ),
                    ],
                ]
            );

        if ($response->failed()) {
            $error = $response->json('error', []);
            $details = $error['details'] ?? [];

            foreach ($details as $detail) {
                $errorCode = $detail['errorCode'] ?? null;
                if (
                    $errorCode === 'UNREGISTERED'
                    || $errorCode === 'INVALID_ARGUMENT'
                ) {
                    throw new InvalidFcmTokenException(
                        'Firebase registration token is invalid or unregistered.'
                    );
                }
            }

            $status = $error['status'] ?? null;
            $message = $error['message'] ?? '';
            if (
                $status === 'INVALID_ARGUMENT'
                && (str_contains(strtolower($message), 'registration token') || str_contains(strtolower($message), 'token'))
            ) {
                throw new InvalidFcmTokenException(
                    'Firebase registration token is invalid or malformed.'
                );
            }

            throw new RuntimeException(
                'FCM request failed with HTTP status ' .
                $response->status()
            );
        }

        return $response->json() ?? [];
    }
}
