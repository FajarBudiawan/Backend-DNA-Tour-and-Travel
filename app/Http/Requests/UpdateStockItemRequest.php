<?php
namespace App\Http\Requests;
use Illuminate\Foundation\Http\FormRequest;
class UpdateStockItemRequest extends FormRequest {
    public function authorize(): bool { return true; }
    public function rules(): array { return [
        'name'=>'sometimes|string|max:150', 'category'=>'sometimes|string|max:100',
        'quantity'=>'sometimes|integer|min:0', 'min_stock'=>'sometimes|integer|min:0',
        'unit'=>'sometimes|string|max:30', 'location'=>'sometimes|string|max:150', 'notes'=>'nullable|string',
    ]; }
}
