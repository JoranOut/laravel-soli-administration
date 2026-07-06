<?php

namespace App\Services\Sad;

use GuzzleHttp\Cookie\CookieJar;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class SadApiClient
{
    private string $baseUrl;

    private string $username;

    private string $password;

    private ?CookieJar $cookieJar = null;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('services.sad.base_url'), '/');
        $this->username = config('services.sad.username') ?? '';
        $this->password = config('services.sad.password') ?? '';
    }

    /**
     * Login to the SAD admin panel via session-based authentication.
     *
     * @throws RuntimeException
     */
    public function login(): void
    {
        $this->cookieJar = new CookieJar;

        $response = Http::withOptions(['cookies' => $this->cookieJar])
            ->asForm()
            ->post($this->baseUrl.'/l_bar.php', [
                'user' => $this->username,
                'pass' => $this->password,
            ]);

        if (! $response->successful()) {
            throw new RuntimeException("SAD login failed with status {$response->status()}");
        }
    }

    /**
     * Fetch the active member overview from site_ov.php.
     *
     * Returns an array of members, each with keys: lid_id, onderdeel, email.
     *
     * @return array<int, array{lid_id: int, onderdeel: string, email: string}>
     *
     * @throws RuntimeException
     */
    public function getActiveMembers(): array
    {
        $body = $this->get('/site_ov.php');

        $lines = preg_split("/\n/", $body);
        $members = [];

        foreach ($lines as $line) {
            if (preg_match('/(\d{3,}) ([A-Z]+) (.*@.*)/', $line, $m)) {
                $members[(int) $m[1]] = [
                    'lid_id' => (int) $m[1],
                    'onderdeel' => $m[2],
                    'email' => trim($m[3]),
                ];
            }
        }

        return $members;
    }

    /**
     * Fetch member details from site_lid.php.
     *
     * Returns array with keys: voornaam, tussenvoegsel, achternaam, email, onderdeel.
     * Returns null if the data is invalid.
     */
    public function getMemberDetails(int $lidId): ?array
    {
        $body = $this->get("/site_lid.php?lid_id={$lidId}");
        $lines = preg_split("/\n/", $body);

        if (count($lines) < 6) {
            Log::warning("SadApiClient: Insufficient data for lid_id {$lidId}");

            return null;
        }

        // Find the "Lidinfo" header
        $offset = null;
        for ($i = 0; $i <= 2; $i++) {
            if (isset($lines[$i]) && $lines[$i] === 'Lidinfo') {
                $offset = $i + 1;
                break;
            }
        }

        if ($offset === null) {
            Log::warning("SadApiClient: No Lidinfo header for lid_id {$lidId}");

            return null;
        }

        $volnaam = $lines[$offset + 0] ?? '';
        $voornaam = $lines[$offset + 1] ?? '';
        $tussenvoegsel = $lines[$offset + 2] ?? '';
        $achternaam = $lines[$offset + 3] ?? '';
        $email = $lines[$offset + 4] ?? '';
        $onderdeel = $lines[$offset + 5] ?? '';

        if (! preg_match('/^[^ ]+ .*[^ ]{2}$/', $volnaam)) {
            Log::warning("SadApiClient: Invalid name for lid_id {$lidId}: {$volnaam}");

            return null;
        }

        if (! str_contains($email, '@')) {
            Log::warning("SadApiClient: Invalid email for lid_id {$lidId}: {$email}");

            return null;
        }

        return [
            'voornaam' => trim($voornaam),
            'tussenvoegsel' => trim($tussenvoegsel) !== '' ? trim($tussenvoegsel) : null,
            'achternaam' => trim($achternaam),
            'email' => trim($email),
            'onderdeel' => trim($onderdeel),
        ];
    }

    /**
     * Fetch PII details from lid_info.php (requires authentication).
     *
     * Returns parsed PII array with keys: adres, postcode, plaats, telefoon, geboortedatum, instrument.
     * Returns null on failure.
     */
    public function getMemberPii(int $lidId): ?array
    {
        try {
            $html = $this->getAuthenticated("/lid_info.php?lid_id={$lidId}&wz=m");

            return SadDataParser::parsePiiHtml($html);
        } catch (\Throwable $e) {
            Log::warning("SadApiClient: Failed to fetch PII for lid_id {$lidId}: {$e->getMessage()}");

            return null;
        }
    }

    /**
     * Unauthenticated GET request to SAD.
     */
    private function get(string $path): string
    {
        $response = Http::timeout(30)
            ->connectTimeout(10)
            ->get($this->baseUrl.$path);

        if (! $response->successful()) {
            throw new RuntimeException("SAD request to {$path} failed with status {$response->status()}");
        }

        return $response->body();
    }

    /**
     * Authenticated GET request to SAD using the session cookie jar.
     */
    private function getAuthenticated(string $path): string
    {
        if (! $this->cookieJar) {
            throw new RuntimeException('SadApiClient: Not logged in. Call login() first.');
        }

        $response = Http::withOptions(['cookies' => $this->cookieJar])
            ->timeout(30)
            ->connectTimeout(10)
            ->get($this->baseUrl.$path);

        if (! $response->successful()) {
            throw new RuntimeException("SAD authenticated request to {$path} failed with status {$response->status()}");
        }

        return $response->body();
    }
}
