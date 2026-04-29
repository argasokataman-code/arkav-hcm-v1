export function bindEmployeePhotoModalPreviewModule() {
    if (document.body.getAttribute("data-employees-photo-modal-bound") === "1") {
        return;
    }
    document.body.setAttribute("data-employees-photo-modal-bound", "1");

    var modalEl = document.getElementById("employees_photo_preview_modal");
    var imageEl = document.querySelector("[data-employees-photo-modal-image]");
    var emptyEl = document.querySelector("[data-employees-photo-modal-empty]");
    var titleEl = document.querySelector("[data-employees-photo-modal-title]");
    if (!modalEl || !window.bootstrap || !window.bootstrap.Modal) {
        return;
    }
    var modal = window.bootstrap.Modal.getOrCreateInstance(modalEl);

    document.addEventListener("click", function (event) {
        var trigger = event.target.closest("[data-employees-photo-view]");
        if (!trigger) {
            return;
        }
        event.preventDefault();
        var url = String(trigger.getAttribute("data-photo-url") || "").trim();
        var employeeName = String(trigger.getAttribute("data-employee-name") || "Employee").trim();

        if (titleEl) {
            titleEl.textContent = employeeName ? (employeeName + " - Profile Photo") : "Employee Photo";
        }
        if (url) {
            if (imageEl) {
                imageEl.src = url;
                imageEl.classList.remove("d-none");
            }
            if (emptyEl) {
                emptyEl.classList.add("d-none");
            }
        } else {
            if (imageEl) {
                imageEl.src = "";
                imageEl.classList.add("d-none");
            }
            if (emptyEl) {
                emptyEl.classList.remove("d-none");
            }
        }
        modal.show();
    });
}