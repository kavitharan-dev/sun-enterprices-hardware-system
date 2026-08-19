<?php

namespace App\Enums;

enum DailyAccountCategory: string
{
    case HardwareSale = 'hardware_sale';
    case StockPurchase = 'stock_purchase';
    case WorkerSalary = 'worker_salary';
    case WorkerAdvance = 'worker_advance';
    case OwnerPayment = 'owner_payment';
    case Labour = 'labour';
    case Transport = 'transport';
    case Equipment = 'equipment';
    case Electricity = 'electricity';
    case Water = 'water';
    case Material = 'material';
    case OtherIncome = 'other_income';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::HardwareSale => 'Hardware sales',
            self::StockPurchase => 'Product / stock purchase',
            self::WorkerSalary => 'Worker salary',
            self::WorkerAdvance => 'Worker advance',
            self::OwnerPayment => 'Site owner payment',
            self::Labour => 'Labour',
            self::Transport => 'Transport',
            self::Equipment => 'Equipment',
            self::Electricity => 'Electricity',
            self::Water => 'Water',
            self::Material => 'Material',
            self::OtherIncome => 'Other income',
            self::Other => 'Other',
        };
    }

    public static function fromExpense(ExpenseCategory $category): self
    {
        return match ($category) {
            ExpenseCategory::Labour => self::Labour,
            ExpenseCategory::Transport => self::Transport,
            ExpenseCategory::Equipment => self::Equipment,
            ExpenseCategory::Electricity => self::Electricity,
            ExpenseCategory::Water => self::Water,
            ExpenseCategory::Material => self::Material,
            ExpenseCategory::Other => self::Other,
        };
    }
}
