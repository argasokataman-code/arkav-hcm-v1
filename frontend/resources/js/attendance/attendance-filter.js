import { esc } from "./attendance-utils";

export function parseTimeToMinutes(v) {
  if (!v || typeof v !== "string") {
    return -1;
  }
  var p = v.split(":");
  if (p.length !== 2) {
    return -1;
  }
  var h = parseInt(p[0], 10);
  var m = parseInt(p[1], 10);
  if (isNaN(h) || isNaN(m)) {
    return -1;
  }
  return h * 60 + m;
}

export function parseProductionHours(row) {
  if (!row || typeof row.productionLabel !== "string") {
    return 0;
  }
  var n = parseFloat(String(row.productionLabel).replace(/[^0-9.]/g, ""));
  return isNaN(n) ? 0 : n;
}

export function getAdminFilters() {
  var depSel = document.querySelector("[data-attendance-admin-filter-department]");
  var statusSel = document.querySelector("[data-attendance-admin-filter-status]");
  var sortSel = document.querySelector("[data-attendance-admin-sort]");
  return {
    department: depSel ? String(depSel.value || "").trim() : "",
    status: statusSel ? String(statusSel.value || "").trim().toLowerCase() : "",
    sort: sortSel ? String(sortSel.value || "name_asc") : "name_asc",
  };
}

export function fillAdminDepartmentFilter(rowsOrDeptList) {
  var depSel = document.querySelector("[data-attendance-admin-filter-department]");
  if (!depSel) {
    return;
  }
  var prev = depSel.value || "";
  var deps;
  if (Array.isArray(rowsOrDeptList) && rowsOrDeptList.length && typeof rowsOrDeptList[0] === "string") {
    deps = rowsOrDeptList.slice().sort(function (a, b) {
      return String(a).localeCompare(String(b));
    });
  } else {
    var rows = rowsOrDeptList || [];
    var map = {};
    for (var i = 0; i < rows.length; i++) {
      var team = String(rows[i].team || "").trim();
      if (!team) {
        continue;
      }
      map[team] = true;
    }
    deps = Object.keys(map).sort(function (a, b) {
      return a.localeCompare(b);
    });
  }
  var html = ['<option value="">All departments</option>'];
  for (var j = 0; j < deps.length; j++) {
    html.push('<option value="' + esc(deps[j]) + '">' + esc(deps[j]) + "</option>");
  }
  depSel.innerHTML = html.join("");
  if (prev && deps.indexOf(prev) !== -1) {
    depSel.value = prev;
  }
}

export function filterAndSortAdminRows(rows) {
  var filters = getAdminFilters();
  var out = rows.filter(function (row) {
    if (filters.department) {
      var team = String(row.team || "").trim();
      if (team !== filters.department) {
        return false;
      }
    }
    if (filters.status) {
      var key = String(row.statusKey || "").trim().toLowerCase();
      if (key !== filters.status) {
        return false;
      }
    }
    return true;
  });

  out.sort(function (a, b) {
    if (filters.sort === "name_desc") {
      return String(b.employeeName || "").localeCompare(String(a.employeeName || ""));
    }
    if (filters.sort === "checkin_asc") {
      return parseTimeToMinutes(a.checkInTime24) - parseTimeToMinutes(b.checkInTime24);
    }
    if (filters.sort === "checkin_desc") {
      return parseTimeToMinutes(b.checkInTime24) - parseTimeToMinutes(a.checkInTime24);
    }
    if (filters.sort === "production_desc") {
      return parseProductionHours(b) - parseProductionHours(a);
    }
    if (filters.sort === "production_asc") {
      return parseProductionHours(a) - parseProductionHours(b);
    }
    return String(a.employeeName || "").localeCompare(String(b.employeeName || ""));
  });

  return out;
}

export function getReportFilters() {
  var depSel = document.querySelector("[data-attendance-report-filter-department]");
  var statusSel = document.querySelector("[data-attendance-report-filter-status]");
  var sortSel = document.querySelector("[data-attendance-report-sort]");
  return {
    department: depSel ? String(depSel.value || "").trim().toLowerCase() : "",
    status: statusSel ? String(statusSel.value || "").trim().toLowerCase() : "",
    sort: sortSel ? String(sortSel.value || "name_asc") : "name_asc",
  };
}

export function fillReportDepartmentFilter(rows, departments) {
  var depSel = document.querySelector("[data-attendance-report-filter-department]");
  if (!depSel) {
    return;
  }
  var prev = depSel.value || "";
  var deps;

  if (Array.isArray(departments) && departments.length) {
    deps = departments
      .map(function (d) {
        return String(d || "").trim();
      })
      .filter(function (d) {
        return !!d;
      });
  } else {
    var map = {};
    for (var i = 0; i < rows.length; i++) {
      var team = String(rows[i].team || "").trim();
      if (!team) {
        continue;
      }
      map[team] = true;
    }
    deps = Object.keys(map);
  }

  deps.sort(function (a, b) {
    return a.localeCompare(b);
  });
  var html = ['<option value="">All departments</option>'];
  for (var j = 0; j < deps.length; j++) {
    html.push('<option value="' + esc(deps[j]) + '">' + esc(deps[j]) + "</option>");
  }
  depSel.innerHTML = html.join("");
  if (prev && deps.indexOf(prev) !== -1) {
    depSel.value = prev;
  }
}

export function filterAndSortReportRows(rows) {
  var filters = getReportFilters();
  var out = rows.filter(function (row) {
    if (filters.department) {
      var team = String(row.team || "").trim().toLowerCase();
      if (team !== filters.department) {
        return false;
      }
    }
    if (filters.status) {
      var key = String(row.statusKey || "").trim().toLowerCase();
      if (key !== filters.status) {
        return false;
      }
    }
    return true;
  });

  out.sort(function (a, b) {
    if (filters.sort === "name_desc") {
      return String(b.employeeName || "").localeCompare(String(a.employeeName || ""));
    }
    if (filters.sort === "checkin_asc") {
      return parseTimeToMinutes(a.checkInTime24) - parseTimeToMinutes(b.checkInTime24);
    }
    if (filters.sort === "checkin_desc") {
      return parseTimeToMinutes(b.checkInTime24) - parseTimeToMinutes(a.checkInTime24);
    }
    if (filters.sort === "production_desc") {
      return parseProductionHours(b) - parseProductionHours(a);
    }
    if (filters.sort === "production_asc") {
      return parseProductionHours(a) - parseProductionHours(b);
    }
    return String(a.employeeName || "").localeCompare(String(b.employeeName || ""));
  });

  return out;
}

export function getTimesheetDateRange() {
  var from = document.querySelector("[data-timesheets-date-from]");
  var to = document.querySelector("[data-timesheets-date-to]");
  var now = new Date();
  var toDefault =
    now.getFullYear() +
    "-" +
    String(now.getMonth() + 1).padStart(2, "0") +
    "-" +
    String(now.getDate()).padStart(2, "0");
  var fromDt = new Date(now.getTime() - 29 * 24 * 60 * 60 * 1000);
  var fromDefault =
    fromDt.getFullYear() +
    "-" +
    String(fromDt.getMonth() + 1).padStart(2, "0") +
    "-" +
    String(fromDt.getDate()).padStart(2, "0");
  return {
    from: from && from.value ? from.value : fromDefault,
    to: to && to.value ? to.value : toDefault,
  };
}
