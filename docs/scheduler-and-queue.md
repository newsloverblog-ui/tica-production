# Scheduler and queue: what TICA needs to run on time

TICA's background work (sending emails, daily/weekly sales summaries, payout
release and reconciliation, ticket transfers, exports) runs from Laravel's
scheduler. The scheduler must be called **every minute**. Each call only
runs the tasks that are due in that exact minute.

## Why notifications didn't arrive

* `vercel.json` has one cron: `/api/vercel/cron` at `0 2 * * *` (02:00 UTC,
  once a day). That is the Vercel Hobby limit.
* So at 02:00 the scheduler runs, and nothing else ever runs: the 08:00
  daily summary, the Monday 08:15 weekly summary and the 10:00 payout
  release are never due at 02:00. "Every minute" jobs (mail, transfers,
  exports) run once a day.
* Production also has `MAIL_MAILER=log`, so email is written to the log
  instead of being sent. Set a real mailer (SMTP, Postmark, Resend, SES).

## Fix on Vercel (pick one)

**A. Vercel Pro** (simplest): change the cron in `vercel.json` to
`"schedule": "* * * * *"`.

**B. Stay on Hobby, use a free external pinger** such as cron-job.org:

* URL: `https://tica.ng/api/vercel/cron`
* Method: GET, every 1 minute
* Header: `Authorization: Bearer <CRON_SECRET>` (the CRON_SECRET value
  from Vercel > Settings > Environment Variables)
* Timeout: 60 seconds

The endpoint takes a lock, so overlapping calls are safe, and it allows 6
calls a minute.

Keep the daily Vercel cron as a backup; it does no harm.

## Fix on a normal server (VPS)

Crontab for the web user:

```
* * * * * cd /var/www/tica && php artisan schedule:run >> /dev/null 2>&1
```

Supervisor for the queue (`/etc/supervisor/conf.d/tica-worker.conf`):

```
[program:tica-worker]
command=php /var/www/tica/artisan queue:work --queue=payments,default --sleep=3 --tries=3 --max-time=3600
numprocs=2
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
redirect_stderr=true
stdout_logfile=/var/www/tica/storage/logs/worker.log
stopwaitsecs=3600
```

Then `supervisorctl reread && supervisorctl update && supervisorctl start tica-worker:*`.
Set `QUEUE_CONNECTION=database` (or redis) and run `php artisan queue:restart`
after each deploy.

## Check it's working

`php artisan schedule:list` shows each task and when it's next due.
The cron endpoint's JSON response includes `schedule_output`, which lists
what ran on that call.
