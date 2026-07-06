<?php

use App\Services\Sad\SadDataParser;

// --- splitPhoneNumbers ---

test('splits phone numbers separated by space', function () {
    $result = SadDataParser::splitPhoneNumbers('0255-534403 06-11052119');
    expect($result)->toBe(['0255-534403', '06-11052119']);
});

test('splits phone numbers separated by semicolon', function () {
    $result = SadDataParser::splitPhoneNumbers('0255-534403;06-11052119');
    expect($result)->toBe(['0255-534403', '06-11052119']);
});

test('splits phone numbers separated by comma', function () {
    $result = SadDataParser::splitPhoneNumbers('0255-534403, 06-11052119');
    expect($result)->toBe(['0255-534403', '06-11052119']);
});

test('splits phone numbers separated by slash', function () {
    $result = SadDataParser::splitPhoneNumbers('0255-534403/06-11052119');
    expect($result)->toBe(['0255-534403', '06-11052119']);
});

test('splits phone numbers separated by Dutch en', function () {
    $result = SadDataParser::splitPhoneNumbers('0255-534403 en 06-11052119');
    expect($result)->toBe(['0255-534403', '06-11052119']);
});

test('returns single phone number as array', function () {
    $result = SadDataParser::splitPhoneNumbers('06-12345678');
    expect($result)->toBe(['06-12345678']);
});

test('strips parenthetical notes from phone numbers', function () {
    $result = SadDataParser::splitPhoneNumbers('06-12345678 (moeder)');
    expect($result)->toBe(['06-12345678']);
});

test('handles mixed separators in phone numbers', function () {
    $result = SadDataParser::splitPhoneNumbers('0255-534403;06-11052119, 06-99887766');
    expect($result)->toBe(['0255-534403', '06-11052119', '06-99887766']);
});

test('returns empty array for empty string', function () {
    $result = SadDataParser::splitPhoneNumbers('');
    expect($result)->toBe([]);
});

// --- splitAddress ---

test('splits address with house number', function () {
    $result = SadDataParser::splitAddress('Dorpsstraat 10');
    expect($result)->toBe(['Dorpsstraat', '10', null]);
});

test('splits address with house number and suffix', function () {
    $result = SadDataParser::splitAddress('Dorpsstraat 10a');
    expect($result)->toBe(['Dorpsstraat', '10', 'a']);
});

test('splits address with house number and spaced suffix', function () {
    $result = SadDataParser::splitAddress('Dorpsstraat 10 bis');
    expect($result)->toBe(['Dorpsstraat', '10', 'bis']);
});

test('handles address without house number', function () {
    $result = SadDataParser::splitAddress('Postbus');
    expect($result)->toBe(['Postbus', '', null]);
});

test('handles multi-word street name', function () {
    $result = SadDataParser::splitAddress('Jan van Galenstraat 42');
    expect($result)->toBe(['Jan van Galenstraat', '42', null]);
});

// --- matchInstrumentSoort ---

test('matches exact instrument name', function () {
    $lookup = [1 => 'Trompet', 2 => 'Klarinet', 3 => 'Dwarsfluit'];
    $result = SadDataParser::matchInstrumentSoort('Trompet', $lookup);
    expect($result)->toBe(1);
});

test('matches instrument name case-insensitively', function () {
    $lookup = [1 => 'Trompet', 2 => 'Klarinet'];
    $result = SadDataParser::matchInstrumentSoort('trompet', $lookup);
    expect($result)->toBe(1);
});

test('matches instrument name ignoring spaces', function () {
    $lookup = [1 => 'Melodisch slagwerk'];
    $result = SadDataParser::matchInstrumentSoort('Melodischslagwerk', $lookup);
    expect($result)->toBe(1);
});

test('returns null for unknown instrument', function () {
    $lookup = [1 => 'Trompet', 2 => 'Klarinet'];
    $result = SadDataParser::matchInstrumentSoort('Banjo', $lookup);
    expect($result)->toBeNull();
});

// --- parseDate ---

test('parses valid DD-MM-YYYY date', function () {
    $result = SadDataParser::parseDate('15-03-1990');
    expect($result)->toBe('1990-03-15');
});

test('parses date at boundary year 1900', function () {
    $result = SadDataParser::parseDate('01-01-1900');
    expect($result)->toBe('1900-01-01');
});

test('returns null for null input', function () {
    $result = SadDataParser::parseDate(null);
    expect($result)->toBeNull();
});

test('returns null for empty string', function () {
    $result = SadDataParser::parseDate('');
    expect($result)->toBeNull();
});

test('returns null for invalid date format', function () {
    $result = SadDataParser::parseDate('1990-03-15');
    expect($result)->toBeNull();
});

test('returns null for nonsensical date', function () {
    $result = SadDataParser::parseDate('00-00-0000');
    expect($result)->toBeNull();
});

// --- parsePiiHtml ---

test('parses PII from HTML table', function () {
    $html = '<table>
        <tr><td>Adres</td><td>Dorpsstraat 10</td></tr>
        <tr><td>Postcode</td><td>1985 AB</td></tr>
        <tr><td>Woonplaats</td><td>Driehuis</td></tr>
        <tr><td>Telefoon</td><td>0255-534403</td></tr>
        <tr><td>Geboortedatum</td><td>15-03-1990</td></tr>
        <tr><td>Instrument</td><td>Trompet</td></tr>
    </table>';

    $result = SadDataParser::parsePiiHtml($html);

    expect($result['adres'])->toBe('Dorpsstraat 10');
    expect($result['postcode'])->toBe('1985 AB');
    expect($result['plaats'])->toBe('Driehuis');
    expect($result['telefoon'])->toBe('0255-534403');
    expect($result['geboortedatum'])->toBe('15-03-1990');
    expect($result['instrument'])->toBe('Trompet');
});

test('returns null values for missing PII fields', function () {
    $html = '<table><tr><td>Adres</td><td>Dorpsstraat 10</td></tr></table>';

    $result = SadDataParser::parsePiiHtml($html);

    expect($result['adres'])->toBe('Dorpsstraat 10');
    expect($result['postcode'])->toBeNull();
    expect($result['plaats'])->toBeNull();
    expect($result['telefoon'])->toBeNull();
    expect($result['geboortedatum'])->toBeNull();
    expect($result['instrument'])->toBeNull();
});

test('returns all null for empty HTML', function () {
    $result = SadDataParser::parsePiiHtml('');

    expect($result)->toBe([
        'adres' => null,
        'postcode' => null,
        'plaats' => null,
        'telefoon' => null,
        'geboortedatum' => null,
        'instrument' => null,
    ]);
});

test('skips empty table cell values', function () {
    $html = '<table><tr><td>Telefoon</td><td></td></tr></table>';

    $result = SadDataParser::parsePiiHtml($html);
    expect($result['telefoon'])->toBeNull();
});
