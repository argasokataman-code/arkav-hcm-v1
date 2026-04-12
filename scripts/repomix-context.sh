#!/usr/bin/env bash
# Usage:
#   ./scripts/repomix-context.sh                     → full output (repomix-output.xml, ~13MB)
#   ./scripts/repomix-context.sh backend             → backend-only (repomix-backend.xml)
#   ./scripts/repomix-context.sh frontend            → frontend JS + views only (repomix-frontend.xml)
#   ./scripts/repomix-context.sh <custom-name.xml>   → custom output file name

set -euo pipefail

PROFILE="${1:-full}"

case "$PROFILE" in
  backend)
    OUT_FILE="repomix-backend.xml"
    echo "[repomix] Profile: backend-only -> ${OUT_FILE}"
    npx -y repomix --config repomix.backend.config.json
    ;;
  frontend)
    OUT_FILE="repomix-frontend.xml"
    echo "[repomix] Profile: frontend-only -> ${OUT_FILE}"
    npx -y repomix --config repomix.frontend.config.json
    ;;
  *)
    # full or custom filename
    OUT_FILE="${PROFILE}"
    [[ "$PROFILE" == "full" ]] && OUT_FILE="repomix-output.xml"
    echo "[repomix] Profile: fullstack -> ${OUT_FILE}"
    npx -y repomix --output "${OUT_FILE}"
    ;;
esac

echo "[repomix] Done: $(du -sh "${OUT_FILE}" | cut -f1) written to ${OUT_FILE}"
