if ($('#calendar').length > 0) {
    document.addEventListener('DOMContentLoaded', function () {
        var calendarInstance = null;
        var selectedCalendarEvent = null;

        function esc(v) {
            return String(v || '')
                .replace(/&/g, '&amp;').replace(/</g, '&lt;')
                .replace(/>/g, '&gt;').replace(/\"/g, '&quot;').replace(/'/g, '&#39;');
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

        function getAuthToken() {
            return (window.AuthApi && typeof window.AuthApi.getToken === 'function' && window.AuthApi.getToken())
                || localStorage.getItem('arcav_access_token')
                || null;
        }

        function handleApiUnauthorized(status, data) {
            if (status === 401 && window.AuthApi && typeof window.AuthApi.handleUnauthorizedFromApi === 'function') {
                window.AuthApi.handleUnauthorizedFromApi(status, data);
            }
        }

        function requestJson(method, url, body) {
            var token = getAuthToken();
            var headers = calTenantHeaders({
                'Accept': 'application/json',
                'Content-Type': 'application/json'
            });
            if (token) { headers['Authorization'] = 'Bearer ' + token; }

            if (window.axios) {
                return window.axios({ method: method, url: url, data: body, headers: headers, withCredentials: true })
                    .then(function (res) { return { ok: true, data: res.data }; })
                    .catch(function (err) {
                        var status = err && err.response ? err.response.status : 0;
                        var data = err && err.response ? err.response.data : null;
                        handleApiUnauthorized(status, data);
                        return { ok: false, status: status, data: data };
                    });
            }

            return fetch(url, {
                method: method,
                headers: headers,
                credentials: 'same-origin',
                body: body ? JSON.stringify(body) : undefined,
            }).then(function (res) {
                return res.json().catch(function () { return null; }).then(function (json) {
                    if (!res.ok) { handleApiUnauthorized(res.status, json); }
                    return { ok: res.ok, status: res.status, data: json };
                });
            }).catch(function () {
                return { ok: false, status: 0, data: null };
            });
        }

        function calApiGet(url) {
            return requestJson('GET', url).then(function (res) {
                return res.ok ? res.data : null;
            });
        }

        function parseIsoToInputs(iso) {
            if (!iso) return { date: '', time: '' };
            var d = new Date(iso);
            if (isNaN(d.getTime())) return { date: '', time: '' };
            var year = d.getFullYear();
            var month = String(d.getMonth() + 1).padStart(2, '0');
            var day = String(d.getDate()).padStart(2, '0');
            var hour = String(d.getHours()).padStart(2, '0');
            var minute = String(d.getMinutes()).padStart(2, '0');
            return {
                date: year + '-' + month + '-' + day,
                time: hour + ':' + minute,
            };
        }

        function buildDateTime(dateInput, timeInput) {
            if (!dateInput) return null;
            var t = timeInput || '00:00';
            return dateInput + 'T' + t + ':00';
        }

        function formatEventDate(iso) {
            if (!iso) return '-';
            var d = new Date(iso);
            if (isNaN(d.getTime())) return '-';
            return d.toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' });
        }

        function formatEventTime(iso) {
            if (!iso) return '-';
            var d = new Date(iso);
            if (isNaN(d.getTime())) return '-';
            return d.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });
        }

        function setElementText(id, value) {
            var el = document.getElementById(id);
            if (el) el.innerHTML = value;
        }

        function showModalById(id) {
            var el = document.getElementById(id);
            if (!el) return;
            if (window.$ && $.fn && $.fn.modal) {
                $('#' + id).modal('show');
                return;
            }
            if (window.bootstrap) {
                new bootstrap.Modal(el).show();
            }
        }

        function hideModalById(id) {
            var el = document.getElementById(id);
            if (!el) return;
            if (window.$ && $.fn && $.fn.modal) {
                $('#' + id).modal('hide');
                return;
            }
            if (window.bootstrap && bootstrap.Modal) {
                var existing = bootstrap.Modal.getInstance(el);
                if (existing) {
                    existing.hide();
                    return;
                }
                new bootstrap.Modal(el).hide();
            }
        }

        function renderUpcomingEvents(events) {
            var container = document.getElementById('upcoming-events-list');
            if (!container) return;

            var today = new Date();
            today.setHours(0, 0, 0, 0);

            var upcoming = events
                .filter(function (ev) {
                    var d = new Date(ev.start);
                    d.setHours(0, 0, 0, 0);
                    return d >= today;
                })
                .sort(function (a, b) { return new Date(a.start) - new Date(b.start); })
                .slice(0, 5);

            var countEl = document.getElementById('upcoming-events-count');
            if (countEl) countEl.textContent = upcoming.length;

            if (!upcoming.length) {
                container.innerHTML = '<p class="text-muted fs-12 mb-0">Tidak ada acara mendatang.</p>';
                return;
            }

            var borderMap = {
                holiday: 'border-success',
                approved: 'border-warning',
                pending: 'border-primary',
                custom: 'border-info'
            };

            container.innerHTML = upcoming.map(function (ev) {
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

        function mapHolidayEvents(payload) {
            var events = [];
            if (!(payload && payload.success && Array.isArray(payload.data))) return events;

            payload.data.forEach(function (h) {
                if (!h.isActive) return;
                events.push({
                    id: 'holiday-' + (h.id || h.holidayDate || Math.random()),
                    title: h.title,
                    start: h.holidayDate,
                    allDay: true,
                    editable: false,
                    _calType: 'holiday',
                    _sourceType: 'holiday',
                    backgroundColor: '#d1fae5',
                    borderColor: '#059669',
                    textColor: '#065f46',
                });
            });

            return events;
        }

        function mapLeaveEvents(payload, isAdmin) {
            var events = [];
            if (!(payload && payload.success)) return events;

            var leaveRows = Array.isArray(payload.data)
                ? payload.data
                : (payload.data && Array.isArray(payload.data.data) ? payload.data.data : []);

            leaveRows.forEach(function (r) {
                if (r.status === 'declined') return;

                var isApproved = r.status === 'approved';
                var label = r.leaveTypeLabel || r.leaveType || 'Cuti';
                var name = isAdmin && r.employeeName ? ' - ' + r.employeeName : '';
                var endDate;

                if (r.dateTo) {
                    var dt = new Date(r.dateTo);
                    dt.setDate(dt.getDate() + 1);
                    endDate = dt.toISOString().slice(0, 10);
                }

                events.push({
                    id: 'leave-' + (r.id || r.uuid || Math.random()),
                    title: label + name,
                    start: r.dateFrom,
                    end: endDate,
                    allDay: true,
                    editable: false,
                    _calType: r.status,
                    _sourceType: 'leave',
                    backgroundColor: isApproved ? '#fef3c7' : '#e0e7ff',
                    borderColor: isApproved ? '#d97706' : '#6366f1',
                    textColor: isApproved ? '#92400e' : '#3730a3',
                });
            });

            return events;
        }

        function mapCustomEvents(payload) {
            var events = [];
            if (!(payload && payload.success && Array.isArray(payload.data))) return events;

            payload.data.forEach(function (row) {
                events.push({
                    id: 'custom-' + row.id,
                    title: row.title,
                    start: row.startAt,
                    end: row.endAt || undefined,
                    allDay: !!row.allDay,
                    editable: true,
                    _calType: 'custom',
                    _sourceType: 'custom',
                    _calendarEventId: row.id,
                    _description: row.description || '',
                    _location: row.location || '',
                    backgroundColor: '#dbeafe',
                    borderColor: '#2563eb',
                    textColor: '#1e3a8a',
                });
            });

            return events;
        }

        function buildCalendarEvents(holidayPayload, leavePayload, customPayload, isAdmin) {
            var holidayEvents = mapHolidayEvents(holidayPayload);
            var leaveEvents = mapLeaveEvents(leavePayload, isAdmin);
            var customEvents = mapCustomEvents(customPayload);
            return holidayEvents.concat(leaveEvents, customEvents);
        }

        function renderCalendar(events) {
            if (!calendarInstance) {
                var containerEl = document.getElementById('external-events');
                if (containerEl && window.FullCalendar && FullCalendar.Draggable) {
                    new FullCalendar.Draggable(containerEl, {
                        itemSelector: '.fc-event',
                        eventData: function (eventEl) {
                            return {
                                title: eventEl.innerText.trim(),
                                classNames: [eventEl.getAttribute('data-event-classname')],
                            };
                        }
                    });
                }

                var calendarEl = document.getElementById('calendar');
                calendarInstance = new FullCalendar.Calendar(calendarEl, {
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
                    eventClick: onEventClick,
                    eventDrop: onEventDrop,
                    eventResize: onEventResize,
                });
                calendarInstance.render();
                return;
            }

            calendarInstance.removeAllEvents();
            events.forEach(function (ev) {
                calendarInstance.addEvent(ev);
            });
        }

        function setViewModal(eventObj) {
            var startIso = eventObj.start ? eventObj.start.toISOString() : '';
            var endIso = eventObj.end ? eventObj.end.toISOString() : '';

            selectedCalendarEvent = {
                id: eventObj.extendedProps._calendarEventId,
                title: eventObj.title,
                location: eventObj.extendedProps._location || '',
                description: eventObj.extendedProps._description || '',
                startAt: startIso,
                endAt: endIso,
                sourceType: eventObj.extendedProps._sourceType,
            };

            setElementText('eventTitle', esc(eventObj.title || '-'));
            setElementText('calendar-event-view-date', '<i class="ti ti-calendar-check text-default me-2"></i>' + esc(formatEventDate(startIso)));
            setElementText('calendar-event-view-time', '<i class="ti ti-clock text-default me-2"></i>' + esc(formatEventTime(startIso) + (endIso ? ' - ' + formatEventTime(endIso) : '')));
            setElementText('calendar-event-view-location', '<i class="ti ti-map-pin-bolt text-default me-2"></i>' + esc(selectedCalendarEvent.location || '-'));
            setElementText('calendar-event-view-description', '<i class="ti ti-notes text-default me-2"></i>' + esc(selectedCalendarEvent.description || '-'));

            var editBtn = document.getElementById('calendar-event-edit-btn');
            var deleteBtn = document.getElementById('calendar-event-delete-btn');
            var canManage = selectedCalendarEvent.sourceType === 'custom';

            if (editBtn) editBtn.style.display = canManage ? '' : 'none';
            if (deleteBtn) deleteBtn.style.display = canManage ? '' : 'none';
        }

        function onEventClick(info) {
            setViewModal(info.event);
            showModalById('event_modal');
        }

        function onEventDrop(info) {
            var sourceType = info.event.extendedProps._sourceType;
            var eventId = info.event.extendedProps._calendarEventId;
            if (sourceType !== 'custom' || !eventId) {
                info.revert();
                return;
            }

            var payload = {
                startAt: info.event.start ? info.event.start.toISOString() : null,
                endAt: info.event.end ? info.event.end.toISOString() : null,
            };

            requestJson('PUT', '/v1/hcm/calendar/events/' + eventId, payload).then(function (res) {
                if (!res.ok || !(res.data && res.data.success)) {
                    info.revert();
                }
            });
        }

        function onEventResize(info) {
            var sourceType = info.event.extendedProps._sourceType;
            var eventId = info.event.extendedProps._calendarEventId;
            if (sourceType !== 'custom' || !eventId) {
                info.revert();
                return;
            }

            var payload = {
                startAt: info.event.start ? info.event.start.toISOString() : null,
                endAt: info.event.end ? info.event.end.toISOString() : null,
            };

            requestJson('PUT', '/v1/hcm/calendar/events/' + eventId, payload).then(function (res) {
                if (!res.ok || !(res.data && res.data.success)) {
                    info.revert();
                }
            });
        }

        function resetCalendarEventForm() {
            var idEl = document.getElementById('calendar-event-id');
            var titleEl = document.getElementById('calendar-event-title');
            var dateEl = document.getElementById('calendar-event-date');
            var startTimeEl = document.getElementById('calendar-event-start-time');
            var endTimeEl = document.getElementById('calendar-event-end-time');
            var locationEl = document.getElementById('calendar-event-location');
            var descEl = document.getElementById('calendar-event-description');
            var modalTitleEl = document.getElementById('calendar-event-modal-title');
            var submitEl = document.getElementById('calendar-event-submit');

            if (idEl) idEl.value = '';
            if (titleEl) titleEl.value = '';
            if (dateEl) dateEl.value = '';
            if (startTimeEl) startTimeEl.value = '';
            if (endTimeEl) endTimeEl.value = '';
            if (locationEl) locationEl.value = '';
            if (descEl) descEl.value = '';
            if (modalTitleEl) modalTitleEl.textContent = 'Add New Event';
            if (submitEl) submitEl.textContent = 'Save Event';
        }

        function fillCalendarEventForm(eventData) {
            var idEl = document.getElementById('calendar-event-id');
            var titleEl = document.getElementById('calendar-event-title');
            var dateEl = document.getElementById('calendar-event-date');
            var startTimeEl = document.getElementById('calendar-event-start-time');
            var endTimeEl = document.getElementById('calendar-event-end-time');
            var locationEl = document.getElementById('calendar-event-location');
            var descEl = document.getElementById('calendar-event-description');
            var modalTitleEl = document.getElementById('calendar-event-modal-title');
            var submitEl = document.getElementById('calendar-event-submit');
            var startInput = parseIsoToInputs(eventData.startAt);
            var endInput = parseIsoToInputs(eventData.endAt);

            if (idEl) idEl.value = String(eventData.id || '');
            if (titleEl) titleEl.value = eventData.title || '';
            if (dateEl) dateEl.value = startInput.date || '';
            if (startTimeEl) startTimeEl.value = startInput.time || '';
            if (endTimeEl) endTimeEl.value = endInput.time || '';
            if (locationEl) locationEl.value = eventData.location || '';
            if (descEl) descEl.value = eventData.description || '';
            if (modalTitleEl) modalTitleEl.textContent = 'Edit Event';
            if (submitEl) submitEl.textContent = 'Update Event';
        }

        function confirmDeleteEvent() {
            if (window.ArcavUi && typeof window.ArcavUi.confirmDelete === 'function') {
                return window.ArcavUi.confirmDelete('Hapus event kalender ini?', 'Delete Event');
            }
            return Promise.resolve(true);
        }

        function loadCalendarData() {
            var isAdmin = window.AuthUser && (window.AuthUser.isHcmAdmin || window.AuthUser.hcmGlobalAdmin);
            var leaveUrl = '/v1/hcm/leave-requests?perPage=100' + (isAdmin ? '' : '&scope=me');

            return Promise.all([
                calApiGet('/v1/hcm/holidays'),
                calApiGet(leaveUrl),
                calApiGet('/v1/hcm/calendar/events'),
            ]).then(function (results) {
                var allEvents = buildCalendarEvents(results[0], results[1], results[2], isAdmin);
                renderCalendar(allEvents);
                renderUpcomingEvents(allEvents);
            }).catch(function () {
                renderCalendar([]);
                renderUpcomingEvents([]);
            });
        }

        function setupCreateButton() {
            var triggers = document.querySelectorAll('[data-bs-target="#add_event"]');
            if (!triggers || !triggers.length) return;

            Array.prototype.forEach.call(triggers, function (trigger) {
                trigger.addEventListener('click', function () {
                    resetCalendarEventForm();
                });
            });
        }

        function setupSaveHandler() {
            var submit = document.getElementById('calendar-event-submit');
            if (!submit) return;

            submit.addEventListener('click', function () {
                var id = (document.getElementById('calendar-event-id') || {}).value || '';
                var title = ((document.getElementById('calendar-event-title') || {}).value || '').trim();
                var date = ((document.getElementById('calendar-event-date') || {}).value || '').trim();
                var startTime = ((document.getElementById('calendar-event-start-time') || {}).value || '').trim();
                var endTime = ((document.getElementById('calendar-event-end-time') || {}).value || '').trim();
                var location = ((document.getElementById('calendar-event-location') || {}).value || '').trim();
                var description = ((document.getElementById('calendar-event-description') || {}).value || '').trim();

                if (!title || !date || !startTime) {
                    return;
                }

                var payload = {
                    title: title,
                    location: location || null,
                    description: description || null,
                    startAt: buildDateTime(date, startTime),
                    endAt: endTime ? buildDateTime(date, endTime) : null,
                    allDay: false,
                };

                var method = id ? 'PUT' : 'POST';
                var url = id ? '/v1/hcm/calendar/events/' + id : '/v1/hcm/calendar/events';

                requestJson(method, url, payload).then(function (res) {
                    if (!res.ok || !(res.data && res.data.success)) {
                        return;
                    }
                    hideModalById('add_event');
                    loadCalendarData();
                });
            });
        }

        function setupEditDeleteHandlers() {
            var editBtn = document.getElementById('calendar-event-edit-btn');
            var deleteBtn = document.getElementById('calendar-event-delete-btn');

            if (editBtn) {
                editBtn.addEventListener('click', function () {
                    if (!selectedCalendarEvent || selectedCalendarEvent.sourceType !== 'custom') return;
                    hideModalById('event_modal');
                    fillCalendarEventForm(selectedCalendarEvent);
                    showModalById('add_event');
                });
            }

            if (deleteBtn) {
                deleteBtn.addEventListener('click', function () {
                    if (!selectedCalendarEvent || selectedCalendarEvent.sourceType !== 'custom' || !selectedCalendarEvent.id) return;
                    confirmDeleteEvent().then(function (ok) {
                        if (!ok) return;

                        requestJson('DELETE', '/v1/hcm/calendar/events/' + selectedCalendarEvent.id).then(function (res) {
                            if (!res.ok || !(res.data && res.data.success)) {
                                return;
                            }
                            hideModalById('event_modal');
                            loadCalendarData();
                        });
                    });
                });
            }
        }

        setupCreateButton();
        setupSaveHandler();
        setupEditDeleteHandlers();
        loadCalendarData();
    });
}

if ($('#calendar1').length > 0) {
    document.addEventListener('DOMContentLoaded', function () {
        var todayDate = moment().startOf('day');
        var TODAY = todayDate.format('YYYY-MM-DD');

        var calendarEl = document.getElementById('calendar');
        var calendar = new FullCalendar.Calendar(calendarEl, {
            headerToolbar: {
                left: 'prev,next today',
                center: 'title',
                right: 'dayGridMonth,timeGridWeek,timeGridDay,listMonth'
            },
            height: 500,
            contentHeight: 580,
            aspectRatio: 3,
            views: {
                dayGridMonth: { buttonText: 'month' },
                timeGridWeek: { buttonText: 'week' },
                timeGridDay: { buttonText: 'day' }
            },
            initialView: 'dayGridMonth',
            initialDate: TODAY,
            editable: true,
            dayMaxEvents: true,
            navLinks: true,
            events: []
        });

        calendar.render();
    });
}
