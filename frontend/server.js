#!/usr/bin/env node

const http = require("http");

const FRONTEND_PORT = Number(process.env.FRONTEND_PORT || 5177);
const BACKEND_HOST = process.env.BACKEND_HOST || "127.0.0.1";
const BACKEND_PORT = Number(process.env.BACKEND_PORT || 8000);

const server = http.createServer((req, res) => {
  const options = {
    hostname: BACKEND_HOST,
    port: BACKEND_PORT,
    path: req.url,
    method: req.method,
    headers: {
      ...req.headers,
      host: req.headers.host || `${BACKEND_HOST}:${BACKEND_PORT}`,
      "x-forwarded-host": req.headers.host || "",
      "x-forwarded-proto": "http",
    },
  };

  const proxyReq = http.request(options, (proxyRes) => {
    res.writeHead(proxyRes.statusCode || 502, proxyRes.headers);
    proxyRes.pipe(res, { end: true });
  });

  proxyReq.on("error", (error) => {
    res.writeHead(502, { "content-type": "application/json" });
    res.end(
      JSON.stringify({
        success: false,
        error: {
          code: "FRONTEND_PROXY_ERROR",
          message: `Unable to reach backend at ${BACKEND_HOST}:${BACKEND_PORT}`,
          details: error.message,
        },
      }),
    );
  });

  req.pipe(proxyReq, { end: true });
});

server.listen(FRONTEND_PORT, "127.0.0.1", () => {
  console.log(
    `[frontend-node] proxy listening on http://127.0.0.1:${FRONTEND_PORT} -> http://${BACKEND_HOST}:${BACKEND_PORT}`,
  );
});
