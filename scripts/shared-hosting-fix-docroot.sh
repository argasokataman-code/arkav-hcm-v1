#!/usr/bin/env bash

set -euo pipefail

# ------------------------------------------------------------
# Bridge setup for shared hosting when cPanel document root
# cannot be changed to backend/public.
#
# Creates two files at app root:
#   - index.php   : Laravel entry point bridge
#   - .htaccess   : Apache rewrite rules to route all requests
#                   through backend/public
#
# Run this from app root:
#   bash scripts/shared-hosting-fix-docroot.sh
# ------------------------------------------------------------

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
if [[ -d "$SCRIPT_DIR/backend" ]]; then
  APP_ROOT="$SCRIPT_DIR"
elif [[ -d "$SCRIPT_DIR/../backend" ]]; then
  APP_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
elif [[ -d "$PWD/backend" ]]; then
  APP_ROOT="$PWD"
else
  echo "[ERR] Cannot find backend directory. Run from app root." >&2
  exit 1
fi

echo "[INFO] App root: $APP_ROOT"
echo "[INFO] Creating bridge files at app root..."

# Remove stale bridge index.php if it exists from a previous run
if [[ -f "$APP_ROOT/index.php" ]]; then
  rm -f "$APP_ROOT/index.php"
  echo "[INFO] Removed stale index.php bridge."
fi

# ---- .htaccess at app root ----
# Directly routes all requests to backend/public without a PHP bridge file.
# Uses PATH_INFO pattern so Laravel receives the full URL path for routing.
cat > "$APP_ROOT/.htaccess" << 'HTEOF'
<IfModule mod_rewrite.c>
    Options -MultiViews -Indexes
    RewriteEngine On

    # Handle Authorization Header
    RewriteCond %{HTTP:Authorization} .
    RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]

    # Handle X-XSRF-Token Header
    RewriteCond %{HTTP:x-xsrf-token} .
    RewriteRule .* - [E=HTTP_X_XSRF_TOKEN:%{HTTP:X-XSRF-Token}]

    # Serve real files directly from backend/public (CSS, JS, images, fonts)
    RewriteCond %{DOCUMENT_ROOT}/backend/public%{REQUEST_URI} -f
    RewriteRule ^(.*)$ backend/public/$1 [L,QSA]

    # Serve real directories from backend/public
    RewriteCond %{DOCUMENT_ROOT}/backend/public%{REQUEST_URI} -d
    RewriteRule ^(.*)$ backend/public/$1 [L,QSA]

    # All other requests: pass full URL path to Laravel via PATH_INFO
    # This is required so Laravel can match routes like /v1/identity/auth/login
    RewriteRule ^(.*)$ backend/public/index.php/$1 [L,QSA]
</IfModule>
HTEOF

echo "[OK]   Created .htaccess (direct route to backend/public/index.php)"
echo
echo "If the site still shows 404 or redirects to /public:"
echo "  - Verify mod_rewrite is enabled on your hosting."
echo "  - Verify AllowOverride is set to All for this directory."
echo "  - Preferred: change cPanel Document Root to $APP_ROOT/backend/public"
