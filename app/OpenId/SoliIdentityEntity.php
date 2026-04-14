<?php

namespace App\OpenId;

use App\Models\RelatieInstrument;
use App\Models\User;
use App\Services\ClientRoleResolver;
use League\OAuth2\Server\Entities\Traits\EntityTrait;
use OpenIDConnect\Claims\Traits\WithClaims;
use OpenIDConnect\Interfaces\IdentityEntityInterface;

class SoliIdentityEntity implements IdentityEntityInterface
{
    use EntityTrait;
    use WithClaims;

    protected User $user;

    public function __construct(User $user)
    {
        $this->user = $user;
        $this->setIdentifier((string) $user->id);
    }

    /**
     * @param  string[]  $scopes
     * @return array<string, mixed>
     */
    public function getClaims(array $scopes = []): array
    {
        $claims = [];

        if (in_array('profile', $scopes)) {
            $relatie = $this->user->relaties()->first();

            $claims['name'] = $this->user->name;
            $claims['preferred_username'] = collect([
                $relatie?->voornaam,
                $relatie?->tussenvoegsel,
                $relatie?->achternaam,
            ])->filter()->implode(' ');
            $claims['given_name'] = $relatie?->voornaam ?? '';
            $claims['family_name'] = $relatie?->achternaam ?? '';
        }

        if (in_array('email', $scopes)) {
            $claims['email'] = $this->user->email;
            $claims['email_verified'] = $this->user->email_verified_at !== null;
        }

        if (in_array('roles', $scopes)) {
            $context = app(OauthClientContext::class);
            $clientId = $context->getClientId();

            if ($clientId) {
                $resolver = app(ClientRoleResolver::class);
                $claims['roles'] = $resolver->resolve($this->user, $clientId);
            } else {
                $claims['roles'] = $this->user->getRoleNames()->toArray();
            }
        }

        if (in_array('assignments', $scopes)) {
            $relatieIds = $this->user->relaties()->pluck('id');

            $claims['assignments'] = RelatieInstrument::whereIn('relatie_id', $relatieIds)
                ->get(['onderdeel_id', 'instrument_soort'])
                ->map(fn ($ri) => [
                    'onderdeel_id' => $ri->onderdeel_id,
                    'instrument_soort' => $ri->instrument_soort,
                ])
                ->values()
                ->toArray();
        }

        return $claims;
    }
}
