<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRelatieRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('relaties.edit');
    }

    public function rules(): array
    {
        return [
            'voornaam' => ['required', 'string', 'max:255'],
            'tussenvoegsel' => ['nullable', 'string', 'max:255'],
            'achternaam' => ['required', 'string', 'max:255'],
            'geslacht' => ['required', 'in:M,V,O'],
            'geboortedatum' => ['nullable', 'date'],
            'actief' => ['boolean'],
            'foto_url' => ['nullable', 'url', 'max:255'],
            'geboorteplaats' => ['nullable', 'string', 'max:255'],
            'nationaliteit' => ['nullable', 'string', 'max:255'],
        ];
    }
}
