<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateQuotelineRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Another workshop's quote, or a line from another quote, is reported as missing
        abort_unless($this->route('quote')->workshop_id === $this->workshop->id, 404);
        abort_unless($this->route('quoteline')->quote_id === $this->route('quote')->id, 404);

        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'quantity' => 'sometimes|required|integer|min:1',
            'colour' => 'sometimes|nullable|string',
        ];
    }
}
