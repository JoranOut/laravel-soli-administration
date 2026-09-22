<?php

use App\Services\Sad\SadDataParser;

/**
 * Contract test against a page captured from the live SAD install.
 *
 * The other parser tests use hand-written HTML, which can only confirm the labels the
 * parser already looks for — that is how "Geboorte datum" stayed unparsed. This one
 * runs on the real page shape, with values replaced by `sad:capture-fixture`.
 *
 * Recapture after any SAD change: php artisan sad:capture-fixture {lid_id}
 */
$fixture = __DIR__.'/../../../fixtures/sad-lid-info.html';

test('every PII field maps from a label on the real lid_info.php', function () use ($fixture) {
    $result = SadDataParser::parsePiiHtml(file_get_contents($fixture));

    // The values are the fictional stand-ins CaptureSadFixture writes per field, so a
    // mismatch means a label mapped to the wrong field, not that the data changed.
    expect($result)->toMatchArray([
        'adres' => 'Dorpsstraat 10',
        'postcode' => '1985 AA',
        'plaats' => 'Driehuis',
        'telefoon' => '0612345678',
        'geboortedatum' => '15-03-1990',
        'instrument' => 'Trompet',
    ]);
})->skip(! file_exists($fixture), 'No captured fixture yet — run: php artisan sad:capture-fixture {lid_id}');

test('the captured fixture holds no personal data', function () use ($fixture) {
    $text = strip_tags(file_get_contents($fixture));

    expect($text)->not->toMatch('/@/');
    expect($text)->not->toMatch('/\b0\d{8,9}\b/');
})->skip(! file_exists($fixture), 'No captured fixture yet.');
