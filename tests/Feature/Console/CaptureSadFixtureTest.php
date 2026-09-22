<?php

use App\Services\Sad\SadApiClient;

beforeEach(function () {
    config(['services.sad.username' => 'user', 'services.sad.password' => 'secret']);
    $this->output = base_path('tests/fixtures/tmp-lid-info.html');
});

afterEach(function () {
    if (file_exists($this->output)) {
        unlink($this->output);
    }
});

function fakeSadClient(string $html): void
{
    $mock = Mockery::mock(SadApiClient::class);
    $mock->shouldReceive('login')->once();
    $mock->shouldReceive('getMemberPiiHtml')->once()->andReturn($html);
    app()->instance(SadApiClient::class, $mock);
}

test('keeps labels and replaces every value', function () {
    fakeSadClient('<table>
        <tr><td>Naam</td><td>Echte Naam</td></tr>
        <tr><td>Adres</td><td>Echtestraat 99</td></tr>
        <tr><td>Postcode</td><td>1234 ZZ</td></tr>
        <tr><td>Plaats</td><td>ECHTEDORP</td></tr>
        <tr><td>Telefoon</td><td>0698765432</td></tr>
        <tr><td>E-mail</td><td>echt@voorbeeld.nl</td></tr>
        <tr><td>Geboorte datum</td><td>01-02-1970</td></tr>
    </table>');

    $this->artisan('sad:capture-fixture', ['lid_id' => 1, '--output' => 'tests/fixtures/tmp-lid-info.html'])
        ->assertExitCode(0);

    $fixture = file_get_contents($this->output);

    // Labels survive — they are what the parser matches on
    expect($fixture)->toContain('<td>Geboorte datum</td>');
    expect($fixture)->toContain('<td>Postcode</td>');

    // No value from the source page survives
    foreach (['Echte Naam', 'Echtestraat 99', '1234 ZZ', 'ECHTEDORP', '0698765432', 'echt@voorbeeld.nl', '01-02-1970'] as $pii) {
        expect($fixture)->not->toContain($pii);
    }

    // Values are fictional but plausible, so the contract test can assert mapping
    expect($fixture)->toContain('15-03-1990');
    expect($fixture)->toContain('Driehuis');
});

test('refuses to write when a value slips through', function () {
    // A layout the row-based scrubber does not cover: values outside any table row
    fakeSadClient('<div>Contact: echt@voorbeeld.nl</div><table><tr><td>Plaats</td><td>ECHTEDORP</td></tr></table>');

    $this->artisan('sad:capture-fixture', ['lid_id' => 1, '--output' => 'tests/fixtures/tmp-lid-info.html'])
        ->expectsOutputToContain('Scrubbing left original values behind')
        ->assertExitCode(1);

    expect(file_exists($this->output))->toBeFalse();
});

test('reports a label the parser would not recognise', function () {
    fakeSadClient('<table>
        <tr><td>Adres</td><td>Echtestraat 99</td></tr>
        <tr><td>Postcode</td><td>1234 ZZ</td></tr>
        <tr><td>Plaats</td><td>ECHTEDORP</td></tr>
        <tr><td>Telefoon</td><td>0698765432</td></tr>
        <tr><td>Instrument</td><td>Bugel</td></tr>
        <tr><td>Verjaardag</td><td>01-02-1970</td></tr>
    </table>');

    $this->artisan('sad:capture-fixture', ['lid_id' => 1, '--output' => 'tests/fixtures/tmp-lid-info.html'])
        ->expectsOutputToContain('No label on this page maps to: geboortedatum')
        ->assertExitCode(0);
});

test('refuses to run on production', function () {
    app()->detectEnvironment(fn () => 'production');

    $this->artisan('sad:capture-fixture', ['lid_id' => 1])
        ->expectsOutputToContain('does not run on production')
        ->assertExitCode(1);
});

test('requires credentials', function () {
    config(['services.sad.username' => null]);

    $this->artisan('sad:capture-fixture', ['lid_id' => 1])->assertExitCode(1);
});
