export function createScheduleCalendarModule(deps) {
  var getScheduleTimingView = deps.getScheduleTimingView;
  var getScheduleCalendar = deps.getScheduleCalendar;
  var setScheduleCalendar = deps.setScheduleCalendar;
  var getScheduleHolidayRowsCache = deps.getScheduleHolidayRowsCache;
  var setScheduleHolidayRowsCache = deps.setScheduleHolidayRowsCache;
  var getSmartPlannerAssignmentByUserId = deps.getSmartPlannerAssignmentByUserId;
  var getSmartPlannerScopeMeta = deps.getSmartPlannerScopeMeta;
  var plannerShiftMeta = deps.plannerShiftMeta;
  var apiGet = deps.apiGet;

  function scheduleDateIso(value) {
    var raw = String(value || "").trim();
    if (!raw) {
      return "";
    }
    if (/^\d{4}-\d{2}-\d{2}$/.test(raw)) {
      return raw;
    }
    var parsed = new Date(raw);
    if (isNaN(parsed.getTime())) {
      return "";
    }
    return parsed.toISOString().slice(0, 10);
  }

  function scheduleEventDateTime(dateIso, timeValue) {
    var datePart = scheduleDateIso(dateIso);
    var timePart = String(timeValue || "").trim();
    if (!datePart || !timePart) {
      return "";
    }
    if (/^\d{2}:\d{2}$/.test(timePart)) {
      return datePart + "T" + timePart + ":00";
    }
    if (/^\d{2}:\d{2}:\d{2}$/.test(timePart)) {
      return datePart + "T" + timePart;
    }
    return "";
  }

  function scheduleCalendarLoading(message, isError) {
    var box = document.querySelector("[data-schedule-calendar-loading]");
    var wrap = document.querySelector("[data-schedule-calendar-wrap]");
    if (box) {
      box.textContent = String(message || "");
      box.classList.remove("d-none", "alert-light", "alert-danger");
      box.classList.add(isError ? "alert-danger" : "alert-light");
    }
    if (wrap && isError) {
      wrap.classList.add("d-none");
      wrap.removeAttribute("data-hydrated");
    }
  }

  function scheduleCalendarMeta(message) {
    var meta = document.querySelector("[data-schedule-calendar-meta]");
    if (meta) {
      meta.textContent = String(message || "");
    }
  }

  function buildScheduleCalendarEvents() {
    var events = [];
    var holidayCount = 0;
    var draftCount = 0;

    (Array.isArray(getScheduleHolidayRowsCache()) ? getScheduleHolidayRowsCache() : []).forEach(function (holiday) {
      if (!holiday || holiday.isActive === false) {
        return;
      }
      var holidayDate = scheduleDateIso(holiday.holidayDate);
      if (!holidayDate) {
        return;
      }
      holidayCount += 1;
      events.push({
        id: "holiday-bg-" + String(holiday.id || holidayDate),
        start: holidayDate,
        allDay: true,
        display: "background",
        backgroundColor: "rgba(220, 38, 38, 0.12)",
        classNames: ["event-holiday-background"],
      });
      events.push({
        id: "holiday-" + String(holiday.id || holidayDate),
        title: "Holiday: " + String(holiday.title || "Holiday"),
        start: holidayDate,
        allDay: true,
        color: "#dc2626",
        textColor: "#ffffff",
        extendedProps: {
          eventType: "holiday",
        },
      });
    });

    var plannerMap = getSmartPlannerAssignmentByUserId() || {};
    Object.keys(plannerMap).forEach(function (userId) {
      var planner = plannerMap[userId];
      if (!planner || !Array.isArray(planner.assignments)) {
        return;
      }
      planner.assignments.forEach(function (assignment, idx) {
        var dateIso = scheduleDateIso(assignment && assignment.date);
        if (!dateIso) {
          return;
        }

        var shiftId = String((assignment && assignment.shift_id) || "");
        if (shiftId === "OFF") {
          draftCount += 1;
          events.push({
            id: "draft-off-" + String(userId) + "-" + String(idx),
            title: String(planner.employeeName || "Employee") + " OFF",
            start: dateIso,
            allDay: true,
            color: "#6b7280",
            textColor: "#ffffff",
            extendedProps: {
              eventType: "draft",
              shiftLabel: "OFF",
              employeeName: String(planner.employeeName || "Employee"),
            },
          });
          return;
        }

        var meta = plannerShiftMeta(assignment);
        var startDateTime = scheduleEventDateTime(dateIso, assignment && assignment.start_time);
        var endDateTime = scheduleEventDateTime(dateIso, assignment && assignment.end_time);
        if (!startDateTime) {
          return;
        }
        if (endDateTime && endDateTime <= startDateTime) {
          var endDate = new Date(endDateTime);
          if (!isNaN(endDate.getTime())) {
            endDate.setDate(endDate.getDate() + 1);
            endDateTime = endDate.toISOString().slice(0, 19);
          }
        }

        draftCount += 1;
        events.push({
          id: "draft-" + String(userId) + "-" + String(idx),
          title: String(planner.employeeName || "Employee") + " " + meta.title,
          start: startDateTime,
          end: endDateTime || undefined,
          allDay: false,
          color: meta.color,
          textColor: meta.textColor,
          extendedProps: {
            eventType: "draft",
            shiftLabel: meta.label,
            employeeName: String(planner.employeeName || "Employee"),
          },
        });
      });
    });

    return {
      events: events,
      holidayCount: holidayCount,
      draftCount: draftCount,
    };
  }

  function ensureScheduleCalendar() {
    var activeCalendar = getScheduleCalendar();
    if (activeCalendar) {
      return true;
    }

    var el = document.querySelector("[data-schedule-calendar]");
    if (!el) {
      return false;
    }

    if (!window.FullCalendar || !window.FullCalendar.Calendar) {
      scheduleCalendarLoading("Calendar library belum tersedia di halaman ini.", true);
      return false;
    }

    activeCalendar = new window.FullCalendar.Calendar(el, {
      initialView: "dayGridMonth",
      height: "auto",
      locale: "id",
      headerToolbar: {
        left: "prev,next today",
        center: "title",
        right: "dayGridMonth,timeGridWeek,listWeek",
      },
      dayMaxEventRows: 3,
      eventDisplay: "block",
      eventDidMount: function (info) {
        var employee = info.event.extendedProps && info.event.extendedProps.employeeName
          ? String(info.event.extendedProps.employeeName)
          : "";
        var label = info.event.extendedProps && info.event.extendedProps.shiftLabel
          ? " (" + String(info.event.extendedProps.shiftLabel) + ")"
          : "";
        if (employee) {
          info.el.setAttribute("title", employee + label);
        }
      },
    });
    activeCalendar.render();
    setScheduleCalendar(activeCalendar);

    var loading = document.querySelector("[data-schedule-calendar-loading]");
    var wrap = document.querySelector("[data-schedule-calendar-wrap]");
    if (loading) {
      loading.classList.add("d-none");
    }
    if (wrap) {
      wrap.classList.remove("d-none");
      wrap.setAttribute("data-hydrated", "1");
    }

    return true;
  }

  function renderScheduleCalendar() {
    if (getScheduleTimingView() !== "calendar") {
      return;
    }
    if (!ensureScheduleCalendar()) {
      return;
    }

    var payload = buildScheduleCalendarEvents();
    var activeCalendar = getScheduleCalendar();
    if (!activeCalendar) {
      return;
    }
    activeCalendar.removeAllEvents();
    activeCalendar.addEventSource(payload.events);

    var scopeMeta = getSmartPlannerScopeMeta()
      ? " Scope draft: " + getSmartPlannerScopeMeta()
      : " Scope draft mengikuti filter planner terakhir.";
    scheduleCalendarMeta(
      "Holiday aktif: " +
      String(payload.holidayCount) +
      " hari, Draft shift planner: " +
      String(payload.draftCount) +
      " event." +
      scopeMeta
    );
    if (!payload.events.length) {
      scheduleCalendarLoading("Belum ada data kalender. Generate planner dulu untuk melihat draft shift.", false);
    }
  }

  function loadScheduleCalendarHolidays() {
    var path = window.location.pathname || "";
    if (path.indexOf("/schedule-timing") !== 0) {
      return;
    }
    if (!document.querySelector("[data-schedule-calendar]") && !document.querySelector("[data-schedule-view-toggle]")) {
      return;
    }
    apiGet("/v1/hcm/holidays")
      .then(function (payload) {
        if (!payload || payload.success !== true || !Array.isArray(payload.data)) {
          setScheduleHolidayRowsCache([]);
          return;
        }
        setScheduleHolidayRowsCache(payload.data);
        renderScheduleCalendar();
      })
      .catch(function () {
        setScheduleHolidayRowsCache([]);
      });
  }

  return {
    renderScheduleCalendar: renderScheduleCalendar,
    loadScheduleCalendarHolidays: loadScheduleCalendarHolidays,
  };
}