<?php

namespace App\Http\Controllers;

use App\Support\CronjobSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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
        $jobs = $request->input('jobs', []);
        if (! is_array($jobs)) {
            $jobs = [];
        }

        foreach ($definitions as $key => $definition) {
            $input = $jobs[$key] ?? [];
            if (! is_array($input)) {
                $input = [];
            }

            $enabled = array_key_exists('enabled', $input);
            $time = $this->normalizeTime((string) ($input['time'] ?? ($definition['defaults']['time'] ?? '00:00')));
            $timezone = trim((string) ($input['timezone'] ?? ($definition['defaults']['timezone'] ?? 'Asia/Jakarta')));
            if (! in_array($timezone, timezone_identifiers_list(), true)) {
                $timezone = (string) ($definition['defaults']['timezone'] ?? 'Asia/Jakarta');
            }

            $payload = [
                'enabled' => $enabled,
                'time' => $time,
                'timezone' => $timezone,
            ];

            if (($definition['scheduleType'] ?? 'daily') === 'monthly') {
                $dayOfMonth = (int) ($input['dayOfMonth'] ?? ($definition['defaults']['dayOfMonth'] ?? 1));
                $payload['dayOfMonth'] = max(1, min(28, $dayOfMonth));
            }

            CronjobSettings::set($key, $payload);
        }

        return redirect()->route('cronjob')->with('cronjobStatus', [
            'type' => 'success',
            'message' => 'Cronjob configuration updated successfully.',
        ]);
    }

    private function normalizeTime(string $value): string
    {
        $value = trim($value);

        if (! preg_match('/^(2[0-3]|[01]?\d):([0-5]\d)$/', $value, $matches)) {
            return '00:00';
        }

        return sprintf('%02d:%02d', (int) $matches[1], (int) $matches[2]);
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
