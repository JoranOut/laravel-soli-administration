<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class MusicLibraryApiService
{
    private string $baseUrl;

    private string $apiKey;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('services.soli_music_library.base_url'), '/');
        $this->apiKey = config('services.soli_music_library.api_key') ?? '';
    }

    /**
     * @return array{families: array<int, array{id: int, name: string}>, soorten: array<int, array{id: int, name: string, instrument_family_id: int}>}
     *
     * @throws ConnectionException
     */
    public function getInstruments(): array
    {
        return $this->get('/api/v1/instruments');
    }

    /**
     * @throws ConnectionException
     */
    private function get(string $path): array
    {
        $response = Http::withHeaders([
            'X-API-Key' => $this->apiKey,
            'Accept' => 'application/json',
        ])->get($this->baseUrl.$path);

        if (! $response->successful()) {
            throw new RuntimeException(
                "Music Library API request to {$path} failed with status {$response->status()}: {$response->body()}"
            );
        }

        return $response->json();
    }
}
