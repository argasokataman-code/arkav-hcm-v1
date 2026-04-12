/**
 * Removes stock Smarthr template demo rows from .datatable tables so menus do not
 * show fake employees / reports. Skips tables wired to Arcav APIs or explicit opt-outs.
 * Runs synchronously before script.js initializes DataTables.
 */
(function (window, document) {
    "use strict";

    var SKIP_TBODY_ATTRS = [
        "data-employees-list-body",
        "data-departments-body",
        "data-designations-body",
        "data-policies-body",
        "data-attendance-admin-body",
        "data-attendance-me-history-body",
        "data-attendance-report-body",
    ];

    var SKIP_TABLE_IDS = [
        "attendance-admin-table",
        "attendance-me-history-table",
        "attendance-report-table",
    ];

    function shouldSkipTable(table) {
        if (!table || !table.classList || !table.classList.contains("datatable")) {
            return true;
        }
        if (table.closest("[data-arcav-keep-dummy]")) {
            return true;
        }
        if (table.hasAttribute("data-employees-table")) {
            return true;
        }
        var tid = table.id || "";
        if (SKIP_TABLE_IDS.indexOf(tid) !== -1) {
            return true;
        }
        var tbody = table.querySelector("tbody");
        if (!tbody) {
            return true;
        }
        if (tbody.getAttribute("data-arcav-placeholder") === "1") {
            return true;
        }
        var a;
        for (a = 0; a < SKIP_TBODY_ATTRS.length; a++) {
            if (tbody.hasAttribute(SKIP_TBODY_ATTRS[a])) {
                return true;
            }
        }
        return false;
    }

    function countTableColumns(table) {
        var headerRow = table.querySelector("thead tr");
        if (!headerRow) {
            var sample = table.querySelector("tbody tr");
            if (sample) {
                headerRow = sample;
            }
        }
        if (!headerRow) {
            return 1;
        }
        var cells = headerRow.querySelectorAll("th, td");
        var n = 0;
        var i;
        for (i = 0; i < cells.length; i++) {
            var cs = parseInt(cells[i].getAttribute("colspan") || "1", 10);
            n += isNaN(cs) ? 1 : cs;
        }
        return Math.max(n, 1);
    }

    function stripDummyTables() {
        var tables = document.querySelectorAll("table.datatable");
        var t;
        var msg =
            "Baris contoh template telah dihapus. Data akan tampil setelah modul ini dihubungkan ke API.";

        for (t = 0; t < tables.length; t++) {
            var table = tables[t];
            if (shouldSkipTable(table)) {
                continue;
            }
            var tbody = table.querySelector("tbody");
            if (!tbody) {
                continue;
            }
            var cols = countTableColumns(table);
            tbody.innerHTML =
                '<tr><td colspan="' +
                String(cols) +
                '" class="text-center text-muted py-4">' +
                msg +
                "</td></tr>";
            tbody.setAttribute("data-arcav-placeholder", "1");
        }
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", stripDummyTables);
    } else {
        stripDummyTables();
    }
})(window, document);
