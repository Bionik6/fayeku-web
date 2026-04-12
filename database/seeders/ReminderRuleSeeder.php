<?php

namespace Database\Seeders;

use App\Models\Auth\Company;
use App\Models\PME\ReminderRule;
use Illuminate\Database\Seeder;

class ReminderRuleSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            ['name' => 'Relance J+3', 'trigger_days' => 3],
            ['name' => 'Relance J+7', 'trigger_days' => 7],
            ['name' => 'Relance J+15', 'trigger_days' => 15],
            ['name' => 'Relance J+30', 'trigger_days' => 30],
        ];

        Company::query()
            ->where('type', 'sme')
            ->each(function (Company $company) use ($defaults) {
                if (! $company->reminder_settings) {
                    $company->update(['reminder_settings' => Company::defaultReminderSettings()]);
                }

                $channel = $company->getReminderSetting('default_channel', 'whatsapp');

                foreach ($defaults as $rule) {
                    ReminderRule::query()->firstOrCreate(
                        [
                            'company_id' => $company->id,
                            'trigger_days' => $rule['trigger_days'],
                        ],
                        [
                            ...$rule,
                            'channel' => $channel,
                            'is_active' => true,
                        ]
                    );
                }
            });
    }
}
