<?php

use App\Services\Sad\SadApiClient;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

beforeEach(function () {
    config([
        'services.sad.base_url' => 'https://sad.test/admin',
        'services.sad.username' => 'user',
        'services.sad.password' => 'secret',
    ]);
});

// The login form as l_bar.php renders it: unquoted attributes, its own field names
function loginForm(): string
{
    return '<form action=l_bar.php method=post>
        <input type=text name=luser size=10 value=>
        <input type=password name=pw size=10 value=\'\'>
        <input type=hidden name=sess_ret value=97u6rfisl7revkosruu95f1e0k>
        <input type=submit name=aktie value=Login></form>';
}

// The row every member page carries, in the spacing lid_info.php actually uses
function memberPage(): string
{
    return '<table>
        <tr><td></td></tr>
        <tr><td>Lid_id </td><td> 1219 <tr><td></td></tr>
        <tr><td> Adres </td><td> Dorpsstraat 10
        </td></tr><tr><td> Plaats </td><td> DRIEHUIS
        </td></tr><tr><td> Geboorte datum </td><td> 15-03-1990
        </td></tr></table>';
}

test('reads PII from the member page', function () {
    Http::fakeSequence()
        ->push(loginForm())      // GET the form
        ->push('<html>Welkom</html>')  // POST accepted
        ->push(memberPage());

    $client = new SadApiClient;
    $client->login();

    $pii = $client->getMemberPii(1219);

    expect($pii['adres'])->toBe('Dorpsstraat 10');
    expect($pii['plaats'])->toBe('DRIEHUIS');
    expect($pii['geboortedatum'])->toBe('15-03-1990');
});

test('returns null when the login screen comes back instead of the member page', function () {
    // HTTP 200 with no member table — what an unauthenticated request gets
    Http::fakeSequence()
        ->push(loginForm())
        ->push('<html>Welkom</html>')
        ->push('<html><body>'.loginForm().'</body></html>');

    $client = new SadApiClient;
    $client->login();

    expect($client->getMemberPii(1219))->toBeNull();
});

test('returns null for an unexpected page that is not a login screen either', function () {
    Http::fakeSequence()
        ->push(loginForm())
        ->push('<html>Welkom</html>')
        ->push('<html><body><h1>Onderhoud</h1></body></html>');

    $client = new SadApiClient;
    $client->login();

    expect($client->getMemberPii(1219))->toBeNull();
});

test('posts the field names l_bar.php actually expects', function () {
    Http::fakeSequence()
        ->push(loginForm())
        ->push('<html>Welkom</html>');

    (new SadApiClient)->login();

    Http::assertSent(function ($request) {
        if ($request->method() !== 'POST') {
            return false;
        }

        // luser/pw, not user/pass — and the hidden token from the form
        return $request['luser'] === 'user'
            && $request['pw'] === 'secret'
            && $request['sess_ret'] === '97u6rfisl7revkosruu95f1e0k'
            && $request['aktie'] === 'Login';
    });
});

test('does not throw when the login form comes back, but says so', function () {
    // A rejected login answers 200 with the form again — but so might a login *bar*
    // on a page that did work, so this must not take the whole sync down with it
    Log::spy();

    Http::fakeSequence()
        ->push(loginForm())
        ->push('<html><body>Onjuist wachtwoord'.loginForm().'</body></html>');

    (new SadApiClient)->login();

    Log::shouldHaveReceived('warning')
        ->withArgs(fn ($m) => str_contains($m, 'login form came back'));
});

test('throws when the form no longer carries sess_ret', function () {
    Http::fakeSequence()->push('<form action=l_bar.php method=post><input name=luser></form>');

    expect(fn () => (new SadApiClient)->login())
        ->toThrow(RuntimeException::class, 'did not contain sess_ret');
});
