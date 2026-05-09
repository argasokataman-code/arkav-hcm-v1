<?php

namespace App\Http\Controllers;

use App\Support\CronjobSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

class CronjobController extends Controller
{
    public function index(Request $request): View|RedirectResponse
    {
        if (! $this->authorized($request)) {
            return redirect()->guest(url('lock-screen'));
        }

        return view('cronjob', [
            'cronjobs' => CronjobSettings::all(),
            'availableTimezones' => $this->availableTimezones(),
            'runtimeFlagStates' => $this->runtimeFlagStates(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        if (! $this->authorized($request)) {
            return redirect()->guest(url('lock-screen'));
        }

        $definitions = CronjobSettings::definitions();
        $validated = Validator::make(
            $request->all(),
            $this->validationRules($definitions),
            [
                'jobs.*.time.required' => 'Time is required for each cronjob.',
                'jobs.*.time.date_format' => 'Time must use HH:MM format.',
                'jobs.*.timezone.required' => 'Timezone is required for each cronjob.',
                'jobs.*.timezone.timezone' => 'Timezone must be a valid IANA timezone.',
                'jobs.*.dayOfMonth.required' => 'Day of month is required for monthly cronjobs.',
                'jobs.*.dayOfMonth.integer' => 'Day of month must be a number between 1 and 28.',
                'jobs.*.dayOfMonth.between' => 'Day of month must be between 1 and 28.',
            ]
        )->validate();

        $jobs = $validated['jobs'] ?? [];

        foreach ($definitions as $key => $definition) {
            $input = $jobs[$key] ?? [];
            if (! is_array($input)) {
                $input = [];
            }

            $enabled = array_key_exists('enabled', $input) && (string) $input['enabled'] === '1';
            $time = trim((string) ($input['time'] ?? ($definition['defaults']['time'] ?? '00:00')));
            $timezone = trim((string) ($input['timezone'] ?? ($definition['defaults']['timezone'] ?? 'Asia/Jakarta')));

            $payload = [
                'enabled' => $enabled,
                'time' => $time,
                'timezone' => $timezone,
            ];

            if (($definition['scheduleType'] ?? 'daily') === 'monthly') {
                $dayOfMonth = (int) ($input['dayOfMonth'] ?? ($definition['defaults']['dayOfMonth'] ?? 1));
                $payload['dayOfMonth'] = $dayOfMonth;
            }

            CronjobSettings::set($key, $payload);
        }

        return redirect()->route('cronjob')->with('cronjobStatus', [
            'type' => 'success',
            'message' => 'Cronjob configuration updated successfully.',
        ]);
    }

    /**
     * @param  array<string, array<string, mixed>>  $definitions
     * @return array<string, array<int, string>>
     */
    private function validationRules(array $definitions): array
    {
        $rules = [
            'jobs' => ['required', 'array'],
        ];

        foreach ($definitions as $key => $definition) {
            $rules["jobs.$key"] = ['nullable', 'array'];
            $rules["jobs.$key.enabled"] = ['nullable', 'in:1'];
            $rules["jobs.$key.time"] = ['required_with:jobs.'.$key, 'date_format:H:i'];
            $rules["jobs.$key.timezone"] = ['required_with:jobs.'.$key, 'timezone'];

            if (($definition['scheduleType'] ?? 'daily') === 'monthly') {
                $rules["jobs.$key.dayOfMonth"] = ['required_with:jobs.'.$key, 'integer', 'between:1,28'];
            } else {
                $rules["jobs.$key.dayOfMonth"] = ['nullable'];
            }
        }

        return $rules;
    }

    private function authorized(Request $request): bool
    {
        $user = $request->user() ?: Auth::user();

        return (bool) ($user && $user->isGlobalHcmAdmin());
    }

    /**
     * @return array<int, string>
     */
    private function availableTimezones(): array
    {
        return array_values(array_unique(array_merge(['Asia/Jakarta', 'UTC'], timezone_identifiers_list())));
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function runtimeFlagStates(): array
    {
        return [
            'saas_terminate_expired_subscriptions' => [
                'flag' => 'app.saas.auto_termination_enabled',
                'enabled' => (bool) config('app.saas.auto_termination_enabled', true),
            ],
            'saas_suspend_overdue_services' => [
                'flag' => 'app.saas.auto_suspension_enabled',
                'enabled' => (bool) config('app.saas.auto_suspension_enabled', true),
            ],
            'saas_check_employee_count_limits' => [
                'flag' => 'app.saas.employee_limit_enforcement_enabled',
                'enabled' => (bool) config('app.saas.employee_limit_enforcement_enabled', true),
            ],
        ];
    }
}
