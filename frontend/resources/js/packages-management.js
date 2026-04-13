(function (window, document) {
  "use strict";

  const API_BASE = "/v1/saas/packages";
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

  // Format currency
  function formatCurrency(amount) {
    if (!amount) return "Rp 0";
    return "Rp " + parseInt(amount).toLocaleString("id-ID");
  }

  // Main PackagesManager object
  const PackagesManager = {
    currentPage: 1,
    totalPages: 1,
    packages: [],
    currentEditId: null,

    /**
     * Initialize the packages list page
     */
    init: function () {
      this.bindEvents();
      this.loadPackages();
    },

    /**
     * Bind event listeners
     */
    bindEvents: function () {
      const self = this;

      // Add form submission
      const addForm = document.getElementById("add_package_form");
      if (addForm) {
        addForm.addEventListener("submit", function (e) {
          e.preventDefault();
          self.handleAddPackage(e.target);
        });
      }

      // Edit form submission
      const editForm = document.getElementById("edit_package_form");
      if (editForm) {
        editForm.addEventListener("submit", function (e) {
          e.preventDefault();
          self.handleEditPackage(e.target);
        });
      }

      // Pagination buttons
      document.addEventListener("click", function (e) {
        if (e.target.matches("[data-page]")) {
          e.preventDefault();
          const page = parseInt(e.target.getAttribute("data-page"));
          self.currentPage = page;
          self.loadPackages();
        }

        // Edit button
        if (e.target.matches("[data-edit-package]")) {
          e.preventDefault();
          const id = e.target.getAttribute("data-edit-package");
          self.editPackage(id);
        }

        // Delete button
        if (e.target.matches("[data-delete-package]")) {
          e.preventDefault();
          const id = e.target.getAttribute("data-delete-package");
          self.deletePackage(id);
        }

        // Add feature button
        if (e.target.matches("[data-add-feature]")) {
          e.preventDefault();
          const id = e.target.getAttribute("data-add-feature");
          self.showAddFeatureModal(id);
        }

        // Remove feature button
        if (e.target.matches("[data-remove-feature]")) {
          e.preventDefault();
          const packageId = e.target.getAttribute("data-package-id");
          const featureId = e.target.getAttribute("data-remove-feature");
          self.removeFeature(packageId, featureId);
        }
      });
    },

    /**
     * Load packages from API
     */
    loadPackages: function () {
      const self = this;
      const url = API_BASE + "?page=" + this.currentPage + "&per_page=" + PAGE_SIZE;

      apiRequest("GET", url, null)
        .then(function (response) {
          if (response.success && response.data) {
            self.packages = response.data;
            self.totalPages = response.pagination ? response.pagination.last_page : 1;
            self.renderPackages();
            self.updateStats();
          } else {
            self.showError("Failed to load packages");
          }
        })
        .catch(function (err) {
          console.error(err);
          self.showError("Error loading packages");
        });
    },

    /**
     * Render packages table
     */
    renderPackages: function () {
      const tbody = document.querySelector("#packages_table tbody");
      if (!tbody) return;

      tbody.innerHTML = "";

      if (this.packages.length === 0) {
        tbody.innerHTML =
          '<tr><td colspan="6" class="text-center py-3">No packages found</td></tr>';
        return;
      }

      this.packages.forEach((pkg) => {
        const row = document.createElement("tr");
        row.innerHTML = `
          <td>${esc(pkg.name)}</td>
          <td>${formatCurrency(pkg.price)}</td>
          <td>${pkg.billingCycle}</td>
          <td><span class="badge bg-${pkg.status === "active" ? "success" : "danger"}">${esc(pkg.status)}</span></td>
          <td>
            <div class="d-flex gap-2">
              <button class="btn btn-sm btn-primary" data-edit-package="${pkg.id}" title="Edit">
                <i class="ti ti-edit"></i>
              </button>
              <button class="btn btn-sm btn-danger" data-delete-package="${pkg.id}" title="Delete">
                <i class="ti ti-trash"></i>
              </button>
              <button class="btn btn-sm btn-info" data-add-feature="${pkg.id}" title="Add Feature">
                <i class="ti ti-plus"></i>
              </button>
            </div>
          </td>
          <td>
            <div class="d-flex gap-2">
              ${(pkg.features || [])
                .map(
                  (f) => `
                <span class="badge bg-light text-dark small">
                  ${esc(f.name)}
                  <button class="btn btn-xs btn-link p-0" data-remove-feature="${f.id}" data-package-id="${pkg.id}" title="Remove">×</button>
                </span>
              `
                )
                .join("")}
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
      const totalEl = document.getElementById("total_packages");
      const activeEl = document.getElementById("active_packages");

      if (totalEl) totalEl.textContent = this.packages.length;
      if (activeEl)
        activeEl.textContent = this.packages.filter((p) => p.status === "active").length;
    },

    /**
     * Handle add package
     */
    handleAddPackage: function (form) {
      const self = this;
      const formData = new FormData(form);
      const data = {
        name: formData.get("name"),
        description: formData.get("description"),
        price: parseFloat(formData.get("price")),
        billingCycle: formData.get("billing_cycle"),
        maxUsers: parseInt(formData.get("max_users")),
        status: formData.get("status"),
      };

      apiRequest("POST", API_BASE, data)
        .then(function (response) {
          if (response.success) {
            self.showSuccess("Package added successfully");
            form.reset();
            const modal = bootstrap.Modal.getInstance(
              document.getElementById("add_plans")
            );
            if (modal) modal.hide();
            self.currentPage = 1;
            self.loadPackages();
          } else {
            self.showError(response.error?.message || "Failed to add package");
          }
        })
        .catch(function (err) {
          console.error(err);
          self.showError("Error adding package");
        });
    },

    /**
     * Edit package
     */
    editPackage: function (id) {
      const self = this;
      const url = API_BASE + "/" + id;

      apiRequest("GET", url, null)
        .then(function (response) {
          if (response.success && response.data) {
            const pkg = response.data;
            document.getElementById("edit_package_id").value = pkg.id;
            document.getElementById("edit_name").value = pkg.name;
            document.getElementById("edit_description").value = pkg.description;
            document.getElementById("edit_price").value = pkg.price;
            document.getElementById("edit_billing_cycle").value = pkg.billingCycle;
            document.getElementById("edit_max_users").value = pkg.maxUsers;
            document.getElementById("edit_status").value = pkg.status;

            self.currentEditId = id;
            const modal = new bootstrap.Modal(document.getElementById("edit_plans"));
            modal.show();
          } else {
            self.showError("Failed to load package");
          }
        })
        .catch(function (err) {
          console.error(err);
          self.showError("Error loading package");
        });
    },

    /**
     * Handle edit package
     */
    handleEditPackage: function (form) {
      const self = this;
      const id = document.getElementById("edit_package_id").value;
      const formData = new FormData(form);
      const data = {
        name: formData.get("name"),
        description: formData.get("description"),
        price: parseFloat(formData.get("price")),
        billingCycle: formData.get("billing_cycle"),
        maxUsers: parseInt(formData.get("max_users")),
        status: formData.get("status"),
      };

      const url = API_BASE + "/" + id;

      apiRequest("PUT", url, data)
        .then(function (response) {
          if (response.success) {
            self.showSuccess("Package updated successfully");
            const modal = bootstrap.Modal.getInstance(
              document.getElementById("edit_plans")
            );
            if (modal) modal.hide();
            self.loadPackages();
          } else {
            self.showError(response.error?.message || "Failed to update package");
          }
        })
        .catch(function (err) {
          console.error(err);
          self.showError("Error updating package");
        });
    },

    /**
     * Delete package
     */
    deletePackage: function (id) {
      if (!confirm("Are you sure you want to delete this package?")) return;

      const self = this;
      const url = API_BASE + "/" + id;

      apiRequest("DELETE", url, null)
        .then(function (response) {
          if (response.success) {
            self.showSuccess("Package deleted successfully");
            self.loadPackages();
          } else {
            self.showError(response.error?.message || "Failed to delete package");
          }
        })
        .catch(function (err) {
          console.error(err);
          self.showError("Error deleting package");
        });
    },

    /**
     * Show add feature modal
     */
    showAddFeatureModal: function (packageId) {
      this.currentEditId = packageId;
      document.getElementById("add_feature_package_id").value = packageId;
      const modal = new bootstrap.Modal(document.getElementById("add_feature_modal"));
      modal.show();
    },

    /**
     * Add feature to package
     */
    addFeature: function () {
      const self = this;
      const packageId = document.getElementById("add_feature_package_id").value;
      const featureName = document.getElementById("feature_name").value;

      if (!featureName.trim()) {
        self.showError("Feature name is required");
        return;
      }

      const url = API_BASE + "/" + packageId + "/features";
      const data = { name: featureName };

      apiRequest("POST", url, data)
        .then(function (response) {
          if (response.success) {
            self.showSuccess("Feature added successfully");
            document.getElementById("feature_name").value = "";
            const modal = bootstrap.Modal.getInstance(
              document.getElementById("add_feature_modal")
            );
            if (modal) modal.hide();
            self.loadPackages();
          } else {
            self.showError(response.error?.message || "Failed to add feature");
          }
        })
        .catch(function (err) {
          console.error(err);
          self.showError("Error adding feature");
        });
    },

    /**
     * Remove feature from package
     */
    removeFeature: function (packageId, featureId) {
      if (!confirm("Remove this feature?")) return;

      const self = this;
      const url = API_BASE + "/" + packageId + "/features/" + featureId;

      apiRequest("DELETE", url, null)
        .then(function (response) {
          if (response.success) {
            self.showSuccess("Feature removed successfully");
            self.loadPackages();
          } else {
            self.showError(response.error?.message || "Failed to remove feature");
          }
        })
        .catch(function (err) {
          console.error(err);
          self.showError("Error removing feature");
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
      PackagesManager.init();
    });
  } else {
    PackagesManager.init();
  }

  // Expose to global scope
  window.PackagesManager = PackagesManager;
})(window, document);
