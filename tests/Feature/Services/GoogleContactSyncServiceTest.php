<?php

use App\Models\Email;
use App\Models\GoogleContactGroup;
use App\Models\GoogleContactSync;
use App\Models\GoogleContactTypeGroup;
use App\Models\Onderdeel;
use App\Models\Relatie;
use App\Models\RelatieType;
use App\Services\Google\GoogleContactSyncService;
use App\Services\Google\GooglePeopleApiClient;
use Google\Service\PeopleService;
use Google\Service\PeopleService\ContactGroup;
use Google\Service\PeopleService\Person;

beforeEach(function () {
    $this->mockApiClient = Mockery::mock(GooglePeopleApiClient::class);
    $this->app->instance(GooglePeopleApiClient::class, $this->mockApiClient);

    $this->mockService = Mockery::mock(PeopleService::class);
    $this->googleEmail = 'workspace@soli.nl';
});

// --- syncForUser: create contacts ---

test('syncForUser creates contacts for active relaties', function () {
    $relatie = Relatie::factory()->create(['voornaam' => 'Jan', 'achternaam' => 'Jansen']);
    $relatie->emails()->create(['email' => 'jan@example.com']);

    $createdPerson = new Person;
    $createdPerson->setResourceName('people/c123');

    $this->mockApiClient->shouldReceive('forUser')->with($this->googleEmail)->andReturn($this->mockService);
    $this->mockApiClient->shouldReceive('getWorkspaceUsers')->andReturn([$this->googleEmail]);
    $this->mockApiClient->shouldReceive('listContactGroups')->andReturn([]);
    $this->mockApiClient->shouldReceive('buildPerson')->andReturn(new Person);
    $this->mockApiClient->shouldReceive('batchCreateContacts')->once()->andReturn([$createdPerson]);
    $this->mockApiClient->shouldReceive('batchUpdateContacts')->never();
    $this->mockApiClient->shouldReceive('batchDeleteContacts')->never();
    $this->mockApiClient->shouldReceive('deleteContactGroup')->never();

    $service = app(GoogleContactSyncService::class);
    $stats = $service->syncForUser($this->googleEmail);

    expect($stats['created'])->toBe(1);
    $this->assertDatabaseHas('soli_google_contact_syncs', [
        'relatie_id' => $relatie->id,
        'google_user_email' => $this->googleEmail,
        'google_resource_name' => 'people/c123',
    ]);
});

// --- syncForUser: skip unchanged ---

test('syncForUser skips relaties with unchanged hash', function () {
    $relatie = Relatie::factory()->create(['voornaam' => 'Jan', 'achternaam' => 'Jansen']);
    $relatie->emails()->create(['email' => 'jan@example.com']);

    $service = app(GoogleContactSyncService::class);
    $hash = $service->computeDataHash($relatie);

    GoogleContactSync::create([
        'relatie_id' => $relatie->id,
        'google_user_email' => $this->googleEmail,
        'google_resource_name' => 'people/c123',
        'data_hash' => $hash,
    ]);

    $this->mockApiClient->shouldReceive('forUser')->with($this->googleEmail)->andReturn($this->mockService);
    $this->mockApiClient->shouldReceive('getWorkspaceUsers')->andReturn([$this->googleEmail]);
    $this->mockApiClient->shouldReceive('listContactGroups')->andReturn([]);
    $this->mockApiClient->shouldReceive('batchCreateContacts')->never();
    $this->mockApiClient->shouldReceive('batchUpdateContacts')->never();
    $this->mockApiClient->shouldReceive('batchDeleteContacts')->never();
    $this->mockApiClient->shouldReceive('deleteContactGroup')->never();

    $stats = $service->syncForUser($this->googleEmail);

    expect($stats['skipped'])->toBe(1);
    expect($stats['created'])->toBe(0);
    expect($stats['updated'])->toBe(0);
});

// --- syncForUser: update changed ---

test('syncForUser updates contacts when hash changes', function () {
    $relatie = Relatie::factory()->create(['voornaam' => 'Jan', 'achternaam' => 'Jansen']);
    $relatie->emails()->create(['email' => 'jan@example.com']);

    GoogleContactSync::create([
        'relatie_id' => $relatie->id,
        'google_user_email' => $this->googleEmail,
        'google_resource_name' => 'people/c123',
        'data_hash' => 'stale-hash',
    ]);

    $existingPerson = new Person;
    $existingPerson->setEtag('etag-abc');

    $this->mockApiClient->shouldReceive('forUser')->with($this->googleEmail)->andReturn($this->mockService);
    $this->mockApiClient->shouldReceive('getWorkspaceUsers')->andReturn([$this->googleEmail]);
    $this->mockApiClient->shouldReceive('listContactGroups')->andReturn([]);
    $this->mockApiClient->shouldReceive('buildPerson')->andReturn(new Person);
    $this->mockApiClient->shouldReceive('getContact')->with($this->mockService, 'people/c123')->andReturn($existingPerson);
    $this->mockApiClient->shouldReceive('getEtag')->with($existingPerson)->andReturn('etag-abc');
    $this->mockApiClient->shouldReceive('batchUpdateContacts')->once()->andReturn([]);
    $this->mockApiClient->shouldReceive('batchCreateContacts')->never();
    $this->mockApiClient->shouldReceive('batchDeleteContacts')->never();
    $this->mockApiClient->shouldReceive('deleteContactGroup')->never();

    $service = app(GoogleContactSyncService::class);
    $stats = $service->syncForUser($this->googleEmail);

    expect($stats['updated'])->toBe(1);

    $sync = GoogleContactSync::where('relatie_id', $relatie->id)->first();
    expect($sync->data_hash)->not->toBe('stale-hash');
});

// --- syncForUser: delete deactivated ---

test('syncForUser deletes contacts for deactivated relaties', function () {
    $relatie = Relatie::factory()->create(['actief' => false]);

    GoogleContactSync::create([
        'relatie_id' => $relatie->id,
        'google_user_email' => $this->googleEmail,
        'google_resource_name' => 'people/c999',
        'data_hash' => 'old-hash',
    ]);

    $this->mockApiClient->shouldReceive('forUser')->with($this->googleEmail)->andReturn($this->mockService);
    $this->mockApiClient->shouldReceive('getWorkspaceUsers')->andReturn([$this->googleEmail]);
    $this->mockApiClient->shouldReceive('listContactGroups')->andReturn([]);
    $this->mockApiClient->shouldReceive('batchDeleteContacts')
        ->with($this->mockService, ['people/c999'])
        ->once();
    $this->mockApiClient->shouldReceive('batchCreateContacts')->never();
    $this->mockApiClient->shouldReceive('batchUpdateContacts')->never();
    $this->mockApiClient->shouldReceive('deleteContactGroup')->never();

    $service = app(GoogleContactSyncService::class);
    $stats = $service->syncForUser($this->googleEmail);

    expect($stats['deleted'])->toBe(1);
    $this->assertDatabaseMissing('soli_google_contact_syncs', [
        'relatie_id' => $relatie->id,
    ]);
});

// --- syncForUser: externally deleted contact ---

test('syncForUser handles externally deleted contact by re-creating', function () {
    $relatie = Relatie::factory()->create(['voornaam' => 'Jan', 'achternaam' => 'Jansen']);
    $relatie->emails()->create(['email' => 'jan@example.com']);

    GoogleContactSync::create([
        'relatie_id' => $relatie->id,
        'google_user_email' => $this->googleEmail,
        'google_resource_name' => 'people/c-deleted',
        'data_hash' => 'stale-hash',
    ]);

    $newPerson = new Person;
    $newPerson->setResourceName('people/c-new');

    $this->mockApiClient->shouldReceive('forUser')->with($this->googleEmail)->andReturn($this->mockService);
    $this->mockApiClient->shouldReceive('getWorkspaceUsers')->andReturn([$this->googleEmail]);
    $this->mockApiClient->shouldReceive('listContactGroups')->andReturn([]);
    $this->mockApiClient->shouldReceive('buildPerson')->andReturn(new Person);
    $this->mockApiClient->shouldReceive('getContact')->with($this->mockService, 'people/c-deleted')->andReturnNull();
    $this->mockApiClient->shouldReceive('batchCreateContacts')->once()->andReturn([$newPerson]);
    $this->mockApiClient->shouldReceive('batchUpdateContacts')->never();
    $this->mockApiClient->shouldReceive('batchDeleteContacts')->never();
    $this->mockApiClient->shouldReceive('deleteContactGroup')->never();

    $service = app(GoogleContactSyncService::class);
    $stats = $service->syncForUser($this->googleEmail);

    expect($stats['created'])->toBe(1);
    $this->assertDatabaseHas('soli_google_contact_syncs', [
        'relatie_id' => $relatie->id,
        'google_resource_name' => 'people/c-new',
    ]);
});

// --- computeDataHash ---

test('computeDataHash changes when name changes', function () {
    $relatie = Relatie::factory()->create(['voornaam' => 'Jan', 'achternaam' => 'Jansen']);
    $relatie->load(['emails', 'onderdelen', 'types']);

    $service = app(GoogleContactSyncService::class);
    $hash1 = $service->computeDataHash($relatie);

    $relatie->voornaam = 'Piet';
    $hash2 = $service->computeDataHash($relatie);

    expect($hash1)->not->toBe($hash2);
});

test('computeDataHash changes when email changes', function () {
    $relatie = Relatie::factory()->create();
    $relatie->load(['emails', 'onderdelen', 'types']);

    $service = app(GoogleContactSyncService::class);
    $hash1 = $service->computeDataHash($relatie);

    $relatie->emails()->create(['email' => 'new@example.com']);
    $relatie->load('emails');
    $hash2 = $service->computeDataHash($relatie);

    expect($hash1)->not->toBe($hash2);
});

test('computeDataHash changes when onderdeel changes', function () {
    $relatie = Relatie::factory()->create();
    $onderdeel = Onderdeel::factory()->create(['type' => 'orkest']);
    $relatie->load(['emails', 'onderdelen', 'types']);

    $service = app(GoogleContactSyncService::class);
    $hash1 = $service->computeDataHash($relatie);

    $relatie->onderdelen()->attach($onderdeel->id, ['van' => now()->subYear()->toDateString()]);
    $relatie->load('onderdelen');
    $hash2 = $service->computeDataHash($relatie);

    expect($hash1)->not->toBe($hash2);
});

// --- Contact groups ---

test('contact groups created for orkest ensemble and opleidingsgroep only', function () {
    $orkest = Onderdeel::factory()->create(['type' => 'orkest', 'naam' => 'Test Orkest']);
    $ensemble = Onderdeel::factory()->create(['type' => 'ensemble', 'naam' => 'Test Ensemble']);
    $opleidingsgroep = Onderdeel::factory()->create(['type' => 'opleidingsgroep', 'naam' => 'Test Opleiding']);
    $commissie = Onderdeel::factory()->create(['type' => 'commissie', 'naam' => 'Test Commissie']);
    $bestuur = Onderdeel::factory()->create(['type' => 'bestuur', 'naam' => 'Test Bestuur']);

    // Create one active relatie so there's something to sync
    Relatie::factory()->create();

    $groupOrkest = new ContactGroup;
    $groupOrkest->setResourceName('contactGroups/orkest1');
    $groupOrkest->setName('Soli - Test Orkest');

    $groupEnsemble = new ContactGroup;
    $groupEnsemble->setResourceName('contactGroups/ensemble1');
    $groupEnsemble->setName('Soli - Test Ensemble');

    $groupOpleiding = new ContactGroup;
    $groupOpleiding->setResourceName('contactGroups/opleiding1');
    $groupOpleiding->setName('Soli - Test Opleiding');

    $createdPerson = new Person;
    $createdPerson->setResourceName('people/c1');

    $this->mockApiClient->shouldReceive('forUser')->with($this->googleEmail)->andReturn($this->mockService);
    $this->mockApiClient->shouldReceive('getWorkspaceUsers')->andReturn([$this->googleEmail]);
    $this->mockApiClient->shouldReceive('listContactGroups')->andReturn([]);
    $this->mockApiClient->shouldReceive('createContactGroup')
        ->with($this->mockService, 'Soli - Test Orkest')
        ->once()
        ->andReturn($groupOrkest);
    $this->mockApiClient->shouldReceive('createContactGroup')
        ->with($this->mockService, 'Soli - Test Ensemble')
        ->once()
        ->andReturn($groupEnsemble);
    $this->mockApiClient->shouldReceive('createContactGroup')
        ->with($this->mockService, 'Soli - Test Opleiding')
        ->once()
        ->andReturn($groupOpleiding);
    $this->mockApiClient->shouldReceive('buildPerson')->andReturn(new Person);
    $this->mockApiClient->shouldReceive('batchCreateContacts')->andReturn([$createdPerson]);
    $this->mockApiClient->shouldReceive('batchDeleteContacts')->never();
    $this->mockApiClient->shouldReceive('deleteContactGroup')->never();

    $service = app(GoogleContactSyncService::class);
    $service->syncForUser($this->googleEmail);

    // Groups created for orkest, ensemble, opleidingsgroep
    $this->assertDatabaseHas('soli_google_contact_groups', ['onderdeel_id' => $orkest->id]);
    $this->assertDatabaseHas('soli_google_contact_groups', ['onderdeel_id' => $ensemble->id]);
    $this->assertDatabaseHas('soli_google_contact_groups', ['onderdeel_id' => $opleidingsgroep->id]);

    // No groups for commissie or bestuur
    $this->assertDatabaseMissing('soli_google_contact_groups', ['onderdeel_id' => $commissie->id]);
    $this->assertDatabaseMissing('soli_google_contact_groups', ['onderdeel_id' => $bestuur->id]);
});

test('computeDataHash changes when type changes', function () {
    $relatie = Relatie::factory()->create();
    $type = RelatieType::factory()->create(['naam' => 'lid']);
    $relatie->load(['emails', 'onderdelen', 'types']);

    $service = app(GoogleContactSyncService::class);
    $hash1 = $service->computeDataHash($relatie);

    $relatie->types()->attach($type->id, ['van' => now()->subYear()->toDateString()]);
    $relatie->load('types');
    $hash2 = $service->computeDataHash($relatie);

    expect($hash1)->not->toBe($hash2);
});

test('stale contact groups are cleaned up', function () {
    // Create an onderdeel that was previously active and has a group, but is now inactive
    $inactiveOnderdeel = Onderdeel::factory()->create(['type' => 'orkest', 'actief' => false, 'naam' => 'Oud Orkest']);

    GoogleContactGroup::create([
        'onderdeel_id' => $inactiveOnderdeel->id,
        'google_user_email' => $this->googleEmail,
        'google_resource_name' => 'contactGroups/stale1',
    ]);

    // Create an active relatie so syncForUser processes properly
    Relatie::factory()->create();

    $createdPerson = new Person;
    $createdPerson->setResourceName('people/c1');

    $this->mockApiClient->shouldReceive('forUser')->with($this->googleEmail)->andReturn($this->mockService);
    $this->mockApiClient->shouldReceive('getWorkspaceUsers')->andReturn([$this->googleEmail]);
    $this->mockApiClient->shouldReceive('listContactGroups')->andReturn([]);
    $this->mockApiClient->shouldReceive('buildPerson')->andReturn(new Person);
    $this->mockApiClient->shouldReceive('batchCreateContacts')->andReturn([$createdPerson]);
    $this->mockApiClient->shouldReceive('deleteContactGroup')->with($this->mockService, 'contactGroups/stale1')->once();
    $this->mockApiClient->shouldReceive('batchDeleteContacts')->never();

    $service = app(GoogleContactSyncService::class);
    $service->syncForUser($this->googleEmail);

    $this->assertDatabaseMissing('soli_google_contact_groups', [
        'onderdeel_id' => $inactiveOnderdeel->id,
    ]);
});

// --- Type contact groups ---

test('contact type groups created for all relatie types', function () {
    $lid = RelatieType::factory()->create(['naam' => 'lid']);
    $donateur = RelatieType::factory()->create(['naam' => 'donateur']);

    Relatie::factory()->create();

    $groupLid = new ContactGroup;
    $groupLid->setResourceName('contactGroups/lid1');
    $groupLid->setName('Soli - Lid');

    $groupDonateur = new ContactGroup;
    $groupDonateur->setResourceName('contactGroups/donateur1');
    $groupDonateur->setName('Soli - Donateur');

    $this->mockApiClient->shouldReceive('forUser')->with($this->googleEmail)->andReturn($this->mockService);
    $this->mockApiClient->shouldReceive('getWorkspaceUsers')->andReturn([$this->googleEmail]);
    $this->mockApiClient->shouldReceive('listContactGroups')->andReturn([]);
    $this->mockApiClient->shouldReceive('createContactGroup')
        ->with($this->mockService, 'Soli - Lid')
        ->once()
        ->andReturn($groupLid);
    $this->mockApiClient->shouldReceive('createContactGroup')
        ->with($this->mockService, 'Soli - Donateur')
        ->once()
        ->andReturn($groupDonateur);
    $this->mockApiClient->shouldReceive('buildPerson')->andReturn(new Person);
    $this->mockApiClient->shouldReceive('createContact')->andReturn(tap(new Person, fn ($p) => $p->setResourceName('people/c1')));
    $this->mockApiClient->shouldReceive('deleteContact')->never();
    $this->mockApiClient->shouldReceive('deleteContactGroup')->never();

    $service = app(GoogleContactSyncService::class);
    $service->syncForUser($this->googleEmail);

    $this->assertDatabaseHas('soli_google_contact_type_groups', ['relatie_type_id' => $lid->id]);
    $this->assertDatabaseHas('soli_google_contact_type_groups', ['relatie_type_id' => $donateur->id]);
});

test('stale contact type groups are cleaned up via cascade delete', function () {
    // With cascadeOnDelete, deleting a RelatieType auto-removes its GoogleContactTypeGroup
    $oldType = RelatieType::factory()->create(['naam' => 'oud_type']);

    GoogleContactTypeGroup::create([
        'relatie_type_id' => $oldType->id,
        'google_user_email' => $this->googleEmail,
        'google_resource_name' => 'contactGroups/staleType1',
    ]);

    $this->assertDatabaseHas('soli_google_contact_type_groups', [
        'relatie_type_id' => $oldType->id,
    ]);

    $oldType->delete();

    $this->assertDatabaseMissing('soli_google_contact_type_groups', [
        'relatie_type_id' => $oldType->id,
    ]);
});
