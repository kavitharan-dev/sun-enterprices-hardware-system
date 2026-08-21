# Render free deploy (demo / temporary public URL)

## Limits (read before customer handover)

- Web service **sleeps after 15 minutes** idle (30–60s cold start)
- Free Postgres **expires after 30 days** (data deleted unless upgraded)
- Not a replacement for a VPS for live shop delivery

## One-click deploy

1. Push `main` to GitHub (this repo).
2. Open: https://dashboard.render.com/blueprints/new?repo=https://github.com/kavitharan-dev/sun-enterprices-hardware-system
3. Apply the Blueprint (`render.yaml`).
4. Wait for the first deploy (Docker build can take several minutes).
5. Open the service URL → `/up` should return OK.
6. Login with seeded accounts (`admin@hardware.local` / `password`) and **change passwords**.

## Env notes

- `DB_CONNECTION=pgsql` (Render does not offer MySQL)
- `QUEUE_CONNECTION=sync` on free (no always-on worker)
- `APP_URL` is set from `RENDER_EXTERNAL_URL` at boot
- After deploy, set custom domain in Render if you use `sunenterprise.lk`

## Local Docker smoke test

```bash
docker build -t sunenterprise .
```
