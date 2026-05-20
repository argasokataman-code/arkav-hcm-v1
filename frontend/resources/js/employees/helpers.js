// Small pure helpers extracted from employees-data.js
export function escapeHtml(value) {
    return String(value || "")
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/\"/g, "&quot;")
        .replace(/'/g, "&#39;");
}

export function formatEmployeeCode(value) {
    var n = Number(value);
    if (!Number.isFinite(n) || n <= 0) {
        return "-";
    }
    return "EMP-" + String(Math.trunc(n));
}

export function formatApiError(data, status) {
    if (window.ApiErrorHelper && typeof window.ApiErrorHelper.format === "function") {
        return window.ApiErrorHelper.format(data, status);
    }
    if (data && data.error && data.error.message) {
        return data.error.message;
    }
    if (data && data.message) {
        return data.message;
    }
    return status ? "Request failed (" + status + ")" : "Request failed";
}

export function formatRupiah(value) {
    var n = Number(value || 0);
    if (!isFinite(n)) {
        n = 0;
    }
    return "Rp" + n.toLocaleString("id-ID", { minimumFractionDigits: 0, maximumFractionDigits: 0 });
}

export function getCurrentListUrl() {
    return String(window.location.pathname || "") + String(window.location.search || "") + String(window.location.hash || "");
}

export function buildEmployeeDetailUrl(employeeId) {
    return "/employee-details?id=" + encodeURIComponent(employeeId) + "&returnTo=" + encodeURIComponent(getCurrentListUrl());
}

export function downloadBlob(filename, type, content) {
    var blob = new Blob([content], { type: type });
    var url = window.URL.createObjectURL(blob);
    var a = document.createElement("a");
    a.href = url;
    a.download = filename;
    document.body.appendChild(a);
    a.click();
    a.remove();
    window.URL.revokeObjectURL(url);
}

export function toCsv(rows, headers) {
    var out = [headers.join(",")];
    rows.forEach(function (row) {
        var line = headers.map(function (key) {
            var value = row[key] == null ? "" : String(row[key]);
            return '"' + value.replace(/"/g, '""') + '"';
        }).join(",");
        out.push(line);
    });
    return out.join("\n");
}

export function normalizeEmployeeScope(scope) {
    var value = String(scope || "").toLowerCase();
    if (value === "global" || value === "active_company") {
        return value;
    }
    return "";
}
