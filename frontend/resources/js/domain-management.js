(function (window, document) {
  "use strict";

  const API_BASE = "/v1/saas/domains";
  const PAGE_SIZE = 10;

  // Utility: API request with auth headers
  function apiRequest(method, url, body) {
    const headers = {
      Accept: "application/json",
      "X-Requested-With": "XMLHttpRequest",
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

    return fetch(url, opts)
      .then(function (res) {
        return res
          .json()
          .catch(function () {
            return {};
          })
          .then(function (data) {
            if (!res.ok) {
              return Promise.reject({
                status: res.status,
                data: data,
              });
            }
            return data;
          });
      })
      .catch(function (err) {
        console.error("API request failed:", err);
        throw err;
      });
  }

  // Helper: escape HTML
  function esc(v) {
    return String(v || "")
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#39;");
  }

  // Format date as DD/MM/YYYY
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

  // Main DomainManager object
  const DomainManager = {
    currentPage: 1,
    totalPages: 1,
    domains: [],
    currentEditId: null,

    /**
     * Initialize the domains list page
     */
    init: function () {
      this.bindEvents();
      this.loadDomains();
    },

    /**
     * Bind event listeners
     */
    bindEvents: function () {
      const self = this;

      // Add form submission
      const addForm = document.getElementById("add_domain_form");
      if (addForm) {
        addForm.addEventListener("submit", function (e) {
          e.preventDefault();
          self.handleAddDomain(e.target);
        });
      }

      // Edit form submission
      const editForm = document.getElementById("edit_domain_form");
      if (editForm) {
        editForm.addEventListener("submit", function (e) {
          e.preventDefault();
          self.handleEditDomain(e.target);
        });
      }

      // Pagination buttons
      document.addEventListener("click", function (e) {
        if (e.target.matches("[data-page]")) {
          e.preventDefault();
          const page = parseInt(e.target.getAttribute("data-page"));
          self.currentPage = page;
          self.loadDomains();
        }

        // Edit button
        if (e.target.matches("[data-edit-domain]")) {
          e.preventDefault();
          const id = e.target.getAttribute("data-edit-domain");
          self.editDomain(id);
        }

        // Delete button
        if (e.target.matches("[data-delete-domain]")) {
          e.preventDefault();
          const id = e.target.getAttribute("data-delete-domain");
          self.deleteDomain(id);
        }

        // Verify button
        if (e.target.matches("[data-verify-domain]")) {
          e.preventDefault();
          const id = e.target.getAttribute("data-verify-domain");
          self.verifyDomain(id);
        }

        // View verification details button
        if (e.target.matches("[data-verify-details]")) {
          e.preventDefault();
          const id = e.target.getAttribute("data-verify-details");
          self.showVerificationDetails(id);
        }
      });
    },

    /**
     * Load domains from API
     */
    loadDomains: function () {
      const self = this;
      const url = API_BASE + "?page=" + this.currentPage + "&per_page=" + PAGE_SIZE;

      apiRequest("GET", url, null)
        .then(function (response) {
          if (response.success && response.data) {
            self.domains = response.data;
            self.totalPages = response.pagination ? response.pagination.last_page : 1;
            self.renderDomains();
            self.updateStats();
          } else {
            self.showError("Failed to load domains");
          }
        })
        .catch(function (err) {
          console.error(err);
          self.showError("Error loading domains");
        });
    },

    /**
     * Render domains table
     */
    renderDomains: function () {
      const tbody = document.querySelector("#domains_table tbody");
      if (!tbody) return;

      tbody.innerHTML = "";

      if (this.domains.length === 0) {
        tbody.innerHTML =
          '<tr><td colspan="7" class="text-center py-3">No domains found</td></tr>';
        return;
      }

      this.domains.forEach((domain) => {
        const statusBadge = `badge bg-${
          domain.verified || domain.status === "verified"
            ? "success"
            : domain.status === "pending"
            ? "warning"
            : "danger"
        }`;
        const verificationMethodBadge = `badge bg-light text-dark`;

        const row = document.createElement("tr");
        row.innerHTML = `
          <td><strong>${esc(domain.domain)}</strong></td>
          <td>${esc(domain.companyName || "N/A")}</td>
          <td><span class="${verificationMethodBadge}">${esc(domain.verificationMethod)}</span></td>
          <td><span class="${statusBadge}">${esc(domain.verified ? "verified" : domain.status)}</span></td>
          <td>${formatDate(domain.verifiedAt) || "Not verified"}</td>
          <td>
            <div class="d-flex gap-2">
              ${
                !domain.verified
                  ? `<button class="btn btn-sm btn-warning" data-verify-details="${domain.id}" title="Verification Details">
                      <i class="ti ti-info-circle"></i>
                    </button>
                    <button class="btn btn-sm btn-success" data-verify-domain="${domain.id}" title="Verify">
                      <i class="ti ti-check"></i>
                    </button>`
                  : ""
              }
              <button class="btn btn-sm btn-primary" data-edit-domain="${domain.id}" title="Edit">
                <i class="ti ti-edit"></i>
              </button>
              <button class="btn btn-sm btn-danger" data-delete-domain="${domain.id}" title="Delete">
                <i class="ti ti-trash"></i>
              </button>
            </div>
          </td>
        `;
        tbody.appendChild(row);
      });

      this.renderPagination();
    },

    /**
     * Render pagination
     */
    renderPagination: function () {
      const container = document.getElementById("pagination_container");
      if (!container) return;

      container.innerHTML = "";
      const nav = document.createElement("nav");
      const ul = document.createElement("ul");
      ul.className = "pagination mb-0";

      if (this.currentPage > 1) {
        const li = document.createElement("li");
        li.className = "page-item";
        li.innerHTML = `<a class="page-link" href="javascript:void(0);" data-page="${
          this.currentPage - 1
        }">Previous</a>`;
        ul.appendChild(li);
      }

      for (let i = 1; i <= this.totalPages; i++) {
        const li = document.createElement("li");
        li.className = "page-item" + (i === this.currentPage ? " active" : "");
        li.innerHTML = `<a class="page-link" href="javascript:void(0);" data-page="${i}">${i}</a>`;
        ul.appendChild(li);
      }

      if (this.currentPage < this.totalPages) {
        const li = document.createElement("li");
        li.className = "page-item";
        li.innerHTML = `<a class="page-link" href="javascript:void(0);" data-page="${
          this.currentPage + 1
        }">Next</a>`;
        ul.appendChild(li);
      }

      nav.appendChild(ul);
      container.appendChild(nav);
    },

    /**
     * Update statistics
     */
    updateStats: function () {
      const totalEl = document.getElementById("total_domains");
      const verifiedEl = document.getElementById("verified_domains");

      if (totalEl) totalEl.textContent = this.domains.length;

      const verifiedCount = this.domains.filter((d) => d.verified || d.status === "verified")
        .length;
      if (verifiedEl) verifiedEl.textContent = verifiedCount;
    },

    /**
     * Handle add domain
     */
    handleAddDomain: function (form) {
      const self = this;
      const formData = new FormData(form);
      const data = {
        domain: formData.get("domain"),
        companyId: parseInt(formData.get("company_id")),
        verificationMethod: formData.get("verification_method"),
      };

      apiRequest("POST", API_BASE, data)
        .then(function (response) {
          if (response.success) {
            self.showSuccess("Domain added successfully");
            form.reset();
            const modal = bootstrap.Modal.getInstance(
              document.getElementById("add_domain")
            );
            if (modal) modal.hide();
            self.currentPage = 1;
            self.loadDomains();
          } else {
            self.showError(response.error?.message || "Failed to add domain");
          }
        })
        .catch(function (err) {
          console.error(err);
          self.showError("Error adding domain");
        });
    },

    /**
     * Edit domain
     */
    editDomain: function (id) {
      const self = this;
      const url = API_BASE + "/" + id;

      apiRequest("GET", url, null)
        .then(function (response) {
          if (response.success && response.data) {
            const domain = response.data;
            document.getElementById("edit_domain_id").value = domain.id;
            document.getElementById("edit_domain").value = domain.domain;
            document.getElementById("edit_company_id").value = domain.companyId;
            document.getElementById("edit_verification_method").value =
              domain.verificationMethod;

            self.currentEditId = id;
            const modal = new bootstrap.Modal(document.getElementById("edit_domain"));
            modal.show();
          } else {
            self.showError("Failed to load domain");
          }
        })
        .catch(function (err) {
          console.error(err);
          self.showError("Error loading domain");
        });
    },

    /**
     * Handle edit domain
     */
    handleEditDomain: function (form) {
      const self = this;
      const id = document.getElementById("edit_domain_id").value;
      const formData = new FormData(form);
      const data = {
        domain: formData.get("domain"),
        verificationMethod: formData.get("verification_method"),
      };

      const url = API_BASE + "/" + id;

      apiRequest("PUT", url, data)
        .then(function (response) {
          if (response.success) {
            self.showSuccess("Domain updated successfully");
            const modal = bootstrap.Modal.getInstance(
              document.getElementById("edit_domain")
            );
            if (modal) modal.hide();
            self.loadDomains();
          } else {
            self.showError(response.error?.message || "Failed to update domain");
          }
        })
        .catch(function (err) {
          console.error(err);
          self.showError("Error updating domain");
        });
    },

    /**
     * Delete domain
     */
    deleteDomain: function (id) {
      if (!confirm("Are you sure you want to delete this domain?")) return;

      const self = this;
      const url = API_BASE + "/" + id;

      apiRequest("DELETE", url, null)
        .then(function (response) {
          if (response.success) {
            self.showSuccess("Domain deleted successfully");
            self.loadDomains();
          } else {
            self.showError(response.error?.message || "Failed to delete domain");
          }
        })
        .catch(function (err) {
          console.error(err);
          self.showError("Error deleting domain");
        });
    },

    /**
     * Show verification details modal
     */
    showVerificationDetails: function (id) {
      const self = this;
      const url = API_BASE + "/" + id;

      apiRequest("GET", url, null)
        .then(function (response) {
          if (response.success && response.data) {
            const domain = response.data;
            let html = `<div class="alert alert-info">
              <p><strong>Domain:</strong> ${esc(domain.domain)}</p>
              <p><strong>Verification Method:</strong> ${esc(domain.verificationMethod)}</p>
              <p><strong>Status:</strong> ${esc(domain.verified ? "Verified" : domain.status)}</p>
            </div>`;

            if (domain.verificationMethod === "dns") {
              html += `
                <div class="card">
                  <div class="card-header">
                    <strong>DNS Record to Add:</strong>
                  </div>
                  <div class="card-body">
                    <p><strong>Type:</strong> TXT</p>
                    <p><strong>Name:</strong> <code>${esc(domain.dnsRecord?.name || "arcav-verify")}</code></p>
                    <p><strong>Value:</strong> <code>${esc(domain.dnsRecord?.value || "N/A")}</code></p>
                    <p class="text-muted">Add this DNS TXT record to your domain provider and click "Verify Domain" button above.</p>
                  </div>
                </div>
              `;
            } else if (domain.verificationMethod === "file") {
              html += `
                <div class="card">
                  <div class="card-header">
                    <strong>File Verification:</strong>
                  </div>
                  <div class="card-body">
                    <p><strong>File Name:</strong> <code>${esc(domain.fileRecord?.filename || "arcav-verification.txt")}</code></p>
                    <p><strong>File URL:</strong> <code>${esc(domain.fileRecord?.url || "N/A")}</code></p>
                    <p><strong>Content:</strong></p>
                    <textarea class="form-control mb-2" rows="3" readonly>${esc(domain.fileRecord?.content || "N/A")}</textarea>
                    <p class="text-muted">Upload this file to your domain and click "Verify Domain" button above.</p>
                  </div>
                </div>
              `;
            }

            document.getElementById("verification_details_content").innerHTML = html;
            const modal = new bootstrap.Modal(
              document.getElementById("verification_details_modal")
            );
            modal.show();
          } else {
            self.showError("Failed to load verification details");
          }
        })
        .catch(function (err) {
          console.error(err);
          self.showError("Error loading verification details");
        });
    },

    /**
     * Verify domain
     */
    verifyDomain: function (id) {
      if (
        !confirm(
          "Verify this domain? Make sure you have completed the verification steps."
        )
      )
        return;

      const self = this;
      const url = API_BASE + "/" + id + "/verify";

      apiRequest("POST", url, {})
        .then(function (response) {
          if (response.success) {
            self.showSuccess("Domain verified successfully!");
            self.loadDomains();
          } else {
            self.showError(
              response.error?.message || "Failed to verify domain. Please check your verification details."
            );
          }
        })
        .catch(function (err) {
          console.error(err);
          self.showError("Error verifying domain");
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
      DomainManager.init();
    });
  } else {
    DomainManager.init();
  }

  // Expose to global scope
  window.DomainManager = DomainManager;
})(window, document);
