(function (window, document) {
  "use strict";

  function esc(value) {
    return String(value == null ? "" : value)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/\"/g, "&quot;")
      .replace(/'/g, "&#39;");
  }

  function formatRupiah(value) {
    var number = Number(value || 0);
    if (!isFinite(number)) {
      number = 0;
    }
    return "Rp" + number.toLocaleString("id-ID", { minimumFractionDigits: 0, maximumFractionDigits: 0 });
  }

  function formatDate(isoValue) {
    if (!isoValue) {
      return "-";
    }
    var date = new Date(isoValue);
    if (isNaN(date.getTime())) {
      return "-";
    }
    return date.toLocaleDateString("id-ID", {
      day: "2-digit",
      month: "short",
      year: "numeric",
    });
  }

  function summarizeFeatures(features) {
    var rows = Array.isArray(features) ? features : [];
    if (!rows.length) {
      return '<span class="text-muted">No features configured.</span>';
    }

    return rows.slice(0, 6).map(function (item) {
      var name = item && item.name ? item.name : item && item.code ? item.code : "Feature";
      var suffix = "";
      if (item && item.isUnlimited) {
        suffix = " (Unlimited)";
      } else if (item && item.limit != null && item.limit !== "") {
        suffix = " (" + item.limit + ")";
      }

      return '<span class="text-dark d-flex align-items-center mb-2"><i class="ti ti-discount-check-filled text-success me-2"></i>' +
        esc(name + suffix) +
        "</span>";
    }).join("");
  }

  function apiGet(url) {
    if (window.axios) {
      return window.axios({
        method: "get",
        url: url,
        withCredentials: true,
        headers: { Accept: "application/json" },
      }).then(function (response) {
        return response.data;
      }).catch(function (error) {
        var status = error && error.response ? error.response.status : 0;
        var data = error && error.response ? error.response.data : null;
        if (window.AuthApi && typeof window.AuthApi.handleUnauthorizedFromApi === "function") {
          if (window.AuthApi.handleUnauthorizedFromApi(status, data)) {
            return null;
          }
        }
        throw error;
      });
    }

    return fetch(url, {
      method: "GET",
      credentials: "same-origin",
      headers: { Accept: "application/json" },
    }).then(function (response) {
      return response.json().catch(function () {
        return {};
      }).then(function (payload) {
        if (!response.ok) {
          if (window.AuthApi && typeof window.AuthApi.handleUnauthorizedFromApi === "function") {
            if (window.AuthApi.handleUnauthorizedFromApi(response.status, payload)) {
              return null;
            }
          }
          throw new Error("Request failed");
        }
        return payload;
      });
    });
  }

  function setText(selector, value) {
    var el = document.querySelector(selector);
    if (el) {
      el.textContent = String(value);
    }
  }

  function renderSummary(plans) {
    var rows = Array.isArray(plans) ? plans : [];
    var totalPlans = rows.length;
    var totalActiveSubscribers = rows.reduce(function (acc, item) {
      return acc + Number(item.activeSubscriptionsCount || 0);
    }, 0);

    var avgMonthly = totalPlans
      ? rows.reduce(function (acc, item) { return acc + Number(item.monthlyPrice || 0); }, 0) / totalPlans
      : 0;
    var avgYearly = totalPlans
      ? rows.reduce(function (acc, item) { return acc + Number(item.yearlyPrice || 0); }, 0) / totalPlans
      : 0;

    setText("[data-pricing-total-plans]", totalPlans);
    setText("[data-pricing-total-active-subscribers]", totalActiveSubscribers);
    setText("[data-pricing-avg-monthly-price]", formatRupiah(avgMonthly));
    setText("[data-pricing-avg-yearly-price]", formatRupiah(avgYearly));
  }

  function renderCards(plans, billingCycle) {
    var container = document.querySelector("[data-pricing-cards]");
    if (!container) {
      return;
    }

    var rows = Array.isArray(plans) ? plans : [];
    if (!rows.length) {
      container.innerHTML = '<div class="col-12 text-center text-muted py-4">No active plans found.</div>';
      return;
    }

    container.innerHTML = rows.map(function (plan) {
      var price = billingCycle === "yearly" ? plan.yearlyPrice : plan.monthlyPrice;
      var cycleLabel = billingCycle === "yearly" ? "year" : "month";
      var subscribers = Number(plan.activeSubscriptionsCount || 0);

      return '<div class="col-lg-4 col-md-6 col-sm-12">' +
        '<div class="card mb-3">' +
        '<div class="card-body">' +
        '<div class="card">' +
        '<div class="card-body">' +
        '<div class="d-flex align-items-center justify-content-between mb-2">' +
        '<h4 class="mb-0">' + esc(plan.name || "Unnamed Plan") + '</h4>' +
        '<span class="badge badge-soft-primary">' + esc((plan.code || "-").toUpperCase()) + '</span>' +
        '</div>' +
        '<h2 class="mb-1">' + formatRupiah(price) + '<span class="fs-14 fw-normal text-gray">/' + cycleLabel + '</span></h2>' +
        '<p class="text-muted mb-0">' + subscribers + ' active subscribers</p>' +
        '</div>' +
        '</div>' +
        '<div class="pricing-content rounded bg-light mb-3 p-3">' +
        '<div class="price-hdr mb-2"><h6 class="fs-14 fw-medium text-gray w-100 mb-0">Features Included</h6></div>' +
        summarizeFeatures(plan.features) +
        '</div>' +
        '<a href="/packages" class="btn btn-dark w-100">Manage Plan</a>' +
        '</div>' +
        '</div>' +
        '</div>';
    }).join("");
  }

  function renderTable(plans) {
    var body = document.querySelector("[data-pricing-table-body]");
    if (!body) {
      return;
    }

    var rows = Array.isArray(plans) ? plans : [];
    if (!rows.length) {
      body.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-4">No plans match your filter.</td></tr>';
      return;
    }

    body.innerHTML = rows.map(function (plan) {
      return '<tr>' +
        '<td><h6 class="fw-medium mb-0">' + esc(plan.name || "-") + '</h6></td>' +
        '<td>' + esc((plan.code || "-").toUpperCase()) + '</td>' +
        '<td>' + esc(formatDate(plan.createdAt)) + '</td>' +
        '<td>' + esc(formatRupiah(plan.monthlyPrice || 0)) + '</td>' +
        '<td>' + esc(formatRupiah(plan.yearlyPrice || 0)) + '</td>' +
        '<td>' + esc(Number(plan.activeSubscriptionsCount || 0)) + '</td>' +
        '</tr>';
    }).join("");
  }

  function bindSearch(onSearch) {
    var searchInput = document.querySelector("[data-pricing-search]");
    if (!searchInput) {
      return;
    }

    var timer = null;
    searchInput.addEventListener("input", function () {
      if (timer) {
        window.clearTimeout(timer);
      }
      timer = window.setTimeout(function () {
        onSearch(String(searchInput.value || "").trim());
      }, 220);
    });
  }

  function bindBillingToggle(onToggle) {
    var toggle = document.querySelector("[data-pricing-billing-toggle]");
    if (!toggle) {
      return;
    }

    toggle.addEventListener("change", function () {
      onToggle(toggle.checked ? "yearly" : "monthly");
    });
  }

  function initPricingPage() {
    var cardsContainer = document.querySelector("[data-pricing-cards]");
    if (!cardsContainer) {
      return;
    }

    var billingCycle = "monthly";
    var allPlans = [];

    function applyFilter(keyword) {
      var q = String(keyword || "").toLowerCase();
      var filtered = allPlans.filter(function (plan) {
        if (!q) {
          return true;
        }
        var name = String(plan.name || "").toLowerCase();
        var code = String(plan.code || "").toLowerCase();
        return name.indexOf(q) !== -1 || code.indexOf(q) !== -1;
      });

      renderCards(filtered, billingCycle);
      renderTable(filtered);
      renderSummary(filtered);
    }

    function setBilling(nextCycle) {
      billingCycle = nextCycle === "yearly" ? "yearly" : "monthly";
      applyFilter((document.querySelector("[data-pricing-search]") || {}).value || "");
    }

    bindSearch(applyFilter);
    bindBillingToggle(setBilling);

    apiGet("/v1/saas/packages?status=active&per_page=100").then(function (payload) {
      if (payload == null) {
        return;
      }

      var rows = payload && Array.isArray(payload.data) ? payload.data : [];
      allPlans = rows;
      applyFilter("");
    }).catch(function () {
      cardsContainer.innerHTML = '<div class="col-12 text-center text-danger py-4">Failed to load pricing plans. Please refresh.</div>';
      renderTable([]);
      renderSummary([]);
    });
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", initPricingPage);
  } else {
    initPricingPage();
  }
})(window, document);
