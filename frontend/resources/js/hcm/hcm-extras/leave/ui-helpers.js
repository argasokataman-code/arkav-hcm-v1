export function buildLeaveTypeOptionsHtml(types, selectedName, esc) {
    var opts = '<option value="">— Pilih jenis cuti —</option>';
    var seen = {};
    var list = types || [];
    for (var i = 0; i < list.length; i++) {
        var t = list[i];
        var name = t && t.name ? String(t.name).trim() : "";
        if (!name) {
            continue;
        }
        seen[name] = true;
        var sel = selectedName && String(selectedName) === name ? " selected" : "";
        opts += '<option value="' + esc(name) + '"' + sel + ">" + esc(name) + "</option>";
    }
    if (selectedName && String(selectedName).trim() && !seen[String(selectedName).trim()]) {
        var legacy = String(selectedName).trim();
        opts += '<option value="' + esc(legacy) + '" selected>' + esc(legacy) + ' (riwayat)</option>';
    }
    return opts;
}

export function splitDeclinedLeaveNotes(rawNotes) {
    var notes = String(rawNotes || "");
    var marker = "\n\n[Admin rejection reason]\n";
    var idx = notes.lastIndexOf(marker);
    if (idx >= 0) {
        return {
            employeeNotes: notes.slice(0, idx).trim(),
            rejectionReason: notes.slice(idx + marker.length).trim(),
        };
    }

    var legacy = /^\s*\[Admin rejection reason\]\s*([\s\S]*)$/i.exec(notes);
    if (legacy && legacy[1]) {
        return {
            employeeNotes: "",
            rejectionReason: String(legacy[1] || "").trim(),
        };
    }

    return {
        employeeNotes: notes.trim(),
        rejectionReason: "",
    };
}

function titleCaseWords(s) {
    return String(s || "")
        .split(" ")
        .filter(Boolean)
        .map(function (w) {
            return w.charAt(0).toUpperCase() + w.slice(1).toLowerCase();
        })
        .join(" ");
}

export function createLeaveUiHelpers(deps) {
    var apiRequest = deps.apiRequest;
    var esc = deps.esc;

    function displayLeaveType(row, leaveTypeLabelByCode) {
        var raw = String((row && row.leaveType) || "").trim();
        if (!raw) {
            return "-";
        }

        if (row && row.leaveTypeLabel) {
            return String(row.leaveTypeLabel);
        }

        var codeKey = raw.toLowerCase();
        if (leaveTypeLabelByCode[codeKey]) {
            return leaveTypeLabelByCode[codeKey];
        }

        var normalizedCode = raw.toLowerCase().replace(/\s+/g, "_");
        if (leaveTypeLabelByCode[normalizedCode]) {
            return leaveTypeLabelByCode[normalizedCode];
        }

        if (raw.indexOf("_") >= 0 || raw.indexOf("-") >= 0) {
            return titleCaseWords(raw.replace(/[_-]+/g, " "));
        }
        return raw;
    }

    function leaveTypeHintByName(name, leaveTypeMetaByName) {
        var key = String(name || "").trim();
        if (!key) {
            return "Info potong saldo akan tampil setelah jenis dipilih.";
        }
        var meta = leaveTypeMetaByName[key];
        if (!meta) {
            return "Info potong saldo belum tersedia untuk tipe ini.";
        }
        return "Dipotong saldo: " + (meta.deductFromBalance ? "Ya" : "Tidak") + " | Berbayar: " + (meta.isPaid ? "Ya" : "Tidak");
    }

    function refreshLeaveTypeHints(leaveTypeMetaByName) {
        var addForm = document.querySelector('[data-hcm-leave-form="add"]');
        if (addForm) {
            var addSelect = addForm.querySelector('[data-hcm-field="leaveType"]');
            var addHint = addForm.querySelector('[data-hcm-leave-type-hint]');
            if (addHint) {
                addHint.textContent = leaveTypeHintByName(addSelect ? addSelect.value : "", leaveTypeMetaByName);
            }
        }

        var editForm = document.querySelector('[data-hcm-leave-form="edit"]');
        if (editForm) {
            var editSelect = editForm.querySelector('[data-hcm-field="leaveType"]');
            var editHint = editForm.querySelector('[data-hcm-leave-type-hint]');
            if (editHint) {
                editHint.textContent = leaveTypeHintByName(editSelect ? editSelect.value : "", leaveTypeMetaByName);
            }
        }
    }

    function updateLeaveBalanceDisplay(leaveTypeSelect) {
        if (!leaveTypeSelect) {
            return;
        }

        var modal = leaveTypeSelect.closest(".modal");
        if (!modal) {
            return;
        }

        var selectedLeaveType = leaveTypeSelect.value;
        var balanceCard = modal.querySelector('[data-hcm-leave-balance-card]');
        if (!balanceCard) {
            return;
        }

        if (!selectedLeaveType) {
            balanceCard.classList.add("d-none");
            return;
        }

        var form = modal.querySelector('[data-hcm-leave-form]');
        var userSelect = form ? form.querySelector('[data-hcm-field="userId"]') : null;
        var userId = userSelect && userSelect.value ? userSelect.value : null;
        if (!userId && !userSelect && window.AuthUser && window.AuthUser.id) {
            userId = String(window.AuthUser.id);
        }
        var params = new URLSearchParams();
        params.append("leaveType", selectedLeaveType);
        if (userId) {
            params.append("userId", userId);
        }

        apiRequest("get", "/v1/hcm/employee-leave-balance?" + params.toString(), null)
            .then(function (response) {
                if (!response || !response.success || !response.data) {
                    balanceCard.classList.add("d-none");
                    return;
                }

                var balance = response.data;
                var valueEl = balanceCard.querySelector('[data-hcm-leave-balance-value]');
                var totalEl = balanceCard.querySelector('[data-hcm-leave-balance-total]');

                if (valueEl && totalEl) {
                    var available = Math.max(0, parseFloat(balance.balance) || 0);
                    var total = (parseFloat(balance.used) || 0) + available;

                    valueEl.textContent = available.toFixed(1);
                    totalEl.textContent = total.toFixed(1);

                    if (available > 0) {
                        balanceCard.classList.remove("d-none", "alert-warning");
                        balanceCard.classList.add("alert-info");
                    } else if (available <= 0) {
                        balanceCard.classList.remove("d-none", "alert-info");
                        balanceCard.classList.add("alert-warning");
                    }
                }
            })
            .catch(function () {
                balanceCard.classList.add("d-none");
                if (window.ArcavUi && typeof window.ArcavUi.showToast === 'function') {
                    window.ArcavUi.showToast('Gagal memuat saldo cuti.', 'warning');
                }
            });
    }

    function setText(sel, value) {
        var el = document.querySelector(sel);
        if (el) {
            el.textContent = value;
        }
    }

    function updateLeaveCards(meta, isAdmin) {
        var summary = (meta && meta.summary) || {};
        if (isAdmin) {
            setText('[data-hcm-leaves-stat="totalRequests"]', String(summary.totalRequests != null ? summary.totalRequests : 0));
            setText('[data-hcm-leaves-stat="approved"]', String(summary.approved != null ? summary.approved : 0));
            setText('[data-hcm-leaves-stat="declined"]', String(summary.declined != null ? summary.declined : 0));
            setText('[data-hcm-leaves-stat="pending"]', String(summary.pending != null ? summary.pending : 0));
            return;
        }

        var balanceSummary = (meta && meta.balanceSummary) || {};
        var byType = Array.isArray(balanceSummary.byType) ? balanceSummary.byType : [];
        var buckets = {
            annual: { total: 0, remain: 0, codes: { annual_leave: true } },
            medical: { total: 0, remain: 0, codes: { sick_leave: true, hospitalisation: true } },
            casual: { total: 0, remain: 0, codes: { maternity_leave: true, paternity_leave: true } },
            other: { total: 0, remain: 0, codes: {} },
        };

        byType.forEach(function (r) {
            var code = String(r.code || "");
            var total = (parseFloat(r.used || 0) || 0) + (parseFloat(r.balance || 0) || 0);
            var remain = parseFloat(r.balance || 0) || 0;
            if (buckets.annual.codes[code]) {
                buckets.annual.total += total;
                buckets.annual.remain += remain;
                return;
            }
            if (buckets.medical.codes[code]) {
                buckets.medical.total += total;
                buckets.medical.remain += remain;
                return;
            }
            if (buckets.casual.codes[code]) {
                buckets.casual.total += total;
                buckets.casual.remain += remain;
                return;
            }
            buckets.other.total += total;
            buckets.other.remain += remain;
        });

        ["annual", "medical", "casual", "other"].forEach(function (key) {
            setText('[data-hcm-leaves-balance-card="' + key + '"]', String(buckets[key].total.toFixed(1)).replace(/\.0$/, ""));
            setText('[data-hcm-leaves-balance-remaining="' + key + '"]', String(buckets[key].remain.toFixed(1)).replace(/\.0$/, ""));
        });
    }

    return {
        displayLeaveType: displayLeaveType,
        refreshLeaveTypeHints: refreshLeaveTypeHints,
        updateLeaveBalanceDisplay: updateLeaveBalanceDisplay,
        updateLeaveCards: updateLeaveCards,
    };
}