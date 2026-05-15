(function (window, document) {
  "use strict";

  const API_BASE = "/v1/saas/subscriptions";
  const PAGE_SIZE = 10;
  const subscriptionsHttp = window.ArcavSubscriptionsHttp || {};
  const apiRequest = subscriptionsHttp.apiRequest || function (method, url, body) {
    const opts = {
      method: method,
      headers: {
        Accept: "application/json",
        "X-Requested-With": "XMLHttpRequest",
        "Content-Type": "application/json",
      },
      credentials: "same-origin",
    };
    if (body && method !== "GET") {
      opts.body = JSON.stringify(body);
    }
    return fetch(url, opts).then(function (res) {
      return res.json();
    });
  };

  const subscriptionsUtils = window.ArcavSubscriptionsUtils || {};
  const formatCompanyReference = subscriptionsUtils.formatCompanyReference || function (row) {
    return row && row.company_code ? String(row.company_code) : "Company tercatat";
  };
  const esc = subscriptionsUtils.esc || function (v) { return String(v || ""); };
  const normalizeAnomalyBadges = subscriptionsUtils.normalizeAnomalyBadges || function () { return '<span class="badge bg-success-subtle text-success">No anomaly</span>'; };
  const formatDate = subscriptionsUtils.formatDate || function (v) { return v ? String(v) : "-"; };
  const formatDateTime = subscriptionsUtils.formatDateTime || function (v) { return v ? String(v) : "-"; };
  const formatCurrency = subscriptionsUtils.formatCurrency || function (amount) { return "Rp " + String(amount || 0); };
  const subscriptionRouteKey = subscriptionsUtils.subscriptionRouteKey || function (sub) { return String((sub && (sub.uuid || sub.id)) || ""); };
  const defaultRenewEndDateFromBillingCycle = subscriptionsUtils.defaultRenewEndDateFromBillingCycle || function () { return new Date().toISOString().slice(0, 10); };
  const isRenewableSubscriptionStatus = subscriptionsUtils.isRenewableSubscriptionStatus || function (status) { return String(status || "") !== "active"; };

  function normalizeStatusLabel(status) {
    return String(status || "-")
      .replace(/_/g, " ")
      .replace(/\b\w/g, function (segment) { return segment.toUpperCase(); });
  }

  function formatRequestNotes(notes, emptyLabel) {
    const value = String(notes == null ? '' : notes).trim();
    if (!value) {
      return '<span class="text-muted">' + esc(emptyLabel || '-') + '</span>';
    }

    return esc(value).replace(/\n/g, '<br>');
  }

  // Main SubscriptionsManager object
  const SubscriptionsManager = {
    isInitialized: false,
    currentUser: null,
    canManageSubscriptions: false,
    currentPage: 1,
    totalPages: 1,
    subscriptions: [],
    companies: [],
    packages: [],
    currentEditId: null,
    isPrimarySuperAdminCodeOne: false,
    subscriptionModalInstance: null,
    subscriptionRenewModalInstance: null,
    subscriptionRenewByIdModalInstance: null,
    subscriptionReactivateConfirmModalInstance: null,
    pendingReactivateConfirmResolver: null,
    pendingReactivateConfirmHandled: false,
    pendingRenewId: null,
    pendingRenewSourceStatus: null,
    pendingRenewContext: null,
    currentEditStatus: null,
    renewByIdLoadedSub: null,

    /**
     * Initialize the subscriptions list page
     */
    init: function () {
      if (this.isInitialized) return;
      this.isInitialized = true;
      const self = this;

      const subModalEl = document.getElementById("subscriptionModal");
      this.subscriptionModalInstance =
        window.bootstrap && subModalEl ? window.bootstrap.Modal.getOrCreateInstance(subModalEl) : null;

      const renewModalEl = document.getElementById("subscriptionRenewModal");
      this.subscriptionRenewModalInstance =
        window.bootstrap && renewModalEl ? window.bootstrap.Modal.getOrCreateInstance(renewModalEl) : null;

      const renewByIdEl = document.getElementById("subscriptionRenewByIdModal");
      this.subscriptionRenewByIdModalInstance =
        window.bootstrap && renewByIdEl ? window.bootstrap.Modal.getOrCreateInstance(renewByIdEl) : null;

      const reactivateConfirmEl = document.getElementById("subscriptionReactivateConfirmModal");
      this.subscriptionReactivateConfirmModalInstance =
        window.bootstrap && reactivateConfirmEl ? window.bootstrap.Modal.getOrCreateInstance(reactivateConfirmEl) : null;

      if (renewByIdEl) {
        const selfInit = this;
        renewByIdEl.addEventListener("hidden.bs.modal", function () {
          selfInit.resetRenewByIdModalUi();
        });
      }

      if (reactivateConfirmEl) {
        const selfInit = this;
        reactivateConfirmEl.addEventListener("hidden.bs.modal", function () {
          if (selfInit.pendingReactivateConfirmResolver && !selfInit.pendingReactivateConfirmHandled) {
            selfInit.pendingReactivateConfirmHandled = true;
            selfInit.pendingReactivateConfirmResolver(false);
          }
          selfInit.pendingReactivateConfirmResolver = null;
          selfInit.pendingReactivateConfirmHandled = false;
        });
      }

      const shell = document.querySelector(".main-wrapper");
      this.isPrimarySuperAdminCodeOne = String(shell?.dataset?.primarySuperAdmin || "0") === "1";

      this.bindEvents();
      this.loadCurrentUser()
        .then(() => {
          this.applyRoleUi();

          const tasks = [];
          if (this.canManageSubscriptions) {
            tasks.push(this.loadCompanies(), this.loadPackages(), this.loadSubscriptions(), this.loadChangeRequestQueue());
          } else {
            this.renderUnauthorizedState();
          }
          return Promise.all(tasks);
        })
        .then(function () {
          self.applyQueryStringDefaults();
        })
        .catch((err) => {
          if (err && window.AuthApi && typeof window.AuthApi.handleUnauthorizedFromApi === "function") {
            window.AuthApi.handleUnauthorizedFromApi(err.status, err.data);
          }
        });
    },

    /**
     * Open create modal with package/company prefill from query string.
     * System-managed statuses such as pending_payment are ignored here.
     */
    applyQueryStringDefaults: function () {
      if (!this.canManageSubscriptions) return;
      const q = new URLSearchParams(window.location.search);
      const packageId = q.get("packageId") || q.get("package_uuid") || q.get("package_id");
      const companyId = q.get("companyId") || q.get("company_id");
      const status = q.get("status");
      if (!packageId && !companyId && !status) return;

      const allowed = [
        "active",
        "trial",
        "inactive",
        "expired",
        "cancelled",
        "suspended",
      ];
      const statusParam = status && allowed.indexOf(status) !== -1 ? status : null;

      if (!packageId && !companyId && !statusParam) return;

      this.openCreateModal({
        packageId: packageId ? String(packageId) : null,
        companyId: companyId ? String(companyId) : null,
        status: statusParam,
      });
      if (this.subscriptionModalInstance) {
        this.subscriptionModalInstance.show();
      }
      try {
        window.history.replaceState({}, "", window.location.pathname);
      } catch (_e) {}
    },

    loadCurrentUser: function () {
      const self = this;
      return apiRequest("GET", "/v1/identity/auth/me", null)
        .then(function (response) {
          self.currentUser = response?.data || null;
          const permissions = response?.data?.permissions || {};
          const hasManagePermission = !!permissions["subscription.manage"];
          const isTenantOrGlobalAdmin = !!(response?.data?.hcmAdmin || response?.data?.hcmGlobalAdmin);

          // Super admin/global admin must always be able to access SaaS subscriptions,
          // even if legacy permission map does not explicitly contain subscription.manage.
          self.canManageSubscriptions = hasManagePermission || isTenantOrGlobalAdmin;
          return response;
        });
    },

    applyRoleUi: function () {
      const addButtons = Array.from(document.querySelectorAll("[data-subscription-add-button]"));
      const readOnlyNotice = document.querySelector("[data-subscription-readonly-notice]");
      const queueCard = document.querySelector("[data-subscription-change-queue-card]");

      addButtons.forEach(function (button) {
        button.classList.toggle("d-none", !this.canManageSubscriptions);
      }.bind(this));

      if (readOnlyNotice) {
        readOnlyNotice.classList.toggle("d-none", this.canManageSubscriptions);
      }

      const renewByIdBtn = document.getElementById("btn_open_renew_by_id");
      if (renewByIdBtn) {
        renewByIdBtn.classList.toggle("d-none", !this.canManageSubscriptions);
      }

      if (queueCard) {
        queueCard.classList.toggle("d-none", !(this.canManageSubscriptions && this.isPrimarySuperAdminCodeOne));
      }
    },

    loadChangeRequestQueue: function () {
      const queueContent = document.querySelector("[data-subscription-change-queue-content]");
      const queueCount = document.querySelector("[data-subscription-change-queue-count]");
      const queueFilter = document.querySelector("[data-subscription-change-queue-filter]");
      const selectedFilter = String(queueFilter?.value || 'all').toLowerCase();
      const requestUrl = selectedFilter === 'pending'
        ? '/v1/saas/subscription-change-requests?status=pending'
        : '/v1/saas/subscription-change-requests';

      if (!this.canManageSubscriptions || !this.isPrimarySuperAdminCodeOne) {
        return Promise.resolve();
      }

      return apiRequest("GET", requestUrl, null)
        .then(function (response) {
          const rows = Array.isArray(response?.data) ? response.data : [];
          const pendingCount = rows.filter(function (row) {
            return String(row?.status || '').toLowerCase() === 'pending';
          }).length;

          if (queueCount) {
            queueCount.textContent = selectedFilter === 'pending' ? (rows.length + ' pending') : (rows.length + ' records');
          }

          if (!queueContent) {
            return;
          }

          if (!rows.length) {
            queueContent.innerHTML = selectedFilter === 'pending'
              ? '<div class="alert alert-success mb-0">Tidak ada pengajuan pending baru.</div>'
              : '<div class="alert alert-secondary mb-0">Belum ada riwayat pengajuan subscription change.</div>';
            return;
          }

          const anomalyCount = rows.filter(function (row) {
            const flags = row?.preview?.anomaly_flags;
            return Array.isArray(flags) && flags.length > 0;
          }).length;

          queueContent.innerHTML =
            (selectedFilter === 'pending'
              ? (anomalyCount > 0
                  ? '<div class="alert alert-danger py-2 mb-2">Perhatian: <strong>' + anomalyCount + '</strong> pengajuan memiliki anomali billing. Wajib review sebelum approve.</div>'
                  : '<div class="alert alert-success py-2 mb-2">Semua pengajuan pending tidak memiliki anomali billing kritikal.</div>')
              : '<div class="alert alert-light py-2 mb-2">Riwayat memuat <strong>' + rows.length + '</strong> request, dengan <strong>' + pendingCount + '</strong> yang masih pending review.</div>')
            + '<div class="table-responsive"><table class="table table-sm align-middle mb-0">'
            + '<thead><tr><th>Company</th><th>Aksi</th><th>Target Paket</th><th>Dibuat</th><th>Status</th><th>Catatan / Alasan</th><th>Risk</th><th>Aksi Admin</th></tr></thead><tbody>'
            + rows.map(function (row) {
              const preview = row?.preview || {};
              const toPackage = preview?.to_package || null;
              const flags = Array.isArray(preview?.anomaly_flags) ? preview.anomaly_flags : [];
              const detailLine = preview?.anomaly_details?.invoice_number
                ? ('<div class="small text-muted mt-1">Invoice ' + esc(preview.anomaly_details.invoice_number) + '</div>')
                : '';
              const isPending = String(row?.status || '').toLowerCase() === 'pending';
              return '<tr>'
                + '<td>' + esc(formatCompanyReference(row)) + '</td>'
                + '<td>' + esc(normalizeStatusLabel(row.action || '-')) + '</td>'
                + '<td>' + esc(toPackage ? ((toPackage.name || "-") + " (" + (toPackage.code || "-") + ")") : "-") + '</td>'
                + '<td>' + esc(formatDateTime(row.created_at)) + '</td>'
                + '<td><span class="badge badge-warning d-inline-flex align-items-center badge-xs"><i class="ti ti-point-filled me-1"></i>' + esc(normalizeStatusLabel(row.status || 'pending')) + '</span></td>'
                + '<td class="small">' + formatRequestNotes(row.notes, 'Belum ada catatan.') + '</td>'
                + '<td><div class="d-flex flex-wrap gap-1">' + normalizeAnomalyBadges(flags) + '</div>' + detailLine + '</td>'
                + '<td class="text-nowrap">'
                + (isPending
                    ? '<button type="button" class="btn btn-sm btn-success me-1" data-approve-change-request="' + esc(row.id || '') + '">Approve</button>'
                      + '<button type="button" class="btn btn-sm btn-outline-danger" data-reject-change-request="' + esc(row.id || '') + '">Reject</button>'
                    : '<span class="text-muted small">Tidak ada aksi lanjutan</span>')
                + '</td>'
                + '</tr>';
            }).join("")
            + "</tbody></table></div>";
        })
        .catch(function () {
          if (queueContent) {
            queueContent.innerHTML = '<div class="alert alert-danger mb-0">Gagal memuat queue pengajuan subscription change.</div>';
          }
          if (queueCount) {
            queueCount.textContent = "error";
          }
          return Promise.resolve();
        });
    },

    renderUnauthorizedState: function () {
      const container = document.querySelector('[data-subscriptions-list-container]');
      if (!container) return;
      container.innerHTML =
        '<div class="card"><div class="card-body text-center text-muted py-4">Subscription data is only available for HCM admins.</div></div>';
    },

    /**
     * Bind event listeners
     */
    bindEvents: function () {
      const self = this;

      const form = document.getElementById("subscriptionForm");
      if (form) {
        form.addEventListener("submit", function (e) {
          e.preventDefault();
          self.handleSaveSubscription();
        });
      }

      const addBtn = document.getElementById("btn_add_subscription");
      if (addBtn) {
        addBtn.addEventListener("click", function () {
          self.openCreateModal();
        });
      }

      const statusFilter = document.getElementById("filter_status");
      if (statusFilter) {
        statusFilter.addEventListener("change", function () {
          self.currentPage = 1;
          self.loadSubscriptions();
        });
      }

      const cycleFilter = document.getElementById("filter_cycle");
      if (cycleFilter) {
        cycleFilter.addEventListener("change", function () {
          self.currentPage = 1;
          self.loadSubscriptions();
        });
      }

      const changeQueueFilter = document.querySelector('[data-subscription-change-queue-filter]');
      if (changeQueueFilter) {
        changeQueueFilter.addEventListener('change', function () {
          self.loadChangeRequestQueue();
        });
      }

      const searchInput = document.getElementById("search_subscriptions");
      if (searchInput) {
        let timer = null;
        searchInput.addEventListener("input", function () {
          window.clearTimeout(timer);
          timer = window.setTimeout(function () {
            self.currentPage = 1;
            self.loadSubscriptions();
          }, 250);
        });
      }

      const resetBtn = document.getElementById("btn_reset_filters");
      if (resetBtn) {
        resetBtn.addEventListener("click", function () {
          const status = document.getElementById("filter_status");
          const cycle = document.getElementById("filter_cycle");
          const search = document.getElementById("search_subscriptions");
          if (status) status.value = "";
          if (cycle) cycle.value = "";
          if (search) search.value = "";
          self.currentPage = 1;
          self.loadSubscriptions();
        });
      }

      const startEl = document.getElementById("input_subscription_start");
      const cycleEl = document.getElementById("input_subscription_cycle");
      if (startEl) {
        startEl.addEventListener("change", function () {
          self.suggestSubscriptionEndDate();
          if (document.getElementById("input_subscription_status")?.value === "trial") {
            const te = document.getElementById("input_subscription_trial_end");
            if (te && !te.value) self.suggestTrialEndFromStart();
          }
        });
      }
      if (cycleEl) {
        cycleEl.addEventListener("change", function () {
          self.suggestSubscriptionEndDate();
        });
      }

      const renewConfirm = document.getElementById("btn_confirm_renew_subscription");
      if (renewConfirm) {
        renewConfirm.addEventListener("click", function () {
          self.confirmRenewSubscription();
        });
      }

      const reactivateConfirmBtn = document.getElementById("btn_confirm_subscription_reactivation");
      if (reactivateConfirmBtn) {
        reactivateConfirmBtn.addEventListener("click", function () {
          if (self.pendingReactivateConfirmResolver && !self.pendingReactivateConfirmHandled) {
            self.pendingReactivateConfirmHandled = true;
            self.pendingReactivateConfirmResolver(true);
          }
          if (self.subscriptionReactivateConfirmModalInstance) {
            self.subscriptionReactivateConfirmModalInstance.hide();
          }
        });
      }

      const openRenewByIdBtn = document.getElementById("btn_open_renew_by_id");
      if (openRenewByIdBtn) {
        openRenewByIdBtn.addEventListener("click", function () {
          self.openRenewByIdModal();
        });
      }

      const loadRenewLookupBtn = document.getElementById("btn_renew_lookup_load");
      if (loadRenewLookupBtn) {
        loadRenewLookupBtn.addEventListener("click", function () {
          self.loadSubscriptionForRenewById();
        });
      }

      const confirmRenewByIdBtn = document.getElementById("btn_confirm_renew_by_id");
      if (confirmRenewByIdBtn) {
        confirmRenewByIdBtn.addEventListener("click", function () {
          self.confirmRenewById();
        });
      }

      const renewLookupInput = document.getElementById("input_renew_lookup_id");
      if (renewLookupInput) {
        renewLookupInput.addEventListener("keydown", function (e) {
          if (e.key === "Enter") {
            e.preventDefault();
            self.loadSubscriptionForRenewById();
          }
        });
      }

      const statusSel = document.getElementById("input_subscription_status");
      if (statusSel) {
        statusSel.addEventListener("change", function () {
          self.toggleSubscriptionTrialUI();
          if (statusSel.value === "trial") {
            const te = document.getElementById("input_subscription_trial_end");
            if (te && !te.value) self.suggestTrialEndFromStart();
          }
        });
      }

      // Pagination buttons
      document.addEventListener("click", function (e) {
        const pageLink = e.target.closest("[data-page]");
        if (pageLink) {
          e.preventDefault();
          const page = parseInt(pageLink.getAttribute("data-page"), 10);
          self.currentPage = page;
          self.loadSubscriptions();
        }

        const editBtn = e.target.closest("[data-edit-subscription]");
        if (editBtn) {
          e.preventDefault();
          const id = editBtn.getAttribute("data-edit-subscription");
          self.editSubscription(id);
        }

        const cancelBtn = e.target.closest("[data-cancel-subscription]");
        if (cancelBtn) {
          e.preventDefault();
          const id = cancelBtn.getAttribute("data-cancel-subscription");
          self.cancelSubscription(id);
        }

        const viewBtn = e.target.closest("[data-view-subscription]");
        if (viewBtn) {
          e.preventDefault();
          const id = viewBtn.getAttribute("data-view-subscription");
          self.viewSubscriptionDetails(id);
        }

        const renewBtn = e.target.closest("[data-renew-subscription]");
        if (renewBtn) {
          e.preventDefault();
          const id = renewBtn.getAttribute("data-renew-subscription");
          self.openRenewModal(id);
        }

        const approveChangeBtn = e.target.closest("[data-approve-change-request]");
        if (approveChangeBtn) {
          e.preventDefault();
          const id = approveChangeBtn.getAttribute("data-approve-change-request");
          self.approveChangeRequest(id);
        }

        const rejectChangeBtn = e.target.closest("[data-reject-change-request]");
        if (rejectChangeBtn) {
          e.preventDefault();
          const id = rejectChangeBtn.getAttribute("data-reject-change-request");
          self.rejectChangeRequest(id);
        }
      });
    },

    approveChangeRequest: function (id) {
      const self = this;
      if (!id) {
        this.showError("Request ID tidak valid.");
        return;
      }

      if (!window.confirm("Approve pengajuan downgrade/upgrade ini?")) {
        return;
      }

      apiRequest("POST", "/v1/saas/subscription-change-requests/" + encodeURIComponent(id) + "/approve", null)
        .then(function (response) {
          if (response.success) {
            self.showSuccess("Pengajuan berhasil di-approve.");
            self.loadChangeRequestQueue();
          } else {
            self.showError(response.error?.message || "Approve gagal.");
          }
        })
        .catch(function (err) {
          self.showError(err?.data?.error?.message || "Approve gagal.");
        });
    },

    rejectChangeRequest: function (id) {
      const self = this;
      if (!id) {
        this.showError("Request ID tidak valid.");
        return;
      }

      const notesRaw = window.prompt("Alasan reject (opsional):", "");
      if (notesRaw === null) {
        return;
      }
      const notes = String(notesRaw || "").trim();

      apiRequest("POST", "/v1/saas/subscription-change-requests/" + encodeURIComponent(id) + "/reject", notes ? { notes: notes } : {})
        .then(function (response) {
          if (response.success) {
            self.showSuccess("Pengajuan berhasil di-reject.");
            self.loadChangeRequestQueue();
          } else {
            self.showError(response.error?.message || "Reject gagal.");
          }
        })
        .catch(function (err) {
          self.showError(err?.data?.error?.message || "Reject gagal.");
        });
    },

    suggestSubscriptionEndDate: function () {
      const startDate = document.getElementById("input_subscription_start")?.value;
      const billingCycle = document.getElementById("input_subscription_cycle")?.value;
      const endEl = document.getElementById("input_subscription_end");
      if (!startDate || !billingCycle || !endEl) return;

      const start = new Date(startDate + "T00:00:00");
      if (Number.isNaN(start.getTime())) return;

      if (billingCycle === "yearly") {
        start.setFullYear(start.getFullYear() + 1);
      } else {
        start.setMonth(start.getMonth() + 1);
      }

      const y = start.getFullYear();
      const m = ("0" + (start.getMonth() + 1)).slice(-2);
      const day = ("0" + start.getDate()).slice(-2);
      endEl.value = y + "-" + m + "-" + day;
    },

    toggleSubscriptionTrialUI: function () {
      const status = document.getElementById("input_subscription_status")?.value;
      const row = document.getElementById("subscription_trial_row");
      const input = document.getElementById("input_subscription_trial_end");
      const isTrial = status === "trial";
      if (row) row.classList.toggle("d-none", !isTrial);
      if (input) {
        input.required = isTrial;
        if (!isTrial) input.value = "";
      }
    },

    suggestTrialEndFromStart: function () {
      const startDate = document.getElementById("input_subscription_start")?.value;
      const trialEl = document.getElementById("input_subscription_trial_end");
      if (!startDate || !trialEl) return;
      const d = new Date(startDate + "T00:00:00");
      if (Number.isNaN(d.getTime())) return;
      d.setDate(d.getDate() + 14);
      trialEl.value = d.toISOString().slice(0, 10);
    },

    loadCompanies: function () {
      const self = this;
      if (!this.canManageSubscriptions) {
        self.companies = [];
        self.renderCompanyOptions();
        return Promise.resolve([]);
      }

      apiRequest("GET", "/v1/company?page=1&per_page=200", null)
        .then(function (response) {
          const list = response?.data?.companies || response?.data || [];
          self.companies = Array.isArray(list) ? list : [];
          self.renderCompanyOptions();
          return self.companies;
        })
        .catch(function () {
          self.companies = [];
          self.renderCompanyOptions();
          return [];
        });
    },

    loadPackages: function () {
      const self = this;
      if (!this.canManageSubscriptions) {
        self.packages = [];
        self.renderPackageOptions();
        return Promise.resolve([]);
      }

      apiRequest("GET", "/v1/saas/packages?status=active&per_page=200", null)
        .then(function (response) {
          const list = response?.data || [];
          self.packages = Array.isArray(list) ? list : [];
          self.renderPackageOptions();
          return self.packages;
        })
        .catch(function () {
          self.packages = [];
          self.renderPackageOptions();
          return [];
        });
    },

    renderCompanyOptions: function () {
      const select = document.getElementById("input_subscription_company");
      if (!select) return;

      const options = this.companies
        .map(function (company) {
          const companyValue = company.uuid || company.id;
          return '<option value="' + esc(companyValue) + '">' + esc(company.name || ("Company #" + company.id)) + "</option>";
        })
        .join("");
      select.innerHTML = '<option value="">Select company</option>' + options;
    },

    setSubscriptionModalMode: function (mode, companyName) {
      const selectGroup = document.querySelector("[data-subscription-company-select-group]");
      const readonlyGroup = document.querySelector("[data-subscription-company-readonly-group]");
      const readonlyInput = document.getElementById("input_subscription_company_readonly");
      const companySelect = document.getElementById("input_subscription_company");
      const impactNote = document.querySelector("[data-subscription-edit-impact-note]");
      const isEdit = mode === "edit";

      if (selectGroup) selectGroup.classList.toggle("d-none", isEdit);
      if (readonlyGroup) readonlyGroup.classList.toggle("d-none", !isEdit);
      if (readonlyInput) readonlyInput.value = isEdit ? String(companyName || "") : "";
      if (companySelect) companySelect.disabled = isEdit;
      if (impactNote) impactNote.classList.toggle("d-none", !isEdit);
    },

    renderPackageOptions: function () {
      const select = document.getElementById("input_subscription_package");
      if (!select) return;

      const options = this.packages
        .map(function (pkg) {
          return '<option value="' + esc(pkg.id) + '">' + esc(pkg.name || pkg.code || ("Package #" + pkg.id)) + "</option>";
        })
        .join("");
      select.innerHTML = '<option value="">Select package</option>' + options;
    },

    /**
     * Load subscriptions from API
     */
    loadSubscriptions: function () {
      const self = this;
      const params = new URLSearchParams({
        page: String(this.currentPage),
        per_page: String(PAGE_SIZE),
      });

      const status = document.getElementById("filter_status")?.value || "";
      const cycle = document.getElementById("filter_cycle")?.value || "";
      const search = String(document.getElementById("search_subscriptions")?.value || "").trim();
      if (status) params.set("status", status);
      if (cycle) params.set("billing_cycle", cycle);
      if (search) params.set("search", search);

      const url = API_BASE + "?" + params.toString();

      apiRequest("GET", url, null)
        .then(function (response) {
          if (response.success && response.data) {
            self.subscriptions = response.data;
            self.totalPages = response.pagination ? response.pagination.last_page : 1;
            self.renderSubscriptions();
          } else {
            self.showError("Failed to load subscriptions");
          }
        })
        .catch(function (err) {
          console.error(err);
          self.showError("Error loading subscriptions");
        });
    },

    /**
     * Render subscriptions table
     */
    renderSubscriptions: function () {
      const container = document.querySelector('[data-subscriptions-list-container]');
      if (!container) return;

      let html = '';
      if (this.subscriptions.length === 0) {
        html = '<div class="card"><div class="card-body text-center text-muted py-4">No subscriptions found</div></div>';
      } else {
        html = `
          <div class="card">
            <div class="table-responsive">
              <table class="table table-hover mb-0">
                <thead class="table-light">
                  <tr>
                    <th>ID</th>
                    <th>Company</th>
                    <th>Package</th>
                    <th>Amount</th>
                    <th>Status</th>
                    <th>Start Date</th>
                    <th>End Date</th>
                    <th>Auto Renew</th>
                    <th>Actions</th>
                  </tr>
                </thead>
                <tbody>
                  ${this.subscriptions.map(sub => {
                    const statusBadgeClass =
                      sub.status === "active"
                        ? "badge-success"
                        : sub.status === "trial"
                        ? "badge-info"
                        : sub.status === "pending_payment"
                        ? "badge-warning"
                        : sub.status === "inactive"
                        ? "badge-secondary"
                        : sub.status === "expired"
                        ? "badge-warning"
                        : sub.status === "suspended"
                        ? "badge-purple"
                        : "badge-danger";
                    const companyName = sub.companyName || sub.company?.name || "-";
                    const packageName = sub.packageName || sub.package?.name || sub.planCode || "-";
                    const amount = sub.amount != null ? formatCurrency(sub.amount) : "-";
                    const startDate = sub.startDate || sub.startsAt || null;
                    const endDate = sub.endDate || sub.endsAt || null;
                    const canEdit = sub.status !== "pending_payment";
                    const canCancel =
                      sub.status === "active" ||
                      sub.status === "trial" ||
                      sub.status === "pending_payment" ||
                      sub.status === "suspended";
                    const showRenew =
                      sub.status === "expired" ||
                      sub.status === "cancelled" ||
                      sub.status === "suspended" ||
                      sub.status === "inactive";
                    return `
                      <tr data-subscription-row="${subscriptionRouteKey(sub)}">
                        <td>
                          <div class="fw-semibold">#${esc(sub.id)}</div>
                          <div class="text-muted small">${esc(subscriptionRouteKey(sub))}</div>
                        </td>
                        <td>${esc(companyName)}</td>
                        <td>${esc(packageName)}</td>
                        <td>
                          <div class="fw-semibold">${esc(amount)}</div>
                          <div class="text-muted small">${esc(sub.billingCycle || "-")}</div>
                        </td>
                        <td>
                          <span class="badge ${statusBadgeClass} d-inline-flex align-items-center badge-xs">
                            <i class="ti ti-point-filled me-1"></i>${esc(normalizeStatusLabel(sub.status))}
                          </span>
                        </td>
                        <td>${formatDate(startDate)}</td>
                        <td>${formatDate(endDate)}</td>
                        <td>
                          <span class="badge ${sub.autoRenew ? "badge-success" : "badge-warning"} d-inline-flex align-items-center badge-xs">
                            <i class="ti ti-point-filled me-1"></i>${sub.autoRenew ? "Yes" : "No"}
                          </span>
                        </td>
                        <td>
                          <div class="action-icon d-inline-flex">
                            <button class="btn btn-icon btn-sm me-2" data-view-subscription="${subscriptionRouteKey(sub)}" title="View Details">
                              <i class="ti ti-eye"></i>
                            </button>
                            ${this.canManageSubscriptions ? `
                              ${canEdit
                                ? `<button class="btn btn-icon btn-sm me-2" data-edit-subscription="${subscriptionRouteKey(sub)}" title="Edit">
                                     <i class="ti ti-edit"></i>
                                   </button>`
                                : ""}
                              ${
                                showRenew
                                  ? `<button class="btn btn-icon btn-sm me-2" data-renew-subscription="${subscriptionRouteKey(sub)}" title="Reactivate Manually">
                                      <i class="ti ti-refresh"></i>
                                    </button>`
                                  : ""
                              }
                              ${
                                canCancel
                                  ? `<button class="btn btn-icon btn-sm me-2" data-cancel-subscription="${subscriptionRouteKey(sub)}" title="Cancel">
                                      <i class="ti ti-x"></i>
                                    </button>`
                                  : ""
                              }
                            ` : ""}
                          </div>
                        </td>
                      </tr>
                    `;
                  }).join('')}
                </tbody>
              </table>
            </div>
            <div class="card-footer d-flex justify-content-between align-items-center">
              <small class="text-muted">Showing ${this.subscriptions.length} subscriptions</small>
              <nav aria-label="Page navigation">
                <ul class="pagination pagination-sm mb-0" data-subscription-pagination></ul>
              </nav>
            </div>
          </div>
        `;
      }
      container.innerHTML = html;
      this.renderPagination();
    },

    /**
     * Render pagination
     */
    renderPagination: function () {
      const container = document.querySelector('[data-subscription-pagination]');
      if (!container) return;
      container.innerHTML = "";
      if (this.currentPage > 1) {
        const li = document.createElement("li");
        li.className = "page-item";
        li.innerHTML = `<a class="page-link" href="javascript:void(0);" data-page="${this.currentPage - 1}">Previous</a>`;
        container.appendChild(li);
      }
      for (let i = 1; i <= this.totalPages; i++) {
        const li = document.createElement("li");
        li.className = "page-item" + (i === this.currentPage ? " active" : "");
        li.innerHTML = `<a class="page-link" href="javascript:void(0);" data-page="${i}">${i}</a>`;
        container.appendChild(li);
      }
      if (this.currentPage < this.totalPages) {
        const li = document.createElement("li");
        li.className = "page-item";
        li.innerHTML = `<a class="page-link" href="javascript:void(0);" data-page="${this.currentPage + 1}">Next</a>`;
        container.appendChild(li);
      }
    },

    openCreateModal: function (opts) {
      if (!this.canManageSubscriptions) {
        this.showError("Admin access required.");
        return;
      }

      this.currentEditId = null;
      this.currentEditStatus = null;

      const form = document.getElementById("subscriptionForm");
      if (form) form.reset();

      const companySel = document.getElementById("input_subscription_company");
      this.setSubscriptionModalMode("create");

      const startEl = document.getElementById("input_subscription_start");
      if (startEl) {
        startEl.value = new Date().toISOString().slice(0, 10);
      }
      const cycleEl = document.getElementById("input_subscription_cycle");
      if (cycleEl) cycleEl.value = "monthly";
      this.suggestSubscriptionEndDate();

      const statusEl = document.getElementById("input_subscription_status");
      const allowed = [
        "active",
        "trial",
        "inactive",
        "expired",
        "cancelled",
        "suspended",
      ];
      if (statusEl) {
        if (opts && opts.status && allowed.indexOf(opts.status) !== -1) {
          statusEl.value = opts.status;
        } else {
          statusEl.value = "active";
        }
      }
      this.toggleSubscriptionTrialUI();

      if (companySel && opts && opts.companyId) {
        companySel.value = opts.companyId;
      }
      const pkgSel = document.getElementById("input_subscription_package");
      if (pkgSel && opts && opts.packageId) {
        pkgSel.value = opts.packageId;
      }

      const title = document.getElementById("subscriptionModalTitle");
      const submitBtn = document.querySelector("#subscriptionForm button[type='submit']");
      if (title) title.textContent = "Add Subscription";
      if (submitBtn) submitBtn.textContent = "Save Subscription";
    },

    handleSaveSubscription: async function () {
      if (!this.canManageSubscriptions) {
        this.showError("Admin access required.");
        return;
      }

      const self = this;
      const companyId = document.getElementById("input_subscription_company")?.value;
      const packageId = document.getElementById("input_subscription_package")?.value;
      const startDate = document.getElementById("input_subscription_start")?.value;
      const billingCycle = document.getElementById("input_subscription_cycle")?.value;
      const endDate = document.getElementById("input_subscription_end")?.value;
      const status = document.getElementById("input_subscription_status")?.value || "active";
      const trialEnd = document.getElementById("input_subscription_trial_end")?.value || "";

      const isEdit = !!this.currentEditId;

      if (!packageId || !startDate || !billingCycle || !endDate) {
        self.showError("Package, start date, billing cycle, dan end date wajib diisi.");
        return;
      }

      if (!isEdit && !companyId) {
        self.showError("Company wajib diisi.");
        return;
      }

      if (endDate <= startDate) {
        self.showError("End date harus setelah start date.");
        return;
      }

      if (status === "trial") {
        if (!trialEnd) {
          self.showError("Trial end date wajib diisi untuk status Trial.");
          return;
        }
        if (trialEnd <= startDate) {
          self.showError("Trial end harus setelah start date.");
          return;
        }
        if (trialEnd > endDate) {
          self.showError("Trial end tidak boleh setelah subscription end date.");
          return;
        }
      }

      const requiresReactivateConfirm = isEdit
        && String(this.currentEditStatus || "") === "suspended"
        && status === "active";

      if (requiresReactivateConfirm) {
        const confirmed = await this.confirmSuspendedReactivation({
          actionLabel: "status update",
          companyName: this.subscriptions.find(function (sub) {
            return String(subscriptionRouteKey(sub)) === String(self.currentEditId);
          })?.companyName || null,
        });
        if (!confirmed) {
          return;
        }
      }

      let data;
      if (isEdit) {
        data = {
          package_uuid: String(packageId),
          status: status,
          starts_at: startDate,
          ends_at: endDate,
          auto_renew: true,
          billing_cycle: billingCycle,
          trial_ends_at: status === "trial" ? trialEnd : null,
        };
      } else {
        data = {
          company_id: String(companyId),
          package_uuid: String(packageId),
          status: status,
          starts_at: startDate,
          ends_at: endDate,
          auto_renew: true,
          billing_cycle: billingCycle,
          trial_ends_at: status === "trial" ? trialEnd : null,
        };
      }

      const method = isEdit ? "PUT" : "POST";
      const url = isEdit ? API_BASE + "/" + this.currentEditId : API_BASE;

      apiRequest(method, url, data)
        .then(function (response) {
          if (response.success) {
            self.showSuccess(isEdit ? "Subscription updated successfully" : "Subscription created successfully");

            self.currentEditId = null;
            self.currentEditStatus = null;
            if (self.subscriptionModalInstance) self.subscriptionModalInstance.hide();
            self.currentPage = 1;
            self.loadSubscriptions();
          } else {
            self.showError(response.error?.message || "Failed to save subscription");
          }
        })
        .catch(function (err) {
          self.showError(err?.data?.error?.message || "Error saving subscription");
        });
    },

    /**
     * Edit subscription
     */
    editSubscription: function (id) {
      if (!this.canManageSubscriptions) {
        this.showError("Admin access required.");
        return;
      }

      const self = this;
      const url = API_BASE + "/" + id;

      apiRequest("GET", url, null)
        .then(function (response) {
          if (response.success && response.data) {
            const sub = response.data;
            if (sub.status === "pending_payment") {
              self.showError("Pending payment dikelola oleh sistem. Gunakan invoice atau checkout flow untuk memproses status ini.");
              return;
            }
            const companySel = document.getElementById("input_subscription_company");
            const companyName = sub.company?.name
              || self.companies.find(function (company) {
                return String(company.id) === String(sub.companyId);
              })?.name
              || "";
            if (companySel) {
              const companyUuid = sub.company?.uuid
                || (self.companies.find(function (company) {
                  return String(company.id) === String(sub.companyId);
                }) || {}).uuid
                || String(sub.companyId || "");
              companySel.value = String(companyUuid || "");
            }
            self.setSubscriptionModalMode("edit", companyName);
            document.getElementById("input_subscription_package").value = String(sub.packageId || "");
            document.getElementById("input_subscription_start").value = sub.startDate || "";
            document.getElementById("input_subscription_cycle").value = sub.billingCycle || "monthly";
            const endEl = document.getElementById("input_subscription_end");
            if (endEl) {
              endEl.value = sub.endDate || (sub.endsAt ? String(sub.endsAt).slice(0, 10) : "");
            }

            const statusEl = document.getElementById("input_subscription_status");
            if (statusEl) {
              statusEl.value = sub.status || "active";
            }
            const trialEl = document.getElementById("input_subscription_trial_end");
            if (trialEl) {
              trialEl.value = sub.trialEndsAt ? String(sub.trialEndsAt).slice(0, 10) : "";
            }
            self.toggleSubscriptionTrialUI();

            self.currentEditId = id;
            self.currentEditStatus = sub.status || null;
            const title = document.getElementById("subscriptionModalTitle");
            const submitBtn = document.querySelector("#subscriptionForm button[type='submit']");
            if (title) title.textContent = "Edit Subscription";
            if (submitBtn) submitBtn.textContent = "Update Subscription";
            if (self.subscriptionModalInstance) self.subscriptionModalInstance.show();
          } else {
            self.showError("Failed to load subscription");
          }
        })
        .catch(function (err) {
          console.error(err);
          self.showError("Error loading subscription");
        });
    },

    cancelSubscription: async function (id) {
      if (!this.canManageSubscriptions) {
        this.showError("Admin access required.");
        return;
      }

      let confirmed = false;
      if (window.ArcavUi && typeof window.ArcavUi.confirmDelete === "function") {
        confirmed = await window.ArcavUi.confirmDelete(
          "Batalkan subscription ini?",
          "Cancel Subscription"
        );
      } else {
        confirmed = window.ArcavUi && typeof window.ArcavUi.confirm === "function"
          ? await window.ArcavUi.confirm("Are you sure you want to cancel this subscription?", "Cancel Subscription")
          : false;
      }
      if (!confirmed) return;

      const self = this;
      const url = API_BASE + "/" + id;
      const data = { status: "cancelled" };

      apiRequest("PUT", url, data)
        .then(function (response) {
          if (response.success) {
            self.showSuccess("Subscription cancelled successfully");
            self.loadSubscriptions();
          } else {
            self.showError(response.error?.message || "Failed to cancel subscription");
          }
        })
        .catch(function (err) {
          console.error(err);
          self.showError("Error cancelling subscription");
        });
    },

    /**
     * View subscription details
     */
    viewSubscriptionDetails: function (id) {
      const self = this;
      apiRequest("GET", API_BASE + "/" + id, null)
        .then(function (response) {
          if (!response.success || !response.data) {
            self.showError("Failed to load subscription");
            return;
          }
          const sub = response.data;
          const trialLine = sub.trialEndsAt
            ? "\nTrial ends: " + formatDate(sub.trialEndsAt)
            : "";
          const text =
            "Company: " + (sub.companyName || "-") + "\n" +
            "Package: " + (sub.packageName || sub.planCode || "-") + "\n" +
            "Status: " + (sub.status || "-") + "\n" +
            "Start Date: " + formatDate(sub.startDate || sub.startsAt) + "\n" +
            "End Date: " + formatDate(sub.endDate || sub.endsAt) + "\n" +
            "Auto Renew: " + (sub.autoRenew ? "Yes" : "No") + "\n" +
            "Billing Cycle: " + (sub.billingCycle || "-") + "\n" +
            "Amount: " + formatCurrency(sub.amount || 0) +
            trialLine;

          if (window.ArcavUi && typeof window.ArcavUi.showInfo === "function") {
            window.ArcavUi.showInfo("Subscription Details", text);
            return;
          }
          self.showToast(text.replace(/\n/g, "<br>"), "info");
        })
        .catch(function (err) {
          console.error(err);
          self.showError("Error loading subscription");
        });
    },

    openRenewModal: function (id) {
      if (!this.canManageSubscriptions) {
        this.showError("Admin access required.");
        return;
      }

      const sub = this.subscriptions.find(function (item) {
        return String(subscriptionRouteKey(item)) === String(id);
      });

      if (!sub) {
        this.showError(
          "Langganan tidak ada di halaman ini. Gunakan tombol Reactivate by ID, masukkan Subscription ID, lalu Load."
        );
        return;
      }

      if (!isRenewableSubscriptionStatus(sub.status)) {
        this.showError("Manual reactivation is only available for expired, cancelled, suspended, or inactive subscriptions.");
        return;
      }

      this.pendingRenewId = id;
      this.pendingRenewSourceStatus = String(sub.status || "");
      this.pendingRenewContext = {
        actionLabel: "manual reactivation",
        companyName: sub.companyName || sub.company?.name || null,
      };
      const endInput = document.getElementById("input_renew_ends_at");
      if (endInput) {
        endInput.value = defaultRenewEndDateFromBillingCycle(sub.billingCycle);
      }

      if (this.subscriptionRenewModalInstance) {
        this.subscriptionRenewModalInstance.show();
      }
    },

    resetRenewByIdModalUi: function () {
      this.renewByIdLoadedSub = null;
      const idIn = document.getElementById("input_renew_lookup_id");
      if (idIn) idIn.value = "";
      const summary = document.getElementById("renew_by_id_summary");
      if (summary) {
        summary.classList.add("d-none");
        summary.innerHTML = "";
      }
      const step2 = document.getElementById("renew_by_id_step2");
      if (step2) step2.classList.add("d-none");
      const endIn = document.getElementById("input_renew_by_id_ends_at");
      if (endIn) endIn.value = "";
      const renewBtn = document.getElementById("btn_confirm_renew_by_id");
      if (renewBtn) renewBtn.classList.add("d-none");
    },

    confirmSuspendedReactivation: async function (context) {
      const actionLabel = context?.actionLabel || "reactivation";
      const companyName = context?.companyName ? String(context.companyName) : "this company";
      const dialogMessage =
        "You are about to reactivate a suspended subscription for "
        + companyName
        + ". Continue with " + actionLabel + "?";

      const modalMessage = document.getElementById("reactivate_confirm_message");
      if (modalMessage) {
        modalMessage.textContent = dialogMessage;
      }

      if (this.subscriptionReactivateConfirmModalInstance) {
        return new Promise(function (resolve) {
          this.pendingReactivateConfirmResolver = resolve;
          this.pendingReactivateConfirmHandled = false;
          this.subscriptionReactivateConfirmModalInstance.show();
        }.bind(this));
      }

      if (window.ArcavUi && typeof window.ArcavUi.confirm === "function") {
        return window.ArcavUi.confirm(dialogMessage, "Reactivate Subscription");
      }

      return window.confirm(dialogMessage);
    },

    openRenewByIdModal: function () {
      if (!this.canManageSubscriptions) {
        this.showError("Admin access required.");
        return;
      }
      this.resetRenewByIdModalUi();
      if (this.subscriptionRenewByIdModalInstance) {
        this.subscriptionRenewByIdModalInstance.show();
      }
    },

    loadSubscriptionForRenewById: function () {
      const self = this;
      if (!this.canManageSubscriptions) {
        this.showError("Admin access required.");
        return;
      }
      const raw = document.getElementById("input_renew_lookup_id")?.value;
      const lookupRaw = String(raw || "").trim();
      if (!lookupRaw) {
        this.showError("Masukkan Subscription ID atau Reference yang valid.");
        return;
      }

      let lookupKey = lookupRaw;
      if (/^\d+$/.test(lookupRaw)) {
        const byNumericId = this.subscriptions.find(function (item) {
          return String(item.id) === lookupRaw;
        });
        if (byNumericId) {
          lookupKey = subscriptionRouteKey(byNumericId);
        }
      }

      apiRequest("GET", API_BASE + "/" + encodeURIComponent(lookupKey), null)
        .then(function (response) {
          if (!response.success || !response.data) {
            self.showError(response.error?.message || "Subscription tidak ditemukan.");
            return;
          }
          const sub = response.data;
          self.renewByIdLoadedSub = sub;

          const summary = document.getElementById("renew_by_id_summary");
          if (summary) {
            summary.classList.remove("d-none");
            summary.innerHTML =
              "<strong>ID " + esc(sub.id) + "</strong><br>" +
              esc(sub.companyName || "-") + " — " + esc(sub.packageName || sub.planCode || "-") + "<br>" +
              "Status: <strong>" + esc(normalizeStatusLabel(sub.status)) + "</strong> · Cycle: " + esc(sub.billingCycle || "-");
          }

          const step2 = document.getElementById("renew_by_id_step2");
          const endIn = document.getElementById("input_renew_by_id_ends_at");
          const renewBtn = document.getElementById("btn_confirm_renew_by_id");

          self.pendingRenewSourceStatus = String(sub.status || "");
          self.pendingRenewContext = {
            actionLabel: "manual reactivation",
            companyName: sub.companyName || sub.company?.name || null,
          };

          if (!isRenewableSubscriptionStatus(sub.status)) {
            self.showError("Status tidak mendukung reaktivasi manual (hanya: expired, cancelled, suspended, inactive).");
            if (step2) step2.classList.add("d-none");
            if (renewBtn) renewBtn.classList.add("d-none");
            return;
          }

          if (step2) step2.classList.remove("d-none");
          if (endIn) {
            endIn.value = defaultRenewEndDateFromBillingCycle(sub.billingCycle);
          }
          if (renewBtn) renewBtn.classList.remove("d-none");
        })
        .catch(function () {
          self.showError("Subscription tidak ditemukan atau tidak dapat diakses.");
        });
    },

    confirmRenewById: async function () {
      const self = this;
      const sub = this.renewByIdLoadedSub;
      const routeKey = subscriptionRouteKey(sub);
      if (!sub || !routeKey) {
        this.showError("Muat subscription dulu (Load).");
        return;
      }
      const endsAt = document.getElementById("input_renew_by_id_ends_at")?.value;
      if (!endsAt) {
        this.showError("Pilih tanggal akhir baru.");
        return;
      }

      if (String(sub.status || "") === "suspended") {
        const confirmed = await this.confirmSuspendedReactivation({
          actionLabel: "manual reactivation",
          companyName: sub.companyName || sub.company?.name || null,
        });
        if (!confirmed) {
          return;
        }
      }

      apiRequest("POST", API_BASE + "/" + encodeURIComponent(routeKey) + "/renew", { ends_at: endsAt })
        .then(function (response) {
          if (response.success) {
            self.showSuccess("Subscription reactivated successfully.");
            self.renewByIdLoadedSub = null;
            self.pendingRenewSourceStatus = null;
            self.pendingRenewContext = null;
            if (self.subscriptionRenewByIdModalInstance) self.subscriptionRenewByIdModalInstance.hide();
            self.loadSubscriptions();
          } else {
            self.showError(response.error?.message || "Manual reactivation failed");
          }
        })
        .catch(function (err) {
          self.showError(err?.data?.error?.message || "Error reactivating subscription");
        });
    },

    confirmRenewSubscription: async function () {
      const self = this;
      const id = this.pendingRenewId;
      const endsAt = document.getElementById("input_renew_ends_at")?.value;

      if (!id || !endsAt) {
        this.showError("Pilih tanggal akhir baru.");
        return;
      }

      if (this.pendingRenewSourceStatus === "suspended") {
        const confirmed = await this.confirmSuspendedReactivation(this.pendingRenewContext || {
          actionLabel: "manual reactivation",
          companyName: null,
        });
        if (!confirmed) {
          return;
        }
      }

      apiRequest("POST", API_BASE + "/" + id + "/renew", { ends_at: endsAt })
        .then(function (response) {
          if (response.success) {
            self.showSuccess("Subscription reactivated successfully");
            self.pendingRenewId = null;
            self.pendingRenewSourceStatus = null;
            self.pendingRenewContext = null;
            if (self.subscriptionRenewModalInstance) self.subscriptionRenewModalInstance.hide();
            self.loadSubscriptions();
          } else {
            self.showError(response.error?.message || "Manual reactivation failed");
          }
        })
        .catch(function (err) {
          self.showError(err?.data?.error?.message || "Error reactivating subscription");
        });
    },

    /**
     * Show success message
     */
    showSuccess: function (message) {
      this.showToast(message, "success");
    },

    /**
     * Show error message
     */
    showError: function (message) {
      this.showToast(message, "danger");
    },

    /**
     * Show toast notification
     */
    showToast: function (message, type) {
      const alertDiv = document.createElement("div");
      alertDiv.className = `alert alert-${type} alert-dismissible fade show position-fixed top-0 end-0 m-3`;
      alertDiv.style.zIndex = 9999;
      alertDiv.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      `;
      document.body.appendChild(alertDiv);
      setTimeout(() => alertDiv.remove(), 5000);
    },
  };

  // Initialize when DOM is ready
  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", function () {
      SubscriptionsManager.init();
    });
  } else {
    SubscriptionsManager.init();
  }

  // Expose to global scope
  window.SubscriptionsManager = SubscriptionsManager;
})(window, document);
