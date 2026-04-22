(function (window, document) {
  "use strict";

  function normalize(s) {
    return String(s || "")
      .toLowerCase()
      .normalize("NFD")
      .replace(/[\u0300-\u036f]/g, "");
  }

  function init() {
    var root = document.querySelector("[data-hcm-portal-hub]");
    if (!root || root.dataset.hcmPortalHubBound === "1") {
      return;
    }
    root.dataset.hcmPortalHubBound = "1";

    var input = root.querySelector("[data-hcm-portal-hub-search]");
    var sections = root.querySelectorAll("[data-hub-section]");
    var empty = root.querySelector("[data-hcm-portal-hub-empty]");

    function applyFilter() {
      var q = normalize(input ? input.value : "");
      var anyVisible = false;

      sections.forEach(function (sec) {
        var items = sec.querySelectorAll("[data-hub-item]");
        var secVisible = false;
        items.forEach(function (item) {
          var hay = normalize(
            (item.getAttribute("data-hub-search") || "") +
              " " +
              (item.textContent || "")
          );
          var show = !q || hay.indexOf(q) !== -1;
          item.classList.toggle("d-none", !show);
          if (show) {
            secVisible = true;
            anyVisible = true;
          }
        });
        sec.classList.toggle("d-none", !secVisible);
      });

      if (empty) {
        empty.classList.toggle("d-none", anyVisible);
      }
    }

    if (input) {
      input.addEventListener("input", applyFilter);
      input.addEventListener("keydown", function (e) {
        if (e.key === "Escape") {
          input.value = "";
          applyFilter();
        }
      });
    }
    applyFilter();
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init);
  } else {
    init();
  }
})(window, document);
