# Deploy JustConnect with Render and Vercel

This app is a server-rendered Laravel app. Render runs Laravel and Postgres. Vercel is configured as a lightweight reverse proxy in front of the Render URL.

## 1. Push the repo to GitHub

Render and Vercel both deploy most easily from a GitHub repository.

## 2. Generate a Laravel app key

Run this locally and keep the value for Render:

```bash
php artisan key:generate --show
```

The value should look like `base64:...`.

## 3. Deploy on Render

1. Open Render and create a new Blueprint from this repository.
2. Render will read `render.yaml`.
3. When prompted, set:
   - `APP_KEY` to the generated Laravel key.
   - `GEMINI_API_KEY` to your Gemini API key.
4. Deploy.
5. After the first deploy, copy the live Render URL.

The default config assumes `https://justconnect-api.onrender.com`. If Render gives you a different URL, update `APP_URL` in Render and update `vercel.json`.

## 4. Deploy on Vercel

1. Import the same GitHub repository into Vercel.
2. Keep the project root as the repository root.
3. Deploy.

Vercel reads `vercel.json` and proxies all requests to the Render service.

## Notes

- If you use a custom Vercel domain, set Render's `APP_URL` to that Vercel domain so Laravel generates the right links.
- Uploaded documents are stored inside the Render container filesystem. On free instances, do not treat that storage as durable long-term storage.
- Render's free services can sleep when inactive, so the first request after inactivity can be slow.
