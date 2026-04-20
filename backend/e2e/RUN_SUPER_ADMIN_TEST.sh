#!/bin/bash

# Super Admin Employee CRUD E2E Test Guide
# ═══════════════════════════════════════════════════════════════

echo "🚀 SUPER ADMIN EMPLOYEE CRUD E2E TEST"
echo "═══════════════════════════════════════════════════════════════"
echo ""

# Check if environment is ready
cd "$(dirname "$0")"/../backend

echo "📋 PREREQUISITES CHECK:"
echo "   ✅ Node.js installed"
echo "   ✅ Playwright installed" 
echo "   ✅ Test server running on http://localhost:8000"
echo ""

echo "🎬 HOW TO RUN:"
echo ""
echo "1. START BACKEND SERVER (if not running):"
echo "   $ cd backend"
echo "   $ php artisan serve"
echo "   (Should be running on http://localhost:8000)"
echo ""

echo "2. RUN THE TEST:"
echo "   $ npm run test:e2e -- 99-super-admin-employee-crud.spec.js"
echo ""
echo "   OR with debug mode:"
echo "   $ npm run test:e2e -- 99-super-admin-employee-crud.spec.js --debug"
echo ""

echo "3. VIEW TEST RESULTS:"
echo "   $ npm run test:report"
echo ""

echo "═══════════════════════════════════════════════════════════════"
echo "📊 WHAT THE TEST CHECKS:"
echo "═══════════════════════════════════════════════════════════════"
echo ""
echo "✅ Login as super admin (qa.login@example.com)"
echo "✅ Navigate to Employees menu"
echo "✅ See employee list"
echo "✅ Create a test employee"
echo "✅ Edit the employee"
echo "✅ Delete the employee"
echo "✅ Verify API permissions"
echo ""

echo "═══════════════════════════════════════════════════════════════"
echo "🐛 IF TEST FAILS:"
echo "═══════════════════════════════════════════════════════════════"
echo ""
echo "1. Check test output for which step failed"
echo "2. Look at generated screenshots/videos in playwright-report/"
echo "3. Check browser console for JS errors"
echo "4. Verify server is running and database is ready"
echo "5. Check if employee menu selector is correct in your UI"
echo ""

echo "═══════════════════════════════════════════════════════════════"
echo "📝 TEST CREDENTIALS:"
echo "═══════════════════════════════════════════════════════════════"
echo ""
echo "Email:    qa.login@example.com"
echo "Password: StrongPass1"
echo ""
