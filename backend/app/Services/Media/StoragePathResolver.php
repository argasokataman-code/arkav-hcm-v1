<?php

namespace App\Services\Media;

final class StoragePathResolver
{
    public static function policyAttachments(int $policyId): string
    {
        return 'hcm/policies/'.$policyId;
    }

    public static function avatar(int $userId): string
    {
        return 'avatars/'.$userId;
    }
}
