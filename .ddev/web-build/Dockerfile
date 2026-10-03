#ddev-generated

# Run Laravel's scheduler (schedule:work) inside the web container so scheduled
# tasks actually fire during local development:
#   chapa:reconcile        - every 5 minutes
#   freight:expire         - daily 02:00
#   app:send-billing-reminders, app:send-plan-usage-alerts, etc.
#
# Without this, nothing scheduled ever runs locally.
#
# Production needs its own cron entry on the server:
#   * * * * * cd /path/to/app && php artisan schedule:run >> /dev/null 2>&1

COPY laravel-scheduler.conf /etc/supervisor/conf.d/ddev-laravel-scheduler.conf
RUN chmod 644 /etc/supervisor/conf.d/ddev-laravel-scheduler.conf