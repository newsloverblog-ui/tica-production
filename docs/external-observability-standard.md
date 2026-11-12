# External observability standard

Application health endpoints and on-host logs do not provide independent monitoring. Production requires off-host uptime probes, centralized error tracking and a safe synthetic checkout journey with an accountable owner and exercised alerts.

## Required signals

1. **Liveness:** poll the exact HTTPS `APP_URL/health/live` from more than one external failure domain. Alert on sustained non-2xx, TLS expiry/failure and unacceptable latency.
2. **Readiness:** poll `APP_URL/health/ready`. It intentionally returns 503 when the database, critical worker heartbeat, queue backlog or failed-job threshold is unhealthy. Do not mask or rewrite this status at the CDN/load balancer.
3. **Error tracking/APM:** collect unhandled server/browser errors, release identifier, route, environment and request ID with source maps where appropriate. Scrub credentials, payment references, ticket payloads, contact data, form answers, document URLs and raw request bodies before transmission.
4. **Synthetic checkout:** use a dedicated synthetic account/event and sandbox or zero-value-safe path to exercise discovery, ticket selection, reservation, checkout return, booking/ticket delivery and reconciliation without live charges or inventory pollution.
5. **Incident delivery:** route actionable alerts to the same accountable rotation as the application’s direct email/signed-webhook incident transport. Deduplicate correlated symptoms without suppressing independent payment or security failures.

## Configuration evidence

Production readiness requires:

- `MONITORING_OWNER_EMAIL` for the responsible operator;
- all three monitoring booleans enabled only after the services are active;
- liveness/readiness URLs matching the configured production host exactly;
- `MONITORING_EXERCISE_AT` and a non-secret `MONITORING_EXERCISE_REFERENCE` from a current end-to-end exercise.

These values do not configure a vendor and must not contain tokens. Provider configuration, alert history and the drill record are authoritative.

## Synthetic safety

- Use synthetic identifiers and a dedicated event excluded from discovery/finance reporting where possible.
- Never store a live card, reusable ticket QR, organizer admin credential or production customer data in the monitoring service.
- Use a gateway-supported sandbox; if that is impossible, stop before money movement and monitor the reservation/cancellation-safe path separately.
- Give the synthetic worker the minimum role, rate-limit it and tag its request/release identifiers.
- Clean up only through normal cancellation/expiry workflows so the synthetic check also detects broken compensating actions.

## Exercise

At least every configured interval:

1. Confirm normal liveness, readiness, error capture and synthetic results include the current release.
2. Remove one test instance or return a controlled readiness failure; verify the load balancer removes it and the external service alerts.
3. Trigger a scrubbed synthetic exception and verify request-ID correlation without sensitive payloads.
4. Break the synthetic journey safely, verify alert routing/escalation, then restore and confirm recovery notification.
5. Record detection and acknowledgement times, recipients, false positives, missing context and corrective actions.

The exercise fails if monitoring shares the application failure domain, a 503 is treated as healthy, sensitive data leaves the platform, alerts reach no accountable person, or the checkout probe can move live money.

## Release checklist

- External probes use the exact production HTTPS URLs and validate TLS/status/latency.
- Error tracking is release-tagged and privacy scrubbing is tested.
- The synthetic journey covers the authoritative booking/ticket path and is financially safe.
- Alert ownership, escalation and recovery notification are exercised.
- `php artisan ops:release-readiness --configuration-only` reports both `operations.external_monitoring` and `operations.monitoring_exercise` as `PASS`.

Passing application tests proves the endpoints; only provider-side evidence proves independent observability.
