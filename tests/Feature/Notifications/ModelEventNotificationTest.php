<?php

namespace Tests\Feature\Notifications;

use App\Models\NotificationSetting;
use App\Models\PurchaseRequest;
use App\Models\RegisteredOrder;
use App\Models\Status;
use App\Models\User;
use App\Notifications\ModelEventEmail;
use App\Notifications\ModelEventNotification;
use DateTime;
use Tests\TestCase;

class ModelEventNotificationTest extends TestCase
{
    private function makeSetting(array $attributes = []): NotificationSetting
    {
        return new NotificationSetting(array_merge([
            'settings' => [
                'is_active' => true,
                'actions' => ['create'],
                'tables' => ['purchase_requests'],
                'users' => [1],
            ],
            'notification_type' => 'in_app',
        ], $attributes));
    }

    private function makeRequest(array $attributes = []): PurchaseRequest
    {
        return (new PurchaseRequest(array_merge(['pr_number' => 'PR-2601-001'], $attributes)))
            ->forceFill(['id' => 5]);
    }

    public function test_identifier_uses_the_scannable_identifier_and_appends_the_contract_no(): void
    {
        $order = new RegisteredOrder(['ro_number' => 'RO-2601-001', 'contract_no' => 'CT-778899']);
        $database = (new ModelEventNotification($order, 'create', [], $this->makeSetting()))->toDatabase(new User);

        $this->assertSame('Registered Order RO-2601-001 (CT-778899) has been created', $database['body'], 'The identifier shows the SCANNABLE_IDENTIFIER value plus the contract number in parentheses.');

        $database = (new ModelEventNotification($this->makeRequest(), 'create', [], $this->makeSetting()))->toDatabase(new User);

        $this->assertSame('Purchase Request PR-2601-001 has been created', $database['body'], 'Without a contract number the identifier is just the SCANNABLE_IDENTIFIER value.');
    }

    public function test_identifier_falls_back_to_the_record_key_when_the_scannable_identifier_is_missing(): void
    {
        $request = (new PurchaseRequest)->forceFill(['id' => 5, 'pr_number' => null]);
        $database = (new ModelEventNotification($request, 'create', [], $this->makeSetting()))->toDatabase(new User);

        $this->assertSame('Purchase Request #5 has been created', $database['body'], 'An empty SCANNABLE_IDENTIFIER value falls back to the record key.');

        $status = (new Status)->forceFill(['id' => 9]);
        $database = (new ModelEventNotification($status, 'create', [], $this->makeSetting()))->toDatabase(new User);

        $this->assertSame('Status #9 has been created', $database['body'], 'A model without a SCANNABLE_IDENTIFIER constant goes straight to the record key.');
    }

    public function test_update_body_lists_the_headline_cased_changed_columns(): void
    {
        $changes = [
            'urgency_level' => ['old' => 'low', 'new' => 'high'],
            'required_by_date' => ['old' => '2026-01-01', 'new' => '2026-02-01'],
        ];

        $notification = new ModelEventNotification($this->makeRequest(), 'update', $changes, $this->makeSetting());
        $database = $notification->toDatabase(new User);

        $this->assertSame('Purchase Request PR-2601-001 updated: Urgency Level, Required By Date', $database['body'], 'The update body joins the headline-cased column names of every change.');

        $notification = new ModelEventNotification($this->makeRequest(), 'update', [], $this->makeSetting());
        $database = $notification->toDatabase(new User);

        $this->assertSame('Purchase Request PR-2601-001 has changed', $database['body'], 'An update with no changes falls through to the generic body.');
    }

    public function test_each_action_arms_its_own_body_title_icon_and_color(): void
    {
        $expectations = [
            'create' => ['has been created', 'PurchaseRequest Created', 'heroicon-o-plus-circle', 'success'],
            'update' => ['has changed', 'PurchaseRequest Updated', 'heroicon-o-pencil-square', 'info'],
            'delete' => ['has been deleted', 'PurchaseRequest Deleted', 'heroicon-o-trash', 'danger'],
            'restore' => ['has changed', 'PurchaseRequest Changed', 'heroicon-o-bell', 'gray'],
        ];

        foreach ($expectations as $action => [$bodyFragment, $title, $icon, $color]) {
            $notification = new ModelEventNotification($this->makeRequest(), $action, [], $this->makeSetting());
            $database = $notification->toDatabase(new User);

            $this->assertSame($title, $database['title'], "The {$action} action should map to the '{$title}' title.");
            $this->assertStringContainsString($bodyFragment, $database['body'], "The {$action} action should say '{$bodyFragment}'.");
            $this->assertSame($icon, $database['icon'], "The {$action} action should map to the '{$icon}' icon.");
            $this->assertSame($color, $database['iconColor'], "The {$action} action should map to the '{$color}' color.");
        }
    }

    public function test_to_database_returns_the_filament_action_contract(): void
    {
        $notification = new ModelEventNotification($this->makeRequest(), 'create', [], $this->makeSetting());
        $database = $notification->toDatabase(new User);

        $this->assertSame([
            'name' => 'view',
            'label' => 'View Record',
            'url' => '/dashboard/purchase-requests/5/edit',
            'shouldMarkAsRead' => true,
        ], $database['actions'][0], 'The database notification carries a single edit action for the record.');
        $this->assertSame('filament', $database['format']);
        $this->assertSame('persistent', $database['duration']);
    }

    public function test_via_returns_the_channel_of_each_concrete_class(): void
    {
        $notification = new ModelEventNotification($this->makeRequest(), 'create', [], $this->makeSetting());
        $this->assertSame(['database'], $notification->via(new User), 'The in-app notification travels only over the database channel.');

        $email = new ModelEventEmail($this->makeRequest(), 'create', [], $this->makeSetting());
        $this->assertSame(['mail'], $email->via(new User), 'The email notification travels only over the mail channel.');
    }

    public function test_to_mail_renders_the_create_contract(): void
    {
        $email = new ModelEventEmail($this->makeRequest(), 'create', [], $this->makeSetting());
        $mail = $email->toMail(new User(['name' => 'Arash']));

        $this->assertSame('Purchase Request Created: PR-2601-001', $mail->subject, 'The subject names the model and its identifier.');
        $this->assertSame('Hello Arash,', $mail->greeting, 'The greeting addresses the notifiable by name.');
        $this->assertContains('A new Purchase Request **PR-2601-001** has been created.🟢', $mail->introLines);
        $this->assertSame('View Record', $mail->actionText);
        $this->assertSame(config('app.url').'/dashboard/purchase-requests/5/edit', $mail->actionUrl, 'The action button links to the record edit page on the app URL.');
        $this->assertContains('This notification was sent based on your notification settings.', $mail->outroLines);
        $this->assertNotContains('**Additional Notes:**', $mail->introLines, 'No notes block is rendered when the setting has no notes.');
    }

    public function test_to_mail_renders_update_changes_with_formatted_values(): void
    {
        $changes = [
            'urgency_level' => ['old' => null, 'new' => 'high'],
            'is_urgent' => ['old' => false, 'new' => true],
            'approval_date' => ['old' => new DateTime('2026-01-02 03:04:00'), 'new' => new DateTime('2026-02-03 04:05:00')],
            'notes' => ['old' => 'draft', 'new' => 'final'],
        ];

        $email = new ModelEventEmail($this->makeRequest(), 'update', $changes, $this->makeSetting());
        $mail = $email->toMail(new User(['name' => 'Arash']));

        $this->assertSame('Purchase Request Updated: PR-2601-001', $mail->subject);
        $this->assertContains('The Purchase Request **PR-2601-001** has been updated.🟡', $mail->introLines);
        $this->assertContains('**Changes:**', $mail->introLines);
        $this->assertContains('• **Urgency Level:** (empty) → high', $mail->introLines, 'A null change value renders as (empty).');
        $this->assertContains('• **Is Urgent:** No → Yes', $mail->introLines, 'A boolean change value renders as No/Yes.');
        $this->assertContains('• **Approval Date:** 2026-01-02 03:04 → 2026-02-03 04:05', $mail->introLines, 'A DateTime change value renders as Y-m-d H:i.');
        $this->assertContains('• **Notes:** draft → final', $mail->introLines);
    }

    public function test_to_mail_renders_the_setting_notes_when_present(): void
    {
        $setting = $this->makeSetting(['notes' => 'Watch this one closely']);
        $email = new ModelEventEmail($this->makeRequest(), 'create', [], $setting);
        $mail = $email->toMail(new User(['name' => 'Arash']));

        $this->assertContains('**Additional Notes:**', $mail->introLines);
        $this->assertContains('Watch this one closely', $mail->introLines);
    }
}