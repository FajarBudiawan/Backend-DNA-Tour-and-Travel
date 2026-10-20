<?php

namespace Tests\Feature;

use App\Models\DeviceToken;
use App\Models\InternalUser;
use App\Models\Jamaah;
use App\Models\Role;
use App\Models\TourLeader;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DeviceTokenTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('user_device_tokens');
        Schema::create('user_device_tokens', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuidMorphs('tokenable');
            $table->string('fcm_token', 500)->unique();
            $table->string('device_id')->nullable();
            $table->string('platform');
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            $table->index(['tokenable_type', 'tokenable_id', 'is_active'], 'user_device_tokens_owner_active_index');
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('user_device_tokens');
        parent::tearDown();
    }

    /**
     * 1. Request tanpa autentikasi ditolak (401).
     */
    public function test_unauthenticated_request_is_rejected(): void
    {
        $response = $this->postJson('/api/device-tokens', [
            'fcm_token' => 'sample_fcm_token_12345678901234567890',
            'platform'  => 'android',
        ]);

        $response->assertStatus(401);

        $deleteResponse = $this->deleteJson('/api/device-tokens', [
            'fcm_token' => 'sample_fcm_token_12345678901234567890',
        ]);

        $deleteResponse->assertStatus(401);
    }

    /**
     * 2. Platform tidak valid ditolak (422).
     */
    public function test_invalid_platform_is_rejected(): void
    {
        $jamaah = new Jamaah();
        $jamaah->id = (string) \Illuminate\Support\Str::uuid();
        Sanctum::actingAs($jamaah, ['jamaah']);

        $response = $this->postJson('/api/device-tokens', [
            'fcm_token' => 'sample_fcm_token_12345678901234567890',
            'platform'  => 'windows_phone',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['platform']);
    }

    /**
     * 3. FCM token kosong atau terlalu pendek ditolak (422).
     */
    public function test_invalid_fcm_token_is_rejected(): void
    {
        $jamaah = new Jamaah();
        $jamaah->id = (string) \Illuminate\Support\Str::uuid();
        Sanctum::actingAs($jamaah, ['jamaah']);

        $response = $this->postJson('/api/device-tokens', [
            'fcm_token' => 'short',
            'platform'  => 'android',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['fcm_token']);
    }

    /**
     * 4. Token Sanctum dengan ability yang tidak diizinkan ditolak (403).
     */
    public function test_unauthorized_ability_is_rejected(): void
    {
        $jamaah = new Jamaah();
        $jamaah->id = (string) \Illuminate\Support\Str::uuid();
        Sanctum::actingAs($jamaah, ['guest_scanner']);

        $response = $this->postJson('/api/device-tokens', [
            'fcm_token' => 'sample_fcm_token_12345678901234567890',
            'platform'  => 'android',
        ]);

        $response->assertStatus(403);
    }

    /**
     * 5. FormRequest validasi StoreDeviceTokenRequest secara terisolasi.
     */
    public function test_store_device_token_form_request_validation_rules(): void
    {
        $validator = \Illuminate\Support\Facades\Validator::make([
            'fcm_token' => 'sample_valid_fcm_token_minimum_20_chars',
            'platform'  => 'android',
            'device_id' => 'device-xyz-123',
        ], (new \App\Http\Requests\StoreDeviceTokenRequest())->rules());

        $this->assertTrue($validator->passes());

        // Test platform iOS
        $validatorIos = \Illuminate\Support\Facades\Validator::make([
            'fcm_token' => 'sample_valid_fcm_token_minimum_20_chars',
            'platform'  => 'ios',
        ], (new \App\Http\Requests\StoreDeviceTokenRequest())->rules());

        $this->assertTrue($validatorIos->passes());

        // Test client mencoba inject field yang dilarang (tokenable_type / is_active)
        $request = new \App\Http\Requests\StoreDeviceTokenRequest();
        $rules = $request->rules();
        $this->assertArrayNotHasKey('tokenable_type', $rules);
        $this->assertArrayNotHasKey('tokenable_id', $rules);
        $this->assertArrayNotHasKey('is_active', $rules);
    }

    /**
     * 6. FormRequest validasi DeleteDeviceTokenRequest.
     */
    public function test_delete_device_token_form_request_validation_rules(): void
    {
        $validator = \Illuminate\Support\Facades\Validator::make([
            'fcm_token' => 'sample_valid_fcm_token_minimum_20_chars',
        ], (new \App\Http\Requests\DeleteDeviceTokenRequest())->rules());

        $this->assertTrue($validator->passes());

        $validatorEmpty = \Illuminate\Support\Facades\Validator::make([], (new \App\Http\Requests\DeleteDeviceTokenRequest())->rules());
        $this->assertFalse($validatorEmpty->passes());
    }

    /**
     * 7. Registrasi token baru, pembaruan, dan pengalihan kepemilikan.
     */
    public function test_token_registration_and_reassignment_flow(): void
    {
        $jamaahA = new Jamaah();
        $jamaahA->id = (string) \Illuminate\Support\Str::uuid();

        $jamaahB = new Jamaah();
        $jamaahB->id = (string) \Illuminate\Support\Str::uuid();

        $fcmToken = 'unique_fcm_token_for_test_' . \Illuminate\Support\Str::random(30);

        // A. Registrasi token baru atas nama Jamaah A
        Sanctum::actingAs($jamaahA, ['jamaah']);
        $resp1 = $this->postJson('/api/device-tokens', [
            'fcm_token' => $fcmToken,
            'platform'  => 'android',
            'device_id' => 'device-hardware-id-1',
        ]);
        $resp1->assertStatus(201);
        $this->assertDatabaseHas('user_device_tokens', [
            'fcm_token'      => $fcmToken,
            'tokenable_type' => Jamaah::class,
            'tokenable_id'   => $jamaahA->id,
            'device_id'      => 'device-hardware-id-1',
            'is_active'      => true,
        ]);

        // B. Re-registrasi tanpa menyertakan device_id -> nilai device-hardware-id-1 HARUS dipertahankan
        $resp2 = $this->postJson('/api/device-tokens', [
            'fcm_token' => $fcmToken,
            'platform'  => 'android',
        ]);
        $resp2->assertStatus(200);
        $this->assertDatabaseHas('user_device_tokens', [
            'fcm_token'      => $fcmToken,
            'tokenable_id'   => $jamaahA->id,
            'device_id'      => 'device-hardware-id-1', // Tetap ada, tidak ditimpa null
        ]);

        // C. Pembaruan device_id baru saat dikirim
        $resp3 = $this->postJson('/api/device-tokens', [
            'fcm_token' => $fcmToken,
            'platform'  => 'android',
            'device_id' => 'device-hardware-id-2',
        ]);
        $resp3->assertStatus(200);
        $this->assertDatabaseHas('user_device_tokens', [
            'fcm_token' => $fcmToken,
            'device_id' => 'device-hardware-id-2',
        ]);

        // D. Pengalihan kepemilikan token ke Jamaah B
        Sanctum::actingAs($jamaahB, ['jamaah']);
        $resp4 = $this->postJson('/api/device-tokens', [
            'fcm_token' => $fcmToken,
            'platform'  => 'android',
        ]);
        $resp4->assertStatus(200);
        $this->assertDatabaseHas('user_device_tokens', [
            'fcm_token'      => $fcmToken,
            'tokenable_type' => Jamaah::class,
            'tokenable_id'   => $jamaahB->id, // Beralih ke Jamaah B
            'device_id'      => 'device-hardware-id-2', // device_id tetap terjaga
        ]);

        // E. Deaktivasi token satu perangkat oleh Jamaah B
        $resp5 = $this->deleteJson('/api/device-tokens', [
            'fcm_token' => $fcmToken,
        ]);
        $resp5->assertStatus(200);
        $this->assertDatabaseHas('user_device_tokens', [
            'fcm_token' => $fcmToken,
            'is_active' => false,
        ]);

        // F. Jamaah A mencoba menonaktifkan token yang sudah menjadi milik Jamaah B -> 404
        Sanctum::actingAs($jamaahA, ['jamaah']);
        $resp6 = $this->deleteJson('/api/device-tokens', [
            'fcm_token' => $fcmToken,
        ]);
        $resp6->assertStatus(404);
    }
}
