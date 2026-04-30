import { esc } from "./attendance-utils.js";

export function renderAdminMessage(msg) {
  var tbody = document.querySelector("[data-attendance-admin-body]");
  if (!tbody) {
    return;
  }
  tbody.innerHTML =
    '<tr><td class="text-center text-muted py-4">' +
    esc(msg) +
    "</td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td></tr>";
  tbody.setAttribute("data-hydrated", "1");
}

export function renderMeHistoryMessage(msg) {
  var tbody = document.querySelector("[data-attendance-me-history-body]");
  if (!tbody) {
    return;
  }
  tbody.innerHTML =
    '<tr><td class="text-center text-muted py-4">' +
    esc(msg) +
    "</td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td></tr>";
  tbody.setAttribute("data-hydrated", "1");
}

export function renderReportMessage(msg) {
  var tbody = document.querySelector("[data-attendance-report-body]");
  if (!tbody) {
    return;
  }
  tbody.innerHTML =
    '<tr><td class="text-center text-muted py-4">' +
    esc(msg) +
    "</td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td></tr>";
  tbody.setAttribute("data-hydrated", "1");
}

export function renderTimesheetsMessage(msg) {
  var tbody = document.querySelector("[data-timesheets-body]");
  if (!tbody) {
    return;
  }
  tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-4">' + esc(msg) + "</td></tr>";
  tbody.setAttribute("data-hydrated", "1");
}

export function renderScheduleTimingMessage(msg) {
  var tbody = document.querySelector("[data-schedule-timing-body]");
  if (!tbody) {
    return;
  }
  tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-4">' + esc(msg) + "</td></tr>";
  tbody.setAttribute("data-hydrated", "1");
}
