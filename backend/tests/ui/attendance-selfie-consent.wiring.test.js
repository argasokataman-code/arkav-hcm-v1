import { describe, test, expect, beforeEach, vi } from "vitest";
import { initSelfieCapture } from "../../../frontend/resources/js/attendance/attendance-selfie.js";

function flush(times = 8) {
    return Array.from({ length: times }).reduce(
        (p) => p.then(() => Promise.resolve()),
        Promise.resolve()
    );
}

function buildDom() {
    document.body.innerHTML = `
        <button data-attendance-me-selfie-btn data-arcav-selfie-allowed="1"></button>
        <div id="arcav_attendance_selfie_modal">
            <video data-selfie-camera-video></video>
            <canvas data-selfie-preview width="400" height="300"></canvas>
            <button data-selfie-capture-btn>Capture</button>
            <button data-selfie-retake-btn class="d-none">Retake</button>
            <button data-selfie-submit-btn class="d-none">Submit</button>
        </div>
        <div id="arcav_attendance_selfie_prereq_modal">
            <span data-arcav-selfie-prereq-message></span>
        </div>
        <div id="arcav_biometric_consent_modal">
            <button id="arcav_biometric_consent_agree_btn">Saya Setuju</button>
            <button id="arcav_biometric_consent_decline_btn">Tolak</button>
        </div>
    `;
}

describe("attendance-selfie biometric consent wiring", () => {
    let notifySpy, apiPostMock, formatApiErrorMock, loadAttendanceMock;

    beforeEach(() => {
        buildDom();

        notifySpy = vi.fn();
        apiPostMock = vi.fn();
        formatApiErrorMock = vi.fn(() => "Error message");
        loadAttendanceMock = vi.fn();

        window.bootstrap = {
            Modal: class {
                static _instances = new Map();
                static getOrCreateInstance(el) {
                    if (!this._instances.has(el)) {
                        this._instances.set(el, new window.bootstrap.Modal());
                    }
                    return this._instances.get(el);
                }
                show() {}
                hide() {}
            },
        };
    });

    test("initSelfieCapture does nothing if required elements missing", () => {
        document.body.innerHTML = "";
        initSelfieCapture({
            notify: notifySpy,
            apiPost: apiPostMock,
            formatApiError: formatApiErrorMock,
            loadEmployeeAttendance: loadAttendanceMock,
        });
        // No errors thrown, no API calls
        expect(apiPostMock).not.toHaveBeenCalled();
    });

    test("click selfie btn shows modal when allowed=1", () => {
        const showSpy = vi.fn();
        window.bootstrap.Modal.prototype.show = showSpy;

        initSelfieCapture({
            notify: notifySpy,
            apiPost: apiPostMock,
            formatApiError: formatApiErrorMock,
            loadEmployeeAttendance: loadAttendanceMock,
        });

        const btn = document.querySelector("[data-attendance-me-selfie-btn]");
        btn.click();

        expect(showSpy).toHaveBeenCalled();
    });

    test("click selfie btn shows prereq modal when allowed=0", () => {
        const btn = document.querySelector("[data-attendance-me-selfie-btn]");
        btn.setAttribute("data-arcav-selfie-allowed", "0");

        const showSpy = vi.fn();
        window.bootstrap.Modal.prototype.show = showSpy;

        initSelfieCapture({
            notify: notifySpy,
            apiPost: apiPostMock,
            formatApiError: formatApiErrorMock,
            loadEmployeeAttendance: loadAttendanceMock,
        });

        btn.click();

        // Should show prereq modal (not selfie modal)
        expect(showSpy).toHaveBeenCalled();
    });

    test("BIOMETRIC_CONSENT_REQUIRED triggers consent modal and calls consent API on agree", async () => {
        const showSpy = vi.fn();
        const hideSpy = vi.fn();
        window.bootstrap.Modal.prototype.show = showSpy;
        window.bootstrap.Modal.prototype.hide = hideSpy;

        // Mock canvas getContext
        const canvas = document.querySelector("[data-selfie-preview]");
        canvas.getContext = vi.fn(() => ({ drawImage: vi.fn() }));
        canvas.toDataURL = vi.fn(() => "data:image/jpeg;base64,fake");
        canvas.classList.add = vi.fn();
        canvas.classList.remove = vi.fn();
        canvas.setAttribute = vi.fn();
        canvas.removeAttribute = vi.fn();

        // First call: submit selfie → BIOMETRIC_CONSENT_REQUIRED error
        apiPostMock.mockRejectedValueOnce({
            response: {
                status: 403,
                data: {
                    success: false,
                    error: { code: "BIOMETRIC_CONSENT_REQUIRED", message: "Persetujuan biometrik diperlukan." },
                },
            },
        });
        // Second call: grant consent → success
        apiPostMock.mockResolvedValueOnce({ success: true, data: { selfieConsent: true } });

        formatApiErrorMock.mockImplementation((data) => {
            if (data && data.error) return data.error.message;
            return "Error";
        });

        initSelfieCapture({
            notify: notifySpy,
            apiPost: apiPostMock,
            formatApiError: formatApiErrorMock,
            loadEmployeeAttendance: loadAttendanceMock,
        });

        // Open selfie modal
        const selfieBtn = document.querySelector("[data-attendance-me-selfie-btn]");
        selfieBtn.click();
        await flush();

        // Click capture
        const captureBtn = document.querySelector("[data-selfie-capture-btn]");
        captureBtn.click();
        await flush();

        // Click submit → triggers BIOMETRIC_CONSENT_REQUIRED → shows consent modal
        const submitBtn = document.querySelector("[data-selfie-submit-btn]");
        submitBtn.click();
        await flush(4);

        // Verify consent modal was shown
        expect(showSpy).toHaveBeenCalled();

        // Click agree button on consent modal
        const agreeBtn = document.getElementById("arcav_biometric_consent_agree_btn");
        agreeBtn.click();
        await flush(6);

        // Verify consent API was called
        expect(apiPostMock).toHaveBeenCalledTimes(2);
        expect(apiPostMock).toHaveBeenCalledWith(
            "/v1/hcm/data-privacy/me/biometric-consent",
            expect.objectContaining({ selfie_consent: true, gps_consent: true })
        );
    });

    test("consent decline shows notification and does not call API", async () => {
        const showSpy = vi.fn();
        const hideSpy = vi.fn();
        window.bootstrap.Modal.prototype.show = showSpy;
        window.bootstrap.Modal.prototype.hide = hideSpy;

        const canvas = document.querySelector("[data-selfie-preview]");
        canvas.getContext = vi.fn(() => ({ drawImage: vi.fn() }));
        canvas.toDataURL = vi.fn(() => "data:image/jpeg;base64,fake");
        canvas.classList.add = vi.fn();
        canvas.classList.remove = vi.fn();
        canvas.setAttribute = vi.fn();
        canvas.removeAttribute = vi.fn();

        // Submit selfie → BIOMETRIC_CONSENT_REQUIRED
        apiPostMock.mockRejectedValueOnce({
            response: {
                status: 403,
                data: { success: false, error: { code: "BIOMETRIC_CONSENT_REQUIRED", message: "Persetujuan biometrik diperlukan." } },
            },
        });

        formatApiErrorMock.mockReturnValue("Persetujuan biometrik diperlukan.");

        initSelfieCapture({
            notify: notifySpy,
            apiPost: apiPostMock,
            formatApiError: formatApiErrorMock,
            loadEmployeeAttendance: loadAttendanceMock,
        });

        const selfieBtn = document.querySelector("[data-attendance-me-selfie-btn]");
        selfieBtn.click();
        await flush();

        const captureBtn = document.querySelector("[data-selfie-capture-btn]");
        captureBtn.click();
        await flush();

        const submitBtn = document.querySelector("[data-selfie-submit-btn]");
        submitBtn.click();
        await flush(4);

        // Click decline button
        const declineBtn = document.getElementById("arcav_biometric_consent_decline_btn");
        declineBtn.click();
        await flush();

        // Verify consent API was NOT called (only selfie submit was called)
        expect(apiPostMock).toHaveBeenCalledTimes(1); // only the selfie submit attempt
        expect(notifySpy).toHaveBeenCalledWith(
            expect.stringContaining("Selfie tidak dapat digunakan"),
            true
        );
    });
});
