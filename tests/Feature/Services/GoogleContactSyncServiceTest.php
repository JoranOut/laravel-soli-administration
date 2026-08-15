<?php

use App\Models\Email;
use App\Models\GoogleContactGroup;
use App\Models\GoogleContactSync;
use App\Models\GoogleContactSyncLog;
use App\Models\GoogleContactTypeGroup;
use App\Models\JobStatus;
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
    $this->mockApiClient->shouldReceive('listManagedContacts')->andReturn([]);
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
    $this->mockApiClient->shouldReceive('listManagedContacts')->andReturn([]);
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
    $existingPerson->setResourceName('people/c123');
    $existingPerson->setEtag('etag-abc');

    $this->mockApiClient->shouldReceive('forUser')->with($this->googleEmail)->andReturn($this->mockService);
    $this->mockApiClient->shouldReceive('getWorkspaceUsers')->andReturn([$this->googleEmail]);
    $this->mockApiClient->shouldReceive('listContactGroups')->andReturn([]);
    $this->mockApiClient->shouldReceive('listManagedContacts')->andReturn([$existingPerson]);
    $this->mockApiClient->shouldReceive('buildPerson')->andReturn(new Person);
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
    $this->mockApiClient->shouldReceive('listManagedContacts')->andReturn([]);
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
    $this->mockApiClient->shouldReceive('listManagedContacts')->andReturn([]);
    $this->mockApiClient->shouldReceive('buildPerson')->andReturn(new Person);
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
    $onderdeel = Onderdeel::factory()->create(['type' => 'muziekgroep']);
    $relatie->load(['emails', 'onderdelen', 'types']);

    $service = app(GoogleContactSyncService::class);
    $hash1 = $service->computeDataHash($relatie);

    $relatie->onderdelen()->attach($onderdeel->id, ['van' => now()->subYear()->toDateString()]);
    $relatie->load('onderdelen');
    $hash2 = $service->computeDataHash($relatie);

    expect($hash1)->not->toBe($hash2);
});

// --- Contact groups ---

test('contact groups created for muziekgroep only', function () {
    $muziekgroep1 = Onderdeel::factory()->create(['type' => 'muziekgroep', 'naam' => 'Test Orkest']);
    $muziekgroep2 = Onderdeel::factory()->create(['type' => 'muziekgroep', 'naam' => 'Test Ensemble']);
    $commissie = Onderdeel::factory()->create(['type' => 'commissie', 'naam' => 'Test Commissie']);
    $bestuur = Onderdeel::factory()->create(['type' => 'bestuur', 'naam' => 'Test Bestuur']);

    // Create one active relatie so there's something to sync
    Relatie::factory()->create();

    $groupOrkest = new ContactGroup;
    $groupOrkest->setResourceName('contactGroups/muziekgroep1');
    $groupOrkest->setName('Soli - Test Orkest');

    $groupEnsemble = new ContactGroup;
    $groupEnsemble->setResourceName('contactGroups/muziekgroep2');
    $groupEnsemble->setName('Soli - Test Ensemble');

    $createdPerson = new Person;
    $createdPerson->setResourceName('people/c1');

    $this->mockApiClient->shouldReceive('forUser')->with($this->googleEmail)->andReturn($this->mockService);
    $this->mockApiClient->shouldReceive('getWorkspaceUsers')->andReturn([$this->googleEmail]);
    $this->mockApiClient->shouldReceive('listContactGroups')->andReturn([]);
    $this->mockApiClient->shouldReceive('listManagedContacts')->andReturn([]);
    $this->mockApiClient->shouldReceive('createContactGroup')
        ->with($this->mockService, 'Soli - Test Orkest')
        ->once()
        ->andReturn($groupOrkest);
    $this->mockApiClient->shouldReceive('createContactGroup')
        ->with($this->mockService, 'Soli - Test Ensemble')
        ->once()
        ->andReturn($groupEnsemble);
    $this->mockApiClient->shouldReceive('buildPerson')->andReturn(new Person);
    $this->mockApiClient->shouldReceive('batchCreateContacts')->andReturn([$createdPerson]);
    $this->mockApiClient->shouldReceive('batchDeleteContacts')->never();
    $this->mockApiClient->shouldReceive('deleteContactGroup')->never();

    $service = app(GoogleContactSyncService::class);
    $service->syncForUser($this->googleEmail);

    // Groups created for muziekgroep onderdelen
    $this->assertDatabaseHas('soli_google_contact_groups', ['onderdeel_id' => $muziekgroep1->id]);
    $this->assertDatabaseHas('soli_google_contact_groups', ['onderdeel_id' => $muziekgroep2->id]);

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
    $inactiveOnderdeel = Onderdeel::factory()->create(['type' => 'muziekgroep', 'actief' => false, 'naam' => 'Oud Orkest']);

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
    $this->mockApiClient->shouldReceive('listManagedContacts')->andReturn([]);
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

    $createdPerson = new Person;
    $createdPerson->setResourceName('people/c1');

    $this->mockApiClient->shouldReceive('forUser')->with($this->googleEmail)->andReturn($this->mockService);
    $this->mockApiClient->shouldReceive('getWorkspaceUsers')->andReturn([$this->googleEmail]);
    $this->mockApiClient->shouldReceive('listContactGroups')->andReturn([]);
    $this->mockApiClient->shouldReceive('listManagedContacts')->andReturn([]);
    $this->mockApiClient->shouldReceive('createContactGroup')
        ->with($this->mockService, 'Soli - Lid')
        ->once()
        ->andReturn($groupLid);
    $this->mockApiClient->shouldReceive('createContactGroup')
        ->with($this->mockService, 'Soli - Donateur')
        ->once()
        ->andReturn($groupDonateur);
    $this->mockApiClient->shouldReceive('buildPerson')->andReturn(new Person);
    $this->mockApiClient->shouldReceive('batchCreateContacts')->andReturn([$createdPerson]);
    $this->mockApiClient->shouldReceive('batchDeleteContacts')->never();
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

// --- Error tracking: batch create failure ---

test('syncForUser tracks batch create failure in stats', function () {
    $relatie = Relatie::factory()->create(['voornaam' => 'Jan', 'achternaam' => 'Jansen']);
    $relatie->emails()->create(['email' => 'jan@example.com']);

    $this->mockApiClient->shouldReceive('forUser')->with($this->googleEmail)->andReturn($this->mockService);
    $this->mockApiClient->shouldReceive('getWorkspaceUsers')->andReturn([$this->googleEmail]);
    $this->mockApiClient->shouldReceive('listContactGroups')->andReturn([]);
    $this->mockApiClient->shouldReceive('listManagedContacts')->andReturn([]);
    $this->mockApiClient->shouldReceive('buildPerson')->andReturn(new Person);
    $this->mockApiClient->shouldReceive('batchCreateContacts')
        ->once()
        ->andThrow(new \RuntimeException('Quota exceeded'));
    $this->mockApiClient->shouldReceive('batchUpdateContacts')->never();
    $this->mockApiClient->shouldReceive('batchDeleteContacts')->never();
    $this->mockApiClient->shouldReceive('deleteContactGroup')->never();

    $service = app(GoogleContactSyncService::class);
    $stats = $service->syncForUser($this->googleEmail);

    expect($stats['failed'])->toBe(1);
    expect($stats['created'])->toBe(0);
    expect($stats['errors'])->toHaveCount(1);
    expect($stats['errors'][0])->toContain('Batch create failed');
    expect($stats['errors'][0])->toContain('Quota exceeded');

    // No sync mapping should be created for failed contacts
    $this->assertDatabaseMissing('soli_google_contact_syncs', [
        'relatie_id' => $relatie->id,
    ]);
});

// --- Error tracking: batch update failure ---

test('syncForUser tracks batch update failure in stats', function () {
    $relatie = Relatie::factory()->create(['voornaam' => 'Jan', 'achternaam' => 'Jansen']);
    $relatie->emails()->create(['email' => 'jan@example.com']);

    GoogleContactSync::create([
        'relatie_id' => $relatie->id,
        'google_user_email' => $this->googleEmail,
        'google_resource_name' => 'people/c123',
        'data_hash' => 'stale-hash',
    ]);

    $existingPerson = new Person;
    $existingPerson->setResourceName('people/c123');
    $existingPerson->setEtag('etag-abc');

    $this->mockApiClient->shouldReceive('forUser')->with($this->googleEmail)->andReturn($this->mockService);
    $this->mockApiClient->shouldReceive('getWorkspaceUsers')->andReturn([$this->googleEmail]);
    $this->mockApiClient->shouldReceive('listContactGroups')->andReturn([]);
    $this->mockApiClient->shouldReceive('listManagedContacts')->andReturn([$existingPerson]);
    $this->mockApiClient->shouldReceive('buildPerson')->andReturn(new Person);
    $this->mockApiClient->shouldReceive('getEtag')->with($existingPerson)->andReturn('etag-abc');
    $this->mockApiClient->shouldReceive('batchUpdateContacts')
        ->once()
        ->andThrow(new \RuntimeException('Rate limit exceeded'));
    $this->mockApiClient->shouldReceive('batchCreateContacts')->never();
    $this->mockApiClient->shouldReceive('batchDeleteContacts')->never();
    $this->mockApiClient->shouldReceive('deleteContactGroup')->never();

    $service = app(GoogleContactSyncService::class);
    $stats = $service->syncForUser($this->googleEmail);

    expect($stats['failed'])->toBe(1);
    expect($stats['updated'])->toBe(0);
    expect($stats['errors'])->toHaveCount(1);
    expect($stats['errors'][0])->toContain('Batch update failed');
    expect($stats['errors'][0])->toContain('Rate limit exceeded');

    // Hash should NOT be updated since the batch failed
    $sync = GoogleContactSync::where('relatie_id', $relatie->id)->first();
    expect($sync->data_hash)->toBe('stale-hash');
});

// --- Error tracking: batch delete failure preserves mappings ---

test('syncForUser preserves sync mappings when batch delete fails', function () {
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
    $this->mockApiClient->shouldReceive('listManagedContacts')->andReturn([]);
    $this->mockApiClient->shouldReceive('batchDeleteContacts')
        ->once()
        ->andThrow(new \RuntimeException('API error'));
    $this->mockApiClient->shouldReceive('batchCreateContacts')->never();
    $this->mockApiClient->shouldReceive('batchUpdateContacts')->never();
    $this->mockApiClient->shouldReceive('deleteContactGroup')->never();

    $service = app(GoogleContactSyncService::class);
    $stats = $service->syncForUser($this->googleEmail);

    expect($stats['failed'])->toBe(1);
    expect($stats['deleted'])->toBe(0);
    expect($stats['errors'])->toHaveCount(1);
    expect($stats['errors'][0])->toContain('Batch delete failed');

    // Sync mapping should be preserved when delete fails
    $this->assertDatabaseHas('soli_google_contact_syncs', [
        'relatie_id' => $relatie->id,
        'google_resource_name' => 'people/c999',
    ]);
});

// --- syncAll: completed_with_errors status ---

test('syncAll sets completed_with_errors when batches fail', function () {
    $relatie = Relatie::factory()->create(['voornaam' => 'Jan', 'achternaam' => 'Jansen']);
    $relatie->emails()->create(['email' => 'jan@example.com']);

    $this->mockApiClient->shouldReceive('forUser')->with($this->googleEmail)->andReturn($this->mockService);
    $this->mockApiClient->shouldReceive('getWorkspaceUsers')->andReturn([$this->googleEmail]);
    $this->mockApiClient->shouldReceive('listContactGroups')->andReturn([]);
    $this->mockApiClient->shouldReceive('listManagedContacts')->andReturn([]);
    $this->mockApiClient->shouldReceive('buildPerson')->andReturn(new Person);
    $this->mockApiClient->shouldReceive('batchCreateContacts')
        ->once()
        ->andThrow(new \RuntimeException('Quota exceeded'));
    $this->mockApiClient->shouldReceive('batchUpdateContacts')->never();
    $this->mockApiClient->shouldReceive('batchDeleteContacts')->never();
    $this->mockApiClient->shouldReceive('deleteContactGroup')->never();

    $service = app(GoogleContactSyncService::class);
    $summary = $service->syncAll();

    expect($summary['failed'])->toBe(1);
    expect($summary['errors'])->toHaveCount(1);

    // Sync log should have completed_with_errors status
    $this->assertDatabaseHas('soli_google_contact_sync_logs', [
        'type' => 'full',
        'status' => 'completed_with_errors',
        'contacts_failed' => 1,
    ]);

    $log = GoogleContactSyncLog::latest()->first();
    expect($log->error_message)->toContain('Quota exceeded');

    // Job status should reflect the partial failure
    $jobStatus = JobStatus::where('name', 'google-contacts-sync')->first();
    expect($jobStatus)->not->toBeNull();
    expect($jobStatus->status)->toBe('completed_with_errors');
    expect($jobStatus->last_error)->toContain('Quota exceeded');
});

// --- syncAll: completed status when no errors ---

test('syncAll sets completed status when all batches succeed', function () {
    $relatie = Relatie::factory()->create(['voornaam' => 'Jan', 'achternaam' => 'Jansen']);
    $relatie->emails()->create(['email' => 'jan@example.com']);

    $createdPerson = new Person;
    $createdPerson->setResourceName('people/c123');

    $this->mockApiClient->shouldReceive('forUser')->with($this->googleEmail)->andReturn($this->mockService);
    $this->mockApiClient->shouldReceive('getWorkspaceUsers')->andReturn([$this->googleEmail]);
    $this->mockApiClient->shouldReceive('listContactGroups')->andReturn([]);
    $this->mockApiClient->shouldReceive('listManagedContacts')->andReturn([]);
    $this->mockApiClient->shouldReceive('buildPerson')->andReturn(new Person);
    $this->mockApiClient->shouldReceive('batchCreateContacts')->once()->andReturn([$createdPerson]);
    $this->mockApiClient->shouldReceive('batchUpdateContacts')->never();
    $this->mockApiClient->shouldReceive('batchDeleteContacts')->never();
    $this->mockApiClient->shouldReceive('deleteContactGroup')->never();

    $service = app(GoogleContactSyncService::class);
    $summary = $service->syncAll();

    expect($summary['created'])->toBe(1);
    expect($summary['failed'])->toBe(0);
    expect($summary['errors'])->toBeEmpty();

    // Sync log should have completed status
    $this->assertDatabaseHas('soli_google_contact_sync_logs', [
        'type' => 'full',
        'status' => 'completed',
        'contacts_created' => 1,
        'contacts_failed' => 0,
    ]);

    $log = GoogleContactSyncLog::latest()->first();
    expect($log->error_message)->toBeNull();

    // Job status should be completed
    $jobStatus = JobStatus::where('name', 'google-contacts-sync')->first();
    expect($jobStatus)->not->toBeNull();
    expect($jobStatus->status)->toBe('completed');
    expect($jobStatus->last_error)->toBeNull();
});

// --- syncAll: errors aggregated across users ---

test('syncAll aggregates errors from all workspace users', function () {
    $relatie = Relatie::factory()->create(['voornaam' => 'Jan', 'achternaam' => 'Jansen']);
    $relatie->emails()->create(['email' => 'jan@example.com']);

    $user1 = 'user1@soli.nl';
    $user2 = 'user2@soli.nl';
    $mockService1 = Mockery::mock(PeopleService::class);
    $mockService2 = Mockery::mock(PeopleService::class);

    $this->mockApiClient->shouldReceive('getWorkspaceUsers')->andReturn([$user1, $user2]);
    $this->mockApiClient->shouldReceive('forUser')->with($user1)->andReturn($mockService1);
    $this->mockApiClient->shouldReceive('forUser')->with($user2)->andReturn($mockService2);
    $this->mockApiClient->shouldReceive('listContactGroups')->andReturn([]);
    $this->mockApiClient->shouldReceive('listManagedContacts')->andReturn([]);
    $this->mockApiClient->shouldReceive('buildPerson')->andReturn(new Person);
    // Both users fail
    $this->mockApiClient->shouldReceive('batchCreateContacts')
        ->with($mockService1, Mockery::any())
        ->andThrow(new \RuntimeException('Error for user1'));
    $this->mockApiClient->shouldReceive('batchCreateContacts')
        ->with($mockService2, Mockery::any())
        ->andThrow(new \RuntimeException('Error for user2'));
    $this->mockApiClient->shouldReceive('batchUpdateContacts')->never();
    $this->mockApiClient->shouldReceive('batchDeleteContacts')->never();
    $this->mockApiClient->shouldReceive('deleteContactGroup')->never();

    $service = app(GoogleContactSyncService::class);
    $summary = $service->syncAll();

    // Errors should be aggregated from both users
    expect($summary['errors'])->toHaveCount(2);
    expect($summary['errors'][0])->toContain('user1@soli.nl');
    expect($summary['errors'][1])->toContain('user2@soli.nl');

    $log = GoogleContactSyncLog::latest()->first();
    expect($log->status)->toBe('completed_with_errors');
    expect($log->error_message)->toContain('Error for user1');
    expect($log->error_message)->toContain('Error for user2');
});

// --- Split contacts for types with a functional email ---

test('syncForUser creates a split contact for a type with a functional email', function () {
    $relatie = Relatie::factory()->create(['voornaam' => 'Peter', 'achternaam' => 'Jansen']);
    $relatie->emails()->create(['email' => 'peter@example.com']);

    $bestuur = RelatieType::factory()->create(['naam' => 'bestuur']);
    $relatie->types()->attach($bestuur->id, [
        'van' => now()->subYear()->toDateString(),
        'email' => 'voorzitter@soli.nl',
    ]);

    $groupBestuur = new ContactGroup;
    $groupBestuur->setResourceName('contactGroups/bestuur1');
    $groupBestuur->setName('Soli - Bestuur');

    $mainPerson = new Person;
    $mainPerson->setResourceName('people/c-main');
    $splitPerson = new Person;
    $splitPerson->setResourceName('people/c-split');

    $this->mockApiClient->shouldReceive('forUser')->with($this->googleEmail)->andReturn($this->mockService);
    $this->mockApiClient->shouldReceive('getWorkspaceUsers')->andReturn([$this->googleEmail]);
    $this->mockApiClient->shouldReceive('listContactGroups')->andReturn([]);
    $this->mockApiClient->shouldReceive('listManagedContacts')->andReturn([]);
    $this->mockApiClient->shouldReceive('createContactGroup')
        ->with($this->mockService, 'Soli - Bestuur')
        ->andReturn($groupBestuur);

    // Main contact: no suffix, no email override, NOT in the bestuur group
    $this->mockApiClient->shouldReceive('buildPerson')
        ->withArgs(fn ($r, $groups, $suffix = null, $emails = null) => $suffix === null && $emails === null && ! in_array('contactGroups/bestuur1', $groups))
        ->once()
        ->andReturn(new Person);

    // Split contact: suffix "Bestuur", only the functional email, only the bestuur group
    $this->mockApiClient->shouldReceive('buildPerson')
        ->withArgs(fn ($r, $groups, $suffix = null, $emails = null) => $suffix === 'Bestuur' && $emails === ['voorzitter@soli.nl'] && $groups === ['contactGroups/bestuur1'])
        ->once()
        ->andReturn(new Person);

    $this->mockApiClient->shouldReceive('batchCreateContacts')->once()->andReturn([$mainPerson, $splitPerson]);
    $this->mockApiClient->shouldReceive('batchUpdateContacts')->never();
    $this->mockApiClient->shouldReceive('batchDeleteContacts')->never();
    $this->mockApiClient->shouldReceive('deleteContactGroup')->never();

    $service = app(GoogleContactSyncService::class);
    $stats = $service->syncForUser($this->googleEmail);

    expect($stats['created'])->toBe(2);
    $this->assertDatabaseHas('soli_google_contact_syncs', [
        'relatie_id' => $relatie->id,
        'relatie_type_id' => null,
    ]);
    $this->assertDatabaseHas('soli_google_contact_syncs', [
        'relatie_id' => $relatie->id,
        'relatie_type_id' => $bestuur->id,
    ]);
});

test('syncForUser deletes the split contact when the functional email is removed', function () {
    $relatie = Relatie::factory()->create(['voornaam' => 'Peter', 'achternaam' => 'Jansen']);
    $bestuur = RelatieType::factory()->create(['naam' => 'bestuur']);
    // Active type assignment, but no functional email (anymore)
    $relatie->types()->attach($bestuur->id, ['van' => now()->subYear()->toDateString()]);
    $relatie->load(['emails', 'onderdelen', 'types']);

    $service = app(GoogleContactSyncService::class);

    GoogleContactSync::create([
        'relatie_id' => $relatie->id,
        'relatie_type_id' => null,
        'google_user_email' => $this->googleEmail,
        'google_resource_name' => 'people/c-main',
        'data_hash' => $service->computeDataHash($relatie),
    ]);
    GoogleContactSync::create([
        'relatie_id' => $relatie->id,
        'relatie_type_id' => $bestuur->id,
        'google_user_email' => $this->googleEmail,
        'google_resource_name' => 'people/c-split',
        'data_hash' => 'stale-split-hash',
    ]);

    $this->mockApiClient->shouldReceive('forUser')->with($this->googleEmail)->andReturn($this->mockService);
    $this->mockApiClient->shouldReceive('getWorkspaceUsers')->andReturn([$this->googleEmail]);
    $this->mockApiClient->shouldReceive('listContactGroups')->andReturn([]);
    $this->mockApiClient->shouldReceive('listManagedContacts')->andReturn([]);
    $this->mockApiClient->shouldReceive('createContactGroup')->andReturn(tap(new ContactGroup, fn ($g) => $g->setResourceName('contactGroups/bestuur1')));
    $this->mockApiClient->shouldReceive('batchDeleteContacts')
        ->with($this->mockService, ['people/c-split'])
        ->once();
    $this->mockApiClient->shouldReceive('batchCreateContacts')->never();
    $this->mockApiClient->shouldReceive('batchUpdateContacts')->never();
    $this->mockApiClient->shouldReceive('deleteContactGroup')->never();

    $stats = $service->syncForUser($this->googleEmail);

    expect($stats['deleted'])->toBe(1);
    expect($stats['skipped'])->toBe(1);
    $this->assertDatabaseMissing('soli_google_contact_syncs', [
        'relatie_id' => $relatie->id,
        'relatie_type_id' => $bestuur->id,
    ]);
});

test('syncRelatieForUser deletes all contacts including splits when relatie is deactivated', function () {
    $relatie = Relatie::factory()->create(['actief' => false]);
    $bestuur = RelatieType::factory()->create(['naam' => 'bestuur']);
    $relatie->load(['emails', 'onderdelen', 'types']);

    GoogleContactSync::create([
        'relatie_id' => $relatie->id,
        'relatie_type_id' => null,
        'google_user_email' => $this->googleEmail,
        'google_resource_name' => 'people/c-main',
        'data_hash' => 'h1',
    ]);
    GoogleContactSync::create([
        'relatie_id' => $relatie->id,
        'relatie_type_id' => $bestuur->id,
        'google_user_email' => $this->googleEmail,
        'google_resource_name' => 'people/c-split',
        'data_hash' => 'h2',
    ]);

    $this->mockApiClient->shouldReceive('forUser')->with($this->googleEmail)->andReturn($this->mockService);
    $this->mockApiClient->shouldReceive('listContactGroups')->andReturn([]);
    $this->mockApiClient->shouldReceive('createContactGroup')->andReturn(tap(new ContactGroup, fn ($g) => $g->setResourceName('contactGroups/bestuur1')));
    $this->mockApiClient->shouldReceive('deleteContact')->with($this->mockService, 'people/c-main')->once();
    $this->mockApiClient->shouldReceive('deleteContact')->with($this->mockService, 'people/c-split')->once();

    $service = app(GoogleContactSyncService::class);
    $stats = $service->syncRelatieForUser($relatie, $this->googleEmail);

    expect($stats['deleted'])->toBe(2);
    $this->assertDatabaseMissing('soli_google_contact_syncs', ['relatie_id' => $relatie->id]);
});

test('syncForUser splits only types with an email and keeps email-less type groups on the main contact', function () {
    $relatie = Relatie::factory()->create(['voornaam' => 'Peter', 'achternaam' => 'Jansen']);
    $relatie->emails()->create(['email' => 'peter@example.com']);

    $bestuur = RelatieType::factory()->create(['naam' => 'bestuur']);
    $lid = RelatieType::factory()->create(['naam' => 'lid']);
    $relatie->types()->attach($bestuur->id, ['van' => now()->subYear()->toDateString(), 'email' => 'voorzitter@soli.nl']);
    $relatie->types()->attach($lid->id, ['van' => now()->subYear()->toDateString()]);

    $groups = [];
    foreach (['Soli - Bestuur' => 'contactGroups/bestuur1', 'Soli - Lid' => 'contactGroups/lid1'] as $name => $resource) {
        $g = new ContactGroup;
        $g->setResourceName($resource);
        $g->setName($name);
        $groups[$name] = $g;
    }

    $mainPerson = new Person;
    $mainPerson->setResourceName('people/c-main');
    $splitPerson = new Person;
    $splitPerson->setResourceName('people/c-split');

    $this->mockApiClient->shouldReceive('forUser')->andReturn($this->mockService);
    $this->mockApiClient->shouldReceive('getWorkspaceUsers')->andReturn([$this->googleEmail]);
    $this->mockApiClient->shouldReceive('listContactGroups')->andReturn([]);
    $this->mockApiClient->shouldReceive('listManagedContacts')->andReturn([]);
    $this->mockApiClient->shouldReceive('createContactGroup')
        ->andReturnUsing(fn ($service, $name) => $groups[$name]);

    // Main contact: personal email untouched, lid group kept, bestuur group excluded
    $this->mockApiClient->shouldReceive('buildPerson')
        ->withArgs(fn ($r, $g, $suffix = null, $emails = null) => $suffix === null
            && $emails === null
            && in_array('contactGroups/lid1', $g)
            && ! in_array('contactGroups/bestuur1', $g))
        ->once()
        ->andReturn(new Person);

    // Split contact: only the bestuur group
    $this->mockApiClient->shouldReceive('buildPerson')
        ->withArgs(fn ($r, $g, $suffix = null, $emails = null) => $suffix === 'Bestuur'
            && $emails === ['voorzitter@soli.nl']
            && $g === ['contactGroups/bestuur1'])
        ->once()
        ->andReturn(new Person);

    $this->mockApiClient->shouldReceive('batchCreateContacts')->once()->andReturn([$mainPerson, $splitPerson]);
    $this->mockApiClient->shouldReceive('batchUpdateContacts')->never();
    $this->mockApiClient->shouldReceive('batchDeleteContacts')->never();
    $this->mockApiClient->shouldReceive('deleteContactGroup')->never();

    $stats = app(GoogleContactSyncService::class)->syncForUser($this->googleEmail);

    expect($stats['created'])->toBe(2);
    $this->assertDatabaseMissing('soli_google_contact_syncs', ['relatie_type_id' => $lid->id]);
});

test('syncForUser creates a split contact per type with an email', function () {
    $relatie = Relatie::factory()->create(['voornaam' => 'Peter', 'achternaam' => 'Jansen']);
    $bestuur = RelatieType::factory()->create(['naam' => 'bestuur']);
    $dirigent = RelatieType::factory()->create(['naam' => 'dirigent']);
    $relatie->types()->attach($bestuur->id, ['van' => now()->subYear()->toDateString(), 'email' => 'voorzitter@soli.nl']);
    $relatie->types()->attach($dirigent->id, ['van' => now()->subYear()->toDateString(), 'email' => 'dirigent@soli.nl']);

    $persons = [];
    foreach (['people/c-main', 'people/c-s1', 'people/c-s2'] as $rn) {
        $p = new Person;
        $p->setResourceName($rn);
        $persons[] = $p;
    }

    $this->mockApiClient->shouldReceive('forUser')->andReturn($this->mockService);
    $this->mockApiClient->shouldReceive('getWorkspaceUsers')->andReturn([$this->googleEmail]);
    $this->mockApiClient->shouldReceive('listContactGroups')->andReturn([]);
    $this->mockApiClient->shouldReceive('listManagedContacts')->andReturn([]);
    $this->mockApiClient->shouldReceive('createContactGroup')
        ->andReturnUsing(function ($service, $name) {
            $g = new ContactGroup;
            $g->setResourceName('contactGroups/'.md5($name));
            $g->setName($name);

            return $g;
        });
    $this->mockApiClient->shouldReceive('buildPerson')->times(3)->andReturn(new Person);
    $this->mockApiClient->shouldReceive('batchCreateContacts')->once()->andReturn($persons);
    $this->mockApiClient->shouldReceive('batchDeleteContacts')->never();
    $this->mockApiClient->shouldReceive('deleteContactGroup')->never();

    $stats = app(GoogleContactSyncService::class)->syncForUser($this->googleEmail);

    expect($stats['created'])->toBe(3);
    $this->assertDatabaseHas('soli_google_contact_syncs', ['relatie_id' => $relatie->id, 'relatie_type_id' => $bestuur->id]);
    $this->assertDatabaseHas('soli_google_contact_syncs', ['relatie_id' => $relatie->id, 'relatie_type_id' => $dirigent->id]);
    $this->assertDatabaseHas('soli_google_contact_syncs', ['relatie_id' => $relatie->id, 'relatie_type_id' => null]);
});

test('same type attached twice with different emails yields one split contact with both emails', function () {
    $relatie = Relatie::factory()->create(['voornaam' => 'Peter', 'achternaam' => 'Jansen']);
    $bestuur = RelatieType::factory()->create(['naam' => 'bestuur']);
    $onderdeel1 = Onderdeel::factory()->create();
    $onderdeel2 = Onderdeel::factory()->create();
    $relatie->types()->attach($bestuur->id, ['van' => now()->subYear()->toDateString(), 'email' => 'voorzitter@soli.nl', 'onderdeel_id' => $onderdeel1->id]);
    $relatie->types()->attach($bestuur->id, ['van' => now()->subYear()->toDateString(), 'email' => 'secretaris@soli.nl', 'onderdeel_id' => $onderdeel2->id]);

    $mainPerson = new Person;
    $mainPerson->setResourceName('people/c-main');
    $splitPerson = new Person;
    $splitPerson->setResourceName('people/c-split');

    $this->mockApiClient->shouldReceive('forUser')->andReturn($this->mockService);
    $this->mockApiClient->shouldReceive('getWorkspaceUsers')->andReturn([$this->googleEmail]);
    $this->mockApiClient->shouldReceive('listContactGroups')->andReturn([]);
    $this->mockApiClient->shouldReceive('listManagedContacts')->andReturn([]);
    $this->mockApiClient->shouldReceive('createContactGroup')
        ->andReturnUsing(function ($service, $name) {
            $g = new ContactGroup;
            $g->setResourceName('contactGroups/'.md5($name));

            return $g;
        });
    $this->mockApiClient->shouldReceive('buildPerson')
        ->withArgs(fn ($r, $g, $suffix = null, $emails = null) => $suffix === null && $emails === null)
        ->once()
        ->andReturn(new Person);
    $this->mockApiClient->shouldReceive('buildPerson')
        ->withArgs(fn ($r, $g, $suffix = null, $emails = null) => $suffix === 'Bestuur'
            && $emails === ['secretaris@soli.nl', 'voorzitter@soli.nl'])
        ->once()
        ->andReturn(new Person);
    $this->mockApiClient->shouldReceive('batchCreateContacts')->once()->andReturn([$mainPerson, $splitPerson]);
    $this->mockApiClient->shouldReceive('batchDeleteContacts')->never();
    $this->mockApiClient->shouldReceive('deleteContactGroup')->never();

    $stats = app(GoogleContactSyncService::class)->syncForUser($this->googleEmail);

    expect($stats['created'])->toBe(2);
    expect(GoogleContactSync::where('relatie_id', $relatie->id)->whereNotNull('relatie_type_id')->count())->toBe(1);
});

test('an expired type assignment with an email does not create a split contact', function () {
    $relatie = Relatie::factory()->create(['voornaam' => 'Peter', 'achternaam' => 'Jansen']);
    $bestuur = RelatieType::factory()->create(['naam' => 'bestuur']);
    $relatie->types()->attach($bestuur->id, [
        'van' => now()->subYears(2)->toDateString(),
        'tot' => now()->subYear()->toDateString(),
        'email' => 'voorzitter@soli.nl',
    ]);

    $mainPerson = new Person;
    $mainPerson->setResourceName('people/c-main');

    $this->mockApiClient->shouldReceive('forUser')->andReturn($this->mockService);
    $this->mockApiClient->shouldReceive('getWorkspaceUsers')->andReturn([$this->googleEmail]);
    $this->mockApiClient->shouldReceive('listContactGroups')->andReturn([]);
    $this->mockApiClient->shouldReceive('listManagedContacts')->andReturn([]);
    $this->mockApiClient->shouldReceive('createContactGroup')
        ->andReturnUsing(function ($service, $name) {
            $g = new ContactGroup;
            $g->setResourceName('contactGroups/'.md5($name));

            return $g;
        });
    $this->mockApiClient->shouldReceive('buildPerson')->once()->andReturn(new Person);
    $this->mockApiClient->shouldReceive('batchCreateContacts')->once()->andReturn([$mainPerson]);
    $this->mockApiClient->shouldReceive('batchDeleteContacts')->never();
    $this->mockApiClient->shouldReceive('deleteContactGroup')->never();

    $stats = app(GoogleContactSyncService::class)->syncForUser($this->googleEmail);

    expect($stats['created'])->toBe(1);
    $this->assertDatabaseMissing('soli_google_contact_syncs', ['relatie_type_id' => $bestuur->id]);
});

test('syncRelatieForUser creates a split contact for a type with a functional email', function () {
    $relatie = Relatie::factory()->create(['voornaam' => 'Peter', 'achternaam' => 'Jansen']);
    $relatie->emails()->create(['email' => 'peter@example.com']);
    $bestuur = RelatieType::factory()->create(['naam' => 'bestuur']);
    $relatie->types()->attach($bestuur->id, ['van' => now()->subYear()->toDateString(), 'email' => 'voorzitter@soli.nl']);
    $relatie->load(['emails', 'onderdelen', 'types']);

    $mainPerson = new Person;
    $mainPerson->setResourceName('people/c-main');
    $splitPerson = new Person;
    $splitPerson->setResourceName('people/c-split');

    $this->mockApiClient->shouldReceive('forUser')->andReturn($this->mockService);
    $this->mockApiClient->shouldReceive('listContactGroups')->andReturn([]);
    $this->mockApiClient->shouldReceive('createContactGroup')
        ->andReturnUsing(function ($service, $name) {
            $g = new ContactGroup;
            $g->setResourceName('contactGroups/'.md5($name));

            return $g;
        });
    $this->mockApiClient->shouldReceive('buildPerson')->twice()->andReturn(new Person);
    $this->mockApiClient->shouldReceive('createContact')->twice()->andReturn($mainPerson, $splitPerson);
    $this->mockApiClient->shouldReceive('deleteContact')->never();

    $stats = app(GoogleContactSyncService::class)->syncRelatieForUser($relatie, $this->googleEmail);

    expect($stats['created'])->toBe(2);
    $this->assertDatabaseHas('soli_google_contact_syncs', ['relatie_id' => $relatie->id, 'relatie_type_id' => null]);
    $this->assertDatabaseHas('soli_google_contact_syncs', ['relatie_id' => $relatie->id, 'relatie_type_id' => $bestuur->id]);
});

test('computeDataHash changes when a type functional email changes', function () {
    $relatie = Relatie::factory()->create();
    $type = RelatieType::factory()->create(['naam' => 'bestuur']);
    $relatie->types()->attach($type->id, ['van' => now()->subYear()->toDateString(), 'email' => 'a@soli.nl']);
    $relatie->load(['emails', 'onderdelen', 'types']);

    $service = app(GoogleContactSyncService::class);
    $hash1 = $service->computeDataHash($relatie);

    $relatie->types()->updateExistingPivot($type->id, ['email' => 'b@soli.nl']);
    $relatie->load('types');
    $hash2 = $service->computeDataHash($relatie);

    expect($hash1)->not->toBe($hash2);
});

// --- JobStatus model ---

test('JobStatus markCompletedWithErrors stores error and sets status', function () {
    $job = JobStatus::markRunning('test-job', 'Test Job');

    expect($job->status)->toBe('running');
    expect($job->last_error)->toBeNull();

    $job->markCompletedWithErrors('Something went wrong', ['processed' => 10, 'failed' => 2]);

    $job->refresh();
    expect($job->status)->toBe('completed_with_errors');
    expect($job->last_error)->toBe('Something went wrong');
    expect($job->last_completed_at)->not->toBeNull();
    expect($job->metadata)->toEqual(['processed' => 10, 'failed' => 2]);
});

test('JobStatus markCompleted clears previous errors', function () {
    $job = JobStatus::markRunning('test-job', 'Test Job');
    $job->markCompletedWithErrors('Previous error');

    $job->refresh();
    expect($job->last_error)->toBe('Previous error');

    // Simulate a new run that succeeds
    $job = JobStatus::markRunning('test-job', 'Test Job');
    $job->markCompleted(['processed' => 10]);

    $job->refresh();
    expect($job->status)->toBe('completed');
    expect($job->last_error)->toBeNull();
});
