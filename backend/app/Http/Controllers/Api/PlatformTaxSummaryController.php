<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\BillingTaxCalculationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Platform Tax Summary Controller
 *
 * Provides platform-provider tax reporting endpoints for Global Super Admin.
 *
 * Covers:
 *  - PPN (Pajak Pertambahan Nilai) atas penjualan layanan SaaS ke tenant
 *  - PPh 23 atas platform service fee / jasa
 *  - PPh Final PP 23/2018 (UMKM 0.5%)
 *  - Dashboard ringkasan kewajiban pajak bulanan platform
 *
 * Guard: hcm.api.global-admin (via route group in routes/api/saas.php)
 */
class PlatformTaxSummaryController extends Controller
{
    // PPN rate saat ini sesuai UU HPP No. 7/2021: 11% (berlaku mulai April 2022).
    // Kenaikan ke 12% diamanatkan UU HPP pasal 7(1), namun masih ditunda pemerintah per Mei 2026.
    // Nilai default bisa di-override via query param ?ppn_rate=.
    private const PPN_RATE_DEFAULT = 11.0;

    // PPh 23 atas jasa platform: 2% dari DPP bruto
    private const PPH23_RATE = 2.0;

    // PPh Final PP 23/2018 (UMKM omset < 4.8 M/thn): 0.5%
    private const PPH_FINAL_RATE = 0.5;

    public function __construct(
        private readonly BillingTaxCalculationService $billingTaxService
    ) {}

    /**
     * GET /v1/saas/tax/dashboard?month=YYYY-MM
     *
     * Dashboard kewajiban pajak platform untuk bulan tertentu.
     * Menghitung PPN, PPh 23, dan PPh Final dari total revenue platform.
     */
    public function dashboard(Request $request): JsonResponse
    {
        $month = $this->resolveMonth($request->query('month'));
        $ppnRate = $this->resolvePpnRate($request->query('ppn_rate'));

        $report = $this->billingTaxService->generateCrossTenantMonthlyReport($month);

        $grossRevenue = (float) ($report['summary']['total_gross_revenue'] ?? 0);
        // DPP untuk PPN: jika billing_tax_rate_snapshot sudah terisi, total sudah termasuk pajak,
        // maka DPP = total / (1 + rate/100). Untuk saat ini semua snapshot NULL → gunakan amount_due langsung sebagai DPP.
        $dppPpn = $this->calculateDppFromInvoices($month);

        $ppnTerutang = round($dppPpn * ($ppnRate / 100), 2);
        $pph23Terutang = round($grossRevenue * (self::PPH23_RATE / 100), 2);
        $pphFinalTerutang = round($grossRevenue * (self::PPH_FINAL_RATE / 100), 2);

        $paidRevenue = $this->getPaidRevenueForMonth($month);
        $pendingRevenue = max(0, $grossRevenue - $paidRevenue);

        return response()->json([
            'success' => true,
            'data' => [
                'period' => $month,
                'ppn_rate' => $ppnRate,
                'revenue_summary' => [
                    'gross_revenue' => $grossRevenue,
                    'paid_revenue' => $paidRevenue,
                    'pending_revenue' => round($pendingRevenue, 2),
                    'dpp_ppn' => $dppPpn,
                    'tenant_count' => (int) ($report['summary']['tenant_count'] ?? 0),
                    'invoice_count' => $this->getInvoiceCountForMonth($month),
                ],
                'tax_obligations' => [
                    'ppn' => [
                        'label' => 'PPN (Pajak Pertambahan Nilai)',
                        'dasar_hukum' => 'UU HPP No. 7/2021 — Pasal 7 (1)',
                        'rate' => $ppnRate,
                        'dpp' => $dppPpn,
                        'amount' => $ppnTerutang,
                        'masa_pelaporan' => $this->getMasaPelaporan($month),
                        'batas_setor' => $this->getBatasSetor($month, 15),
                        'batas_lapor' => $this->getBatasLapor($month, 'ppn'),
                        'kode_akun_pajak' => '411211',
                        'kode_jenis_setoran' => '100',
                    ],
                    'pph23' => [
                        'label' => 'PPh Pasal 23 atas Jasa Platform',
                        'dasar_hukum' => 'PMK-141/PMK.03/2015',
                        'rate' => self::PPH23_RATE,
                        'dpp' => $grossRevenue,
                        'amount' => $pph23Terutang,
                        'masa_pelaporan' => $this->getMasaPelaporan($month),
                        'batas_setor' => $this->getBatasSetor($month, 10),
                        'batas_lapor' => $this->getBatasLapor($month, 'pph23'),
                        'kode_akun_pajak' => '411124',
                        'kode_jenis_setoran' => '104',
                        'catatan' => 'Berlaku jika platform menerima pembayaran dari badan (wajib potong oleh pembayar)',
                    ],
                    'pph_final' => [
                        'label' => 'PPh Final PP 23/2018 (UMKM)',
                        'dasar_hukum' => 'PP No. 23/2018',
                        'rate' => self::PPH_FINAL_RATE,
                        'dpp' => $grossRevenue,
                        'amount' => $pphFinalTerutang,
                        'masa_pelaporan' => $this->getMasaPelaporan($month),
                        'batas_setor' => $this->getBatasSetor($month, 15),
                        'batas_lapor' => $this->getBatasLapor($month, 'pph_final'),
                        'kode_akun_pajak' => '411128',
                        'kode_jenis_setoran' => '420',
                        'catatan' => 'Berlaku jika omset tahunan < Rp 4.800.000.000 (empat koma delapan miliar). Tidak berlaku bersamaan dengan PPh Badan.',
                    ],
                ],
                'total_kewajiban_pajak' => round($ppnTerutang + $pph23Terutang, 2),
            ],
        ]);
    }

    /**
     * GET /v1/saas/tax/spt-ppn?month=YYYY-MM
     *
     * Data untuk SPT Masa PPN (formulir 1111) bulan tertentu.
     * Merangkum penyerahan BKP/JKP ke tenant per invoice.
     */
    public function sptPpn(Request $request): JsonResponse
    {
        $month = $this->resolveMonth($request->query('month'));
        $ppnRate = $this->resolvePpnRate($request->query('ppn_rate'));

        $periodStart = $month . '-01';
        $periodEnd = date('Y-m-t', strtotime($periodStart));

        $invoices = DB::table('invoices as i')
            ->join('companies as c', 'c.id', '=', 'i.company_id')
            ->whereBetween('i.issue_date', [$periodStart, $periodEnd])
            ->select(
                'i.id',
                'i.uuid',
                'i.invoice_number',
                'i.issue_date',
                'i.due_date',
                'i.amount_due',
                'i.billing_tax_rate_snapshot',
                'i.status',
                'i.is_paid',
                'c.id as company_id',
                'c.name as company_name',
                DB::raw("COALESCE(i.subscription_uuid, '') as subscription_uuid")
            )
            ->orderBy('i.issue_date')
            ->orderBy('i.id')
            ->get();

        $rows = [];
        $totalDpp = 0.0;
        $totalPpn = 0.0;

        foreach ($invoices as $inv) {
            $effectiveRate = $inv->billing_tax_rate_snapshot !== null
                ? (float) $inv->billing_tax_rate_snapshot
                : $ppnRate;

            // DPP = amount_due (karena saat ini amount_due belum termasuk PPN — invoices tidak mencantumkan PPN terpisah)
            $dpp = (float) $inv->amount_due;
            $ppn = round($dpp * ($effectiveRate / 100), 2);

            $totalDpp += $dpp;
            $totalPpn += $ppn;

            $rows[] = [
                'no' => count($rows) + 1,
                'invoice_number' => $inv->invoice_number,
                'invoice_date' => $inv->issue_date,
                'npwp_pembeli' => '-',           // data tenant NPWP belum ada di schema
                'nama_pembeli' => $inv->company_name,
                'dpp' => $dpp,
                'ppn_rate' => $effectiveRate,
                'ppn' => $ppn,
                'faktur_status' => 'normal',     // default; e-faktur integration future
                'invoice_status' => (bool)$inv->is_paid || strtolower((string)$inv->status) === 'paid'
                    ? 'paid' : 'unpaid',
            ];
        }

        return response()->json([
            'success' => true,
            'data' => [
                'period' => $month,
                'form_type' => 'SPT Masa PPN 1111',
                'masa_pajak' => $this->getMasaPelaporan($month),
                'batas_lapor' => $this->getBatasLapor($month, 'ppn'),
                'ppn_rate_used' => $ppnRate,
                'summary' => [
                    'total_penyerahan_dpp' => round($totalDpp, 2),
                    'total_ppn_keluaran' => round($totalPpn, 2),
                    'total_ppn_masukan' => 0.0,   // PM belum di-track dalam sistem
                    'ppn_kurang_bayar' => round($totalPpn, 2),
                    'invoice_count' => count($rows),
                ],
                'detail_penyerahan' => $rows,
                'catatan' => [
                    'Nilai DPP diambil dari amount_due di tabel invoices.',
                    'PPN Masukan (PM) belum dikelola dalam sistem — isi manual saat lapor.',
                    'NPWP pembeli tenant belum tersedia — lengkapi sebelum e-faktur.',
                ],
            ],
        ]);
    }

    /**
     * GET /v1/saas/tax/spt-pph23?month=YYYY-MM
     *
     * Data untuk SPT Masa PPh 23 bulan tertentu.
     * Merangkum objek pemotongan PPh 23 dari pembayaran subscription platform.
     */
    public function sptPph23(Request $request): JsonResponse
    {
        $month = $this->resolveMonth($request->query('month'));

        $periodStart = $month . '-01';
        $periodEnd = date('Y-m-t', strtotime($periodStart));

        $payments = DB::table('payments as p')
            ->join('companies as c', 'c.id', '=', 'p.company_id')
            ->whereBetween('p.paid_at', [$periodStart, ' ' . $periodEnd . ' 23:59:59'])
            ->where('p.status', 'completed')
            ->select(
                'p.id',
                'p.uuid',
                'p.amount',
                'p.paid_at',
                'p.payment_method',
                'c.id as company_id',
                'c.name as company_name'
            )
            ->orderBy('p.paid_at')
            ->get();

        $rows = [];
        $totalBruto = 0.0;
        $totalPph23 = 0.0;

        foreach ($payments as $pay) {
            $bruto = (float) $pay->amount;
            $pph23 = round($bruto * (self::PPH23_RATE / 100), 2);

            $totalBruto += $bruto;
            $totalPph23 += $pph23;

            $rows[] = [
                'no' => count($rows) + 1,
                'nama_pemotong' => $pay->company_name,
                'npwp_pemotong' => '-',       // data tenant NPWP belum ada di schema
                'jenis_penghasilan' => 'Jasa Langganan Platform SaaS',
                'kode_objek_pajak' => '24-100-09',
                'tanggal_bayar' => $pay->paid_at,
                'jumlah_bruto' => $bruto,
                'tarif_pph23' => self::PPH23_RATE,
                'pph23_dipotong' => $pph23,
                'payment_method' => $pay->payment_method,
            ];
        }

        return response()->json([
            'success' => true,
            'data' => [
                'period' => $month,
                'form_type' => 'SPT Masa PPh 23',
                'masa_pajak' => $this->getMasaPelaporan($month),
                'batas_setor' => $this->getBatasSetor($month, 10),
                'batas_lapor' => $this->getBatasLapor($month, 'pph23'),
                'summary' => [
                    'total_bruto' => round($totalBruto, 2),
                    'total_pph23_terutang' => round($totalPph23, 2),
                    'payment_count' => count($rows),
                ],
                'detail_pemotongan' => $rows,
                'catatan' => [
                    'PPh 23 dipotong oleh pembayar (tenant) saat melakukan pembayaran ke platform.',
                    'NPWP pemotong (tenant) belum tersedia — lengkapi sebelum lapor.',
                    'Kode objek pajak 24-100-09: Jasa Manajemen & Konsultasi Lainnya.',
                ],
            ],
        ]);
    }

    // ─── Private Helpers ──────────────────────────────────────────────────────

    private function resolveMonth(mixed $month): string
    {
        if (is_string($month) && preg_match('/^\d{4}-\d{2}$/', $month)) {
            return $month;
        }

        return date('Y-m');
    }

    private function resolvePpnRate(mixed $rate): float
    {
        $r = (float) ($rate ?? self::PPN_RATE_DEFAULT);

        // Clamp to valid PPN range (7-15%)
        return max(7.0, min(15.0, $r));
    }

    private function calculateDppFromInvoices(string $month): float
    {
        $periodStart = $month . '-01';
        $periodEnd = date('Y-m-t', strtotime($periodStart));

        return (float) DB::table('invoices')
            ->whereBetween('issue_date', [$periodStart, $periodEnd])
            ->sum('amount_due');
    }

    private function getPaidRevenueForMonth(string $month): float
    {
        $periodStart = $month . '-01';
        $periodEnd = date('Y-m-t', strtotime($periodStart));

        return (float) DB::table('invoices')
            ->whereBetween('issue_date', [$periodStart, $periodEnd])
            ->where(function ($q): void {
                $q->where('is_paid', true)->orWhere('status', 'paid');
            })
            ->sum('amount_due');
    }

    private function getInvoiceCountForMonth(string $month): int
    {
        $periodStart = $month . '-01';
        $periodEnd = date('Y-m-t', strtotime($periodStart));

        return (int) DB::table('invoices')
            ->whereBetween('issue_date', [$periodStart, $periodEnd])
            ->count();
    }

    private function getMasaPelaporan(string $month): string
    {
        $dt = \DateTime::createFromFormat('Y-m', $month);

        return $dt ? $dt->format('F Y') : $month;
    }

    private function getBatasSetor(string $month, int $dayOfFollowingMonth): string
    {
        $nextMonth = date('Y-m', strtotime($month . '-01 +1 month'));

        return $nextMonth . '-' . str_pad((string) $dayOfFollowingMonth, 2, '0', STR_PAD_LEFT);
    }

    private function getBatasLapor(string $month, string $type): string
    {
        // PPN: akhir bulan berikutnya
        // PPh 23: tanggal 20 bulan berikutnya
        $nextMonth = date('Y-m', strtotime($month . '-01 +1 month'));

        return match ($type) {
            'ppn'       => date('Y-m-t', strtotime($nextMonth . '-01')),
            'pph23'     => $nextMonth . '-20',
            'pph_final' => $nextMonth . '-15',
            default     => $nextMonth . '-30',
        };
    }
}
