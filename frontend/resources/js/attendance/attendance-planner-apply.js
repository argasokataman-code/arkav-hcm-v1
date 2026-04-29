export function bindPlannerApplyButtons(deps) {
  var applyDominantBtn = deps.applyDominantBtn;
  var applyDailyBtn = deps.applyDailyBtn;
  var getSmartPlannerLastResult = deps.getSmartPlannerLastResult;
  var setSmartPlannerFeedback = deps.setSmartPlannerFeedback;
  var applyPlannerDominantShifts = deps.applyPlannerDominantShifts;
  var applyPlannerDailyRoster = deps.applyPlannerDailyRoster;
  var notify = deps.notify;
  var loadScheduleTiming = deps.loadScheduleTiming;
  var updatePlannerApplyState = deps.updatePlannerApplyState;

  if (applyDominantBtn && applyDominantBtn.getAttribute("data-bound") !== "1") {
    applyDominantBtn.setAttribute("data-bound", "1");
    applyDominantBtn.addEventListener("click", function () {
      var latest = getSmartPlannerLastResult();
      if (!latest) {
        setSmartPlannerFeedback("Generate planner dulu sebelum publish dominant shift.", true);
        return;
      }

      applyDominantBtn.disabled = true;
      applyPlannerDominantShifts(latest)
        .then(function (summary) {
          var failedCount = Array.isArray(summary.failed) ? summary.failed.length : 0;
          if (failedCount > 0) {
            setSmartPlannerFeedback(
              "Publish selesai: " + String(summary.success) + " berhasil, " + String(failedCount) + " gagal.",
              true
            );
          } else {
            setSmartPlannerFeedback("Publish dominant shift berhasil untuk " + String(summary.success) + " user.", false);
            notify("Schedule timing berhasil diupdate dari draft planner.", false);
          }
          loadScheduleTiming();
          updatePlannerApplyState(getSmartPlannerLastResult());
        })
        .catch(function (err) {
          setSmartPlannerFeedback(String((err && err.plannerMessage) || "Gagal publish dominant shift."), true);
          updatePlannerApplyState(getSmartPlannerLastResult());
        });
    });
  }

  if (applyDailyBtn && applyDailyBtn.getAttribute("data-bound") !== "1") {
    applyDailyBtn.setAttribute("data-bound", "1");
    applyDailyBtn.addEventListener("click", function () {
      var latest = getSmartPlannerLastResult();
      if (!latest) {
        setSmartPlannerFeedback("Generate planner dulu sebelum publish roster harian.", true);
        return;
      }

      applyDailyBtn.disabled = true;
      applyPlannerDailyRoster(latest)
        .then(function (summary) {
          setSmartPlannerFeedback(
            "Publish roster harian berhasil. Created: " +
              String(summary.created || 0) +
              ", updated: " +
              String(summary.updated || 0) +
              ", off-days: " +
              String(summary.offDays || 0) +
              ".",
            false
          );
          notify("Roster harian per tanggal berhasil dipublish.", false);
          updatePlannerApplyState(getSmartPlannerLastResult());
        })
        .catch(function (err) {
          setSmartPlannerFeedback(String((err && err.plannerMessage) || "Gagal publish roster harian."), true);
          updatePlannerApplyState(getSmartPlannerLastResult());
        });
    });
  }
}
