export function esc(v) {
  return String(v || "")
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/\"/g, "&quot;")
    .replace(/'/g, "&#39;");
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

export function notify(message, isError) {
  var existing = document.querySelector("[data-hcm-toast-container]");
  var container = existing;
  if (!container) {
    container = document.createElement("div");
    container.setAttribute("data-hcm-toast-container", "1");
    container.style.position = "fixed";
    container.style.top = "16px";
    container.style.right = "16px";
    container.style.zIndex = "3000";
    document.body.appendChild(container);
  }
  var toast = document.createElement("div");
  toast.className = "alert " + (isError ? "alert-danger" : "alert-success") + " shadow-sm mb-2";
  toast.textContent = message;
  container.appendChild(toast);
  window.setTimeout(function () {
    toast.remove();
  }, 2600);
}

export function minutesToTimeStr(totalMins) {
  var n = Math.max(0, parseInt(totalMins, 10) || 0);
  var h = Math.floor(n / 60) % 24;
  var m = n % 60;
  return String(h).padStart(2, "0") + ":" + String(m).padStart(2, "0");
}

export function timeInputToHi(val) {
  if (!val || typeof val !== "string") {
    return "09:00";
  }
  var p = val.split(":");
  return String(parseInt(p[0], 10) || 0).padStart(2, "0") + ":" + String(parseInt(p[1], 10) || 0).padStart(2, "0");
}

export function parseHiToMinutes(hi) {
  if (!hi || typeof hi !== "string") {
    return 0;
  }
  var p = hi.split(":");
  return (parseInt(p[0], 10) || 0) * 60 + (parseInt(p[1], 10) || 0);
}

export function formatIsoDate(iso) {
  if (!iso || typeof iso !== "string") {
    return "—";
  }
  var p = iso.split("-");
  if (p.length !== 3) {
    return iso;
  }
  var y = parseInt(p[0], 10);
  var mo = parseInt(p[1], 10) - 1;
  var d = parseInt(p[2], 10);
  if (!y || mo < 0 || mo > 11 || !d) {
    return iso;
  }
  var dt = new Date(y, mo, d);
  return dt.toLocaleDateString(undefined, { day: "numeric", month: "short", year: "numeric" });
}

export function formatMmSs(totalSeconds) {
  var safe = Math.max(0, parseInt(totalSeconds, 10) || 0);
  var mins = Math.floor(safe / 60);
  var secs = safe % 60;
  return String(mins).padStart(2, "0") + ":" + String(secs).padStart(2, "0");
}

export function todayIsoLocal() {
  var today = new Date();
  var y = today.getFullYear();
  var m = String(today.getMonth() + 1).padStart(2, "0");
  var da = String(today.getDate()).padStart(2, "0");
  return y + "-" + m + "-" + da;
}

export function toCsvCell(v) {
  var str = String(v == null ? "" : v);
  if (/[\",\n]/.test(str)) {
    return '"' + str.replace(/\"/g, '""') + '"';
  }
  return str;
}

export function downloadCsv(filename, headers, rows) {
  var csv = [headers.map(toCsvCell).join(",")];
  for (var i = 0; i < rows.length; i++) {
    csv.push(rows[i].map(toCsvCell).join(","));
  }
  var blob = new Blob([csv.join("\n")], { type: "text/csv;charset=utf-8;" });
  var url = URL.createObjectURL(blob);
  var a = document.createElement("a");
  a.href = url;
  a.download = filename;
  document.body.appendChild(a);
  a.click();
  document.body.removeChild(a);
  URL.revokeObjectURL(url);
}
