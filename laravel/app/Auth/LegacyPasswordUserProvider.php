<?php

namespace App\Auth;

use App\Support\LegacyPassword;
use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\UserProvider;

class LegacyPasswordUserProvider extends EloquentUserProvider implements UserProvider
{
    public function validateCredentials(Authenticatable $user, array $credentials): bool
    {
        $plain = $credentials["password"] ?? null;
        $hashed = $user->getAuthPassword();

        if ($plain === null || $hashed === null || $hashed === "") {
            return false;
        }

        $result = LegacyPassword::verify((string) $plain, (string) $hashed);

        if ($result["verified"] && $result["needsRehash"]) {
            $user->forceFill([
                $user->getAuthPasswordName() => LegacyPassword::hash((string) $plain),
            ])->save();
        }

        return $result["verified"];
    }
}
