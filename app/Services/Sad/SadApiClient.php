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
        if (empty($this->username) || empty($this->password)) {
            throw new RuntimeException('SAD credentials not configured. Set SAD_USERNAME and SAD_PASSWORD in .env');
        }

        $this->cookieJar = new CookieJar;

        // The form carries a hidden sess_ret that has to come back with the post, so
        // fetch it first. Field names are l_bar.php's own: luser, pw, aktie.
        $form = Http::withOptions(['cookies' => $this->cookieJar])
            ->get($this->baseUrl.'/l_bar.php');

        if (! $form->successful()) {
            throw new RuntimeException("SAD login form unavailable, status {$form->status()}");
        }

        if (! preg_match('/name=[\'"]?sess_ret[\'"]?\s+value=[\'"]?([^\'">\s]+)/i', $form->body(), $m)) {
            throw new RuntimeException('SAD login form did not contain sess_ret — the form has changed');
        }

        $response = Http::withOptions(['cookies' => $this->cookieJar])
            ->asForm()
            ->post($this->baseUrl.'/l_bar.php', [
                'luser' => $this->username,
                'pw' => $this->password,
                'sess_ret' => $m[1],
                'aktie' => 'Login',
            ]);

        if (! $response->successful()) {
            throw new RuntimeException("SAD login failed with status {$response->status()}");
        }

        // A rejected login answers 200 with the form again, so the status proves
        // nothing. This is a hint, not a verdict: l_bar.php is a login *bar* and may
        // well render the form on every page. Throwing on a false positive would stop
        // the whole sync, including the members that do come in over the
        // unauthenticated endpoints — so log it and let the per-member page check,
        // which tests for the member page itself, decide what actually failed.
        if (preg_match('/name=[\'"]?pw[\'"]?/i', $response->body())) {
            Log::warning('SadApiClient: the login form came back after posting credentials — the session may not be authenticated');
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
     * The raw lid_info.php page, before parsing. Only for capturing a test fixture —
     * the response holds PII, so never log or store it unscrubbed.
     */
    public function getMemberPiiHtml(int $lidId): string
    {
        return $this->getAuthenticated("/lid_info.php?lid_id={$lidId}&wz=m");
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
            $html = $this->getMemberPiiHtml($lidId);

            // SAD answers an unauthenticated request with the login screen and HTTP
            // 200, so the status says nothing. Confirm we got the member page itself:
            // anything else — login screen, error, maintenance notice — parses as a
            // member whose every field happens to be empty, and syncs as success.
            if (! preg_match('/<td[^>]*>\s*lid_id\s*<\/td>/i', $html)) {
                Log::warning("SadApiClient: lid_info.php did not return the member page for lid_id {$lidId} — session may not be authenticated");

                return null;
            }

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
