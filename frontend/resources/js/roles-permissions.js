(function (window, document) {
  "use strict";

  var RolesPermissions = {
    state: {
      roles: [],
      permissions: [],
      search: "",
      status: "active",
      editingRoleId: null,
      syncingRoleId: null,
      rolePermissionsMap: {},
    },

    init: function () {
      if (!document.getElementById("rp_roles_tbody")) {
        return;
      }
      this.bindEvents();
      this.loadPermissions();
      this.loadRoles();
    },

    bindEvents: function () {
      var self = this;

      var searchEl = document.getElementById("rp_search");
      if (searchEl) {
        var timer = null;
        searchEl.addEventListener("input", function () {
          var value = String(this.value || "").trim();
          window.clearTimeout(timer);
          timer = window.setTimeout(function () {
            self.state.search = value;
            self.renderRoles();
          }, 250);
        });
      }

      var statusEl = document.getElementById("rp_status");
      if (statusEl) {
        statusEl.addEventListener("change", function () {
          self.state.status = this.value || "active";
          self.loadRoles();
        });
      }

      var resetBtn = document.getElementById("rp_reset");
      if (resetBtn) {
        resetBtn.addEventListener("click", function () {
          self.state.search = "";
          self.state.status = "active";
          if (searchEl) {
            searchEl.value = "";
          }
          if (statusEl) {
            statusEl.value = "active";
          }
          self.loadRoles();
        });
      }

      var openCreate = document.getElementById("rp_open_create_modal");
      if (openCreate) {
        openCreate.addEventListener("click", function () {
          self.openCreateModal();
        });
      }

      var roleForm = document.getElementById("rp_role_form");
      if (roleForm) {
        roleForm.addEventListener("submit", function (e) {
          e.preventDefault();
          self.submitRoleForm();
        });
      }

      var savePermissionsBtn = document.getElementById("rp_save_permissions");
      if (savePermissionsBtn) {
        savePermissionsBtn.addEventListener("click", function () {
          self.syncRolePermissions();
        });
      }

      var exportBtn = document.getElementById("rp_export_roles_csv");
      if (exportBtn) {
        exportBtn.addEventListener("click", function (e) {
          e.preventDefault();
          self.exportCsv();
        });
      }

      document.addEventListener("click", function (e) {
        var editBtn = e.target.closest("[data-rp-edit]");
        if (editBtn) {
          e.preventDefault();
          self.openEditModal(Number(editBtn.getAttribute("data-rp-edit")));
          return;
        }

        var deleteBtn = e.target.closest("[data-rp-delete]");
        if (deleteBtn) {
          e.preventDefault();
          self.deleteRole(Number(deleteBtn.getAttribute("data-rp-delete")));
          return;
        }

        var permBtn = e.target.closest("[data-rp-permissions]");
        if (permBtn) {
          e.preventDefault();
          self.openPermissionsModal(Number(permBtn.getAttribute("data-rp-permissions")));
        }
      });
    },

    api: function (method, path, payload) {
      var token = window.AuthApi && typeof window.AuthApi.getToken === "function" ? window.AuthApi.getToken() : null;
      var tenant = window.AuthApi && typeof window.AuthApi.getTenantContext === "function" ? window.AuthApi.getTenantContext() || {} : {};
      var headers = {
        Accept: "application/json",
      };

      if (token) {
        headers.Authorization = "Bearer " + token;
      }
      if (tenant.companyCode) {
        headers["X-Company-Code"] = tenant.companyCode;
      }
      if (tenant.companyId) {
        headers["X-Company-Id"] = String(tenant.companyId);
      }

      var options = {
        method: method,
        headers: headers,
        credentials: "same-origin",
      };

      if (payload !== undefined) {
        headers["Content-Type"] = "application/json";
        options.body = JSON.stringify(payload);
      }

      return fetch("/v1" + path, options).then(function (res) {
        return res.json().catch(function () {
          return {};
        }).then(function (data) {
          if (!res.ok || data.success === false) {
            var message = (data && data.error && data.error.message) || (data && data.message) || "Request failed";
            var err = new Error(message);
            err.status = res.status;
            err.data = data;
            throw err;
          }
          return data;
        });
      });
    },

    loadRoles: function () {
      var self = this;
      var tbody = document.getElementById("rp_roles_tbody");
      if (tbody) {
        tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-4">Loading roles...</td></tr>';
      }

      this.api("GET", "/hcm/user-management/roles?scope=company&status=" + encodeURIComponent(this.state.status || "active"))
        .then(function (resp) {
          self.state.roles = Array.isArray(resp.data) ? resp.data : [];
          self.renderRoles();
        })
        .catch(function (err) {
          self.showAlert(err.message || "Failed to load roles", "danger");
        });
    },

    loadPermissions: function () {
      var self = this;
      this.api("GET", "/hcm/user-management/permissions")
        .then(function (resp) {
          self.state.permissions = Array.isArray(resp.data) ? resp.data : [];
        })
        .catch(function (err) {
          self.showAlert(err.message || "Failed to load permissions", "danger");
        });
    },

    loadRolePermissions: function (roleId) {
      var role = this.state.roles.find(function (item) {
        return Number(item.id) === Number(roleId);
      });
      var permissionCodes = role && Array.isArray(role.permissionCodes) ? role.permissionCodes : [];
      this.state.rolePermissionsMap[Number(roleId)] = permissionCodes;
      return Promise.resolve(permissionCodes);
    },

    filteredRoles: function () {
      var query = String(this.state.search || "").toLowerCase();
      if (!query) {
        return this.state.roles.slice();
      }
      return this.state.roles.filter(function (role) {
        var code = String(role.code || "").toLowerCase();
        var name = String(role.name || "").toLowerCase();
        var description = String(role.description || "").toLowerCase();
        return code.indexOf(query) >= 0 || name.indexOf(query) >= 0 || description.indexOf(query) >= 0;
      });
    },

    renderRoles: function () {
      var tbody = document.getElementById("rp_roles_tbody");
      if (!tbody) {
        return;
      }

      var rows = this.filteredRoles();
      if (!rows.length) {
        tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-4">No roles found.</td></tr>';
        return;
      }

      tbody.innerHTML = rows.map(function (role) {
        var roleId = Number(role.id);
        var status = String(role.status || "inactive");
        var statusClass = status === "active" ? "badge-success" : (status === "archived" ? "badge-warning" : "badge-danger");
        var statusLabel = status.charAt(0).toUpperCase() + status.slice(1);
        var permCount = Array.isArray(role.permissionCodes) ? role.permissionCodes.length : 0;

        return [
          "<tr>",
          '<td><span class="badge badge-md p-2 fs-10 badge-purple-transparent">' + RolesPermissions.escape(role.code || "-") + "</span></td>",
          '<td><h6 class="fw-medium mb-0">' + RolesPermissions.escape(role.name || "-") + "</h6></td>",
          '<td class="text-muted">' + RolesPermissions.escape(role.description || "-") + "</td>",
          '<td><span class="badge badge-light text-dark">' + permCount + " permissions</span></td>",
          '<td><span class="badge d-inline-flex align-items-center badge-xs ' + statusClass + '"><i class="ti ti-point-filled me-1"></i>' + RolesPermissions.escape(statusLabel) + "</span></td>",
          '<td><div class="action-icon d-inline-flex">' +
            '<a href="#" class="me-2" title="Manage permissions" data-rp-permissions="' + roleId + '"><i class="ti ti-shield"></i></a>' +
            '<a href="#" class="me-2" title="Edit role" data-rp-edit="' + roleId + '"><i class="ti ti-edit"></i></a>' +
            '<a href="#" class="text-danger" title="Delete role" data-rp-delete="' + roleId + '"><i class="ti ti-trash"></i></a>' +
          "</div></td>",
          "</tr>",
        ].join("");
      }).join("");
    },

    openCreateModal: function () {
      this.state.editingRoleId = null;
      document.getElementById("rp_role_modal_title").textContent = "Add Role";
      document.getElementById("rp_role_id").value = "";
      document.getElementById("rp_code").value = "";
      document.getElementById("rp_name").value = "";
      document.getElementById("rp_description").value = "";
      document.getElementById("rp_role_status").value = "active";
      document.getElementById("rp_code_wrap").classList.remove("d-none");
    },

    openEditModal: function (roleId) {
      var role = this.state.roles.find(function (r) {
        return Number(r.id) === Number(roleId);
      });
      if (!role) {
        return;
      }
      this.state.editingRoleId = Number(role.id);
      document.getElementById("rp_role_modal_title").textContent = "Edit Role";
      document.getElementById("rp_role_id").value = String(role.id);
      document.getElementById("rp_code").value = role.code || "";
      document.getElementById("rp_name").value = role.name || "";
      document.getElementById("rp_description").value = role.description || "";
      document.getElementById("rp_role_status").value = role.status || "active";
      document.getElementById("rp_code_wrap").classList.add("d-none");

      var modalEl = document.getElementById("rp_role_modal");
      if (window.bootstrap && modalEl) {
        window.bootstrap.Modal.getOrCreateInstance(modalEl).show();
      }
    },

    submitRoleForm: function () {
      var self = this;
      var roleId = this.state.editingRoleId;

      if (roleId) {
        var updatePayload = {
          name: String(document.getElementById("rp_name").value || "").trim(),
          description: String(document.getElementById("rp_description").value || "").trim(),
          status: String(document.getElementById("rp_role_status").value || "active"),
        };

        this.api("PUT", "/hcm/user-management/roles/" + Number(roleId), updatePayload)
          .then(function () {
            self.hideRoleModal();
            self.showAlert("Role updated successfully.", "success");
            self.loadRoles();
          })
          .catch(function (err) {
            self.showAlert(err.message || "Failed to update role", "danger");
          });

        return;
      }

      var createPayload = {
        code: String(document.getElementById("rp_code").value || "").trim(),
        name: String(document.getElementById("rp_name").value || "").trim(),
        description: String(document.getElementById("rp_description").value || "").trim(),
        status: String(document.getElementById("rp_role_status").value || "active"),
      };

      this.api("POST", "/hcm/user-management/roles", createPayload)
        .then(function () {
          self.hideRoleModal();
          self.showAlert("Role created successfully.", "success");
          self.loadRoles();
        })
        .catch(function (err) {
          self.showAlert(err.message || "Failed to create role", "danger");
        });
    },

    openPermissionsModal: function (roleId) {
      var self = this;
      var role = this.state.roles.find(function (item) {
        return Number(item.id) === Number(roleId);
      });
      if (!role) {
        return;
      }

      this.state.syncingRoleId = Number(roleId);
      document.getElementById("rp_permissions_role_id").value = String(roleId);
      document.getElementById("rp_permissions_role_meta").textContent = "Role: " + (role.code || "-") + " - " + (role.name || "-");

      var loading = document.getElementById("rp_permissions_loading");
      if (loading) {
        loading.classList.remove("d-none");
      }

      this.loadRolePermissions(roleId)
        .then(function (codes) {
          self.renderPermissionChecklist(codes);
        })
        .catch(function (err) {
          self.showAlert(err.message || "Failed to load role permissions", "danger");
          self.renderPermissionChecklist([]);
        })
        .finally(function () {
          if (loading) {
            loading.classList.add("d-none");
          }
        });

      var modalEl = document.getElementById("rp_permissions_modal");
      if (window.bootstrap && modalEl) {
        window.bootstrap.Modal.getOrCreateInstance(modalEl).show();
      }
    },

    renderPermissionChecklist: function (selectedCodes) {
      var listEl = document.getElementById("rp_permissions_list");
      if (!listEl) {
        return;
      }

      var selectedMap = {};
      (selectedCodes || []).forEach(function (code) {
        selectedMap[String(code)] = true;
      });

      if (!this.state.permissions.length) {
        listEl.innerHTML = '<div class="col-12 text-muted">No permissions available.</div>';
        return;
      }

      listEl.innerHTML = this.state.permissions.map(function (permission) {
        var code = String(permission.code || "");
        var checked = selectedMap[code] ? " checked" : "";
        return [
          '<div class="col-md-6">',
          '<label class="border rounded p-2 d-flex align-items-start gap-2">',
          '<input class="form-check-input mt-1" type="checkbox" data-rp-permission-code="' + RolesPermissions.escape(code) + '"' + checked + '>',
          '<span>',
          '<span class="fw-semibold d-block">' + RolesPermissions.escape(code) + '</span>',
          '<small class="text-muted">' + RolesPermissions.escape(permission.name || "-") + '</small>',
          '</span>',
          '</label>',
          '</div>',
        ].join("");
      }).join("");
    },

    syncRolePermissions: function () {
      var self = this;
      var roleId = Number(this.state.syncingRoleId || 0);
      if (!roleId) {
        return;
      }

      var selectedCodes = Array.prototype.slice.call(document.querySelectorAll("[data-rp-permission-code]:checked"))
        .map(function (item) {
          return item.getAttribute("data-rp-permission-code");
        })
        .filter(Boolean);

      if (!selectedCodes.length) {
        this.showAlert("Select at least one permission.", "warning");
        return;
      }

      this.api("POST", "/hcm/user-management/roles/" + roleId + "/permissions:sync", {
        permissionCodes: selectedCodes,
      })
        .then(function () {
          self.state.rolePermissionsMap[roleId] = selectedCodes;
          self.hidePermissionsModal();
          self.showAlert("Role permissions updated.", "success");
          self.loadRoles();
        })
        .catch(function (err) {
          self.showAlert(err.message || "Failed to sync permissions", "danger");
        });
    },

    deleteRole: function (roleId) {
      var self = this;
      var proceed = Promise.resolve(true);
      if (window.ArcavUi && typeof window.ArcavUi.confirmDelete === "function") {
        proceed = window.ArcavUi.confirmDelete("Delete this role? Active role assignments may archive it.", "Delete Role");
      }

      proceed.then(function (ok) {
        if (!ok) {
          return;
        }
        self.api("DELETE", "/hcm/user-management/roles/" + Number(roleId))
          .then(function () {
            self.showAlert("Role removed successfully.", "success");
            self.loadRoles();
          })
          .catch(function (err) {
            self.showAlert(err.message || "Failed to delete role", "danger");
          });
      });
    },

    exportCsv: function () {
      var rows = this.filteredRoles();
      if (!rows.length) {
        this.showAlert("No role data to export.", "warning");
        return;
      }

      var lines = ["Code,Name,Description,Status"];
      rows.forEach(function (row) {
        lines.push([
          RolesPermissions.csvCell(row.code),
          RolesPermissions.csvCell(row.name),
          RolesPermissions.csvCell(row.description),
          RolesPermissions.csvCell(row.status),
        ].join(","));
      });

      var blob = new Blob([lines.join("\n")], { type: "text/csv;charset=utf-8;" });
      var url = window.URL.createObjectURL(blob);
      var a = document.createElement("a");
      a.href = url;
      a.download = "roles-permissions-export.csv";
      document.body.appendChild(a);
      a.click();
      a.remove();
      window.URL.revokeObjectURL(url);
    },

    hideRoleModal: function () {
      var modalEl = document.getElementById("rp_role_modal");
      if (window.bootstrap && modalEl) {
        window.bootstrap.Modal.getOrCreateInstance(modalEl).hide();
      }
    },

    hidePermissionsModal: function () {
      var modalEl = document.getElementById("rp_permissions_modal");
      if (window.bootstrap && modalEl) {
        window.bootstrap.Modal.getOrCreateInstance(modalEl).hide();
      }
    },

    showAlert: function (message, type) {
      var alertEl = document.getElementById("rp_alert");
      if (!alertEl) {
        return;
      }

      alertEl.className = "alert alert-" + (type || "info") + " m-3";
      alertEl.textContent = message;
      alertEl.classList.remove("d-none");

      window.clearTimeout(this._alertTimer);
      this._alertTimer = window.setTimeout(function () {
        alertEl.classList.add("d-none");
      }, 3500);
    },

    escape: function (value) {
      return String(value || "")
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/\"/g, "&quot;")
        .replace(/'/g, "&#39;");
    },

    csvCell: function (value) {
      var text = String(value || "").replace(/\"/g, '""');
      return '"' + text + '"';
    },
  };

  document.addEventListener("DOMContentLoaded", function () {
    RolesPermissions.init();
  });
})(window, document);
