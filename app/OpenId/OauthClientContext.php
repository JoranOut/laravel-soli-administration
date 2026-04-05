<?php

namespace App\OpenId;

class OauthClientContext
{
    protected ?string $clientId = null;

    public function setClientId(string $id): void
    {
        $this->clientId = $id;
    }

    public function getClientId(): ?string
    {
        return $this->clientId;
    }
}
