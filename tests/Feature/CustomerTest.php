<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerTest extends TestCase
{
    use RefreshDatabase;

    public function test_crud_scoped_to_owner(): void
    {
        $me = User::factory()->create();
        $this->actingAs($me, 'sanctum');

        $id = $this->postJson('/api/customers', ['name' => 'Acme', 'email' => 'billing@acme.test', 'currency' => 'EUR'])
            ->assertCreated()->json('data.id');
        $this->patchJson("/api/customers/{$id}", ['name' => 'Acme Ltd'])->assertOk()->assertJsonPath('data.name', 'Acme Ltd');
        $this->getJson('/api/customers?q=acme')->assertJsonCount(1, 'data')->assertJsonPath('meta.total', 1);

        $theirs = Customer::factory()->create();
        $this->getJson("/api/customers/{$theirs->id}")->assertForbidden();
        $this->deleteJson("/api/customers/{$id}")->assertNoContent();
    }

    public function test_email_unique_per_owner_only(): void
    {
        Customer::factory()->create(['email' => 'shared@client.test']); // another user's customer
        $me = User::factory()->create();
        $this->actingAs($me, 'sanctum');
        $this->postJson('/api/customers', ['name' => 'A', 'email' => 'shared@client.test'])->assertCreated();
        $this->postJson('/api/customers', ['name' => 'B', 'email' => 'shared@client.test'])->assertStatus(422);
    }

    public function test_rejects_unsupported_currency(): void
    {
        $this->actingAs(User::factory()->create(), 'sanctum');
        $this->postJson('/api/customers', ['name' => 'A', 'email' => 'a@b.test', 'currency' => 'XXX'])
            ->assertStatus(422)->assertJsonValidationErrors('currency');
    }
}
