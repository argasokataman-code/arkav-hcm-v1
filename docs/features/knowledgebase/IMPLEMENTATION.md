# Knowledge Base — implementasi

## File utama

| File | Fungsi |
|------|--------|
| `backend/config/hcm_knowledgebase.php` | Kategori (`slug`, `title`, `icon`, opsional `description`) + artikel (`slug`, `title`, `excerpt`, `body_html`, opsional `reading_minutes`) |
| `backend/app/Support/HcmKnowledgebase.php` | Helper baca config, resolve slug, pencarian, populer/terbaru |
| `backend/app/Http/Controllers/KnowledgebaseController.php` | `index`, `category`, `article` |
| `backend/routes/web.php` | Route named + redirect legacy |
| `backend/resources/views/knowledgebase*.blade.php` | UI |
| `backend/resources/views/hcm/partials/knowledgebase-aside.blade.php` | Sidebar kanan (kategori + artikel) |

## Aturan konten

- **`body_html`** hanya dari isi repo (trusted); tidak memuat input pengguna.
- Slug **artikel** harus unik di seluruh kategori.
- Sunting teks di config lalu deploy; tidak perlu migrasi DB.
- Daftar slug kategori saat ini: lihat tabel **Peta kategori** di [README.md](README.md) — tambahkan baris di README saat menambah kategori baru agar admin menemukan cakupan menu dengan cepat.

## Tes

`php artisan test --filter=KnowledgebaseWebTest`

## Menu aktif (sidebar)

`Request::is` memasukkan pola `knowledgebase/*` agar subpath kategori/artikel tetap menyorot menu **Help & Supports → Knowledge Base**.
