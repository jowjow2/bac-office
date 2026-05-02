# Vercel Deployment Notes

This project can run on Vercel using the PHP community runtime configured in [vercel.json](./vercel.json).

## What was added

- `api/index.php`: forwards all Vercel PHP requests to Laravel's `public/index.php`
- `vercel.json`: configures the PHP runtime, static asset routes, and writable temp/cache env vars
- `.vercelignore`: avoids uploading local dependencies and generated files
- `composer.json` `vercel` script: runs `npm ci` and `npm run build` during the Vercel build

## Required Vercel environment variables

Set these in the Vercel project before deploying:

- `APP_KEY`
- `APP_URL`
- `DB_CONNECTION`
- `DB_HOST`
- `DB_PORT`
- `DB_DATABASE`
- `DB_USERNAME`
- `DB_PASSWORD`

Recommended:

- `SESSION_DRIVER=database`
- `CACHE_STORE=database`
- `QUEUE_CONNECTION=sync`
- `SESSION_SECURE_COOKIE=true`
- `MAIL_MAILER` plus your real mail provider settings

## Uploads / file storage

This app should not use local uploads on Vercel for production because the serverless filesystem is ephemeral.

Recommended Vercel values:

- `FILESYSTEM_DISK=s3`
- `UPLOADS_DISK=s3`
- `AWS_ACCESS_KEY_ID`
- `AWS_SECRET_ACCESS_KEY`
- `AWS_DEFAULT_REGION`
- `AWS_BUCKET`
- optional: `AWS_URL`, `AWS_ENDPOINT`, `AWS_USE_PATH_STYLE_ENDPOINT`

If you keep `local`, uploads may work only temporarily and will not persist reliably across deployments/invocations.

## Deploy options

### Option 1: Git import in Vercel dashboard

1. Push this repo to GitHub.
2. Import the GitHub repo into Vercel.
3. Add the environment variables above.
4. Deploy.

### Option 2: Vercel CLI

1. Install the CLI: `npm i -g vercel`
2. Login: `vercel login`
3. From the repo root run: `vercel`
4. For production deployment run: `vercel --prod`
