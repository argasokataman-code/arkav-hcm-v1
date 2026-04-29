export function bindGpsDebugButton(runGpsDebugCheck) {
  document.addEventListener("click", function (e) {
    var t = e.target;
    if (!t || !t.closest) {
      return;
    }
    var btn = t.closest("[data-attendance-gps-debug-btn]");
    if (!btn) {
      return;
    }
    e.preventDefault();
    runGpsDebugCheck();
  });
}
