<?php
namespace App\Http\Requests;
use Illuminate\Foundation\Http\FormRequest;
class StoreMuthawwifRequest extends FormRequest {
    public function authorize(): bool { return true; }
    public function rules(): array { return [
        'name'=>'required|string|max:150', 'language'=>'required|string|max:255',
        'experience'=>'nullable|string',
        'status'=>'required|in:active,standby',
    ]; }
}
