<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\TaxGovernance;

use App\Http\Controllers\Controller;
use App\Models\HcmBillingTaxPolicy;
use App\Services\BillingTaxCalculationService;
use App\Support\Exports\TabularExportResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

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

    /**
     * GET /v1/saas/tax/active-ppn-rate
     *
     * Single source of truth tarif PPN untuk halaman SPT platform,
     * diambil dari government tax compliance policy aktif terbaru.
     */
    public function activePpnRate(): JsonResponse
    {
        $activePolicy = HcmBillingTaxPolicy::query()
            ->where('status', 'active')
            ->where('notes', 'like', '%government_tax_compliance_policy%')
            ->orderByDesc('effective_from')
            ->orderByDesc('created_at')
            ->first();

        if (! $activePolicy) {
            return response()->json([
                'success' => true,
                'data' => [
                    'ppn_rate' => self::PPN_RATE_DEFAULT,
                    'source' => 'default',
                ],
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'ppn_rate' => $this->extractTransactionTaxRateFromPolicy($activePolicy),
                'source' => 'compliance_settings',
                'billing_month' => (string) ($activePolicy->billing_month ?? ''),
                'policy_version' => (string) ($activePolicy->version ?? ''),
            ],
        ]);
    }

    /**
     * GET /v1/saas/tax/spt-pph-badan?year=YYYY
     *
     * Ringkasan estimasi SPT Tahunan PPh Badan berbasis data compliance bulanan
     * yang sudah active di government tax compliance policy.
     */
    public function sptPphBadan(Request $request): JsonResponse
    {
        $year = $this->resolveYear($request->query('year'));
        $months = $this->monthsForYear($year);

        $rows = [];
        $totalTaxableRevenue = 0.0;
        $totalTransactionTaxLiability = 0.0;
        $totalPphBadanPayable = 0.0;

        foreach ($months as $month) {
            $monthly = $this->buildMonthlyPphBadanSummary($month);

            $totalTaxableRevenue += $monthly['taxable_revenue'];
            $totalTransactionTaxLiability += $monthly['transaction_tax_liability'];
            $totalPphBadanPayable += $monthly['pph_badan_payable'];

            $rows[] = [
                'month' => $month,
                'taxable_revenue' => round($monthly['taxable_revenue'], 2),
                'transaction_tax_liability' => round($monthly['transaction_tax_liability'], 2),
                'pph_badan_payable' => round($monthly['pph_badan_payable'], 2),
                'net_profit_estimate' => round($monthly['net_profit_estimate'], 2),
                'effective_pph_badan_rate' => round($monthly['effective_pph_badan_rate'], 2),
                'policy_configured' => $monthly['policy_configured'],
            ];
        }

        $netProfitEstimate = max(0, $totalTaxableRevenue - $totalTransactionTaxLiability - $totalPphBadanPayable);

        return response()->json([
            'success' => true,
            'data' => [
                'year' => $year,
                'form_type' => 'SPT Tahunan PPh Badan (Estimasi Internal)',
                'summary' => [
                    'total_taxable_revenue' => round($totalTaxableRevenue, 2),
                    'total_transaction_tax_liability' => round($totalTransactionTaxLiability, 2),
                    'total_pph_badan_payable' => round($totalPphBadanPayable, 2),
                    'total_net_profit_estimate' => round($netProfitEstimate, 2),
                ],
                'monthly_breakdown' => $rows,
                'catatan' => [
                    'Data bersifat estimasi internal untuk monitoring; bukan pengganti filing final DJP.',
                    'Perhitungan memakai policy government tax compliance yang aktif pada bulan terkait.',
                    'Lakukan rekonsiliasi final dengan tim akuntansi sebelum pelaporan SPT Tahunan 1771.',
                ],
            ],
        ]);
    }

    /**
     * GET /v1/saas/tax/spt-pph-badan/export?year=YYYY&format=xlsx
     *
     * Export estimasi SPT Tahunan PPh Badan ke Excel/XLSX.
     */
    public function exportSptPphBadan(Request $request): StreamedResponse|JsonResponse
    {
        $validated = $request->validate([
            'year' => ['nullable', 'integer', 'min:2020', 'max:2100'],
            'format' => ['nullable', 'string', 'in:xlsx'],
        ]);

        $year = isset($validated['year']) ? (int) $validated['year'] : (int) date('Y');
        $format = strtolower((string) ($validated['format'] ?? 'xlsx'));

        if ($format !== 'xlsx') {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'UNSUPPORTED_EXPORT_FORMAT',
                    'message' => 'Only XLSX export is currently supported for SPT PPh Badan.',
                ],
            ], 422);
        }

        $months = $this->monthsForYear($year);
        $rows = [];

        foreach ($months as $month) {
            $monthly = $this->buildMonthlyPphBadanSummary($month);

            $rows[] = [
                $month,
                $this->formatRupiah((float) $monthly['taxable_revenue']),
                $this->formatRupiah((float) $monthly['transaction_tax_liability']),
                $this->formatRupiah((float) $monthly['pph_badan_payable']),
                $this->formatRupiah((float) $monthly['net_profit_estimate']),
                $this->formatPercent((float) $monthly['effective_pph_badan_rate']),
                $monthly['policy_configured'] ? 'Configured' : 'Not configured',
            ];
        }

        return TabularExportResponse::download(
            [
                'Bulan',
                'Taxable Revenue',
                'Transaction Tax Liability',
                'PPh Badan Payable',
                'Net Profit Estimate',
                'Effective PPh Badan Rate',
                'Policy Status',
            ],
            $rows,
            'spt-pph-badan-estimasi-'.$year.'-'.now()->format('Ymd_His'),
            'xlsx',
            'SPT PPh Badan'
        );
    }

    /**
     * GET /v1/saas/tax/dashboard/export?month=YYYY-MM&ppn_rate=11&format=xlsx
     */
    public function exportDashboard(Request $request): StreamedResponse|JsonResponse
    {
        $validated = $request->validate([
            'month' => ['nullable', 'date_format:Y-m'],
            'ppn_rate' => ['nullable', 'numeric', 'min:7', 'max:15'],
            'format' => ['nullable', 'string', 'in:xlsx'],
        ]);

        $month = $this->resolveMonth($validated['month'] ?? null);
        $ppnRate = $this->resolvePpnRate($validated['ppn_rate'] ?? null);
        $format = strtolower((string) ($validated['format'] ?? 'xlsx'));

        if ($format !== 'xlsx') {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'UNSUPPORTED_EXPORT_FORMAT',
                    'message' => 'Only XLSX export is currently supported for dashboard export.',
                ],
            ], 422);
        }

        $payload = $this->dashboard($request->duplicate([
            'month' => $month,
            'ppn_rate' => $ppnRate,
        ]))->getData(true);

        $data = (array) ($payload['data'] ?? []);
        $revenue = (array) ($data['revenue_summary'] ?? []);
        $taxes = (array) ($data['tax_obligations'] ?? []);

        $rows = [
            ['Periode', $month],
            ['Tarif PPN Aktif', $this->formatPercent((float) ($data['ppn_rate'] ?? $ppnRate))],
            ['Gross Revenue', $this->formatRupiah((float) ($revenue['gross_revenue'] ?? 0))],
            ['Revenue Dibayar', $this->formatRupiah((float) ($revenue['paid_revenue'] ?? 0))],
            ['Revenue Belum Dibayar', $this->formatRupiah((float) ($revenue['pending_revenue'] ?? 0))],
            ['DPP PPN', $this->formatRupiah((float) ($revenue['dpp_ppn'] ?? 0))],
            ['Jumlah Tenant', (string) ($revenue['tenant_count'] ?? 0)],
            ['Jumlah Invoice', (string) ($revenue['invoice_count'] ?? 0)],
            ['', ''],
            ['Jenis Pajak', 'Pajak Terutang'],
            ['PPN', $this->formatRupiah((float) (($taxes['ppn']['amount'] ?? 0)))],
            ['PPh 23', $this->formatRupiah((float) (($taxes['pph23']['amount'] ?? 0)))],
            ['PPh Final', $this->formatRupiah((float) (($taxes['pph_final']['amount'] ?? 0)))],
            ['Total Kewajiban Pajak', $this->formatRupiah((float) ($data['total_kewajiban_pajak'] ?? 0))],
        ];

        return TabularExportResponse::download(
            ['Metrik', 'Nilai'],
            $rows,
            'spt-platform-dashboard-'.$month.'-'.now()->format('Ymd_His'),
            'xlsx',
            'Dashboard Pajak'
        );
    }

    /**
     * GET /v1/saas/tax/spt-ppn/export?month=YYYY-MM&ppn_rate=11&format=xlsx
     */
    public function exportSptPpn(Request $request): StreamedResponse|JsonResponse
    {
        $validated = $request->validate([
            'month' => ['nullable', 'date_format:Y-m'],
            'ppn_rate' => ['nullable', 'numeric', 'min:7', 'max:15'],
            'format' => ['nullable', 'string', 'in:xlsx'],
        ]);

        $month = $this->resolveMonth($validated['month'] ?? null);
        $ppnRate = $this->resolvePpnRate($validated['ppn_rate'] ?? null);
        $format = strtolower((string) ($validated['format'] ?? 'xlsx'));

        if ($format !== 'xlsx') {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'UNSUPPORTED_EXPORT_FORMAT',
                    'message' => 'Only XLSX export is currently supported for SPT PPN export.',
                ],
            ], 422);
        }

        $payload = $this->sptPpn($request->duplicate([
            'month' => $month,
            'ppn_rate' => $ppnRate,
        ]))->getData(true);

        $data = (array) ($payload['data'] ?? []);
        $details = collect($data['detail_penyerahan'] ?? []);

        $rows = $details->map(function (array $item): array {
            return [
                (string) ($item['no'] ?? ''),
                (string) ($item['invoice_number'] ?? '-'),
                (string) ($item['invoice_date'] ?? '-'),
                (string) ($item['nama_pembeli'] ?? '-'),
                (string) ($item['npwp_pembeli'] ?? '-'),
                $this->formatRupiah((float) ($item['dpp'] ?? 0)),
                $this->formatPercent((float) ($item['ppn_rate'] ?? 0)),
                $this->formatRupiah((float) ($item['ppn'] ?? 0)),
                (string) ($item['invoice_status'] ?? '-'),
            ];
        })->values()->all();

        return TabularExportResponse::download(
            [
                'No',
                'No. Invoice',
                'Tanggal Invoice',
                'Nama Pembeli',
                'NPWP Pembeli',
                'DPP',
                'Tarif PPN',
                'PPN',
                'Status Invoice',
            ],
            $rows,
            'spt-ppn-'.$month.'-'.now()->format('Ymd_His'),
            'xlsx',
            'SPT PPN'
        );
    }

    /**
     * GET /v1/saas/tax/spt-pph23/export?month=YYYY-MM&format=xlsx
     */
    public function exportSptPph23(Request $request): StreamedResponse|JsonResponse
    {
        $validated = $request->validate([
            'month' => ['nullable', 'date_format:Y-m'],
            'format' => ['nullable', 'string', 'in:xlsx'],
        ]);

        $month = $this->resolveMonth($validated['month'] ?? null);
        $format = strtolower((string) ($validated['format'] ?? 'xlsx'));

        if ($format !== 'xlsx') {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'UNSUPPORTED_EXPORT_FORMAT',
                    'message' => 'Only XLSX export is currently supported for SPT PPh 23 export.',
                ],
            ], 422);
        }

        $payload = $this->sptPph23($request->duplicate([
            'month' => $month,
        ]))->getData(true);

        $data = (array) ($payload['data'] ?? []);
        $details = collect($data['detail_pemotongan'] ?? []);

        $rows = $details->map(function (array $item): array {
            return [
                (string) ($item['no'] ?? ''),
                (string) ($item['nama_pemotong'] ?? '-'),
                (string) ($item['npwp_pemotong'] ?? '-'),
                (string) ($item['jenis_penghasilan'] ?? '-'),
                (string) ($item['kode_objek_pajak'] ?? '-'),
                (string) ($item['tanggal_bayar'] ?? '-'),
                $this->formatRupiah((float) ($item['jumlah_bruto'] ?? 0)),
                $this->formatPercent((float) ($item['tarif_pph23'] ?? 0)),
                $this->formatRupiah((float) ($item['pph23_dipotong'] ?? 0)),
            ];
        })->values()->all();

        return TabularExportResponse::download(
            [
                'No',
                'Nama Pemotong',
                'NPWP Pemotong',
                'Jenis Penghasilan',
                'Kode Objek Pajak',
                'Tanggal Bayar',
                'Jumlah Bruto',
                'Tarif PPh 23',
                'PPh 23 Dipotong',
            ],
            $rows,
            'spt-pph23-'.$month.'-'.now()->format('Ymd_His'),
            'xlsx',
            'SPT PPh 23'
        );
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

    private function formatRupiah(float $amount): string
    {
        return 'Rp '.number_format($amount, 2, ',', '.');
    }

    private function formatPercent(float $percent): string
    {
        return number_format($percent, 2, ',', '.').'%';
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

    private function resolveYear(mixed $year): int
    {
        $resolved = is_numeric($year) ? (int) $year : (int) date('Y');

        if ($resolved < 2020 || $resolved > 2100) {
            return (int) date('Y');
        }

        return $resolved;
    }

    /**
     * @return list<string>
     */
    private function monthsForYear(int $year): array
    {
        $months = [];
        for ($month = 1; $month <= 12; $month++) {
            $months[] = sprintf('%04d-%02d', $year, $month);
        }

        return $months;
    }

    /**
     * @return array{taxable_revenue: float, transaction_tax_liability: float, pph_badan_payable: float, net_profit_estimate: float, effective_pph_badan_rate: float, policy_configured: bool}
     */
    private function buildMonthlyPphBadanSummary(string $month): array
    {
        $invoiceRows = DB::table('invoices')
            ->whereBetween('issue_date', [$month . '-01', date('Y-m-t', strtotime($month . '-01'))])
            ->select('company_id', 'amount_due', 'issue_date')
            ->get();

        $taxableRevenue = (float) $invoiceRows->sum('amount_due');

        if ($taxableRevenue <= 0.0) {
            return [
                'taxable_revenue' => 0.0,
                'transaction_tax_liability' => 0.0,
                'pph_badan_payable' => 0.0,
                'net_profit_estimate' => 0.0,
                'effective_pph_badan_rate' => 0.0,
                'policy_configured' => false,
            ];
        }

        $activePolicies = HcmBillingTaxPolicy::query()
            ->where('billing_month', $month)
            ->where('status', 'active')
            ->where('notes', 'like', '%government_tax_compliance_policy%')
            ->orderBy('company_id')
            ->orderByDesc('effective_from')
            ->orderByDesc('created_at')
            ->get();

        if ($activePolicies->isEmpty()) {
            return [
                'taxable_revenue' => $taxableRevenue,
                'transaction_tax_liability' => 0.0,
                'pph_badan_payable' => 0.0,
                'net_profit_estimate' => $taxableRevenue,
                'effective_pph_badan_rate' => 0.0,
                'policy_configured' => false,
            ];
        }

        $policyByCompany = $this->buildLatestPolicyByCompany($activePolicies);

        $transactionTaxLiability = 0.0;
        $pphBadanPayable = 0.0;

        foreach ($invoiceRows as $invoice) {
            $companyId = (int) ($invoice->company_id ?? 0);
            if ($companyId <= 0 || ! isset($policyByCompany[$companyId])) {
                continue;
            }

            $amount = (float) ($invoice->amount_due ?? 0);
            if ($amount <= 0) {
                continue;
            }

            $rates = $policyByCompany[$companyId];
            $transactionTaxLiability += $amount * ($rates['transaction_tax_rate'] / 100);
            $pphBadanPayable += $amount * ($rates['corporate_tax_rate'] / 100);
        }

        $transactionTaxLiability = round($transactionTaxLiability, 2);
        $pphBadanPayable = round($pphBadanPayable, 2);
        $netProfitEstimate = max(0, round($taxableRevenue - $transactionTaxLiability - $pphBadanPayable, 2));
        $effectiveRate = $taxableRevenue > 0 ? round(($pphBadanPayable / $taxableRevenue) * 100, 2) : 0.0;

        return [
            'taxable_revenue' => round($taxableRevenue, 2),
            'transaction_tax_liability' => $transactionTaxLiability,
            'pph_badan_payable' => $pphBadanPayable,
            'net_profit_estimate' => $netProfitEstimate,
            'effective_pph_badan_rate' => $effectiveRate,
            'policy_configured' => true,
        ];
    }

    /**
     * @param Collection<int, HcmBillingTaxPolicy> $policies
     * @return array<int, array{corporate_tax_rate: float, transaction_tax_rate: float}>
     */
    private function buildLatestPolicyByCompany(Collection $policies): array
    {
        $result = [];

        foreach ($policies as $policy) {
            $companyId = (int) ($policy->company_id ?? 0);
            if ($companyId <= 0 || isset($result[$companyId])) {
                continue;
            }

            $corporateTaxRate = (float) ($policy->tax_rate_percentage ?? 0);
            $transactionTaxRate = $this->extractTransactionTaxRateFromPolicy($policy);

            $result[$companyId] = [
                'corporate_tax_rate' => max(0.0, min(100.0, $corporateTaxRate)),
                'transaction_tax_rate' => max(0.0, min(100.0, $transactionTaxRate)),
            ];
        }

        return $result;
    }

    private function extractTransactionTaxRateFromPolicy(HcmBillingTaxPolicy $policy): float
    {
        $decodedNotes = json_decode((string) ($policy->notes ?? ''), true);
        if (! is_array($decodedNotes)) {
            return 0.0;
        }

        $notesPayload = $decodedNotes;
        if (! isset($notesPayload['transaction_tax'])) {
            $rawNotes = $decodedNotes['notes'] ?? null;
            $notesPayload = is_array($rawNotes)
                ? $rawNotes
                : (is_string($rawNotes) ? json_decode($rawNotes, true) : []);
        }

        if (! is_array($notesPayload)) {
            return 0.0;
        }

        $rate = (float) ($notesPayload['transaction_tax']['tax_rate'] ?? 0);

        return max(0.0, min(100.0, $rate));
    }
}
