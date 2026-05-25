import { escapeHtml, formatEmployeeCode, buildEmployeeDetailUrl } from './helpers.js';

// Factory that returns renderer functions bound to provided deps
export function makeRenderers(deps) {
    var selectedEmployeeProfilesMap = deps.selectedEmployeeProfilesMap;
    var getSelectedEmployeeProfileIds = deps.getSelectedEmployeeProfileIds;
    var syncSelectAllCheckboxState = deps.syncSelectAllCheckboxState;
    var updateBulkSelectionUi = deps.updateBulkSelectionUi;

    function renderList(rows) {
        var tbody = document.querySelector("[data-employees-list-body]");
        if (!tbody) {
            return;
        }

        if (!rows.length) {
            tbody.innerHTML = '<tr><td class="text-center text-muted py-4">No employees found.</td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td></tr>';
            tbody.setAttribute("data-hydrated", "1");
            updateBulkSelectionUi();
            return;
        }

        tbody.innerHTML = rows.map(function (row) {
            var st = row.employmentStatus || "active";
            var statusClass = st === "active" ? "success" : st === "probation" ? "warning" : "danger";
            var teamLabel = row.teamName || row.team || "—";
            var teamLeaderLabel = row.managerName || "Belum ditentukan";
            var teamBadge = row.teamIsActive === false
                ? '<span class="badge bg-soft-warning text-warning ms-1">inactive</span>'
                : "";
            var employeeProfileId = row.employeeProfileId != null ? String(row.employeeProfileId) : "";
            var checked = employeeProfileId && selectedEmployeeProfilesMap[employeeProfileId] ? ' checked' : '';
            var nameCell = row.profilePhotoUrl
                ? '<div class="d-flex align-items-center"><span class="avatar avatar-sm me-2"><img src="' + escapeHtml(row.profilePhotoUrl) + '" alt="Photo" class="rounded-circle w-100 h-100"></span><span>' + escapeHtml(row.fullName) + '</span></div>'
                : '<div class="d-flex align-items-center"><span class="avatar avatar-sm me-2 bg-primary-subtle text-primary rounded-circle d-inline-flex align-items-center justify-content-center">' + escapeHtml((row.fullName || "?").charAt(0).toUpperCase()) + '</span><span>' + escapeHtml(row.fullName) + '</span></div>';
            return (
                '<tr data-employees-row-preview="' + escapeHtml(row.id) + '" data-employee-id="' + escapeHtml(row.id) + '" data-employee-profile-id="' + escapeHtml(employeeProfileId) + '" data-employee-team-id="' + escapeHtml(row.teamId != null ? String(row.teamId) : "") + '" class="cursor-pointer">' +
                '<td><div class="form-check form-check-md"><input class="form-check-input" type="checkbox" data-employees-select data-employee-profile-id="' + escapeHtml(employeeProfileId) + '"' + checked + '></div></td>' +
                '<td><a href="' + buildEmployeeDetailUrl(row.id) + '" data-employee-detail-link data-employee-id="' + escapeHtml(row.id) + '">' + escapeHtml(formatEmployeeCode(row.id)) + "</a></td>" +
                "<td>" + nameCell + "</td>" +
                "<td>" + escapeHtml(row.email) + "</td>" +
                "<td>" + escapeHtml(teamLabel) + teamBadge + "</td>" +
                "<td>" + escapeHtml(teamLeaderLabel) + "</td>" +
                "<td>" + escapeHtml(row.departmentName || "—") + "</td>" +
                "<td>" + escapeHtml(row.designation || "Employee") + "</td>" +
                "<td>" + escapeHtml(row.joinDate || "-") + "</td>" +
                '<td><span class="badge badge-' + statusClass + ' d-inline-flex align-items-center badge-xs"><i class="ti ti-point-filled me-1"></i>' + escapeHtml(st) + "</span></td>" +
                '<td><div class="action-icon d-inline-flex">' +
                '<a href="javascript:void(0);" class="me-2" data-employee-edit-open data-employee-id="' + escapeHtml(row.id) + '" title="Edit"><i class="ti ti-edit"></i></a>' +
                '<a href="javascript:void(0);" class="me-2 ' + (row.profilePhotoUrl ? '' : 'text-muted disabled') + '" data-employees-photo-view data-photo-url="' + escapeHtml(row.profilePhotoUrl || '') + '" data-employee-name="' + escapeHtml(row.fullName || '') + '" title="View Photo"><i class="ti ti-photo"></i></a>' +
                '<a href="' + buildEmployeeDetailUrl(row.id) + '" class="me-2" data-employee-detail-link data-employee-id="' + escapeHtml(row.id) + '"><i class="ti ti-eye"></i></a>' +
                "</div></td>" +
                "</tr>"
            );
        }).join("");
        tbody.setAttribute("data-hydrated", "1");
        syncSelectAllCheckboxState();
        updateBulkSelectionUi();
    }

    function renderListMessage(message) {
        var tbody = document.querySelector("[data-employees-list-body]");
        if (!tbody) return;
        tbody.innerHTML = '<tr><td class="text-center text-muted py-4">' + escapeHtml(message) + '</td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td></tr>';
        tbody.setAttribute("data-hydrated", "1");
        updateBulkSelectionUi();
    }

    function renderGrid(rows) {
        var gridBody = document.querySelector("[data-employees-grid-body]");
        if (!gridBody) {
            return;
        }

        if (!rows.length) {
            gridBody.innerHTML = '<div class="col-12"><div class="alert alert-light text-center mb-0">No employees found.</div></div>';
            return;
        }

        gridBody.innerHTML = rows.map(function (row) {
            var st = row.employmentStatus || "active";
            var avatarHtml = row.profilePhotoUrl
                ? '<img src="' + escapeHtml(row.profilePhotoUrl) + '" alt="Photo" class="rounded-circle w-100 h-100">'
                : '<span class="avatar-title rounded-circle bg-primary-subtle text-primary">' + escapeHtml((row.fullName || "?").charAt(0).toUpperCase()) + "</span>";
            return (
                '<div class="col-xl-3 col-lg-4 col-md-6">' +
                '<div class="card"><div class="card-body">' +
                '<div class="text-center mb-3">' +
                '<a href="' + buildEmployeeDetailUrl(row.id) + '" data-employee-detail-link data-employee-id="' + escapeHtml(row.id) + '" class="avatar avatar-xl avatar-rounded border p-1 border-primary rounded-circle">' +
                avatarHtml +
                "</a>" +
                '<h6 class="mb-1 mt-3"><a href="' + buildEmployeeDetailUrl(row.id) + '" data-employee-detail-link data-employee-id="' + escapeHtml(row.id) + '">' + escapeHtml(row.fullName) + "</a></h6>" +
                '<span class="badge badge-purple-transparent fs-10 fw-medium">' + escapeHtml(row.designation || "Employee") + "</span>" +
                "</div>" +
                '<p class="mb-1 text-center"><strong>ID:</strong> ' + escapeHtml(formatEmployeeCode(row.id)) + "</p>" +
                '<p class="mb-1 text-center"><strong>Dept:</strong> ' + escapeHtml(row.departmentName || "—") + "</p>" +
                '<p class="mb-1 text-center"><strong>Email:</strong> ' + escapeHtml(row.email) + "</p>" +
                '<p class="mb-0 text-center"><strong>Status:</strong> ' + escapeHtml(st) + "</p>" +
                "</div></div></div>"
            );
        }).join("");
        gridBody.setAttribute("data-hydrated", "1");
    }

    return {
        renderList: renderList,
        renderListMessage: renderListMessage,
        renderGrid: renderGrid,
    };
}

export default { makeRenderers };
