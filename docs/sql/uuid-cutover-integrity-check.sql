-- UUID cutover integrity checks (MySQL/MariaDB)
-- Scope: users, companies, employee_profiles, hcm_user_roles, company_users

-- 1) Check PK column for core tables
SELECT
  kcu.table_name,
  kcu.column_name AS primary_key_column
FROM information_schema.key_column_usage kcu
WHERE kcu.table_schema = DATABASE()
  AND kcu.constraint_name = 'PRIMARY'
  AND kcu.table_name IN ('users', 'companies', 'employee_profiles', 'hcm_user_roles', 'company_users')
ORDER BY kcu.table_name;

-- 2) Check null uuid on core tables
SELECT 'users' AS table_name, COUNT(*) AS null_uuid_count FROM users WHERE uuid IS NULL
UNION ALL
SELECT 'companies', COUNT(*) FROM companies WHERE uuid IS NULL
UNION ALL
SELECT 'employee_profiles', COUNT(*) FROM employee_profiles WHERE uuid IS NULL
UNION ALL
SELECT 'hcm_user_roles', COUNT(*) FROM hcm_user_roles WHERE uuid IS NULL
UNION ALL
SELECT 'company_users', COUNT(*) FROM company_users WHERE uuid IS NULL;

-- 3) Check duplicate uuid on core tables
SELECT 'users' AS table_name, COUNT(*) AS duplicate_groups
FROM (SELECT uuid FROM users GROUP BY uuid HAVING COUNT(*) > 1) t
UNION ALL
SELECT 'companies', COUNT(*)
FROM (SELECT uuid FROM companies GROUP BY uuid HAVING COUNT(*) > 1) t
UNION ALL
SELECT 'employee_profiles', COUNT(*)
FROM (SELECT uuid FROM employee_profiles GROUP BY uuid HAVING COUNT(*) > 1) t
UNION ALL
SELECT 'hcm_user_roles', COUNT(*)
FROM (SELECT uuid FROM hcm_user_roles GROUP BY uuid HAVING COUNT(*) > 1) t
UNION ALL
SELECT 'company_users', COUNT(*)
FROM (SELECT uuid FROM company_users GROUP BY uuid HAVING COUNT(*) > 1) t;

-- 4) Check orphan UUID references for key relations
SELECT 'employee_profiles.user_uuid -> users.uuid' AS relation_name, COUNT(*) AS orphan_count
FROM employee_profiles ep
LEFT JOIN users u ON u.uuid = ep.user_uuid
WHERE ep.user_uuid IS NOT NULL AND u.uuid IS NULL
UNION ALL
SELECT 'employee_profiles.company_uuid -> companies.uuid', COUNT(*)
FROM employee_profiles ep
LEFT JOIN companies c ON c.uuid = ep.company_uuid
WHERE ep.company_uuid IS NOT NULL AND c.uuid IS NULL
UNION ALL
SELECT 'hcm_user_roles.user_uuid -> users.uuid', COUNT(*)
FROM hcm_user_roles r
LEFT JOIN users u ON u.uuid = r.user_uuid
WHERE r.user_uuid IS NOT NULL AND u.uuid IS NULL
UNION ALL
SELECT 'hcm_user_roles.company_uuid -> companies.uuid', COUNT(*)
FROM hcm_user_roles r
LEFT JOIN companies c ON c.uuid = r.company_uuid
WHERE r.company_uuid IS NOT NULL AND c.uuid IS NULL
UNION ALL
SELECT 'company_users.user_uuid -> users.uuid', COUNT(*)
FROM company_users cu
LEFT JOIN users u ON u.uuid = cu.user_uuid
WHERE cu.user_uuid IS NOT NULL AND u.uuid IS NULL
UNION ALL
SELECT 'company_users.company_uuid -> companies.uuid', COUNT(*)
FROM company_users cu
LEFT JOIN companies c ON c.uuid = cu.company_uuid
WHERE cu.company_uuid IS NOT NULL AND c.uuid IS NULL;

-- 5) Check migration record exists after run
SELECT migration
FROM migrations
WHERE migration = '2026_04_26_150000_finalize_uuid_primary_keys_for_core_tables';
