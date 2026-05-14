export function createEmployeeCompensationWilayah(deps) {
    var requestJson = deps.requestJson;

    function getWilayahElements(form) {
        if (!form) {
            return null;
        }
        var province = form.querySelector("[data-employee-wilayah-province]");
        var regency = form.querySelector("[data-employee-wilayah-regency]");
        var district = form.querySelector("[data-employee-wilayah-district]");
        var village = form.querySelector("[data-employee-wilayah-village]");
        var address = form.querySelector("[data-employee-address-autofill]");
        if (!province || !regency || !district || !village || !address) {
            return null;
        }
        return {
            province: province,
            regency: regency,
            district: district,
            village: village,
            address: address,
        };
    }

    function setSelectOptions(selectEl, rows, placeholder, selectedValue) {
        if (!selectEl) {
            return;
        }
        var selected = selectedValue == null ? "" : String(selectedValue);
        selectEl.innerHTML = "";
        var first = document.createElement("option");
        first.value = "";
        first.textContent = placeholder;
        selectEl.appendChild(first);

        (Array.isArray(rows) ? rows : []).forEach(function (row) {
            var opt = document.createElement("option");
            opt.value = String(row && row.id != null ? row.id : "");
            opt.textContent = String(row && row.name ? row.name : "");
            selectEl.appendChild(opt);
        });

        if (selected) {
            var exists = Array.prototype.slice.call(selectEl.options).some(function (optEl) {
                return optEl.value === selected;
            });
            if (exists) {
                selectEl.value = selected;
            }
        }
    }

    function setSelectLoading(selectEl, placeholder) {
        if (!selectEl) {
            return;
        }
        selectEl.disabled = true;
        selectEl.innerHTML = '<option value="">' + placeholder + "</option>";
    }

    function composeAddressLabel(form) {
        var wilayah = getWilayahElements(form);
        if (!wilayah) {
            return "";
        }
        var villageLabel = wilayah.village.options[wilayah.village.selectedIndex] && wilayah.village.value
            ? wilayah.village.options[wilayah.village.selectedIndex].textContent
            : "";
        var districtLabel = wilayah.district.options[wilayah.district.selectedIndex] && wilayah.district.value
            ? wilayah.district.options[wilayah.district.selectedIndex].textContent
            : "";
        var regencyLabel = wilayah.regency.options[wilayah.regency.selectedIndex] && wilayah.regency.value
            ? wilayah.regency.options[wilayah.regency.selectedIndex].textContent
            : "";
        var provinceLabel = wilayah.province.options[wilayah.province.selectedIndex] && wilayah.province.value
            ? wilayah.province.options[wilayah.province.selectedIndex].textContent
            : "";

        return [villageLabel, districtLabel, regencyLabel, provinceLabel]
            .filter(function (item) {
                return !!item;
            })
            .join(", ");
    }

    function syncAddressAutofill(form) {
        var wilayah = getWilayahElements(form);
        if (!wilayah) {
            return;
        }
        var composed = composeAddressLabel(form);
        var current = String(wilayah.address.value || "").trim();
        var previousAuto = String(form.getAttribute("data-employee-address-auto") || "").trim();
        if (current === "" || current === previousAuto) {
            wilayah.address.value = composed || "";
        }
        form.setAttribute("data-employee-address-auto", composed || "");
    }

    function resetWilayahSelect(selectEl, placeholder) {
        if (!selectEl) {
            return;
        }
        selectEl.disabled = true;
        selectEl.innerHTML = '<option value="">' + placeholder + "</option>";
    }

    function fetchWilayah(url) {
        return requestJson("get", url, null)
            .then(function (resp) {
                if (!resp || resp.success !== true || !Array.isArray(resp.data)) {
                    return [];
                }
                return resp.data;
            })
            .catch(function () {
                return [];
            });
    }

    function loadProvinces(form, selectedProvinceId) {
        var wilayah = getWilayahElements(form);
        if (!wilayah) {
            return Promise.resolve();
        }
        setSelectLoading(wilayah.province, "Loading provinces...");
        return fetchWilayah("/v1/hcm/wilayah/provinces").then(function (rows) {
            setSelectOptions(wilayah.province, rows, "Select province", selectedProvinceId || "");
            wilayah.province.disabled = false;
            syncAddressAutofill(form);
        });
    }

    function loadRegencies(form, provinceId, selectedRegencyId) {
        var wilayah = getWilayahElements(form);
        if (!wilayah) {
            return Promise.resolve();
        }
        resetWilayahSelect(wilayah.regency, "Select regency");
        resetWilayahSelect(wilayah.district, "Select district");
        resetWilayahSelect(wilayah.village, "Select village");
        syncAddressAutofill(form);
        if (!provinceId) {
            return Promise.resolve();
        }

        setSelectLoading(wilayah.regency, "Loading regencies...");
        return fetchWilayah("/v1/hcm/wilayah/regencies?provinceId=" + encodeURIComponent(String(provinceId))).then(function (rows) {
            setSelectOptions(wilayah.regency, rows, "Select regency", selectedRegencyId || "");
            wilayah.regency.disabled = false;
            syncAddressAutofill(form);
        });
    }

    function loadDistricts(form, regencyId, selectedDistrictId) {
        var wilayah = getWilayahElements(form);
        if (!wilayah) {
            return Promise.resolve();
        }
        resetWilayahSelect(wilayah.district, "Select district");
        resetWilayahSelect(wilayah.village, "Select village");
        syncAddressAutofill(form);
        if (!regencyId) {
            return Promise.resolve();
        }

        setSelectLoading(wilayah.district, "Loading districts...");
        return fetchWilayah("/v1/hcm/wilayah/districts?regencyId=" + encodeURIComponent(String(regencyId))).then(function (rows) {
            setSelectOptions(wilayah.district, rows, "Select district", selectedDistrictId || "");
            wilayah.district.disabled = false;
            syncAddressAutofill(form);
        });
    }

    function setVillageUnavailableHint(form, unavailable) {
        var hint = form ? form.querySelector("[data-village-unavailable-hint]") : null;
        if (hint) {
            hint.style.display = unavailable ? "" : "none";
        }
    }

    function loadVillages(form, districtId, selectedVillageId) {
        var wilayah = getWilayahElements(form);
        if (!wilayah) {
            return Promise.resolve();
        }
        resetWilayahSelect(wilayah.village, "Select village");
        wilayah.village.required = true;
        wilayah.village.removeAttribute("data-village-unavailable");
        setVillageUnavailableHint(form, false);
        syncAddressAutofill(form);
        if (!districtId) {
            return Promise.resolve();
        }

        setSelectLoading(wilayah.village, "Loading villages...");
        return fetchWilayah("/v1/hcm/wilayah/villages?districtId=" + encodeURIComponent(String(districtId))).then(function (rows) {
            if (!rows || rows.length === 0) {
                wilayah.village.innerHTML = '<option value="">Tidak ada data kelurahan — isi Address Detail</option>';
                wilayah.village.value = "";
                wilayah.village.required = false;
                wilayah.village.disabled = false;
                wilayah.village.setAttribute("data-village-unavailable", "1");
                setVillageUnavailableHint(form, true);
                var addressDetailField = form.querySelector('[data-employee-add-field="addressDetail"], [data-employee-edit-field="addressDetail"]');
                if (addressDetailField) {
                    addressDetailField.focus();
                }
            } else {
                setSelectOptions(wilayah.village, rows, "Select village", selectedVillageId || "");
                wilayah.village.disabled = false;
                wilayah.village.required = true;
                setVillageUnavailableHint(form, false);
            }
            syncAddressAutofill(form);
        });
    }

    function resetWilayahCascade(form) {
        var wilayah = getWilayahElements(form);
        if (!wilayah) {
            return;
        }
        resetWilayahSelect(wilayah.province, "Select province");
        resetWilayahSelect(wilayah.regency, "Select regency");
        resetWilayahSelect(wilayah.district, "Select district");
        resetWilayahSelect(wilayah.village, "Select village");
        wilayah.village.required = true;
        setVillageUnavailableHint(form, false);
        wilayah.address.value = "";
        form.setAttribute("data-employee-address-auto", "");
        loadProvinces(form, "").then(function () {
            resetWilayahSelect(wilayah.regency, "Select regency");
            resetWilayahSelect(wilayah.district, "Select district");
            resetWilayahSelect(wilayah.village, "Select village");
            syncAddressAutofill(form);
        });
    }

    function setWilayahCascade(form, region, fallbackAddress) {
        var wilayah = getWilayahElements(form);
        if (!wilayah) {
            return Promise.resolve();
        }
        var provinceId = region && region.provinceId != null ? String(region.provinceId) : "";
        var regencyId = region && region.regencyId != null ? String(region.regencyId) : "";
        var districtId = region && region.districtId != null ? String(region.districtId) : "";
        var villageId = region && region.villageId != null ? String(region.villageId) : "";

        return loadProvinces(form, provinceId)
            .then(function () {
                return loadRegencies(form, provinceId, regencyId);
            })
            .then(function () {
                return loadDistricts(form, regencyId, districtId);
            })
            .then(function () {
                return loadVillages(form, districtId, villageId);
            })
            .then(function () {
                var composed = composeAddressLabel(form);
                var fallback = String(fallbackAddress || "").trim();
                wilayah.address.value = fallback || composed || "";
                form.setAttribute("data-employee-address-auto", composed || "");
            });
    }

    function bindWilayahChangeHandlers(form) {
        var wilayah = getWilayahElements(form);
        if (!wilayah || form.getAttribute("data-employee-wilayah-bound") === "1") {
            return;
        }
        form.setAttribute("data-employee-wilayah-bound", "1");

        wilayah.province.addEventListener("change", function () {
            var provinceId = wilayah.province.value || "";
            loadRegencies(form, provinceId, "");
        });

        wilayah.regency.addEventListener("change", function () {
            var regencyId = wilayah.regency.value || "";
            loadDistricts(form, regencyId, "");
        });

        wilayah.district.addEventListener("change", function () {
            var districtId = wilayah.district.value || "";
            loadVillages(form, districtId, "");
        });

        wilayah.village.addEventListener("change", function () {
            syncAddressAutofill(form);
        });
    }

    return {
        bindWilayahChangeHandlers: bindWilayahChangeHandlers,
        resetWilayahCascade: resetWilayahCascade,
        setWilayahCascade: setWilayahCascade,
    };
}
