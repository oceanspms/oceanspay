# Oceans Pay website by Ocean Across

This folder is the complete deployable website. Ocean Across is the company; Oceans Pay is the payroll software product.

## Deploy to Vercel

This repository is the Oceans Pay marketing site. Vercel serves the HTML and assets and runs `api/send-form.mjs` at `/api/send-form`.

## Book a Demo email setup

In Vercel, open the `oceanspay` project and add `BREVO_API_KEY` under Settings → Environment Variables for Production. Enter the API key privately in Vercel and redeploy. Never commit API keys, place them in HTML, or put them into a public deployment artifact.

The form uses Brevo’s transactional API. It sends from `info@oceanspay.co.uk` to `palkesh@oceanspay.co.uk`, with the visitor's email as Reply-To. Test the form and confirm both the on-page confirmation and inbox delivery. The old PHP endpoint is not used.

## Included

- All public HTML pages
- All hero artwork and supporting image assets
- Ocean Across light and reverse wordmark SVGs
- Oceans Pay product mark
- Shared, page-specific, responsive and readability stylesheets
- Mobile navigation and form-result JavaScript
- Vercel server-side Brevo form handler

## Current temporary behaviour

All “watch demo” calls to action route to the Book a Demo page until video content is available.

