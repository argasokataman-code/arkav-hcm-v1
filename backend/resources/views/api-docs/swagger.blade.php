<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Arcav API Docs (Swagger)</title>
    <link rel="stylesheet" href="https://unpkg.com/swagger-ui-dist@5/swagger-ui.css" />
    <style>
      html, body { height: 100%; }
      body { margin: 0; background: #fff; }
      .topbar { display: none; }
    </style>
  </head>
  <body>
    <div id="swagger-ui"></div>

    <script src="https://unpkg.com/swagger-ui-dist@5/swagger-ui-bundle.js"></script>
    <script src="https://unpkg.com/swagger-ui-dist@5/swagger-ui-standalone-preset.js"></script>
    <script>
      window.onload = function () {
        window.ui = SwaggerUIBundle({
          url: "{{ url('/api-docs/openapi.yaml') }}",
          dom_id: "#swagger-ui",
          deepLinking: true,
          presets: [
            SwaggerUIBundle.presets.apis,
            SwaggerUIStandalonePreset,
          ],
          layout: "StandaloneLayout",
          requestInterceptor: function (req) {
            // Allow quick testing: if user stored a token in localStorage, attach it.
            // QA can set it in DevTools: localStorage.setItem('arcavSwaggerToken', '<token>')
            try {
              var token = localStorage.getItem("arcavSwaggerToken");
              if (token) {
                req.headers["Authorization"] = "Bearer " + token;
              }
            } catch (e) {}
            return req;
          },
        });
      };
    </script>
  </body>
</html>

