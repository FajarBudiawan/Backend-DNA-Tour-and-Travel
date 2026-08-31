<?php
namespace App\Http\Requests;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
class UpdateTourLeaderRequest extends FormRequest {
    public function authorize(): bool { return true; }
    public function rules(): array { $tourLeader = $this->route('tourLeader'); $id = $tourLeader instanceof \App\Models\TourLeader ? $tourLeader->id : $tourLeader; return [
        'login_id' => ['sometimes','string','max:20',Rule::unique('tour_leaders')->ignore($id)],
        'full_name' => 'sometimes|string|max:150',
        'certification_number' => ['sometimes','string','max:50',Rule::unique('tour_leaders')->ignore($id)],
        'phone' => 'nullable|string|max:20', 'status' => 'sometimes|in:active,resting,standby',
        'experience' => 'nullable|string', 'performance' => 'nullable|string|max:255',
    ]; }
}
