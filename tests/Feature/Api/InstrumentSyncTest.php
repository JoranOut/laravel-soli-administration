<?php

use App\Models\InstrumentFamilie;
use App\Models\InstrumentSoort;

beforeEach(function () {
    config(['services.soli_instruments.api_key' => 'test-instruments-api-key']);
});

function instrumentHeaders(): array
{
    return ['X-API-Key' => 'test-instruments-api-key'];
}

// --- Authentication ---

test('rejects request without API key', function () {
    $response = $this->getJson('/api/v1/instruments');

    $response->assertStatus(401);
});

test('rejects request with wrong API key', function () {
    $response = $this->getJson('/api/v1/instruments', ['X-API-Key' => 'wrong-key']);

    $response->assertStatus(401);
});

test('returns 200 with correct API key', function () {
    $response = $this->getJson('/api/v1/instruments', instrumentHeaders());

    $response->assertOk();
});

// --- Response Structure ---

test('response contains families and soorten arrays', function () {
    $response = $this->getJson('/api/v1/instruments', instrumentHeaders());

    $response->assertOk()
        ->assertJsonStructure([
            'families',
            'soorten',
        ]);
});

test('families have correct structure', function () {
    InstrumentFamilie::create(['naam' => 'Trompet']);

    $response = $this->getJson('/api/v1/instruments', instrumentHeaders());

    $response->assertOk()
        ->assertJsonStructure([
            'families' => [
                ['id', 'naam'],
            ],
        ]);
});

test('soorten have correct structure', function () {
    $familie = InstrumentFamilie::create(['naam' => 'Trompet']);
    InstrumentSoort::create(['naam' => 'Cornet', 'instrument_familie_id' => $familie->id]);

    $response = $this->getJson('/api/v1/instruments', instrumentHeaders());

    $response->assertOk()
        ->assertJsonStructure([
            'soorten' => [
                ['id', 'naam', 'instrument_familie_id'],
            ],
        ]);
});

// --- Data Correctness ---

test('response data matches database records', function () {
    $familie1 = InstrumentFamilie::create(['naam' => 'Klarinet']);
    $familie2 = InstrumentFamilie::create(['naam' => 'Saxofoon']);

    $soort1 = InstrumentSoort::create(['naam' => 'Basklarinet', 'instrument_familie_id' => $familie1->id]);
    $soort2 = InstrumentSoort::create(['naam' => 'Altsaxofoon', 'instrument_familie_id' => $familie2->id]);

    $response = $this->getJson('/api/v1/instruments', instrumentHeaders());

    $response->assertOk()
        ->assertJsonCount(2, 'families')
        ->assertJsonCount(2, 'soorten')
        ->assertJsonFragment(['id' => $familie1->id, 'naam' => 'Klarinet'])
        ->assertJsonFragment(['id' => $familie2->id, 'naam' => 'Saxofoon'])
        ->assertJsonFragment(['id' => $soort1->id, 'naam' => 'Basklarinet', 'instrument_familie_id' => $familie1->id])
        ->assertJsonFragment(['id' => $soort2->id, 'naam' => 'Altsaxofoon', 'instrument_familie_id' => $familie2->id]);
});

test('returns empty arrays when no data exists', function () {
    $response = $this->getJson('/api/v1/instruments', instrumentHeaders());

    $response->assertOk()
        ->assertJsonCount(0, 'families')
        ->assertJsonCount(0, 'soorten');
});
