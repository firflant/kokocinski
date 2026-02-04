# Page Analytics

Page Analytics provides server-side page view analytics for Drupal. It records
daily view counts per non-admin path and displays an admin report with charts.
No JavaScript tracking or third-party services are required; all data is stored
in your database and processed via Drupal's queue on cron.

## Installation

Install and enable the module as usual. To see data in the report: visit some
front-end pages (as an anonymous user if "Exclude logged-in users" is on), then
run cron. The queue worker runs on cron and writes to the analytics table.

**Cron:** Run cron often (e.g. every 15 minutes). If cron runs rarely, the
queue can grow large and a single cron run may process many items at once,
which can temporarily overload the server. Frequent cron keeps batches small.

## Configuration

Go to **Administration » Configuration » System » Page analytics** to configure:

1. **Sampling rate (1 in N)**
   Record only a random fraction of page views (e.g. 3 = 1 in 3 views). Use
   this to reduce queue size and database writes on high-traffic sites. The
   report shows estimated totals; higher N improves performance and reduces
   accuracy. Use 1 for exact counts.

2. **Keep data for (days)**
   Rows older than this many days are deleted when cron runs. Default is 365.

3. **Exclude logged-in users**
   When enabled, page views by authenticated users are not counted. Use this
   to exclude staff or admin traffic from analytics.

## Report

- **Reports » Page analytics** shows the top 30, 100, or 300 paths by total
  views over the last 30 days (configurable via the report page).
- Charts show daily view counts for the selected period (7 or 30 days).
- Use the period links to switch between 7-day and 30-day chart ranges.

## How it works

- On each successful (200) response for a non-admin path that is not a static
  asset, the module may enqueue a view (subject to sampling and optional
  exclusion of authenticated users). Paths under `/admin` and asset file
  extensions (e.g. `.jpg`, `.png`, `.js`) are not tracked. Paths longer than 255 characters are
  truncated.
- Eligible page views are added to the `page_analytics` queue during the
  response.
- When cron runs, the queue worker upserts into the `page_analytics_daily`
  table (path + date, incrementing view count). The worker processes up to 100
  items per cron run and respects the configured sampling rate when
  estimating totals.
- The report reads from `page_analytics_daily` and uses the configured
  retention so that old rows are removed on cron.

## Dependencies

The report page uses [Chart.js](https://www.chartjs.org/) for line charts,
loaded from a CDN (jsDelivr). Sites with a strict Content-Security-Policy or
that disallow external scripts may need to override the `page_analytics/report`
library to serve Chart.js from a local or allowed source.

## Troubleshooting & FAQ

**Q: The report is empty or numbers are zero.**
**A:** Ensure cron is running (e.g. **Reports » Status report** or `drush cron`).
Views are processed only when the queue worker runs. Also check that the
sampling rate and "Exclude logged-in users" settings match your expectations.

**Q: I want to exclude certain paths from being tracked.**
**A:** The module does not expose path exclusion patterns in the UI. Admin
routes and asset paths (images, JS, etc.) are excluded by default. For custom exclusions, you
would need to implement an event subscriber that runs before
`PageAnalyticsSubscriber` and prevents specific paths from being enqueued, or
contribute a patch to add configurable path patterns.

**Q: Does this work with reverse proxies or CDNs?**
**A:** Yes. Tracking happens in PHP on the Drupal server, so it counts requests
that reach Drupal. Cached responses served by a CDN or reverse proxy without
hitting Drupal are not counted unless you configure your stack to pass through
(or re-request) for counting.

**Q: How do I reset all data and start over?**
**A:** Go to **Configuration » System » Page analytics** and use **Flush
analytics** under "Reset data". Confirm on the next page. All rows in
`page_analytics_daily` are deleted.
