<?php

namespace App\Policies;

use App\Models\{TripDocument, User};

class TripDocumentPolicy
{
    public function upload(User $user): bool
    {
        return $user->hasPermission('document.upload');
    }

    public function delete(User $user, TripDocument $document): bool
    {
        if (!$user->hasPermission('document.delete')) return false;
        if ($user->isPlatformUser()) return true;
        return $document->customer_id === $user->customer_id;
    }
}
