<?php

namespace Tests\Feature;

use App\Mail\NutritionTaskAssignedMail;
use App\Models\NutritionTask;
use App\Support\OutboundMail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class OutboundMailTest extends TestCase
{
    use RefreshDatabase;

    public function test_unconfigured_mailer_does_not_claim_delivery(): void
    {
        Mail::fake();
        config(['mail.default' => 'log']);
        $task = new NutritionTask(['task_number' => 'NT-TEST-001']);
        $this->assertFalse(app(OutboundMail::class)->send('member@example.com', new NutritionTaskAssignedMail($task)));
        Mail::assertNothingSent();
    }
}
