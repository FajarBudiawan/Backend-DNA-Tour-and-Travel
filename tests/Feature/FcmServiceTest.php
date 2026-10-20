<?php

namespace Tests\Feature;

use App\Exceptions\InvalidFcmTokenException;
use App\Services\FcmService;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class FcmServiceTest extends TestCase
{
    private function makeService(): FcmService
    {
        return new class extends FcmService {
            protected function fetchAccessToken(
                string $credentialsPath
            ): string {
                return 'fake-access-token';
            }
        };
    }

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.firebase.credentials' => __FILE__,
            'services.firebase.project_id' => 'test-project',
        ]);
    }

    public function test_successful_fcm_response_is_returned(): void
    {
        Http::fake([
            'fcm.googleapis.com/*' => Http::response([
                'name' => 'projects/test-project/messages/test-message',
            ], 200),
        ]);

        $result = $this->makeService()->send('test-device-token', [
            'title' => 'SOS Darurat',
            'body' => 'Jamaah membutuhkan bantuan',
            'data' => ['type' => 'sos_created'],
        ]);

        $this->assertSame(
            'projects/test-project/messages/test-message',
            $result['name']
        );

        Http::assertSent(fn ($request) =>
            $request->hasHeader(
                'Authorization',
                'Bearer fake-access-token'
            )
            && $request['message']['token'] === 'test-device-token'
        );
    }

    public function test_unregistered_token_throws_specific_exception(): void
    {
        Http::fake([
            'fcm.googleapis.com/*' => Http::response([
                'error' => [
                    'code' => 404,
                    'details' => [
                        ['errorCode' => 'UNREGISTERED'],
                    ],
                ],
            ], 404),
        ]);

        $this->expectException(InvalidFcmTokenException::class);

        $this->makeService()->send('unregistered-token', [
            'title' => 'SOS Darurat',
            'body' => 'Jamaah membutuhkan bantuan',
        ]);
    }

    public function test_server_error_does_not_become_invalid_token_exception(): void
    {
        Http::fake([
            'fcm.googleapis.com/*' => Http::response([
                'error' => [
                    'code' => 500,
                    'message' => 'Internal server error',
                ],
            ], 500),
        ]);

        try {
            $this->makeService()->send('temporary-error-token', [
                'title' => 'SOS Darurat',
                'body' => 'Jamaah membutuhkan bantuan',
            ]);

            $this->fail('Expected a RuntimeException.');
        } catch (InvalidFcmTokenException $exception) {
            $this->fail(
                'A server error must not invalidate the device token.'
            );
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString(
                'HTTP status 500',
                $exception->getMessage()
            );
        }
    }
}
