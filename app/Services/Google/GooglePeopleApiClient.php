<?php

namespace App\Services\Google;

use App\Models\Relatie;
use Google\Client;
use Google\Service\Directory;
use Google\Service\PeopleService;
use Google\Service\PeopleService\ClientData;
use Google\Service\PeopleService\ContactGroup;
use Google\Service\PeopleService\EmailAddress;
use Google\Service\PeopleService\ModifyContactGroupMembersRequest;
use Google\Service\PeopleService\Name;
use Google\Service\PeopleService\Person;

class GooglePeopleApiClient
{
    private Client $client;

    public function __construct()
    {
        $this->client = new Client;
        $this->client->setApplicationName('Soli Administration');

        $keyPath = config('services.google.service_account_key');
        if ($keyPath) {
            $this->client->setAuthConfig($keyPath);
        }
    }

    public function forUser(string $email): PeopleService
    {
        $client = clone $this->client;
        $client->setSubject($email);
        $client->setScopes([PeopleService::CONTACTS]);

        return new PeopleService($client);
    }

    public function getWorkspaceUsers(): array
    {
        $adminEmail = config('services.google.admin_email');
        $domain = config('services.google.workspace_domain');

        $client = clone $this->client;
        $client->setSubject($adminEmail);
        $client->setScopes([Directory::ADMIN_DIRECTORY_USER_READONLY]);

        $directory = new Directory($client);

        $users = [];
        $pageToken = null;

        do {
            $params = [
                'domain' => $domain,
                'maxResults' => 500,
                'orderBy' => 'email',
            ];

            if ($pageToken) {
                $params['pageToken'] = $pageToken;
            }

            $result = $directory->users->listUsers($params);

            foreach ($result->getUsers() ?? [] as $user) {
                if (! $user->getSuspended()) {
                    $users[] = $user->getPrimaryEmail();
                }
            }

            $pageToken = $result->getNextPageToken();
        } while ($pageToken);

        return $users;
    }

    public function buildPerson(Relatie $relatie, array $groupResourceNames = []): Person
    {
        $person = new Person;

        $name = new Name;
        $name->setGivenName($relatie->voornaam);
        $name->setFamilyName(collect([$relatie->tussenvoegsel, $relatie->achternaam])->filter()->implode(' '));
        $person->setNames([$name]);

        $emailAddresses = $relatie->emails->map(function ($email) {
            $addr = new EmailAddress;
            $addr->setValue($email->email);

            return $addr;
        })->all();

        if (! empty($emailAddresses)) {
            $person->setEmailAddresses($emailAddresses);
        }

        $person->setClientData([
            $this->makeClientData('managed_by', 'soli_admin'),
            $this->makeClientData('relatie_id', (string) $relatie->id),
        ]);

        if (! empty($groupResourceNames)) {
            $memberships = array_map(function ($resourceName) {
                $membership = new PeopleService\Membership;
                $group = new PeopleService\ContactGroupMembership;
                $group->setContactGroupResourceName($resourceName);
                $membership->setContactGroupMembership($group);

                return $membership;
            }, $groupResourceNames);

            $person->setMemberships($memberships);
        }

        return $person;
    }

    public function createContact(PeopleService $service, Person $person): Person
    {
        return $service->people->createContact($person, [
            'personFields' => 'names,emailAddresses,clientData,memberships',
        ]);
    }

    public function updateContact(PeopleService $service, string $resourceName, Person $person, string $etag): Person
    {
        $person->setEtag($etag);

        return $service->people->updateContact($resourceName, $person, [
            'updatePersonFields' => 'names,emailAddresses,clientData,memberships',
            'personFields' => 'names,emailAddresses,clientData,memberships',
        ]);
    }

    public function deleteContact(PeopleService $service, string $resourceName): void
    {
        $service->people->deleteContact($resourceName);
    }

    public function getContact(PeopleService $service, string $resourceName): ?Person
    {
        try {
            return $service->people->get($resourceName, [
                'personFields' => 'names,emailAddresses,clientData,memberships,metadata',
            ]);
        } catch (\Google\Service\Exception $e) {
            if ($e->getCode() === 404) {
                return null;
            }
            throw $e;
        }
    }

    public function listManagedContacts(PeopleService $service): array
    {
        $contacts = [];
        $pageToken = null;

        do {
            $params = [
                'personFields' => 'names,emailAddresses,clientData,memberships,metadata',
                'pageSize' => 1000,
                'sources' => ['READ_SOURCE_TYPE_CONTACT'],
            ];

            if ($pageToken) {
                $params['pageToken'] = $pageToken;
            }

            $result = $service->people_connections->listPeopleConnections('people/me', $params);

            foreach ($result->getConnections() ?? [] as $person) {
                if ($this->isManagedBySoli($person)) {
                    $contacts[] = $person;
                }
            }

            $pageToken = $result->getNextPageToken();
        } while ($pageToken);

        return $contacts;
    }

    public function createContactGroup(PeopleService $service, string $name): ContactGroup
    {
        $group = new ContactGroup;
        $group->setName($name);

        return $service->contactGroups->create(
            new PeopleService\CreateContactGroupRequest(['contactGroup' => $group])
        );
    }

    public function listContactGroups(PeopleService $service): array
    {
        $groups = [];
        $pageToken = null;

        do {
            $params = ['pageSize' => 1000, 'groupFields' => 'name,groupType,memberCount'];

            if ($pageToken) {
                $params['pageToken'] = $pageToken;
            }

            $result = $service->contactGroups->listContactGroups($params);

            foreach ($result->getContactGroups() ?? [] as $group) {
                if ($group->getGroupType() === 'USER_CONTACT_GROUP') {
                    $groups[] = $group;
                }
            }

            $pageToken = $result->getNextPageToken();
        } while ($pageToken);

        return $groups;
    }

    public function deleteContactGroup(PeopleService $service, string $resourceName): void
    {
        $service->contactGroups->delete($resourceName, ['deleteContacts' => false]);
    }

    public function modifyContactGroupMembers(
        PeopleService $service,
        string $groupResourceName,
        array $addResourceNames = [],
        array $removeResourceNames = [],
    ): void {
        $request = new ModifyContactGroupMembersRequest;

        if (! empty($addResourceNames)) {
            $request->setResourceNamesToAdd($addResourceNames);
        }

        if (! empty($removeResourceNames)) {
            $request->setResourceNamesToRemove($removeResourceNames);
        }

        if (empty($addResourceNames) && empty($removeResourceNames)) {
            return;
        }

        $service->contactGroups_members->modify($groupResourceName, $request);
    }

    private function isManagedBySoli(Person $person): bool
    {
        foreach ($person->getClientData() ?? [] as $data) {
            if ($data->getKey() === 'managed_by' && $data->getValue() === 'soli_admin') {
                return true;
            }
        }

        return false;
    }

    private function makeClientData(string $key, string $value): ClientData
    {
        $data = new ClientData;
        $data->setKey($key);
        $data->setValue($value);

        return $data;
    }

    public function getRelatieIdFromPerson(Person $person): ?int
    {
        foreach ($person->getClientData() ?? [] as $data) {
            if ($data->getKey() === 'relatie_id') {
                return (int) $data->getValue();
            }
        }

        return null;
    }

    public function getEtag(Person $person): string
    {
        return $person->getEtag();
    }
}
