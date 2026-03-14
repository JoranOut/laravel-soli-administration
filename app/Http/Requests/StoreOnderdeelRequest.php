<?php

namespace App\Http\Requests;

use App\Models\Onderdeel;
use Illuminate\Foundation\Http\FormRequest;

class StoreOnderdeelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('onderdelen.create');
    }

    public function rules(): array
    {
        return [
            'naam' => ['required', 'string', 'max:255'],
            'afkorting' => ['nullable', 'string', 'max:10', 'unique:soli_onderdelen,afkorting'],
            'type' => ['required', 'in:'.implode(',', Onderdeel::TYPES)],
            'beschrijving' => ['nullable', 'string'],
        ];
    }
}
