<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

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
            'geboortedatum' => ['nullable', 'date'],
            'actief' => ['boolean'],
            'beheerd_in_admin' => ['boolean'],
        ];
    }
}
