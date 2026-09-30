<?php

namespace App\Policies;

use App\Models\Invoice;
use App\Models\User;

class InvoicePolicy
{
    public function view(User $user, Invoice $invoice): bool
    {
        return $invoice->user_id === $user->id;
    }

    /** Only drafts can change; anything a customer has seen is immutable. */
    public function update(User $user, Invoice $invoice): bool
    {
        return $invoice->user_id === $user->id && $invoice->status->isEditable();
    }

    public function delete(User $user, Invoice $invoice): bool
    {
        return $this->update($user, $invoice);
    }

    public function transition(User $user, Invoice $invoice): bool
    {
        return $invoice->user_id === $user->id;
    }
}
