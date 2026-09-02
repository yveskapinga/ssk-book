/**
 * PM2 — https://ssk-book.solutic.app
 * API :18180 · Front: nginx static /var/www/test/ssk-book
 */
module.exports = {
  apps: [
    {
      name: "ssk-book-api",
      script: "/usr/bin/php8.3",
      args: "-c /var/lib/.local-state/yves/workspace/ssk-book/deploy/php.ini -S 127.0.0.1:18180 router.php",
      cwd: "/var/lib/.local-state/yves/workspace/ssk-book/api",
      interpreter: "none",
      env: {
        APP_ENV: "prod",
        APP_DEBUG: "0",
        PATH: "/var/lib/.local-state/yves/.local/bin:/usr/local/bin:/usr/bin:/bin",
      },
      log_file: "/var/www/test/_runtime/logs/ssk-book-api.log",
      merge_logs: true,
      autorestart: true,
    },
  ],
};
