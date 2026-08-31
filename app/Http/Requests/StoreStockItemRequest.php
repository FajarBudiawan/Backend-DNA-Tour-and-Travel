<?php
namespace App\Http\Requests;
use Illuminate\Foundation\Http\FormRequest;
class StoreStockItemRequest extends FormRequest {
    public function authorize(): bool { return true; }
    public function rules(): array { return [
        'name'=>'required|string|max:150', 'category'=>'required|string|max:100',
        'quantity'=>'required|integer|min:0', 'min_stock'=>'required|integer|min:0',
        'unit'=>'required|string|max:30', 'location'=>'required|string|max:150', 'notes'=>'nullable|string',
    ]; }
}
