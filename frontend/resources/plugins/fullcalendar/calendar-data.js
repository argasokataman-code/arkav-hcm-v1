
if($('#calendar').length > 0) {
    document.addEventListener('DOMContentLoaded', function() {

        // ------------- helpers -------------
        function esc(v) {
            return String(v || '')
                .replace(/&/g, '&amp;').replace(/</g, '&lt;')
                .replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#39;');
        }

        function getTenantCtx() {
            if (window.AuthApi && typeof window.AuthApi.getTenantContext === 'function') {
                return window.AuthApi.getTenantContext() || {};
            }
            return {};
        }

        function calTenantHeaders(h) {
            var ctx = getTenantCtx();
            if (ctx.companyCode) h['X-Company-Code'] = String(ctx.companyCode);
            if (ctx.companyId != null && ctx.companyId !== '') h['X-Company-Id'] = String(ctx.companyId);
            if (ctx.companyUuid) h['X-Company-UUID'] = String(ctx.companyUuid);
            return h;
        }

        function calApiGet(url) {
            var headers = calTenantHeaders({ 'Accept': 'application/json' });
            if (window.axios) {
                return window.axios({ method: 'GET', url: url, headers: headers, withCredentials: true })
                    .then(function(res) { return res.data; })
                    .catch(function() { return null; });
            }
            return fetch(url, { method: 'GET', headers: headers, credentials: 'same-origin' })
                .then(function(res) { return res.ok ? res.json().catch(function() { return null; }) : null; })
                .catch(function() { return null; });
        }

        // ------------- calendar init -------------
        function initCalendar(events) {
            var containerEl = document.getElementById('external-events');
            if (containerEl && window.FullCalendar && FullCalendar.Draggable) {
                new FullCalendar.Draggable(containerEl, {
                    itemSelector: '.fc-event',
                    eventData: function(eventEl) {
                        return {
                            title: eventEl.innerText.trim(),
                            classNames: [eventEl.getAttribute('data-event-classname')],
                        };
                    }
                });
            }

            var calendarEl = document.getElementById('calendar');
            var calendar = new FullCalendar.Calendar(calendarEl, {
                headerToolbar: {
                    left: 'prev,next today',
                    center: 'title',
                    right: 'dayGridMonth,timeGridWeek,timeGridDay'
                },
                initialView: 'dayGridMonth',
                editable: true,
                droppable: true,
                dayMaxEvents: true,
                navLinks: true,
                events: events,
                eventClick: function(info) {
                    var modal = document.getElementById('event_modal');
                    var titleEl = document.getElementById('eventTitle');
                    if (modal && titleEl) {
                        titleEl.innerText = info.event.title;
                        if (window.$ && $.fn && $.fn.modal) {
                            $('#event_modal').modal('show');
                        } else if (window.bootstrap) {
                            new bootstrap.Modal(modal).show();
                        }
                    }
                }
            });
            calendar.render();
        }

        // ------------- upcoming events sidebar -------------
        function renderUpcomingEvents(events) {
            var container = document.getElementById('upcoming-events-list');
            if (!container) return;

            var today = new Date();
            today.setHours(0, 0, 0, 0);

            var upcoming = events
                .filter(function(ev) {
                    var d = new Date(ev.start);
                    d.setHours(0, 0, 0, 0);
                    return d >= today;
                })
                .sort(function(a, b) { return new Date(a.start) - new Date(b.start); })
                .slice(0, 5);

            var countEl = document.getElementById('upcoming-events-count');
            if (countEl) countEl.textContent = upcoming.length;

            if (!upcoming.length) {
                container.innerHTML = '<p class="text-muted fs-12 mb-0">Tidak ada acara mendatang.</p>';
                return;
            }

            var borderMap = { holiday: 'border-success', approved: 'border-warning', pending: 'border-primary' };
            container.innerHTML = upcoming.map(function(ev) {
                var d = new Date(ev.start);
                var dateStr = d.toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' });
                var borderClass = borderMap[ev._calType] || 'border-purple';
                return '<div class="border-start ' + borderClass + ' border-3 mb-3">' +
                    '<div class="ps-3">' +
                    '<h6 class="fw-medium mb-1">' + esc(ev.title) + '</h6>' +
                    '<p class="fs-12 mb-0"><i class="ti ti-calendar-check text-info me-2"></i>' + dateStr + '</p>' +
                    '</div></div>';
            }).join('');
        }

        // ------------- load real data -------------
        var isAdmin = window.AuthUser && (window.AuthUser.isHcmAdmin || window.AuthUser.hcmGlobalAdmin);
        var leaveUrl = '/v1/hcm/leave-requests?perPage=100' + (isAdmin ? '' : '&scope=me');

        Promise.all([
            calApiGet('/v1/hcm/holidays'),
            calApiGet(leaveUrl),
        ]).then(function(results) {
            var calEvents = [];

            // holidays — green
            var holidayPayload = results[0];
            if (holidayPayload && holidayPayload.success && Array.isArray(holidayPayload.data)) {
                holidayPayload.data.forEach(function(h) {
                    if (!h.isActive) return;
                    calEvents.push({
                        title: h.title,
                        start: h.holidayDate,
                        allDay: true,
                        _calType: 'holiday',
                        backgroundColor: '#d1fae5',
                        borderColor: '#059669',
                        textColor: '#065f46',
                    });
                });
            }

            // leave requests — yellow (approved) / indigo (pending)
            var leavePayload = results[1];
            if (leavePayload && leavePayload.success) {
                var leaveRows = Array.isArray(leavePayload.data)
                    ? leavePayload.data
                    : (leavePayload.data && Array.isArray(leavePayload.data.data) ? leavePayload.data.data : []);
                leaveRows.forEach(function(r) {
                    if (r.status === 'declined') return;
                    var isApproved = r.status === 'approved';
                    var label = r.leaveTypeLabel || r.leaveType || 'Cuti';
                    var name = isAdmin && r.employeeName ? ' – ' + r.employeeName : '';
                    // FullCalendar end date is exclusive — add 1 day
                    var endDate;
                    if (r.dateTo) {
                        var dt = new Date(r.dateTo);
                        dt.setDate(dt.getDate() + 1);
                        endDate = dt.toISOString().slice(0, 10);
                    }
                    calEvents.push({
                        title: label + name,
                        start: r.dateFrom,
                        end: endDate,
                        allDay: true,
                        _calType: r.status,
                        backgroundColor: isApproved ? '#fef3c7' : '#e0e7ff',
                        borderColor: isApproved ? '#d97706' : '#6366f1',
                        textColor: isApproved ? '#92400e' : '#3730a3',
                    });
                });
            }

            initCalendar(calEvents);
            renderUpcomingEvents(calEvents);
        }).catch(function() {
            initCalendar([]);
        });
    });
}



if($('#calendar1').length > 0) {

    document.addEventListener('DOMContentLoaded', function() {
        var todayDate = moment().startOf('day');
        var YM = todayDate.format('YYYY-MM');
        var YESTERDAY = todayDate.clone().subtract(1, 'day').format('YYYY-MM-DD');
        var TODAY = todayDate.format('YYYY-MM-DD');
        var TOMORROW = todayDate.clone().add(1, 'day').format('YYYY-MM-DD');

        var calendarEl = document.getElementById('calendar');
        var calendar = new FullCalendar.Calendar(calendarEl, {
            headerToolbar: {
                left: 'prev,next today',
                center: 'title',
                right: 'dayGridMonth,timeGridWeek,timeGridDay'
            },
            headerToolbar: {
                left: 'prev,next today',
                center: 'title',
                right: 'dayGridMonth,timeGridWeek,timeGridDay,listMonth'
            },

            height: 500,
            contentHeight: 580,
            aspectRatio: 3,  // see: redacted-url


            views: {
                dayGridMonth: { buttonText: 'month' },
                timeGridWeek: { buttonText: 'week' },
                timeGridDay: { buttonText: 'day' }
            },

            initialView: 'dayGridMonth',
            initialDate: TODAY,

            editable: true,
            dayMaxEvents: true, // allow "more" link when too many events
            navLinks: true,
            events: [
                {
                    title: 'All Day Event',
                    start: new Date($.now() - 168000000).toJSON().slice(0, 10),
                    backgroundColor: '#FDE9ED'
                },
                {
                    id: 1000,
                    title: 'Repeating Event',
                    start: new Date($.now() - 338000000).toJSON().slice(0, 10) 
                },
                {
                    title: 'Meeting',
                    start: new Date($.now() - 338000000).toJSON().slice(0, 10)
                },
                {
                    title: 'Click for Google',
                    start: new Date($.now() + 68000000).toJSON().slice(0, 10),
                    className: "bg-secondary text-white" 
                }
            ]
        });

        calendar.render();
    });
}
