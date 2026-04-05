<?php

namespace App\Providers;

use App\OpenId\SoliIdTokenResponse;
use Illuminate\Encryption\Encrypter;
use Laravel\Passport;
use Laravel\Passport\Bridge\AccessTokenRepository;
use Laravel\Passport\Bridge\ClientRepository;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Key\InMemory;
use League\OAuth2\Server\AuthorizationServer;
use League\OAuth2\Server\ResponseTypes\ResponseTypeInterface;
use OpenIDConnect\ClaimExtractor;
use OpenIDConnect\Claims\ClaimSet;
use OpenIDConnect\Laravel\LaravelCurrentRequestService;
use OpenIDConnect\Laravel\PassportServiceProvider;

class SoliPassportServiceProvider extends PassportServiceProvider
{
    public function makeAuthorizationServer(?ResponseTypeInterface $responseType = null): AuthorizationServer
    {
        $cryptKey = $this->makeCryptKey('private');
        $encryptionKey = $this->getEncryptionKey(app(Encrypter::class)->getKey());

        $customClaimSets = config('openid.custom_claim_sets');

        $claimSets = array_map(function ($claimSet, $name) {
            return new ClaimSet($name, $claimSet);
        }, $customClaimSets, array_keys($customClaimSets));

        $responseType = new SoliIdTokenResponse(
            app(config('openid.repositories.identity')),
            new ClaimExtractor(...$claimSets),
            Configuration::forSymmetricSigner(
                app(config('openid.signer')),
                InMemory::plainText($cryptKey->getKeyContents(), $cryptKey->getPassPhrase() ?? '')
            ),
            config('openid.token_headers'),
            config('openid.use_microseconds'),
            app(LaravelCurrentRequestService::class),
            $encryptionKey,
            config('openid.issuedBy', 'laravel')
        );

        return new AuthorizationServer(
            app(ClientRepository::class),
            app(AccessTokenRepository::class),
            app(Passport\Bridge\ScopeRepository::class),
            $cryptKey,
            $encryptionKey,
            $responseType,
        );
    }
}
