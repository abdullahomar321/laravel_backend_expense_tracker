<?php

namespace App\Services;

use App\Models\User;

class PremiumAccessService
{
    /**
     * Read-only check against a fresh DB record.
     * Does NOT write or mutate any state.
     */
    public function isPremium(User $user): bool
    {
        return (bool) $user->fresh()?->is_premium;
    }
}
