<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreRelatieRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('relaties.create');
    }

    public function rules(): array
    {
        return [
            // Base relatie fields
            'relatie_nummer' => ['required', 'integer', 'unique:soli_relaties,relatie_nummer'],
            'voornaam' => ['required', 'string', 'max:255'],
            'tussenvoegsel' => ['nullable', 'string', 'max:255'],
            'achternaam' => ['required', 'string', 'max:255'],
            'geslacht' => ['required', 'in:M,V,O'],
            'geboortedatum' => ['nullable', 'date'],
            'actief' => ['boolean'],
            'geboorteplaats' => ['nullable', 'string', 'max:255'],
            'nationaliteit' => ['nullable', 'string', 'max:255'],

            // Types (pivot)
            'types' => ['nullable', 'array'],
            'types.*.type_id' => ['required', 'exists:soli_relatie_types,id'],
            'types.*.van' => ['required', 'date'],
            'types.*.tot' => ['nullable', 'date'],
            'types.*.functie' => ['nullable', 'string', 'max:255'],
            'types.*.email' => ['nullable', 'email', 'max:255'],
            'types.*.onderdeel_id' => ['nullable', 'exists:soli_onderdelen,id'],

            // Adressen
            'adressen' => ['nullable', 'array'],
            'adressen.*.straat' => ['required', 'string', 'max:255'],
            'adressen.*.huisnummer' => ['required', 'string', 'max:20'],
            'adressen.*.huisnummer_toevoeging' => ['nullable', 'string', 'max:20'],
            'adressen.*.postcode' => ['required', 'string', 'max:10'],
            'adressen.*.plaats' => ['required', 'string', 'max:255'],
            'adressen.*.land' => ['required', 'string', 'max:255'],

            // Emails
            'emails' => ['required', 'array', 'min:1'],
            'emails.0.email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'emails.*.email' => ['required', 'email', 'max:255'],

            // Telefoons
            'telefoons' => ['nullable', 'array'],
            'telefoons.*.nummer' => ['required', 'string', 'max:20'],

            // Giro gegevens
            'giro_gegevens' => ['nullable', 'array'],
            'giro_gegevens.*.iban' => ['required', 'string', 'max:34'],
            'giro_gegevens.*.bic' => ['nullable', 'string', 'max:11'],
            'giro_gegevens.*.tenaamstelling' => ['required', 'string', 'max:255'],
            'giro_gegevens.*.machtiging' => ['required', 'boolean'],

            // Lidmaatschappen
            'lidmaatschappen' => ['nullable', 'array'],
            'lidmaatschappen.*.lid_sinds' => ['required', 'date'],
            'lidmaatschappen.*.lid_tot' => ['nullable', 'date'],
            'lidmaatschappen.*.reden_vertrek' => ['nullable', 'string', 'max:255'],

            // Onderdelen (pivot)
            'onderdelen' => ['nullable', 'array'],
            'onderdelen.*.onderdeel_id' => ['required', 'exists:soli_onderdelen,id'],
            'onderdelen.*.functie' => ['nullable', 'string', 'max:255'],
            'onderdelen.*.instrument_soort_ids' => ['nullable', 'array'],
            'onderdelen.*.instrument_soort_ids.*' => ['integer', 'exists:soli_instrument_soorten,id'],
            'onderdelen.*.van' => ['required', 'date'],
            'onderdelen.*.tot' => ['nullable', 'date'],

            // Opleidingen
            'opleidingen' => ['nullable', 'array'],
            'opleidingen.*.naam' => ['required', 'string', 'max:255'],
            'opleidingen.*.instituut' => ['nullable', 'string', 'max:255'],
            'opleidingen.*.instrument' => ['nullable', 'string', 'max:255'],
            'opleidingen.*.diploma' => ['nullable', 'string', 'max:255'],
            'opleidingen.*.datum_start' => ['nullable', 'date'],
            'opleidingen.*.datum_einde' => ['nullable', 'date'],
            'opleidingen.*.opmerking' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'emails.required' => __('At least one email address is required.'),
            'emails.min' => __('At least one email address is required.'),
            'emails.0.email.unique' => __('This email address is already in use by another account.'),
        ];
    }
}
