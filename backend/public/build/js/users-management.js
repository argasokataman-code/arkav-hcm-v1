(function (window, document) {
  "use strict";

  var UsersManager = {
    state: {
      page: 1,
      perPage: 20,
      total: 0,
      lastPage: 1,
      search: "",
      status: "active",
      roleCode: "",
      rows: [],
      roles: [],
      editingId: null,
      managingRoleUser: null,
    },

    init: function () {
      if (!document.getElementById("um_users_tbody")) {
        return;
      }
      var authUser = window.AuthUser || null;
      var isAppSuperUser = !!(authUser && authUser.hcmGlobalAdmin === true);
      var hasPermission = window.AuthPermissions && window.AuthPermissions.hasPermission
        ? window.AuthPermissions.hasPermission.bind(window.AuthPermissions)
        : null;
      this.canManageUsers = isAppSuperUser || (hasPermission
        ? (
          hasPermission('user_management.manage') ||
          hasPermission('user.create') ||
          hasPermission('user.update') ||
          hasPermission('user.assign_role')
        )
        : false);
      this.bindEvents();
      this.loadRoles();
      this.loadUsers();
      // Hide create button if not allowed
      var openCreateBtn = document.querySelector("[data-bs-target='#um_user_modal']");
      if (openCreateBtn && !this.canManageUsers) {
        openCreateBtn.classList.add('d-none');
      }
    },

    bindEvents: function () {
      var self = this;

      var searchEl = document.getElementById("um_search");
      if (searchEl) {
        var timer = null;
        searchEl.addEventListener("input", function () {
          var value = String(this.value || "").trim();
          window.clearTimeout(timer);
          timer = window.setTimeout(function () {
            self.state.search = value;
            self.state.page = 1;
            self.loadUsers();
          }, 300);
        });
      }

      var statusEl = document.getElementById("um_status_filter");
      if (statusEl) {
        statusEl.addEventListener("change", function () {
          self.state.status = this.value || "active";
          self.state.page = 1;
          self.loadUsers();
        });
      }

      var roleEl = document.getElementById("um_role_filter");
      if (roleEl) {
        roleEl.addEventListener("change", function () {
          self.state.roleCode = this.value || "";
          self.state.page = 1;
          self.loadUsers();
        });
      }

      var resetBtn = document.getElementById("um_reset_filters");
      if (resetBtn) {
        resetBtn.addEventListener("click", function () {
          self.resetFilters();
        });
      }

      var prevBtn = document.getElementById("um_prev_page");
      if (prevBtn) {
        prevBtn.addEventListener("click", function () {
          if (self.state.page > 1) {
            self.state.page -= 1;
            self.loadUsers();
          }
        });
      }

      var nextBtn = document.getElementById("um_next_page");
      if (nextBtn) {
        nextBtn.addEventListener("click", function () {
          if (self.state.page < self.state.lastPage) {
            self.state.page += 1;
            self.loadUsers();
          }
        });
      }

      var exportBtn = document.getElementById("btn_um_export_csv");
      if (exportBtn) {
        exportBtn.addEventListener("click", function (e) {
          e.preventDefault();
          self.exportCsv();
        });
      }

      var form = document.getElementById("um_user_form");
      if (form) {
        if (!self.canManageUsers) {
          form.querySelectorAll('input,select,button').forEach(function(el){el.disabled=true;});
        } else {
          form.addEventListener("submit", function (e) {
            e.preventDefault();
            self.submitUserForm();
          });
        }
      }

      var assignRoleBtn = document.getElementById("um_assign_role_btn");
      if (assignRoleBtn) {
        if (!self.canManageUsers) {
          assignRoleBtn.disabled = true;
        } else {
          assignRoleBtn.addEventListener("click", function () {
            self.assignRole();
          });
        }
      }

      document.addEventListener("click", function (e) {
        var editBtn = e.target.closest("[data-um-edit]");
        if (editBtn) {
          if (!self.canManageUsers) {
            self.showAlert("You don't have permission to edit users.", "danger");
            return;
          }
          var id = Number(editBtn.getAttribute("data-um-edit"));
          self.openEditModal(id);
          return;
        }

        var deactivateBtn = e.target.closest("[data-um-deactivate]");
        if (deactivateBtn) {
          if (!self.canManageUsers) {
            self.showAlert("You don't have permission to deactivate users.", "danger");
            return;
          }
          var uid = Number(deactivateBtn.getAttribute("data-um-deactivate"));
          self.deactivateUser(uid);
          return;
        }

        var roleBtn = e.target.closest("[data-um-roles]");
        if (roleBtn) {
          e.preventDefault();
          var roleUid = Number(roleBtn.getAttribute("data-um-roles"));
          self.openRoleModal(roleUid);
          return;
        }

        var deleteBtn = e.target.closest("[data-um-delete]");
        if (deleteBtn) {
          e.preventDefault();
          if (!self.canManageUsers) {
            self.showAlert("You don't have permission to delete users.", "danger");
            return;
          }
          var deleteUid = Number(deleteBtn.getAttribute("data-um-delete"));
          self.deleteUser(deleteUid);
          return;
        }

        var revokeBtn = e.target.closest("[data-um-revoke-assignment]");
        if (revokeBtn) {
          e.preventDefault();
          var assignmentId = Number(revokeBtn.getAttribute("data-um-revoke-assignment"));
          self.revokeRole(assignmentId);
          return;
        }
      });

      var openCreateBtn = document.querySelector("[data-bs-target='#um_user_modal']");
      if (openCreateBtn) {
        openCreateBtn.addEventListener("click", function () {
          self.openCreateModal();
        });
      }
    },

    api: function (method, path, payload, extraHeaders) {
      var base = (window.AuthApi && typeof window.AuthApi.getToken === "function") ? window.AuthApi.getToken() : null;
      var headers = {
        Accept: "application/json",
      };

      if (base) {
        headers.Authorization = "Bearer " + base;
      }

      var tenant = (window.AuthApi && typeof window.AuthApi.getTenantContext === "function")
        ? (window.AuthApi.getTenantContext() || {})
        : {};

      if (tenant.companyCode) {
        headers["X-Company-Code"] = tenant.companyCode;
      }
      if (tenant.companyId) {
        headers["X-Company-Id"] = String(tenant.companyId);
      }
      if (tenant.companyUuid) {
        headers["X-Company-UUID"] = String(tenant.companyUuid);
      }

      if (extraHeaders) {
        Object.keys(extraHeaders).forEach(function (k) {
          headers[k] = extraHeaders[k];
        });
      }

      var options = {
        method: method,
        headers: headers,
        credentials: "same-origin",
      };

      if (payload !== undefined && payload !== null && !(payload instanceof FormData)) {
        headers["Content-Type"] = "application/json";
        options.body = JSON.stringify(payload);
      }

      return fetch("/v1" + path, options).then(function (res) {
        return res.json().catch(function () {
          return {};
        }).then(function (data) {
          if (!res.ok || !data.success) {
            var message = (data && data.error && data.error.message) || "Request failed";
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
      this.api("GET", "/hcm/user-management/roles?scope=company&status=active")
        .then(function (resp) {
          self.state.roles = Array.isArray(resp.data) ? resp.data : [];
          self.renderRoleFilters();
          self.renderRoleOptionsInModal();
        })
        .catch(function (err) {
          self.showAlert(err.message || "Failed to load roles", "danger");
        });
    },

    loadUsers: function () {
      var self = this;
      this.renderLoadingRow();
      var params = new URLSearchParams();
      params.set("page", String(this.state.page));
      params.set("perPage", String(this.state.perPage));
      if (this.state.search) {
        params.set("search", this.state.search);
      }
      if (this.state.status) {
        params.set("status", this.state.status);
      }
      if (this.state.roleCode) {
        params.set("roleCode", this.state.roleCode);
      }

      this.api("GET", "/hcm/user-management/users?" + params.toString())
        .then(function (resp) {
          self.state.rows = Array.isArray(resp.data) ? resp.data : [];
          var pagination = (resp.meta && resp.meta.pagination) || {};
          self.state.page = Number(pagination.page || 1);
          self.state.perPage = Number(pagination.perPage || 20);
          self.state.total = Number(pagination.total || 0);
          self.state.lastPage = Number(pagination.lastPage || 1);
          self.renderRows();
          self.renderPagination();
        })
        .catch(function (err) {
          self.showAlert(err.message || "Failed to load users", "danger");
        });
    },

    renderRoleFilters: function () {
      var roleFilter = document.getElementById("um_role_filter");
      if (!roleFilter) {
        return;
      }
      var current = this.state.roleCode || "";
      var html = ['<option value="">All Roles</option>'];
      this.state.roles.forEach(function (role) {
        var code = String(role.code || "");
        html.push('<option value="' + UsersManager.escape(code) + '">' + UsersManager.escape(code + " - " + (role.name || "")) + '</option>');
      });
      roleFilter.innerHTML = html.join("");
      roleFilter.value = current;
    },

    renderRoleOptionsInModal: function () {
      var roleSelect = document.getElementById("um_role_codes");
      if (!roleSelect) {
        return;
      }
      roleSelect.innerHTML = this.state.roles.map(function (role) {
        var code = String(role.code || "");
        return '<option value="' + UsersManager.escape(code) + '">' + UsersManager.escape(code + " - " + (role.name || "")) + '</option>';
      }).join("");

      var assignRole = document.getElementById("um_assign_role_code");
      if (assignRole) {
        assignRole.innerHTML = ['<option value="">Select role</option>'].concat(this.state.roles.map(function (role) {
          var code = String(role.code || "");
          return '<option value="' + UsersManager.escape(code) + '">' + UsersManager.escape(code + " - " + (role.name || "")) + '</option>';
        })).join("");
      }
    },

    renderLoadingRow: function () {
      var tbody = document.getElementById("um_users_tbody");
      if (!tbody) {
        return;
      }
      tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted py-4">Loading users...</td></tr>';
    },

    renderRows: function () {
      var tbody = document.getElementById("um_users_tbody");
      if (!tbody) {
        return;
      }

      if (!this.state.rows.length) {
        tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted py-4">No users found for selected filter.</td></tr>';
        return;
      }

      tbody.innerHTML = this.state.rows.map(function (row) {
        var roles = Array.isArray(row.activeRoleCodes) ? row.activeRoleCodes.join(", ") : "-";
        var statusClass = row.status === "active" ? "badge-success" : "badge-danger";
        var statusText = row.status === "active" ? "Active" : "Inactive";
        var createdAt = row.createdAt ? new Date(row.createdAt).toLocaleDateString("id-ID") : "-";

        return [
          '<tr>',
            '<td><div class="form-check form-check-md"><input class="form-check-input" type="checkbox" disabled></div></td>',
            '<td><div class="d-flex align-items-center file-name-icon"><div class="ms-2"><h6 class="fw-medium mb-0">' + UsersManager.escape(row.name || "-") + '</h6></div></div></td>',
            '<td>' + UsersManager.escape(row.email || "-") + '</td>',
            '<td>' + UsersManager.escape(createdAt) + '</td>',
            '<td><span class="badge badge-md p-2 fs-10 badge-pink-transparent">' + UsersManager.escape(roles || "-") + '</span></td>',
            '<td><span class="badge d-inline-flex align-items-center badge-xs ' + statusClass + '"><i class="ti ti-point-filled me-1"></i>' + statusText + '</span></td>',
            '<td><div class="action-icon d-inline-flex">',
              '<a href="#" class="me-2" title="Roles" data-um-roles="' + Number(row.id) + '"><i class="ti ti-shield"></i></a>',
              '<a href="#" class="me-2" data-um-edit="' + Number(row.id) + '"><i class="ti ti-edit"></i></a>',
              (row.status === "active"
                ? '<a href="#" class="text-danger" title="Delete" data-um-delete="' + Number(row.id) + '"><i class="ti ti-trash"></i></a>'
                : '<a href="#" data-um-deactivate="' + Number(row.id) + '"><i class="ti ti-user-off"></i></a>'),
            '</div></td>',
          '</tr>'
        ].join("");
      }).join("");
    },

    openRoleModal: function (userId) {
      var modalEl = document.getElementById("um_role_modal");
      if (!modalEl || !window.bootstrap) {
        return;
      }

      this.state.managingRoleUser = Number(userId);
      var row = this.state.rows.find(function (r) {
        return Number(r.id) === Number(userId);
      });
      document.getElementById("um_role_user_id").value = String(userId);
      document.getElementById("um_role_user_name").textContent = row ? (row.name + " (" + row.email + ")") : "User #" + userId;
      document.getElementById("um_role_assignment_list").innerHTML = "";
      document.getElementById("um_role_empty").classList.add("d-none");
      document.getElementById("um_role_loading").classList.remove("d-none");

      window.bootstrap.Modal.getOrCreateInstance(modalEl).show();
      this.loadRoleAssignments(userId);
    },

    loadRoleAssignments: function (userId) {
      var self = this;
      this.api("GET", "/hcm/user-management/users/" + Number(userId) + "/roles")
        .then(function (resp) {
          var rows = Array.isArray(resp.data) ? resp.data : [];
          var list = document.getElementById("um_role_assignment_list");
          var empty = document.getElementById("um_role_empty");
          var loading = document.getElementById("um_role_loading");

          if (loading) {
            loading.classList.add("d-none");
          }
          if (!list) {
            return;
          }
          if (!rows.length) {
            list.innerHTML = "";
            if (empty) {
              empty.classList.remove("d-none");
            }
            return;
          }

          if (empty) {
            empty.classList.add("d-none");
          }

          list.innerHTML = rows.map(function (item) {
            var role = item.role || {};
            var isRevokable = String(item.status || "") === "active";
            return [
              '<div class="list-group-item d-flex justify-content-between align-items-center">',
                '<div>',
                  '<div class="fw-semibold">' + UsersManager.escape(role.code || "-") + ' - ' + UsersManager.escape(role.name || "-") + '</div>',
                  '<div class="text-muted small">Status: ' + UsersManager.escape(item.status || "-") + '</div>',
                '</div>',
                (isRevokable ? '<button type="button" class="btn btn-sm btn-outline-danger" data-um-revoke-assignment="' + Number(item.assignmentId) + '">Revoke</button>' : ''),
              '</div>'
            ].join("");
          }).join("");
        })
        .catch(function (err) {
          var loading = document.getElementById("um_role_loading");
          if (loading) {
            loading.classList.add("d-none");
          }
          self.showAlert(err.message || "Failed to load role assignments", "danger");
        });
    },

    assignRole: function () {
      var self = this;
      var userId = Number(this.state.managingRoleUser || 0);
      var roleCode = String((document.getElementById("um_assign_role_code") || {}).value || "").trim();
      if (!userId || !roleCode) {
        this.showAlert("Select role before assign.", "warning");
        return;
      }

      this.api("POST", "/hcm/user-management/users/" + userId + "/roles", {
        roleCode: roleCode,
      })
        .then(function () {
          self.showAlert("Role assigned successfully.", "success");
          self.loadRoleAssignments(userId);
          self.loadUsers();
        })
        .catch(function (err) {
          self.showAlert(err.message || "Failed to assign role", "danger");
        });
    },

    revokeRole: function (assignmentId) {
      var self = this;
      var userId = Number(this.state.managingRoleUser || 0);
      if (!userId || !assignmentId) {
        return;
      }

      var proceed = Promise.resolve(true);
      if (window.ArcavUi && typeof window.ArcavUi.confirmDelete === "function") {
        proceed = window.ArcavUi.confirmDelete("Revoke this role assignment?", "Revoke Role");
      }

      proceed.then(function (ok) {
        if (!ok) {
          return;
        }

        self.api("DELETE", "/hcm/user-management/users/" + userId + "/roles/" + Number(assignmentId))
          .then(function () {
            self.showAlert("Role assignment revoked.", "success");
            self.loadRoleAssignments(userId);
            self.loadUsers();
          })
          .catch(function (err) {
            self.showAlert(err.message || "Failed to revoke role", "danger");
          });
      });
    },

    deleteUser: function (userId) {
      var self = this;
      var proceed = Promise.resolve(true);
      if (window.ArcavUi && typeof window.ArcavUi.confirmDelete === "function") {
        proceed = window.ArcavUi.confirmDelete("Delete this user from active company?", "Delete User");
      }

      proceed.then(function (ok) {
        if (!ok) {
          return;
        }
        self.api("DELETE", "/hcm/user-management/users/" + Number(userId))
          .then(function () {
            self.showAlert("User deleted from active company.", "success");
            self.loadUsers();
          })
          .catch(function (err) {
            self.showAlert(err.message || "Failed to delete user", "danger");
          });
      });
    },

    renderPagination: function () {
      var meta = document.getElementById("um_pagination_meta");
      if (meta) {
        meta.textContent = "Page " + this.state.page + " / " + this.state.lastPage + " • Total " + this.state.total + " users";
      }

      var prevBtn = document.getElementById("um_prev_page");
      var nextBtn = document.getElementById("um_next_page");
      if (prevBtn) {
        prevBtn.disabled = this.state.page <= 1;
      }
      if (nextBtn) {
        nextBtn.disabled = this.state.page >= this.state.lastPage;
      }
    },

    openCreateModal: function () {
      this.state.editingId = null;
      document.getElementById("um_user_modal_title").textContent = "Add User";
      document.getElementById("um_user_id").value = "";
      document.getElementById("um_name").value = "";
      document.getElementById("um_email").value = "";
      document.getElementById("um_password").value = "";
      document.getElementById("um_status").value = "active";
      document.getElementById("um_password_wrap").classList.remove("d-none");
      document.getElementById("um_roles_wrap").classList.remove("d-none");
      var roleSelect = document.getElementById("um_role_codes");
      if (roleSelect) {
        Array.prototype.forEach.call(roleSelect.options, function (opt) {
          opt.selected = false;
        });
      }
    },

    openEditModal: function (userId) {
      var self = this;
      this.api("GET", "/hcm/user-management/users/" + Number(userId))
        .then(function (resp) {
          var user = (resp.data && resp.data.user) || null;
          if (!user) {
            throw new Error("User detail not found");
          }

          self.state.editingId = Number(user.id);
          document.getElementById("um_user_modal_title").textContent = "Edit User";
          document.getElementById("um_user_id").value = String(user.id);
          document.getElementById("um_name").value = user.name || "";
          document.getElementById("um_email").value = user.email || "";
          document.getElementById("um_status").value = user.status || "active";
          document.getElementById("um_password").value = "";
          document.getElementById("um_password_wrap").classList.add("d-none");
          document.getElementById("um_roles_wrap").classList.add("d-none");

          var modalEl = document.getElementById("um_user_modal");
          if (window.bootstrap && modalEl) {
            window.bootstrap.Modal.getOrCreateInstance(modalEl).show();
          }
        })
        .catch(function (err) {
          self.showAlert(err.message || "Failed to load user detail", "danger");
        });
    },

    submitUserForm: function () {
      var self = this;
      var editingId = this.state.editingId;
      var payload;

      if (editingId) {
        payload = {
          name: String(document.getElementById("um_name").value || "").trim(),
          email: String(document.getElementById("um_email").value || "").trim(),
          status: String(document.getElementById("um_status").value || "active"),
        };

        this.api("PUT", "/hcm/user-management/users/" + Number(editingId), payload)
          .then(function () {
            self.hideModal();
            self.showAlert("User updated successfully.", "success");
            self.loadUsers();
          })
          .catch(function (err) {
            self.showAlert(err.message || "Failed to update user", "danger");
          });

        return;
      }

      var roleCodes = [];
      var roleSelect = document.getElementById("um_role_codes");
      if (roleSelect) {
        roleCodes = Array.prototype.filter.call(roleSelect.options, function (opt) {
          return opt.selected;
        }).map(function (opt) {
          return opt.value;
        });
      }

      payload = {
        name: String(document.getElementById("um_name").value || "").trim(),
        email: String(document.getElementById("um_email").value || "").trim(),
        password: String(document.getElementById("um_password").value || "").trim(),
        status: String(document.getElementById("um_status").value || "active"),
        roleCodes: roleCodes,
      };

      this.api("POST", "/hcm/user-management/users", payload)
        .then(function () {
          self.hideModal();
          self.showAlert("User created successfully.", "success");
          self.loadUsers();
        })
        .catch(function (err) {
          self.showAlert(err.message || "Failed to create user", "danger");
        });
    },

    deactivateUser: function (userId) {
      var self = this;
      var proceed = Promise.resolve(true);
      if (window.ArcavUi && typeof window.ArcavUi.confirmDelete === "function") {
        proceed = window.ArcavUi.confirmDelete("Deactivate this user from current company?", "Deactivate User");
      }

      proceed.then(function (ok) {
        if (!ok) {
          return;
        }
        self.api("PUT", "/hcm/user-management/users/" + Number(userId), { status: "inactive" })
          .then(function () {
            self.showAlert("User deactivated.", "success");
            self.loadUsers();
          })
          .catch(function (err) {
            self.showAlert(err.message || "Failed to deactivate user", "danger");
          });
      });
    },

    exportCsv: function () {
      var params = new URLSearchParams();
      if (this.state.search) {
        params.set("search", this.state.search);
      }
      if (this.state.status) {
        params.set("status", this.state.status);
      }
      if (this.state.roleCode) {
        params.set("roleCode", this.state.roleCode);
      }
      params.set("format", "csv");

      var token = (window.AuthApi && typeof window.AuthApi.getToken === "function") ? window.AuthApi.getToken() : null;
      var tenant = (window.AuthApi && typeof window.AuthApi.getTenantContext === "function")
        ? (window.AuthApi.getTenantContext() || {})
        : {};

      var headers = { Accept: "text/csv" };
      if (token) {
        headers.Authorization = "Bearer " + token;
      }
      if (tenant.companyCode) {
        headers["X-Company-Code"] = tenant.companyCode;
      }
      if (tenant.companyId) {
        headers["X-Company-Id"] = String(tenant.companyId);
      }
      if (tenant.companyUuid) {
        headers["X-Company-UUID"] = String(tenant.companyUuid);
      }

      fetch("/v1/hcm/user-management/users/export?" + params.toString(), {
        method: "GET",
        headers: headers,
        credentials: "same-origin",
      })
        .then(function (res) {
          if (!res.ok) {
            return res.json().catch(function () {
              return {};
            }).then(function (data) {
              throw new Error((data && data.error && data.error.message) || "Export failed");
            });
          }
          return res.blob();
        })
        .then(function (blob) {
          var url = window.URL.createObjectURL(blob);
          var a = document.createElement("a");
          a.href = url;
          a.download = "user-management-export.csv";
          document.body.appendChild(a);
          a.click();
          a.remove();
          window.URL.revokeObjectURL(url);
        })
        .catch(function (err) {
          UsersManager.showAlert(err.message || "Export failed", "danger");
        });
    },

    resetFilters: function () {
      this.state.search = "";
      this.state.status = "active";
      this.state.roleCode = "";
      this.state.page = 1;

      var searchEl = document.getElementById("um_search");
      var statusEl = document.getElementById("um_status_filter");
      var roleEl = document.getElementById("um_role_filter");
      if (searchEl) {
        searchEl.value = "";
      }
      if (statusEl) {
        statusEl.value = "active";
      }
      if (roleEl) {
        roleEl.value = "";
      }

      this.loadUsers();
    },

    hideModal: function () {
      var modalEl = document.getElementById("um_user_modal");
      if (window.bootstrap && modalEl) {
        window.bootstrap.Modal.getOrCreateInstance(modalEl).hide();
      }
    },

    showAlert: function (message, type) {
      var alertEl = document.getElementById("um_alert");
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
  };

  document.addEventListener("DOMContentLoaded", function () {
    UsersManager.init();
  });
})(window, document);
