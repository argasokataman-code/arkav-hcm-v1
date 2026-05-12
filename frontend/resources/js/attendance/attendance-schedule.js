export function setupScheduleViewMode(deps) {
  var getScheduleTimingView = deps.getScheduleTimingView;
  var setScheduleTimingView = deps.setScheduleTimingView;
  var getScheduleTimingPaginationCache = deps.getScheduleTimingPaginationCache;
  var renderScheduleTimingPagination = deps.renderScheduleTimingPagination;
  var renderScheduleCalendar = deps.renderScheduleCalendar;

  var path = window.location.pathname || "";
  if (path.indexOf("/schedule-timing") !== 0) {
    return;
  }

  var toggles = Array.prototype.slice.call(document.querySelectorAll("[data-schedule-view-toggle]"));
  var listPanel = document.querySelector('[data-schedule-view-panel="list"]');
  var calendarPanel = document.querySelector('[data-schedule-view-panel="calendar"]');
  var pagination = document.querySelector("[data-schedule-timing-pagination]");
  if (!toggles.length || !listPanel || !calendarPanel) {
    return;
  }

  function applyView(view) {
    var nextView = view === "calendar" ? "calendar" : "list";
    setScheduleTimingView(nextView);

    toggles.forEach(function (btn) {
      var isActive = String(btn.getAttribute("data-schedule-view-toggle") || "") === getScheduleTimingView();
      btn.classList.toggle("active", isActive);
    });

    listPanel.classList.toggle("d-none", getScheduleTimingView() !== "list");
    calendarPanel.classList.toggle("d-none", getScheduleTimingView() !== "calendar");

    if (pagination) {
      if (getScheduleTimingView() === "calendar") {
        pagination.style.display = "none";
      } else {
        renderScheduleTimingPagination(getScheduleTimingPaginationCache());
      }
    }

    if (getScheduleTimingView() === "calendar") {
      renderScheduleCalendar();
    }
  }

  toggles.forEach(function (btn) {
    if (btn.getAttribute("data-bound") === "1") {
      return;
    }
    btn.setAttribute("data-bound", "1");
    btn.addEventListener("click", function () {
      var view = String(btn.getAttribute("data-schedule-view-toggle") || "list");
      applyView(view);
    });
  });

  applyView("list");
}

export function renderScheduleTimingPagination(deps, pagination) {
  var setScheduleTimingPaginationCache = deps.setScheduleTimingPaginationCache;
  var getScheduleTimingView = deps.getScheduleTimingView;

  setScheduleTimingPaginationCache(pagination || null);

  var foot = document.querySelector("[data-schedule-timing-pagination]");
  var info = document.querySelector("[data-schedule-timing-page-info]");
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

  foot.style.display = getScheduleTimingView() === "calendar" ? "none" : "";

  if (info) {
    var from = total === 0 ? 0 : (page - 1) * perPage + 1;
    var to = Math.min(page * perPage, total);
    info.textContent = "Menampilkan " + from + "-" + to + " dari " + total;
  }

  var prev = foot.querySelector("[data-schedule-timing-prev]");
  var next = foot.querySelector("[data-schedule-timing-next]");
  if (prev) {
    prev.disabled = page <= 1;
  }
  if (next) {
    next.disabled = page >= totalPages;
  }
}

export function setupScheduleTimingPaginationControls(deps) {
  var getScheduleTimingPage = deps.getScheduleTimingPage;
  var setScheduleTimingPage = deps.setScheduleTimingPage;
  var loadScheduleTiming = deps.loadScheduleTiming;

  var foot = document.querySelector("[data-schedule-timing-pagination]");
  if (!foot) {
    return;
  }

  var prev = foot.querySelector("[data-schedule-timing-prev]");
  var next = foot.querySelector("[data-schedule-timing-next]");

  if (prev && !prev.getAttribute("data-bound")) {
    prev.setAttribute("data-bound", "1");
    prev.addEventListener("click", function () {
      if (getScheduleTimingPage() > 1) {
        setScheduleTimingPage(getScheduleTimingPage() - 1);
        loadScheduleTiming();
      }
    });
  }

  if (next && !next.getAttribute("data-bound")) {
    next.setAttribute("data-bound", "1");
    next.addEventListener("click", function () {
      setScheduleTimingPage(getScheduleTimingPage() + 1);
      loadScheduleTiming();
    });
  }
}

export function loadScheduleTiming(deps) {
  var getScheduleTimingPage = deps.getScheduleTimingPage;
  var setScheduleTimingPage = deps.setScheduleTimingPage;
  var setScheduleTimingRowsCache = deps.setScheduleTimingRowsCache;
  var apiGet = deps.apiGet;
  var formatApiError = deps.formatApiError;
  var renderScheduleTimingMessage = deps.renderScheduleTimingMessage;
  var renderScheduleTimingRows = deps.renderScheduleTimingRows;
  var renderScheduleTimingPagination = deps.renderScheduleTimingPagination;

  var path = window.location.pathname || "";
  if (path.indexOf("/schedule-timing") !== 0) {
    return;
  }

  var tbody = document.querySelector("[data-schedule-timing-body]");
  if (tbody) {
    tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-4">Memuat data shift & schedule...</td></tr>';
    tbody.removeAttribute("data-hydrated");
  }

  var searchEl = document.querySelector("[data-schedule-timing-search]");
  var sortEl = document.querySelector("[data-schedule-timing-sort]");
  var deptFilterEl = document.querySelector("[data-schedule-timing-dept-filter]");
  var search = searchEl ? String(searchEl.value || "").trim() : "";
  var sort = sortEl ? String(sortEl.value || "name_asc") : "name_asc";
  var dept = deptFilterEl ? String(deptFilterEl.value || "").trim() : "";
  var url =
    "/v1/hcm/schedule-timing?sort=" +
    encodeURIComponent(sort) +
    "&page=" +
    encodeURIComponent(String(getScheduleTimingPage())) +
    "&perPage=50";

  if (search) {
    url += "&search=" + encodeURIComponent(search);
  }
  if (dept) {
    url += "&department=" + encodeURIComponent(dept);
  }

  apiGet(url)
    .then(function (payload) {
      if (!payload || payload.success !== true) {
        renderScheduleTimingMessage(formatApiError(payload, 0) || "Gagal memuat data shift & schedule.");
        return;
      }

      var pag = (payload.meta && payload.meta.pagination) || {};
      if (pag.totalPages != null && getScheduleTimingPage() > pag.totalPages && pag.totalPages > 0) {
        setScheduleTimingPage(pag.totalPages);
        loadScheduleTiming(deps);
        return;
      }

      var rows = Array.isArray(payload.data) ? payload.data : [];
      setScheduleTimingRowsCache(rows);
      renderScheduleTimingRows(rows);
      renderScheduleTimingPagination(pag);
      // Populate dept dropdown from loaded rows
      var deptDd = document.querySelector('[data-schedule-timing-dept-filter]');
      if (deptDd && typeof deptDd._populateDeptOptions === 'function') {
        deptDd._populateDeptOptions(rows);
      }
    })
    .catch(function (err) {
      var data = err && err.response ? err.response.data : err && err.data ? err.data : null;
      var status = err && err.response ? err.response.status : err && err.status ? err.status : 0;
      renderScheduleTimingMessage(formatApiError(data, status) || "Gagal memuat data shift & schedule.");
    });
}

export function setupScheduleTimingFilters(deps) {
  var setScheduleTimingPage = deps.setScheduleTimingPage;
  var loadScheduleTiming = deps.loadScheduleTiming;
  var setScheduleTimingAiOnly = deps.setScheduleTimingAiOnly;
  var getScheduleTimingRowsCache = deps.getScheduleTimingRowsCache;
  var renderScheduleTimingRows = deps.renderScheduleTimingRows;

  var searchEl = document.querySelector("[data-schedule-timing-search]");
  var sortEl = document.querySelector("[data-schedule-timing-sort]");
  var aiOnlyEl = document.querySelector("[data-schedule-timing-ai-only]");

  if (searchEl) {
    var timer = null;
    searchEl.addEventListener("input", function () {
      if (timer) {
        window.clearTimeout(timer);
      }
      timer = window.setTimeout(function () {
        setScheduleTimingPage(1);
        loadScheduleTiming();
      }, 250);
    });
  }

  if (sortEl) {
    sortEl.addEventListener("change", function () {
      setScheduleTimingPage(1);
      loadScheduleTiming();
    });
  }

  if (aiOnlyEl && !aiOnlyEl.getAttribute("data-bound")) {
    aiOnlyEl.setAttribute("data-bound", "1");
    aiOnlyEl.addEventListener("change", function () {
      setScheduleTimingAiOnly(!!aiOnlyEl.checked);
      renderScheduleTimingRows(getScheduleTimingRowsCache());
    });
  }

  var deptDropdownEl = document.querySelector("[data-schedule-timing-dept-filter]");
  if (deptDropdownEl && !deptDropdownEl.getAttribute("data-bound")) {
    deptDropdownEl.setAttribute("data-bound", "1");
    // Populate dept options from loaded rows
    function populateDeptOptions(rows) {
      var depts = [];
      (Array.isArray(rows) ? rows : []).forEach(function (r) {
        var d = String(r.department || '').trim();
        if (d && depts.indexOf(d) === -1) depts.push(d);
      });
      depts.sort();
      var current = deptDropdownEl.value;
      while (deptDropdownEl.options.length > 1) deptDropdownEl.remove(1);
      depts.forEach(function (d) {
        var opt = document.createElement('option');
        opt.value = d; opt.textContent = d;
        deptDropdownEl.appendChild(opt);
      });
      if (current) deptDropdownEl.value = current;
    }
    // Patch loadScheduleTiming to update dropdown after load
    var origLoad = loadScheduleTiming;
    // Expose populator for use after rows are loaded
    deptDropdownEl._populateDeptOptions = populateDeptOptions;
    deptDropdownEl.addEventListener("change", function () {
      setScheduleTimingPage(1);
      loadScheduleTiming();
    });
  }
}
