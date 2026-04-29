import { formatIsoDate, todayIsoLocal } from "./attendance-utils";

export function getSelectedReportDate() {
  var input = document.querySelector("[data-attendance-report-date]");
  if (input && input.value && /^\d{4}-\d{2}-\d{2}$/.test(input.value)) {
    return input.value;
  }
  var params = new URLSearchParams(window.location.search || "");
  var qd = params.get("date");
  if (qd && /^\d{4}-\d{2}-\d{2}$/.test(qd)) {
    return qd;
  }
  return todayIsoLocal();
}

export function getReportSourceMode() {
  var sel = document.querySelector("[data-attendance-report-source]");
  var mode = sel ? String(sel.value || "live").toLowerCase() : "live";
  return mode === "archive" ? "archive" : "live";
}

export function getSelectedSnapshotId() {
  var input = document.querySelector("[data-attendance-report-snapshot-id]");
  if (!input) {
    return 0;
  }
  var id = parseInt(String(input.value || "0"), 10);
  return Number.isFinite(id) && id > 0 ? id : 0;
}

export function setReportSourceBadge(mode, snapshotId) {
  var badge = document.querySelector("[data-attendance-report-source-badge]");
  if (!badge) {
    return;
  }
  if (mode === "archive") {
    var suffix = snapshotId > 0 ? " #" + String(snapshotId) : "";
    badge.textContent = "Source: Archive" + suffix;
    return;
  }
  badge.textContent = "Source: Live";
}

export function normalizeArchiveAttendanceRows(snapshotPayload, dateYmd) {
  var moduleData = snapshotPayload && snapshotPayload.dataByModule ? snapshotPayload.dataByModule.attendance : null;
  if (!moduleData || typeof moduleData !== "object") {
    return [];
  }
  var keys = Object.keys(moduleData);
  var out = [];
  for (var i = 0; i < keys.length; i++) {
    var key = keys[i];
    if (key.indexOf("user_") !== 0) {
      continue;
    }
    var item = moduleData[key] || {};
    var presentCount = Number(item.present || 0);
    var absentCount = Number(item.absent || 0);
    var statusKey = presentCount >= absentCount ? "present" : "absent";
    out.push({
      initial: String(item.user_name || "?").trim().charAt(0).toUpperCase() || "?",
      employeeName: item.user_name || "Unknown",
      team: "Archive",
      checkIn: "-",
      checkOut: "-",
      checkInTime24: "00:00",
      break: "-",
      late: String(item.total_late_minutes || 0) + " min",
      overtime: "-",
      productionLabel: "-",
      productionBadgeClass: "danger",
      statusKey: statusKey,
      statusLabel: statusKey === "present" ? "Present" : "Absent",
      workDate: dateYmd,
    });
  }
  return out;
}

export function normalizeArchiveAttendanceSummary(snapshotPayload) {
  var moduleData = snapshotPayload && snapshotPayload.dataByModule ? snapshotPayload.dataByModule.attendance : null;
  var summary = moduleData && moduleData.summary ? moduleData.summary : {};
  var byStatus = moduleData && moduleData.by_status ? moduleData.by_status : {};
  var present = Number(byStatus.present || 0);
  var absent = Number(byStatus.absent || 0);
  var total = Number(summary.total_records || present + absent);
  return {
    totalEmployees: total,
    present: present,
    absent: absent,
    lateLogin: 0,
    permission: 0,
    uninformed: 0,
  };
}

export { formatIsoDate };
