<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreQuotelineRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Another workshop's quote is reported as missing, not forbidden
        abort_unless($this->route('quote')->workshop_id === $this->workshop->id, 404);

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
            'partId' => 'required|integer|exists:parts,id',
            'quantity' => 'required|integer|min:1',
            'colour' => 'nullable|string',
        ];
    }
}
