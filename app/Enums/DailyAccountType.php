<?php

namespace App\Enums;

enum DailyAccountType: string
{
    case Sale = 'sale';
    case Purchase = 'purchase';
    case WorkerAdvance = 'worker_advance';
    case WorkerSettlement = 'worker_settlement';
    case OwnerPayment = 'owner_payment';
    case ProjectExpense = 'project_expense';
    case OtherIncome = 'other_income';
    case OtherExpense = 'other_expense';

    /** Plain language for cashiers picking a type. */
    public function label(): string
    {
        return match ($this) {
            self::Sale => 'Customer paid for a sale',
            self::Purchase => 'Paid supplier for stock',
            self::WorkerAdvance => 'Gave worker an advance',
            self::WorkerSettlement => 'Paid worker salary',
            self::OwnerPayment => 'Site owner paid us',
            self::ProjectExpense => 'Paid a site expense',
            self::OtherIncome => 'Other money received',
            self::OtherExpense => 'Other money paid out',
        };
    }

    /** Short label for tables and filters. */
    public function shortLabel(): string
    {
        return match ($this) {
            self::Sale => 'Sale',
            self::Purchase => 'Purchase',
            self::WorkerAdvance => 'Worker advance',
            self::WorkerSettlement => 'Worker salary',
            self::OwnerPayment => 'Owner payment',
            self::ProjectExpense => 'Site expense',
            self::OtherIncome => 'Other income',
            self::OtherExpense => 'Other expense',
        };
    }

    public function choiceHint(): string
    {
        return match ($this) {
            self::Sale => 'Choose which sale was paid',
            self::Purchase => 'Choose which stock bill to pay',
            self::WorkerAdvance => 'Money given before payday',
            self::WorkerSettlement => 'Saturday wage / settlement',
            self::OwnerPayment => 'Money received for a project',
            self::ProjectExpense => 'Transport, tools, site costs…',
            self::OtherIncome => 'Bank interest, scrap, etc.',
            self::OtherExpense => 'Tea, petty cash, etc.',
        };
    }

    public function directionLabel(): string
    {
        return $this->isIncome() ? 'Money in' : 'Money out';
    }

    public function isIncome(): bool
    {
        return in_array($this, [self::Sale, self::OwnerPayment, self::OtherIncome], true);
    }

    /** @return list<self> */
    public static function incomeCases(): array
    {
        return array_values(array_filter(self::cases(), fn (self $t) => $t->isIncome()));
    }

    /** @return list<self> */
    public static function expenseCases(): array
    {
        return array_values(array_filter(self::cases(), fn (self $t) => ! $t->isIncome()));
    }
}
