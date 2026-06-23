#!/usr/bin/env bash

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

cd "$ROOT_DIR"

# Check staged JS files for inline validation pattern (the forbidden boilerplate)
STAGED_FILES="$(git diff --cached --name-only --diff-filter=ACMR -- 'frontend/resources/js/**/*.js' 2>/dev/null || true)"

if [[ -z "$STAGED_FILES" ]]; then
  exit 0
fi

EXIT_CODE=0

echo "$STAGED_FILES" | while IFS= read -r file; do
  [[ -f "$file" ]] || continue

  # Check for the forbidden inline pattern: classList.add("was-validated") + forEach.call + is-invalid
  if grep -qE 'classList\.add\(["'"'"']was-validated["'"'"']\).*forEach\.call.*querySelectorAll.*is-invalid' "$file" 2>/dev/null; then
    echo "❌ lint-form-validation: $file"
    echo "   Inline validation pattern terdeteksi. WAJIB ganti dengan global helper:"
    echo ""
    echo "   ❌ Salah:"
    echo "      form.classList.add(\"was-validated\");"
    echo "      [].forEach.call(form.querySelectorAll('input,select,textarea'),function(e){"
    echo "          e.classList.add('is-invalid');"
    echo "          if(e.checkValidity())e.classList.remove('is-invalid');"
    echo "      });"
    echo "      if(!form.checkValidity()){return;}"
    echo ""
    echo "   ✅ Benar:"
    echo "      if (!ArcavValidation.validateForm(form)) { return; }"
    echo ""
    EXIT_CODE=1
  fi
done

exit "$EXIT_CODE"
