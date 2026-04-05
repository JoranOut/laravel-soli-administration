<?php

namespace App\OpenId;

use League\OAuth2\Server\Entities\AccessTokenEntityInterface;
use OpenIDConnect\IdTokenResponse;

class SoliIdTokenResponse extends IdTokenResponse
{
    protected function getExtraParams(AccessTokenEntityInterface $accessToken): array
    {
        $clientId = $accessToken->getClient()->getIdentifier();

        $context = app(OauthClientContext::class);
        $context->setClientId($clientId);

        return parent::getExtraParams($accessToken);
    }
}
