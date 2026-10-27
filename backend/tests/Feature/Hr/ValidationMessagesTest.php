<?php

namespace Tests\Feature\Hr;

use App\Models\Organization;
use App\Models\Position;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ValidationMessagesTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_validation_messages_are_uzbek(): void
    {
        app()->setLocale('uz');
        $organization = Organization::factory()->create();
        Sanctum::actingAs(User::factory()->withRole('hr')->create(['organization_id' => $organization->id]));

        $this->postJson('/api/employees', [
            'organization_id' => $organization->id,
            'first_name' => 'Anvar',
            'pinfl' => '123',
            'hire' => [
                'effective_date' => now()->addDay()->toDateString(),
                'order_number' => '1-K',
                'order_date' => now()->toDateString(),
                'position_id' => Position::factory()->create()->id,
            ],
        ])->assertUnprocessable()->assertJsonValidationErrors([
            'last_name' => "Familiya maydonini to'ldirish shart.",
            'pinfl' => "JShShIR maydoni noto'g'ri formatda.",
            'hire.effective_date' => "Qabul sanasi maydoni bugungi sana yoki undan oldin bo'lishi kerak.",
        ]);
    }
}
