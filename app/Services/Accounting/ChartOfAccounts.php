<?php

namespace App\Services\Accounting;

use App\Models\GlAccount;
use App\Models\GlAccountMap;
use App\Models\School;
use App\Support\TenantContext;
use Illuminate\Support\Facades\DB;

/**
 * Default Gambian-school chart of accounts, copied per school on first AIS open.
 * Codes stay numeric/English; UI labels are translated via ais.account.* keys.
 */
class ChartOfAccounts
{
    /** @return list<array{code:string,name:string,type:string,subtype:?string,sort:int,map:?string}> */
    public static function defaults(): array
    {
        return [
            ['code' => '1000', 'name' => 'Cash on hand', 'type' => 'asset', 'subtype' => 'cash', 'sort' => 10, 'map' => 'cash'],
            ['code' => '1010', 'name' => 'Bank', 'type' => 'asset', 'subtype' => 'bank', 'sort' => 20, 'map' => 'bank'],
            ['code' => '1100', 'name' => 'Fees receivable', 'type' => 'asset', 'subtype' => 'receivable', 'sort' => 30, 'map' => 'fees_receivable'],
            ['code' => '2000', 'name' => 'Accounts payable', 'type' => 'liability', 'subtype' => 'payable', 'sort' => 40, 'map' => 'ap'],
            ['code' => '2100', 'name' => 'Accrued salaries', 'type' => 'liability', 'subtype' => 'payable', 'sort' => 50, 'map' => 'salaries_payable'],
            ['code' => '3000', 'name' => 'Accumulated surplus', 'type' => 'equity', 'subtype' => 'other', 'sort' => 60, 'map' => null],
            ['code' => '4000', 'name' => 'Tuition / fee income', 'type' => 'income', 'subtype' => 'fee_income', 'sort' => 70, 'map' => 'fee_income'],
            ['code' => '4100', 'name' => 'Other income', 'type' => 'income', 'subtype' => 'other', 'sort' => 80, 'map' => null],
            ['code' => '5000', 'name' => 'Salaries', 'type' => 'expense', 'subtype' => 'payroll', 'sort' => 90, 'map' => 'payroll_expense'],
            ['code' => '5100', 'name' => 'Operating expenses', 'type' => 'expense', 'subtype' => 'other', 'sort' => 100, 'map' => null],
            ['code' => '5200', 'name' => 'Utilities', 'type' => 'expense', 'subtype' => 'other', 'sort' => 110, 'map' => null],
            ['code' => '5300', 'name' => 'Procurement / supplies', 'type' => 'expense', 'subtype' => 'cogs', 'sort' => 120, 'map' => 'inventory'],
            ['code' => '5400', 'name' => 'Fee discounts & waivers', 'type' => 'expense', 'subtype' => 'other', 'sort' => 130, 'map' => 'discounts'],
        ];
    }

    /** Ensure the school has the default COA and default maps. Safe to call repeatedly. */
    public function ensureFor(?School $school = null): void
    {
        $school ??= app(TenantContext::class)->school();
        if (! $school) {
            return;
        }

        if (GlAccount::query()->where('school_id', $school->id)->exists()) {
            return;
        }

        DB::transaction(function () use ($school) {
            $ids = [];
            foreach (self::defaults() as $row) {
                $account = GlAccount::query()->create([
                    'school_id' => $school->id,
                    'code' => $row['code'],
                    'name' => $row['name'],
                    'type' => $row['type'],
                    'subtype' => $row['subtype'],
                    'is_system' => true,
                    'is_active' => true,
                    'sort_order' => $row['sort'],
                ]);
                $ids[$row['code']] = $account->id;
                if ($row['map']) {
                    GlAccountMap::query()->firstOrCreate(
                        ['school_id' => $school->id, 'key' => $row['map']],
                        ['account_id' => $account->id]
                    );
                }
            }
        });
    }

    /** @return array<string, int|null> map key => account_id */
    public function maps(): array
    {
        $out = array_fill_keys(GlAccountMap::KEYS, null);
        foreach (GlAccountMap::query()->get(['key', 'account_id']) as $row) {
            $out[$row->key] = (int) $row->account_id;
        }

        return $out;
    }

    public function mapped(string $key): ?GlAccount
    {
        $map = GlAccountMap::query()->where('key', $key)->first();

        return $map?->account;
    }

    /** True when the keys needed for fee auto-posting are set. */
    public function feesReady(): bool
    {
        $maps = $this->maps();

        return filled($maps['fees_receivable']) && filled($maps['fee_income'])
            && (filled($maps['cash']) || filled($maps['bank']));
    }
}
