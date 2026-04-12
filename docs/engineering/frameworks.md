# Frameworks and Stack Mapping

Mapping stack resmi untuk arsitektur project saat ini.

- **Laravel (`^12.8.1`)**
  - Single backend API app untuk seluruh domain endpoint:
    - `/v1/identity/*`
    - `/v1/hcm/*`
    - `/v1/leave/*`
  - PHP requirement `^8.2`

- **Vite (`^6.0.11`)**
  - Build tool untuk existing frontend assets (melalui integrasi backend)

- **Node.js**
  - Menjalankan frontend proxy server agar UI template bisa diakses sebagai service terpisah dari backend

- **Tailwind CSS (`^4.0.0`)** + **Laravel Vite Plugin (`^1.2.0`)**
  - Styling dan integrasi asset template existing

- **Axios (`^1.7.4`)**
  - HTTP client frontend ke backend API

## Notes

- Runtime resmi: frontend Node + backend Laravel (single app).
- Database resmi untuk dev/runtime: MySQL.
- Pengembangan backend wajib kompatibel dengan tampilan dan flow template saat ini; tidak membuat pola UI di luar template.
