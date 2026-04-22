# Dockerfile – Minimal runtime image
#
# Deploy primer pakai docker-compose.yml + volume (lihat docker-compose.yml).
# Image ini hanya dipakai sebagai base runtime; source code, vendor, dan
# node_modules datang dari volume — tidak perlu COPY atau install di sini.
#
# Untuk custom image build (opsional), uncomment bagian yang diperlukan.

FROM tyomboreinz/php-npm

WORKDIR /app

# Tidak ada COPY source atau RUN composer/npm install di sini.
# Semua itu ditangani di CI/CD via docker-compose run + named volumes.

EXPOSE 8007 5179
CMD ["bash", "/app/run.sh"]
