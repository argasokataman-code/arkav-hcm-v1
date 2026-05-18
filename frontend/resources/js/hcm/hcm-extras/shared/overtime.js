import { formatApiError } from "./runtime.js";

export function normalizeOvertimeDayType(dayType) {
    var key = String(dayType || "workday").trim().toLowerCase();
    if (key === "holiday") {
        return "public_holiday";
    }
    if (key === "rest_day" || key === "weekly_rest") {
        return "weekly_rest_day";
    }
    if (key === "short_rest_day" || key === "weekly_rest_short") {
        return "weekly_rest_day_short";
    }
    if (key !== "workday" && key !== "public_holiday" && key !== "weekly_rest_day" && key !== "weekly_rest_day_short") {
        return "workday";
    }
    return key;
}

export function overtimeDayTypeLabel(dayType) {
    var key = normalizeOvertimeDayType(dayType);
    if (key === "public_holiday") {
        return "Hari libur nasional/tanggal merah";
    }
    if (key === "weekly_rest_day") {
        return "Hari istirahat mingguan";
    }
    if (key === "weekly_rest_day_short") {
        return "Istirahat mingguan (hari kerja terpendek)";
    }
    return "Hari kerja";
}

export function formatOvertimeComplianceError(data, status, fallbackMessage) {
    var code = data && data.error && data.error.code ? String(data.error.code) : "";
    if (code === "OT_DAILY_LIMIT_EXCEEDED") {
        return "Durasi lembur melewati batas legal 4 jam per hari. Kurangi menit lembur atau pisah ke tanggal lain.";
    }
    if (code === "OT_WEEKLY_LIMIT_EXCEEDED") {
        return "Total lembur melewati batas legal 18 jam per minggu. Tinjau ulang distribusi lembur minggu berjalan.";
    }
    return formatApiError(data, status) || fallbackMessage || "Request failed";
}

export function overtimeStatusMeta(status) {
    var key = String(status || "pending").toLowerCase();
    if (key === "approved") {
        return { badge: "success", label: "Disetujui", note: "Siap diproses payroll" };
    }
    if (key === "declined") {
        return { badge: "danger", label: "Ditolak", note: "Perlu revisi/klarifikasi" };
    }
    return { badge: "warning", label: "Menunggu", note: "Menunggu review atasan/HR" };
}

export function overtimePolicyTypeLabel(requestType) {
    var key = String(requestType || "employee_request").toLowerCase();
    if (key === "company_assignment") {
        return "Penugasan perusahaan";
    }
    if (key === "missed_log_correction") {
        return "Koreksi lupa catat";
    }
    return "Pengajuan karyawan";
}

export function isPendingOlderThan24h(row) {
    if (!row || String(row.status || "").toLowerCase() !== "pending") {
        return false;
    }
    var dt = String(row.workDate || "").slice(0, 10);
    if (!dt) {
        return false;
    }
    var workDate = new Date(dt + "T00:00:00");
    if (Number.isNaN(workDate.getTime())) {
        return false;
    }
    return (Date.now() - workDate.getTime()) > (24 * 60 * 60 * 1000);
}