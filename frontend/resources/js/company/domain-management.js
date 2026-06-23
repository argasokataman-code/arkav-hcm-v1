(function (window, document) {
  "use strict";

  const API_BASE = "/v1/saas/domains";
  const COMPANY_API = "/v1/company";
  const PAGE_SIZE = 10;
  const DOMAIN_NAME_REGEX = /^(?=.{1,253}$)(?!-)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/;
  let apiToken = null;

  function normalizeDomainName(value) {
    return String(value || "").trim().toLowerCase();
  }

  function validateDomainPayload(payload) {
    const errors = [];
    const companyId = String(payload?.company_id || "").trim();
    const domainName = normalizeDomainName(payload?.domain_name);
    const verificationType = String(payload?.verification_type || "").trim();

    if (!companyId) {
      errors.push("Company wajib dipilih.");
    }

    if (!domainName) {
      errors.push("Domain name wajib diisi.");
    } else if (!DOMAIN_NAME_REGEX.test(domainName)) {
      errors.push("Domain name harus berupa host/domain valid tanpa http:// atau path.");
    }

    if (verificationType !== "dns" && verificationType !== "file") {
      errors.push("Verification type wajib dipilih.");
    }

    return errors;
  }

  function formatApiError(err, fallback) {
    const data = err?.data || {};
    const validationErrors = data?.errors;

    if (validationErrors && typeof validationErrors === "object") {
      const firstField = Object.keys(validationErrors)[0];
      const firstMessage = firstField && Array.isArray(validationErrors[firstField])
        ? validationErrors[firstField][0]
        : null;
      if (firstMessage) {
        return firstMessage;
      }
    }

    return data?.message || data?.error?.message || fallback;
  }

  function getApiToken() {
    if (apiToken) {
      return Promise.resolve(apiToken);
    }

    return fetch("/api-token", {
      method: "GET",
      headers: {
        Accept: "application/json",
        "X-Requested-With": "XMLHttpRequest",
      },
      credentials: "same-origin",
    })
      .then(function (res) {
        return res.json().then(function (data) {
          if (!res.ok || !data.success) {
            return Promise.reject({ status: res.status, data: data });
          }
          apiToken = data.data.token;
          return apiToken;
        });
      })
      .catch(function (err) {
        console.error("Failed to fetch API token:", err);
        throw err;
      });
  }

  function apiRequest(method, url, body) {
    return getApiToken().then(function (token) {
      const headers = {
        Accept: "application/json",
        "X-Requested-With": "XMLHttpRequest",
        Authorization: "Bearer " + token,
      };

      if (body && typeof body === "object" && !(body instanceof FormData)) {
        headers["Content-Type"] = "application/json";
      }

      const opts = {
        method: method,
        headers: headers,
        credentials: "same-origin",
      };

      if (body && method !== "GET") {
        opts.body = body instanceof FormData ? body : JSON.stringify(body);
      }

      return fetch(url, opts).then(function (res) {
        return res
          .json()
          .catch(function () {
            return {};
          })
          .then(function (data) {
            if (!res.ok) {
              return Promise.reject({ status: res.status, data: data });
            }
            return data;
          });
      });
    });
  }

  function esc(v) {
    return String(v || "")
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/\"/g, "&quot;")
      .replace(/'/g, "&#39;");
  }

  function formatDate(dateStr) {
    if (!dateStr) return "-";
    const d = new Date(dateStr);
    return (
      ("0" + d.getDate()).slice(-2) +
      "/" +
      ("0" + (d.getMonth() + 1)).slice(-2) +
      "/" +
      d.getFullYear()
    );
  }

  function toTitleCase(value) {
    return String(value || "")
      .replace(/[_-]/g, " ")
      .replace(/\b\w/g, function (s) {
        return s.toUpperCase();
      });
  }

  const DomainManager = {
    isInitialized: false,
    currentPage: 1,
    totalPages: 1,
    domains: [],
    companies: [],
    currentEditId: null,
    pendingVerifyDomainId: null,
    domainModalInstance: null,
    verificationModalInstance: null,

    getCompanyByIdentifier: function (identifier) {
      const raw = String(identifier || "").trim();
      if (!raw) return null;

      return this.companies.find(function (company) {
        return String(company.uuid || "") === raw || String(company.id || "") === raw;
      }) || null;
    },

    init: function () {
      if (this.isInitialized) return;
      this.isInitialized = true;

      this.domainModalInstance = window.bootstrap
        ? window.bootstrap.Modal.getOrCreateInstance(document.getElementById("domainModal"))
        : null;
      this.verificationModalInstance = window.bootstrap
        ? window.bootstrap.Modal.getOrCreateInstance(document.getElementById("verificationModal"))
        : null;

      this.bindEvents();
      this.loadCompanies();
      this.loadDomains();
    },

    bindEvents: function () {
      const self = this;

      const form = document.getElementById("domainForm");
      if (form) {
        form.addEventListener("submit", function (e) {
          e.preventDefault();
          if (!ArcavValidation.validateForm(form)) { return; }
          self.handleSaveDomain();
        });
      }

      const addBtn = document.getElementById("btn_add_domain");
      if (addBtn) {
        addBtn.addEventListener("click", function () {
          self.openCreateModal();
        });
      }

      const statusFilter = document.getElementById("filter_status");
      if (statusFilter) {
        statusFilter.addEventListener("change", function () {
          self.currentPage = 1;
          self.loadDomains();
        });
      }

      const companyFilter = document.getElementById("filter_company");
      if (companyFilter) {
        companyFilter.addEventListener("change", function () {
          self.currentPage = 1;
          self.loadDomains();
        });
      }

      const searchInput = document.getElementById("search_domains");
      if (searchInput) {
        let timer = null;
        searchInput.addEventListener("input", function () {
          window.clearTimeout(timer);
          timer = window.setTimeout(function () {
            self.currentPage = 1;
            self.loadDomains();
          }, 250);
        });
      }

      const resetBtn = document.getElementById("btn_reset_filters");
      if (resetBtn) {
        resetBtn.addEventListener("click", function () {
          const status = document.getElementById("filter_status");
          const company = document.getElementById("filter_company");
          const search = document.getElementById("search_domains");
          if (status) status.value = "";
          if (company) company.value = "";
          if (search) search.value = "";
          self.currentPage = 1;
          self.loadDomains();
        });
      }

      const verifyBtn = document.getElementById("btn_verify_domain");
      if (verifyBtn) {
        verifyBtn.addEventListener("click", function () {
          if (self.pendingVerifyDomainId) {
            self.verifyDomain(self.pendingVerifyDomainId);
          }
        });
      }

      document.addEventListener("click", function (e) {
        const pageLink = e.target.closest("[data-page]");
        if (pageLink) {
          e.preventDefault();
          self.currentPage = parseInt(pageLink.getAttribute("data-page"), 10) || 1;
          self.loadDomains();
        }

        const editBtn = e.target.closest("[data-edit-domain]");
        if (editBtn) {
          e.preventDefault();
          self.editDomain(editBtn.getAttribute("data-edit-domain"));
        }

        const deleteBtn = e.target.closest("[data-delete-domain]");
        if (deleteBtn) {
          e.preventDefault();
          self.deleteDomain(deleteBtn.getAttribute("data-delete-domain"));
        }

        const detailBtn = e.target.closest("[data-verify-details]");
        if (detailBtn) {
          e.preventDefault();
          self.showVerificationDetails(detailBtn.getAttribute("data-verify-details"));
        }

        const verifyDomainBtn = e.target.closest("[data-verify-domain]");
        if (verifyDomainBtn) {
          e.preventDefault();
          self.verifyDomain(verifyDomainBtn.getAttribute("data-verify-domain"));
        }
      });
    },

    loadCompanies: function () {
      const self = this;
      return apiRequest("GET", COMPANY_API + "?page=1&per_page=200", null)
        .then(function (response) {
          const list =
            response?.data?.companies ||
            response?.data ||
            [];
          self.companies = Array.isArray(list) ? list : [];
          self.renderCompanyOptions();
        })
        .catch(function () {
          self.companies = [];
          self.renderCompanyOptions();
        });
    },

    renderCompanyOptions: function () {
      const modalSelect = document.getElementById("input_domain_company");
      const filterSelect = document.getElementById("filter_company");

      const options = this.companies
        .map(function (company) {
          const optionValue = company.uuid || company.id;
          const name = company.name || ("Company #" + (company.id || ""));
          return '<option value="' + esc(optionValue) + '">' + esc(name) + "</option>";
        })
        .join("");

      if (modalSelect) {
        modalSelect.innerHTML = '<option value="">Select company</option>' + options;
      }
      if (filterSelect) {
        filterSelect.innerHTML = '<option value="">All Companies</option>' + options;
      }
    },

    loadDomains: function () {
      const self = this;
      const params = new URLSearchParams({
        page: String(this.currentPage),
        per_page: String(PAGE_SIZE),
      });

      const status = document.getElementById("filter_status")?.value || "";
      const companyId = document.getElementById("filter_company")?.value || "";
      const search = String(document.getElementById("search_domains")?.value || "").trim();

      if (status) params.set("status", status);
      if (companyId) params.set("company_id", companyId);
      if (search) params.set("search", search);

      apiRequest("GET", API_BASE + "?" + params.toString(), null)
        .then(function (response) {
          self.domains = response?.data || [];
          self.totalPages = response?.pagination?.last_page || 1;
          self.renderDomains();
        })
        .catch(function () {
          self.showError("Error loading domains");
        });
    },

    renderDomains: function () {
      const container = document.querySelector("[data-domains-list-container]");
      if (!container) return;

      if (!this.domains.length) {
        container.innerHTML =
          '<div class="card"><div class="card-body text-center text-muted py-4">No domains found</div></div>';
        return;
      }

      const html =
        '<div class="card">' +
        '<div class="table-responsive">' +
        '<table class="table table-hover mb-0">' +
        '<thead class="table-light">' +
        '<tr><th>Domain</th><th>Company</th><th>Verification Type</th><th>Status</th><th>Verified At</th><th>Actions</th></tr>' +
        '</thead><tbody>' +
        this.domains
          .map(function (domain) {
            const statusClass =
              domain.status === "verified"
                ? "badge-success"
                : domain.status === "pending"
                ? "badge-warning"
                : "badge-danger";
            const verificationType = toTitleCase(domain.verificationType || "-");
            const showVerify = domain.status !== "verified";

            return (
              '<tr>' +
              '<td><strong>' + esc(domain.domainName) + "</strong></td>" +
              "<td>" +
              esc(domain.companyName || "N/A") +
              "</td>" +
              '<td><span class="badge bg-light text-dark">' +
              esc(verificationType) +
              "</span></td>" +
              '<td><span class="badge ' +
              statusClass +
              ' d-inline-flex align-items-center badge-xs"><i class="ti ti-point-filled me-1"></i>' +
              esc(domain.status) +
              "</span></td>" +
              "<td>" +
              formatDate(domain.verifiedAt) +
              "</td>" +
              '<td><div class="action-icon d-inline-flex">' +
              '<button class="btn btn-icon btn-sm me-2" data-edit-domain="' +
              esc(domain.id) +
              '" title="Edit"><i class="ti ti-edit"></i></button>' +
              (showVerify
                ? '<button class="btn btn-icon btn-sm me-2" data-verify-details="' +
                  esc(domain.id) +
                  '" title="Verification Details"><i class="ti ti-info-circle"></i></button>' +
                  '<button class="btn btn-icon btn-sm me-2" data-verify-domain="' +
                  esc(domain.id) +
                  '" title="Verify"><i class="ti ti-check"></i></button>'
                : "") +
              '<button class="btn btn-icon btn-sm" data-delete-domain="' +
              esc(domain.id) +
              '" title="Delete"><i class="ti ti-trash"></i></button>' +
              "</div></td>" +
              "</tr>"
            );
          })
          .join("") +
        "</tbody></table></div>" +
        '<div class="card-footer d-flex justify-content-end"><ul class="pagination pagination-sm mb-0" data-domain-pagination></ul></div>' +
        "</div>";

      container.innerHTML = html;
      this.renderPagination();
    },

    renderPagination: function () {
      const container = document.querySelector("[data-domain-pagination]");
      if (!container) return;
      container.innerHTML = "";

      if (this.currentPage > 1) {
        container.insertAdjacentHTML(
          "beforeend",
          '<li class="page-item"><a class="page-link" href="javascript:void(0);" data-page="' +
            (this.currentPage - 1) +
            '">Previous</a></li>'
        );
      }

      for (let i = 1; i <= this.totalPages; i += 1) {
        container.insertAdjacentHTML(
          "beforeend",
          '<li class="page-item' +
            (i === this.currentPage ? " active" : "") +
            '"><a class="page-link" href="javascript:void(0);" data-page="' +
            i +
            '">' +
            i +
            "</a></li>"
        );
      }

      if (this.currentPage < this.totalPages) {
        container.insertAdjacentHTML(
          "beforeend",
          '<li class="page-item"><a class="page-link" href="javascript:void(0);" data-page="' +
            (this.currentPage + 1) +
            '">Next</a></li>'
        );
      }
    },

    openCreateModal: function () {
      this.currentEditId = null;
      const form = document.getElementById("domainForm");
      if (form) form.reset();

      const title = document.getElementById("domainModalTitle");
      const submitBtn = document.querySelector("#domainForm button[type='submit']");
      if (title) title.textContent = "Add Domain";
      if (submitBtn) submitBtn.textContent = "Add Domain";
    },

    editDomain: function (id) {
      const self = this;
      apiRequest("GET", API_BASE + "/" + id, null)
        .then(function (response) {
          const domain = response?.data;
          if (!domain) {
            self.showError("Failed to load domain");
            return;
          }

          self.currentEditId = domain.id;

          const company = document.getElementById("input_domain_company");
          const name = document.getElementById("input_domain_name");
          const notes = document.getElementById("input_domain_notes");
          const matchedCompany = self.getCompanyByIdentifier(domain.companyUuid || domain.companyId);
          if (company) company.value = String(matchedCompany?.uuid || domain.companyUuid || "");
          if (name) name.value = domain.domainName || "";
          if (notes) notes.value = domain.notes || "";

          const dnsRadio = document.getElementById("verification_dns");
          const fileRadio = document.getElementById("verification_file");
          if (dnsRadio) dnsRadio.checked = domain.verificationType === "dns";
          if (fileRadio) fileRadio.checked = domain.verificationType === "file";

          const title = document.getElementById("domainModalTitle");
          const submitBtn = document.querySelector("#domainForm button[type='submit']");
          if (title) title.textContent = "Edit Domain";
          if (submitBtn) submitBtn.textContent = "Update Domain";

          if (self.domainModalInstance) {
            self.domainModalInstance.show();
            var firstInput = document.querySelector("#domainModal input:not([type=hidden]):not([type=password]), #domainModal select");
            if (firstInput) setTimeout(function() { firstInput.focus(); }, 100);
          }
        })
        .catch(function () {
          self.showError("Error loading domain");
        });
    },

    handleSaveDomain: function () {
      const self = this;
      const companyId = document.getElementById("input_domain_company")?.value;
      const domainName = String(document.getElementById("input_domain_name")?.value || "").trim();
      const notes = String(document.getElementById("input_domain_notes")?.value || "").trim();
      const verificationType = document.querySelector("input[name='verification_type']:checked")?.value;

      if (!companyId || !domainName || !verificationType) {
        self.showError("Company, domain name, dan verification type wajib diisi.");
        return;
      }

      const payload = {
        company_id: companyId,
        domain_name: normalizeDomainName(domainName),
        verification_type: verificationType,
        notes: notes || null,
      };

      const validationErrors = validateDomainPayload(payload);
      if (validationErrors.length) {
        self.showError(validationErrors[0]);
        return;
      }

      const isEdit = !!this.currentEditId;
      const method = isEdit ? "PUT" : "POST";
      const url = isEdit ? API_BASE + "/" + this.currentEditId : API_BASE;

      apiRequest(method, url, payload)
        .then(function () {
          self.showSuccess(isEdit ? "Domain updated successfully" : "Domain added successfully");
          self.currentEditId = null;
          if (self.domainModalInstance) self.domainModalInstance.hide();
          self.currentPage = 1;
          self.loadDomains();
        })
        .catch(function (err) {
          const message = formatApiError(err, "Failed to save domain");
          self.showError(message);
        });
    },

    deleteDomain: async function (id) {
      let confirmed = false;
      if (window.ArcavUi && typeof window.ArcavUi.confirmDelete === "function") {
        confirmed = await window.ArcavUi.confirmDelete(
          "Hapus domain ini? Tindakan tidak dapat dibatalkan.",
          "Delete Domain"
        );
      } else {
        confirmed = window.ArcavUi && typeof window.ArcavUi.confirm === "function"
          ? await window.ArcavUi.confirm("Are you sure you want to delete this domain?", "Delete Domain")
          : false;
      }

      if (!confirmed) return;

      const self = this;
      apiRequest("DELETE", API_BASE + "/" + id, null)
        .then(function () {
          self.showSuccess("Domain deleted successfully");
          self.loadDomains();
        })
        .catch(function (err) {
          const message = formatApiError(err, "Failed to delete domain");
          self.showError(message);
        });
    },

    showVerificationDetails: function (id) {
      const self = this;
      apiRequest("GET", API_BASE + "/" + id + "/verification-details", null)
        .then(function (response) {
          const data = response?.data;
          if (!data) {
            self.showError("Failed to load verification details");
            return;
          }

          self.pendingVerifyDomainId = id;

          const instructions = data.instructions || {};
          const html =
            '<div class="alert alert-info mb-3">' +
            '<p class="mb-1"><strong>Domain:</strong> ' + esc(data.domainName) + "</p>" +
            '<p class="mb-0"><strong>Type:</strong> ' + esc(toTitleCase(data.verificationType)) + "</p>" +
            "</div>" +
            '<div class="card"><div class="card-body">' +
            '<ol class="mb-3">' +
            '<li>' + esc(instructions.step1 || "-") + "</li>" +
            '<li>' + esc(instructions.step2 || "-") + "</li>" +
            '<li>' + esc(instructions.step3 || "-") + "</li>" +
            '<li>' + esc(instructions.step4 || "-") + "</li>" +
            "</ol>" +
            '<p class="mb-1"><strong>Verification Token</strong></p>' +
            '<code class="d-block p-2 bg-light rounded">' + esc(data.token || "-") + "</code>" +
            "</div></div>";

          const target = document.getElementById("verification_instructions");
          if (target) target.innerHTML = html;
          if (self.verificationModalInstance) {
            self.verificationModalInstance.show();
            var firstInput = document.querySelector("#verificationModal input:not([type=hidden]):not([type=password]), #verificationModal select");
            if (firstInput) setTimeout(function() { firstInput.focus(); }, 100);
          }
        })
        .catch(function () {
          self.showError("Error loading verification details");
        });
    },

    verifyDomain: function (id) {
      const self = this;
      apiRequest("POST", API_BASE + "/" + id + "/verify", {})
        .then(function () {
          self.showSuccess("Domain verified successfully");
          self.pendingVerifyDomainId = null;
          if (self.verificationModalInstance) self.verificationModalInstance.hide();
          self.loadDomains();
        })
        .catch(function (err) {
          const message = formatApiError(err, "Failed to verify domain");
          self.showError(message);
        });
    },

    showSuccess: function (message) {
      this.showToast(message, "success");
    },

    showError: function (message) {
      this.showToast(message, "danger");
    },

    showToast: function (message, type) {
      const alertDiv = document.createElement("div");
      alertDiv.className =
        "alert alert-" +
        type +
        " alert-dismissible fade show position-fixed top-0 end-0 m-3";
      alertDiv.style.zIndex = 9999;
      alertDiv.innerHTML =
        esc(message) +
        '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
      document.body.appendChild(alertDiv);
      window.setTimeout(function () {
        alertDiv.remove();
      }, 5000);
    },
  };

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", function () {
      DomainManager.init();
    });
  } else {
    DomainManager.init();
  }

  window.DomainManager = DomainManager;
  window.DomainManagementRules = {
    normalizeDomainName: normalizeDomainName,
    validateDomainPayload: validateDomainPayload,
    formatApiError: formatApiError,
  };
})(window, document);
