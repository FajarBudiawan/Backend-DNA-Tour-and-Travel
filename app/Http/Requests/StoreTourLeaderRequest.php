<?php
namespace App\Http\Requests;
use Illuminate\Foundation\Http\FormRequest;
class StoreTourLeaderRequest extends FormRequest {
    public function authorize(): bool { return true; }
    public function rules(): array { return [
        'login_id' => 'required|string|max:20|unique:tour_leaders,login_id',
        'full_name' => 'required|string|max:150',
        'certification_number' => 'required|string|max:50|unique:tour_leaders,certification_number',
        'phone' => 'nullable|string|max:20', 'status' => 'required|in:active,resting,standby',
        'experience' => 'nullable|string', 'performance' => 'nullable|string|max:255',
    ]; }
}
