<?php

namespace App\Services;

use App\Enums\ProjectStatus;
use App\Enums\SaleStatus;
use App\Mail\ImportantAlertMail;
use App\Models\Product;
use App\Models\Project;
use App\Models\Sale;
use App\Models\Setting;
use App\Models\SmsLog;
use App\Models\User;
use App\Notifications\InAppAlert;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;

class NotificationService
{
    public function __construct(private readonly SmsService $sms) {}

    public function criticalLowStock(Product $product, bool $forceSms = false): void
    {
        $title = 'Critical low stock';
        $body = "{$product->name} ({$product->sku}) is at {$product->formatQuantity()}, below the minimum of {$product->min_stock_level}.";

        $this->notifyStaffInApp($title, $body, 'critical_low_stock', route('store.inventory.index', ['low_stock' => 1]));

        $recentlyNotified = $product->last_low_stock_notified_at
            && $product->last_low_stock_notified_at->gt(now()->subDay());

        if ($forceSms || ! $recentlyNotified) {
            $this->smsStaff($body, 'critical_low_stock', $product);
            $this->emailAdmins($title, $body);
            $product->forceFill(['last_low_stock_notified_at' => now()])->saveQuietly();
        }
    }

    public function materialRequestSubmitted(Model $request, string $summary, ?string $url = null): void
    {
        $this->notifyStaffInApp('Material request submitted', $summary, 'material_request_submitted', $url);
    }

    public function materialRequestApproved(Model $request, string $summary, ?string $phone = null): void
    {
        $this->notifyStaffInApp('Material request approved', $summary, 'material_request_approved');
        $this->smsOptional($phone, $summary, 'material_request_approved', $request);
    }

    public function materialIssued(Model $issue, string $summary, ?string $phone = null): void
    {
        $this->notifyStaffInApp('Material issued', $summary, 'material_issued');
        $this->smsOptional($phone, $summary, 'material_issued', $issue);
    }

    public function paymentReceived(Model $payment, string $summary, ?string $phone = null): void
    {
        $this->notifyStaffInApp('Payment received', $summary, 'payment_received');
        $this->smsOptional($phone, $summary, 'payment_received', $payment);
        $this->emailAdmins('Payment received', $summary);
    }

    public function invoiceNotification(Model $invoice, string $summary, ?string $phone = null, ?string $email = null): void
    {
        $this->notifyStaffInApp('Invoice generated', $summary, 'invoice_notification');
        $this->smsOptional($phone, $summary, 'invoice_notification', $invoice);

        if ($email) {
            Mail::to($email)->queue(new ImportantAlertMail('Invoice', $summary));
        }
    }

    public function outstandingPaymentReminder(Model $sale, string $summary, ?string $phone = null): void
    {
        $this->notifyStaffInApp('Outstanding payment', $summary, 'outstanding_payment');
        $this->smsOptional($phone, $summary, 'outstanding_payment', $sale);
    }

    public function projectBudgetAlert(Model $project, string $summary): void
    {
        $this->notifyStaffInApp('Project budget alert', $summary, 'project_budget', route('construction.projects.dashboard', $project));
        $this->smsStaff($summary, 'project_budget', $project);
        $this->emailAdmins('Project budget alert', $summary);

        if ($project instanceof Project && $project->siteManager?->is_active) {
            $project->siteManager->notify(new InAppAlert(
                'Project budget alert',
                $summary,
                'project_budget',
                route('construction.projects.dashboard', $project),
            ));
        }
    }

    public function maybeNotifyBudgetAlert(Project $project): void
    {
        if ((float) $project->budget <= 0 || $project->budgetUsedPercent() < 80) {
            return;
        }

        if ($this->recentlyAlerted($project, 'project_budget')) {
            return;
        }

        $summary = "{$project->name} ({$project->project_code}) has used "
            .number_format($project->budgetUsedPercent(), 1)
            .'% of its Rs. '.number_format((float) $project->budget, 2).' budget.';

        $this->projectBudgetAlert($project, $summary);
    }

    public function projectDeadlineAlert(Model $project, string $summary): void
    {
        $this->notifyStaffInApp('Project deadline', $summary, 'project_deadline');
        $this->smsStaff($summary, 'project_deadline', $project);
    }

    public function checkOverduePayments(): int
    {
        if (! Schema::hasTable('sales')) {
            return 0;
        }

        $sales = Sale::query()
            ->with('customer')
            ->where('status', SaleStatus::Completed)
            ->where('balance', '>', 0)
            ->whereDate('sale_date', '<=', now()->subDays(7)->toDateString())
            ->get();

        $sent = 0;

        foreach ($sales as $sale) {
            $recentlyReminded = Schema::hasTable('sms_logs')
                && SmsLog::query()
                    ->where('event_type', 'outstanding_payment')
                    ->where('related_type', $sale::class)
                    ->where('related_id', $sale->id)
                    ->where('created_at', '>=', now()->subDays(7))
                    ->exists();

            if ($recentlyReminded) {
                continue;
            }

            $summary = "Outstanding payment on {$sale->invoice_no} for {$sale->customerName()} — Rs. "
                .number_format((float) $sale->balance, 2);

            $this->outstandingPaymentReminder($sale, $summary, $sale->customer?->phone);
            $sent++;
        }

        return $sent;
    }

    public function checkProjectDeadlines(): int
    {
        if (! Schema::hasTable('projects')) {
            return 0;
        }

        $projects = Project::query()
            ->with('siteManager')
            ->whereNotNull('expected_end_date')
            ->whereIn('status', [ProjectStatus::Planning, ProjectStatus::Active, ProjectStatus::OnHold])
            ->whereDate('expected_end_date', '<=', now()->addDays(7)->toDateString())
            ->get();

        $sent = 0;

        foreach ($projects as $project) {
            if ($this->recentlyAlerted($project, 'project_deadline')) {
                continue;
            }

            $due = $project->expected_end_date->format('d/m/Y');
            $summary = $project->expected_end_date->isPast()
                ? "{$project->name} ({$project->project_code}) expected end date {$due} has passed."
                : "{$project->name} ({$project->project_code}) is due on {$due}.";

            $this->projectDeadlineAlert($project, $summary);
            $sent++;
        }

        return $sent;
    }

    private function recentlyAlerted(Model $related, string $eventType): bool
    {
        return Schema::hasTable('sms_logs')
            && SmsLog::query()
                ->where('event_type', $eventType)
                ->where('related_type', $related::class)
                ->where('related_id', $related->id)
                ->where('created_at', '>=', now()->subDays(7))
                ->exists();
    }

    /**
     * @param  list<string>  $roles
     */
    private function notifyStaffInApp(string $title, string $body, string $eventType, ?string $url = null, array $roles = ['admin', 'store_manager']): void
    {
        User::query()
            ->role($roles)
            ->where('is_active', true)
            ->get()
            ->each(fn (User $user) => $user->notify(new InAppAlert($title, $body, $eventType, $url)));
    }

    private function smsStaff(string $message, string $eventType, ?Model $related = null): void
    {
        User::query()
            ->role(['admin', 'store_manager'])
            ->where('is_active', true)
            ->whereNotNull('phone')
            ->where('phone', '!=', '')
            ->get()
            ->each(fn (User $user) => $this->sms->queue($user->phone, $message, $eventType, $related));
    }

    private function smsOptional(?string $phone, string $message, string $eventType, ?Model $related = null): void
    {
        if (filled($phone)) {
            $this->sms->queue($phone, $message, $eventType, $related);
        }
    }

    private function emailAdmins(string $title, string $body): void
    {
        $companyEmail = Setting::get('company_email');

        $recipients = User::query()
            ->role('admin')
            ->where('is_active', true)
            ->pluck('email')
            ->filter()
            ->values();

        if ($companyEmail) {
            $recipients->push($companyEmail);
        }

        $recipients->unique()->each(
            fn (string $email) => Mail::to($email)->queue(new ImportantAlertMail($title, $body))
        );
    }
}
