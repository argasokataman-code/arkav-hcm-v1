<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

class CronjobSettings
{
    /**
     * @return array<string, array<string, mixed>>
     */
    public static function definitions(): array
    {
        $defaultTimezone = self::defaultTimezone();

        return [
            'payment_reminder' => [
                'label' => 'Send Payment Reminder',
                'description' => 'Kirim pengingat otomatis ke customer/tenant untuk tagihan yang mendekati atau melewati jatuh tempo.',
                'frequencyExplanation' => 'Daily = sistem menjalankan pengecekan invoice belum lunas setiap hari pada jam yang diatur.',
                'businessPurpose' => 'Mengingatkan customer/company owner terhadap tagihan yang mendekati atau melewati jatuh tempo.',
                'expectedOutcome' => 'Rasio pembayaran tepat waktu meningkat dan aging invoice menurun.',
                'scheduleType' => 'daily',
                'defaults' => [
                    'enabled' => true,
                    'time' => '08:00',
                    'timezone' => 'Asia/Jakarta',
                ],
            ],
            'wilayah_sync' => [
                'label' => 'Wilayah Sync',
                'description' => 'Sinkronisasi data referensi wilayah (provinsi/kabupaten/kecamatan/kelurahan) dari sumber resmi.',
                'frequencyExplanation' => 'Monthly = sinkronisasi data master wilayah dijalankan bulanan pada hari dan jam yang diatur.',
                'businessPurpose' => 'Menjaga data referensi provinsi/kabupaten/kecamatan/kelurahan tetap selaras dengan sumber resmi.',
                'expectedOutcome' => 'Input alamat dan validasi lokasi tetap akurat dan tidak stale.',
                'scheduleType' => 'monthly',
                'defaults' => [
                    'enabled' => true,
                    'dayOfMonth' => 1,
                    'time' => '01:00',
                    'timezone' => 'Asia/Jakarta',
                ],
            ],
            'payroll_refresh_open_period' => [
                'label' => 'Payroll Refresh Open Period',
                'description' => 'Perbarui otomatis draft payroll periode aktif agar selalu mencerminkan data karyawan dan komponen terkini.',
                'frequencyExplanation' => 'Daily = setiap hari sistem mengecek periode payroll berstatus open lalu refresh draft payroll.',
                'businessPurpose' => 'Memastikan draft payroll periode aktif selalu mengikuti data karyawan/komponen terbaru sebelum finalisasi.',
                'expectedOutcome' => 'Perhitungan payroll bulanan lebih konsisten dan minim koreksi manual.',
                'scheduleType' => 'daily',
                'defaults' => [
                    'enabled' => true,
                    'time' => '00:00',
                    'timezone' => 'Asia/Jakarta',
                ],
            ],
            'leave_monthly_accrual' => [
                'label' => 'Leave Monthly Accrual',
                'description' => 'Tambahkan hak cuti bulanan karyawan secara otomatis sesuai kebijakan perusahaan.',
                'frequencyExplanation' => 'Daily = worker berjalan harian, namun logika hanya memproses accrual jatah cuti bulanan saat kondisi terpenuhi.',
                'businessPurpose' => 'Menambahkan hak cuti earned leave karyawan secara otomatis sesuai policy.',
                'expectedOutcome' => 'Saldo cuti bulanan akurat tanpa proses manual HR.',
                'scheduleType' => 'daily',
                'defaults' => [
                    'enabled' => true,
                    'time' => '00:10',
                    'timezone' => 'Asia/Jakarta',
                ],
            ],
            'leave_yearly_carry' => [
                'label' => 'Leave Yearly Carry',
                'description' => 'Pindahkan saldo cuti tahunan yang dapat dibawa ke tahun berikutnya sesuai kebijakan.',
                'frequencyExplanation' => 'Daily = worker cek setiap hari, tetapi carry-forward efektif saat jendela policy tahunan terpenuhi.',
                'businessPurpose' => 'Memindahkan saldo cuti tahunan yang boleh dibawa ke tahun berikutnya.',
                'expectedOutcome' => 'Transisi saldo cuti antar tahun sesuai kebijakan perusahaan.',
                'scheduleType' => 'daily',
                'defaults' => [
                    'enabled' => true,
                    'time' => '00:15',
                    'timezone' => 'Asia/Jakarta',
                ],
            ],
            'leave_daily_expire' => [
                'label' => 'Leave Daily Expire',
                'description' => 'Hapus otomatis saldo cuti carry-forward yang sudah melewati batas masa berlaku sesuai kebijakan.',
                'frequencyExplanation' => 'Daily = setiap hari sistem mengecek apakah ada saldo carry-forward yang sudah melewati cutoff policy.',
                'businessPurpose' => 'Menghapus otomatis saldo carry-forward yang masa berlakunya habis.',
                'expectedOutcome' => 'Saldo cuti tetap patuh aturan expiry tanpa audit manual berkala.',
                'scheduleType' => 'daily',
                'defaults' => [
                    'enabled' => true,
                    'time' => '00:20',
                    'timezone' => 'Asia/Jakarta',
                ],
            ],
            'saas_convert_ended_trials' => [
                'label' => 'SaaS Convert Ended Trials',
                'description' => 'Konversi tenant yang masa trial-nya berakhir menjadi status menunggu pembayaran dan buat invoice otomatis.',
                'frequencyExplanation' => 'Daily = sistem memeriksa trial yang berakhir setiap hari lalu memproses konversi status + invoice.',
                'businessPurpose' => 'Mengubah tenant trial yang habis menjadi pending payment agar flow billing lanjut otomatis.',
                'expectedOutcome' => 'Tidak ada tenant trial berakhir yang terlewat ke proses penagihan.',
                'scheduleType' => 'daily',
                'defaults' => [
                    'enabled' => true,
                    'time' => '00:20',
                    'timezone' => 'Asia/Jakarta',
                ],
            ],
            'saas_terminate_expired_subscriptions' => [
                'label' => 'SaaS Terminate Expired Subscriptions',
                'description' => 'Nonaktifkan entitlement tenant yang masa berlangganannya sudah berakhir.',
                'frequencyExplanation' => 'Daily = setiap hari sistem mengecek subscription yang sudah melewati end date.',
                'businessPurpose' => 'Menonaktifkan entitlement tenant yang masa langganannya sudah berakhir.',
                'expectedOutcome' => 'Akses fitur SaaS tetap sesuai masa berlaku langganan.',
                'scheduleType' => 'daily',
                'defaults' => [
                    'enabled' => true,
                    'time' => '00:30',
                    'timezone' => 'Asia/Jakarta',
                ],
            ],
            'saas_suspend_overdue_services' => [
                'label' => 'SaaS Suspend Overdue Services',
                'description' => 'Suspend layanan tenant yang memiliki tagihan menunggak melebihi batas toleransi pembayaran.',
                'frequencyExplanation' => 'Daily = sistem mengecek invoice unpaid yang lewat grace period setiap hari.',
                'businessPurpose' => 'Mensuspend layanan tenant yang menunggak pembayaran melebihi batas toleransi.',
                'expectedOutcome' => 'Kontrol risiko piutang lebih baik dan enforcement billing konsisten.',
                'scheduleType' => 'daily',
                'defaults' => [
                    'enabled' => true,
                    'time' => '06:00',
                    'timezone' => 'Asia/Jakarta',
                ],
            ],
            'saas_check_employee_count_limits' => [
                'label' => 'SaaS Check Employee Limits',
                'description' => 'Pantau dan deteksi tenant yang jumlah karyawan aktifnya melebihi batas paket berlangganan.',
                'frequencyExplanation' => 'Daily = pengecekan jumlah employee aktif tenant dilakukan harian terhadap limit paket.',
                'businessPurpose' => 'Mendeteksi pelanggaran limit seat/employee berdasarkan paket subscription.',
                'expectedOutcome' => 'Usage tenant tetap sesuai kapasitas paket yang dibayar.',
                'scheduleType' => 'daily',
                'defaults' => [
                    'enabled' => true,
                    'time' => '01:00',
                    'timezone' => 'Asia/Jakarta',
                ],
            ],
            'saas_recurring_billing' => [
                'label' => 'SaaS Recurring Billing',
                'description' => 'Jalankan perpanjangan berlangganan dan siklus penagihan berulang tenant SaaS secara otomatis.',
                'frequencyExplanation' => 'Daily = setiap hari sistem mengecek subscription yang masuk siklus renewal/billing berulang.',
                'businessPurpose' => 'Menjalankan automation perpanjangan dan siklus tagihan berulang tenant SaaS.',
                'expectedOutcome' => 'Siklus billing periodik berjalan stabil tanpa intervensi manual rutin.',
                'scheduleType' => 'daily',
                'defaults' => [
                    'enabled' => true,
                    'time' => '06:00',
                    'timezone' => 'Asia/Jakarta',
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function get(string $key): array
    {
        $definition = self::definitions()[$key] ?? null;
        if ($definition === null) {
            return [];
        }

        $defaults = $definition['defaults'];
        $stored = self::readSetting($key);
        if (! is_array($stored)) {
            $stored = [];
        }

        return array_merge($defaults, $stored);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function all(): array
    {
        $result = [];

        foreach (self::definitions() as $key => $definition) {
            $result[$key] = array_merge($definition, [
                'config' => self::get($key),
            ]);
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function set(string $key, array $payload): void
    {
        if (! array_key_exists($key, self::definitions())) {
            return;
        }

        try {
            if (! Schema::hasTable('settings')) {
                return;
            }

            Setting::set('cronjob_'.$key, $payload, 'cronjob');
        } catch (Throwable $exception) {
            Log::warning('Unable to persist cronjob setting.', [
                'setting_key' => 'cronjob_'.$key,
                'group' => 'cronjob',
                'exception' => $exception->getMessage(),
            ]);
        }
    }

    private static function readSetting(string $key): mixed
    {
        try {
            if (! Schema::hasTable('settings')) {
                return null;
            }

            return Setting::get('cronjob_'.$key);
        } catch (Throwable) {
            return null;
        }
    }

    private static function defaultTimezone(): string
    {
        try {
            return WebsiteSettings::localizationTimezone();
        } catch (Throwable) {
            return (string) config('app.timezone', 'UTC');
        }
    }
}
