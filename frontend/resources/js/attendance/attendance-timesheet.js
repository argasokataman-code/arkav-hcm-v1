import { esc } from "./attendance-utils";
import { renderTimesheetsMessage } from "./attendance-table";

export function createAttendanceTimesheetModule(deps) {
  var apiGet = deps.apiGet;
  var formatApiError = deps.formatApiError;
  var getTimesheetDateRange = deps.getTimesheetDateRange;

  var timesheetRowsCache = [];
  var timesheetPage = 1;

  function renderTimesheetsRows(rows) {
    var tbody = document.querySelector("[data-timesheets-body]");
    if (!tbody) return;
    if (!rows.length) {
      tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-4">No timesheet rows found.</td></tr>';
      tbody.setAttribute("data-hydrated", "1");
      return;
    }
    tbody.innerHTML = rows
      .map(function (r) {
        return (
          "<tr>" +
          "<td>" +
          esc(r.employeeName) +
          "</td>" +
          "<td>" +
          esc(r.dateLabel || r.date || "-") +
          "</td>" +
          "<td>" +
          esc(r.project || "-") +
          "</td>" +
          "<td>" +
          esc(String(r.assignedHours != null ? Number(r.assignedHours).toFixed(2) : "0.00")) +
          "</td>" +
          "<td>" +
          esc(String(r.workedHours != null ? Number(r.workedHours).toFixed(2) : "0.00")) +
          "</td>" +
          '<td><span class="text-muted">-</span></td>' +
          "</tr>"
        );
      })
      .join("");
    tbody.setAttribute("data-hydrated", "1");
  }

  function renderTimesheetPagination(pagination) {
    var foot = document.querySelector("[data-timesheets-pagination]");
    var info = document.querySelector("[data-timesheets-page-info]");
    if (!foot) {
      return;
    }
    if (!pagination || pagination.total == null) {
      foot.style.display = "none";
      return;
    }
    var total = parseInt(pagination.total, 10) || 0;
    var page = parseInt(pagination.page, 10) || 1;
    var perPage = parseInt(pagination.perPage, 10) || 50;
    var totalPages = parseInt(pagination.totalPages, 10) || 1;
    if (totalPages <= 1) {
      foot.style.display = "none";
      return;
    }
    foot.style.display = "";
    if (info) {
      var from = total === 0 ? 0 : (page - 1) * perPage + 1;
      var to = Math.min(page * perPage, total);
      info.textContent = "Menampilkan " + from + "–" + to + " dari " + total;
    }
    var prev = foot.querySelector("[data-timesheets-prev]");
    var next = foot.querySelector("[data-timesheets-next]");
    if (prev) {
      prev.disabled = page <= 1;
    }
    if (next) {
      next.disabled = page >= totalPages;
    }
  }

  function setupTimesheetPaginationControls() {
    var foot = document.querySelector("[data-timesheets-pagination]");
    if (!foot) {
      return;
    }
    var prev = foot.querySelector("[data-timesheets-prev]");
    var next = foot.querySelector("[data-timesheets-next]");
    if (prev && !prev.getAttribute("data-bound")) {
      prev.setAttribute("data-bound", "1");
      prev.addEventListener("click", function () {
        if (timesheetPage > 1) {
          timesheetPage -= 1;
          loadTimesheets();
        }
      });
    }
    if (next && !next.getAttribute("data-bound")) {
      next.setAttribute("data-bound", "1");
      next.addEventListener("click", function () {
        timesheetPage += 1;
        loadTimesheets();
      });
    }
  }

  function fillTimesheetsProjectFilter(metaProjects) {
    var sel = document.querySelector("[data-timesheets-filter-project]");
    if (!sel) return;
    var prev = sel.value || "";
    var projects = Array.isArray(metaProjects) ? metaProjects : [];
    var html = ['<option value="">All projects</option>'];
    for (var i = 0; i < projects.length; i++) {
      html.push('<option value="' + esc(projects[i]) + '">' + esc(projects[i]) + "</option>");
    }
    sel.innerHTML = html.join("");
    if (prev) sel.value = prev;
  }

  function loadTimesheets() {
    var path = window.location.pathname || "";
    if (path.indexOf("/timesheets") !== 0) return;

    var tbody = document.querySelector("[data-timesheets-body]");
    if (tbody) {
      tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-4">Loading timesheets...</td></tr>';
      tbody.removeAttribute("data-hydrated");
    }

    var range = getTimesheetDateRange();
    if (range.from && range.to && range.from > range.to) {
      renderTimesheetsMessage("Date to harus sama atau setelah Date from.");
      return;
    }
    var sortSel = document.querySelector("[data-timesheets-sort]");
    var projectSel = document.querySelector("[data-timesheets-filter-project]");
    var sort = sortSel ? sortSel.value || "date_desc" : "date_desc";
    var project = projectSel ? projectSel.value || "" : "";
    var url =
      "/v1/hcm/timesheets?dateFrom=" +
      encodeURIComponent(range.from) +
      "&dateTo=" +
      encodeURIComponent(range.to) +
      "&sort=" +
      encodeURIComponent(sort) +
      "&page=" +
      encodeURIComponent(String(timesheetPage)) +
      "&perPage=50";
    if (project) url += "&project=" + encodeURIComponent(project);

    apiGet(url)
      .then(function (payload) {
        if (!payload || payload.success !== true) {
          renderTimesheetsMessage(formatApiError(payload, 0) || "Failed loading timesheets.");
          return;
        }
        var pag = (payload.meta && payload.meta.pagination) || {};
        if (pag.totalPages != null && timesheetPage > pag.totalPages && pag.totalPages > 0) {
          timesheetPage = pag.totalPages;
          loadTimesheets();
          return;
        }
        timesheetRowsCache = Array.isArray(payload.data) ? payload.data : [];
        fillTimesheetsProjectFilter(payload.meta && payload.meta.projects ? payload.meta.projects : []);
        renderTimesheetsRows(timesheetRowsCache);
        renderTimesheetPagination(pag);
      })
      .catch(function (err) {
        var data = err && err.response ? err.response.data : err && err.data ? err.data : null;
        var status = err && err.response ? err.response.status : err && err.status ? err.status : 0;
        renderTimesheetsMessage(formatApiError(data, status) || "Failed loading timesheets.");
      });
  }

  function setupTimesheetFilters() {
    var from = document.querySelector("[data-timesheets-date-from]");
    var to = document.querySelector("[data-timesheets-date-to]");
    var sort = document.querySelector("[data-timesheets-sort]");
    var proj = document.querySelector("[data-timesheets-filter-project]");
    var range = getTimesheetDateRange();
    if (from && !from.value) from.value = range.from;
    if (to && !to.value) to.value = range.to;
    function onChange() {
      timesheetPage = 1;
      loadTimesheets();
    }
    if (from) from.addEventListener("change", onChange);
    if (to) to.addEventListener("change", onChange);
    if (sort) sort.addEventListener("change", onChange);
    if (proj) proj.addEventListener("change", onChange);
  }

  function getRowsCache() {
    return timesheetRowsCache || [];
  }

  return {
    setupTimesheetFilters: setupTimesheetFilters,
    setupTimesheetPaginationControls: setupTimesheetPaginationControls,
    loadTimesheets: loadTimesheets,
    getRowsCache: getRowsCache,
  };
}
