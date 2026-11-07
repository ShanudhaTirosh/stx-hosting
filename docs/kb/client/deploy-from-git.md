---
title: Deploy from a Git repository
summary: Connect a repository, pick a branch, set environment variables and deploy. Pushes redeploy automatically.
order: 3
---
You need an active **app hosting** plan (or bot or website hosting). Without one, a deployment is refused with a message saying your team has no active plan.

## Steps

1. Open **Projects**, create a project, and add a **new resource**.
2. Choose to deploy from a **Git repository** using the platform's GitHub App. Install the app on the repositories you want, or choose a public repository.
3. Pick the **branch** to deploy and how it builds (Dockerfile, Nixpacks or a static site).
4. Add your **environment variables** under the resource's environment settings. Values are hidden after you save them.
5. Press **Deploy** and watch the **build logs**.

## After the first deploy

- **Auto-deploy:** a push to the chosen branch deploys again automatically.
- **Logs:** open the resource's logs to see what your app prints.
- **Start, stop, restart:** from the resource page.
- **Roll back:** open the deployments list and redeploy an earlier one.
- **Address:** every app gets its own `your-app-name` address on our domain with HTTPS. See *Domains and HTTPS* to use your own.

## Limits

Your plan sets the CPU and memory your apps can use together. If a deployment is refused, the message says whether you have no plan, your plan has ended, or you reached a limit.
