<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\OpenId\SoliIdentityEntity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OidcUserinfoController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();
        $token = $user->token();

        $scopes = collect(['openid', 'profile', 'email', 'roles'])
            ->filter(fn (string $scope) => $token->can($scope))
            ->values()
            ->all();

        $entity = new SoliIdentityEntity($user);
        $claims = $entity->getClaims($scopes);

        $claims['sub'] = (string) $user->id;

        return response()->json($claims);
    }
}
