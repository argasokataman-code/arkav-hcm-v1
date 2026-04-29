export function bindOvertimeCalculatorModule(deps) {
    var notify = deps.notify;
    var apiRequest = deps.apiRequest;
    var normalizeOvertimeDayType = deps.normalizeOvertimeDayType;
    var overtimeDayTypeLabel = deps.overtimeDayTypeLabel;
    var formatOvertimeComplianceError = deps.formatOvertimeComplianceError;
    var loadEmployeeOptions = deps.loadEmployeeOptions;
    var getEmployeeCompensationById = deps.getEmployeeCompensationById;

    var resultEl = document.querySelector('[data-hcm-ot-calc="result"]');
    var btn = document.querySelector('[data-hcm-ot-calc="run"]');
    var employeeSelect = document.querySelector('[data-hcm-ot-calc="employeeId"]');
    var baseSalaryInput = document.querySelector('[data-hcm-ot-calc="baseSalary"]');
    var fixedAllowanceInput = document.querySelector('[data-hcm-ot-calc="fixedAllowance"]');
    if (!resultEl || !btn) {
        return;
    }

    function applyCompensationFromEmployee() {
        if (!employeeSelect || !baseSalaryInput || !fixedAllowanceInput) {
            return;
        }
        var employeesById = getEmployeeCompensationById();
        var emp = employeesById[String(employeeSelect.value || "")];
        if (!emp) {
            return;
        }
        baseSalaryInput.value = String(Math.round(emp.baseSalary));
        fixedAllowanceInput.value = String(Math.round(emp.fixedAllowance));
    }

    if (employeeSelect) {
        employeeSelect.addEventListener("change", applyCompensationFromEmployee);
    }

    btn.addEventListener("click", function () {
        var selectedDayType = normalizeOvertimeDayType((document.querySelector('[data-hcm-ot-calc="dayType"]') || {}).value || "workday");
        var payload = {
            baseMonthlySalary: parseFloat((document.querySelector('[data-hcm-ot-calc="baseSalary"]') || {}).value || "0"),
            fixedAllowance: parseFloat((document.querySelector('[data-hcm-ot-calc="fixedAllowance"]') || {}).value || "0"),
            minutes: parseInt((document.querySelector('[data-hcm-ot-calc="minutes"]') || {}).value || "0", 10),
            dayType: selectedDayType,
            weeklyWorkDays: parseInt((document.querySelector('[data-hcm-ot-calc="weeklyWorkDays"]') || {}).value || "5", 10),
        };
        if (!payload.baseMonthlySalary || !payload.minutes) {
            notify("Isi dulu gaji pokok dan menit lembur.", true);
            return;
        }
        if (payload.minutes > 240) {
            notify("Catatan: pengajuan lembur legal dibatasi 4 jam per hari, kalkulator ini tetap menjalankan simulasi.", false);
        }
        apiRequest("post", "/v1/hcm/overtime-requests/calculate", payload)
            .then(function (r) {
                if (!r || r.success !== true) {
                    resultEl.textContent = "Gagal menghitung.";
                    return;
                }
                var d = r.data || {};
                var seg = (d.segments || []).map(function (s) {
                    return (s.label || "-") + ": " + (s.hours || 0) + " jam x " + (s.multiplier || 0) + "x";
                }).join(" | ");
                var sc = d.salaryComponent;
                var scPart =
                    sc && (sc.code || sc.name)
                        ? " | Komponen slip: " + (sc.code || "") + (sc.name ? " — " + sc.name : "")
                        : "";
                resultEl.textContent =
                    "Tipe hari: " + overtimeDayTypeLabel(selectedDayType) +
                    " | " +
                    "Upah sejam Rp" + Number(d.hourlyWage || 0).toLocaleString("id-ID") +
                    " | Segment: " + seg +
                    " | Total lembur Rp" + Number(d.totalOvertimePay || 0).toLocaleString("id-ID") +
                    scPart;
            })
            .catch(function (e) {
                resultEl.textContent = formatOvertimeComplianceError(e.data, e.status, "Perhitungan gagal.");
            });
    });

    if (employeeSelect) {
        loadEmployeeOptions(employeeSelect).then(function () {
            if (employeeSelect.value) {
                applyCompensationFromEmployee();
            }
        });
    }
}