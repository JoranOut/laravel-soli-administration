<?php

namespace App\OpenId;

use App\Models\User;
use OpenIDConnect\Interfaces\IdentityEntityInterface;
use OpenIDConnect\Interfaces\IdentityRepositoryInterface;

class SoliIdentityRepository implements IdentityRepositoryInterface
{
    public function getByIdentifier(string $identifier): IdentityEntityInterface
    {
        $user = User::findOrFail($identifier);

        return new SoliIdentityEntity($user);
    }
}
