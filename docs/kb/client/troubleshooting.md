---
title: Troubleshooting
summary: Quick fixes for the most common problems.
order: 10
---
## My deployment is refused: "no active plan"
Your team has no paid plan of that kind, or the period you paid for ended. Check **Billing** for an unpaid invoice, or order the plan again.

## My receipt was rejected
Read the reason in the email, fix it (right amount, clear and complete receipt, the right invoice) and upload a new one from **Billing**.

## My VPS has not arrived
VPS servers are set up by hand within 24 to 72 hours of payment approval. After that, open a conversation in **Support**.

## My domain will not verify
DNS can take a while. Check the TXT record name and value exactly as shown on **Domains**, with no extra quotes or spaces added by your provider, then press **Check now**. The TXT record is for ownership; the A record is what points visitors to us.

## HTTPS does not work on my own domain
The domain must point at the IP shown on **Domains**. If it sits behind a proxy (such as Cloudflare's orange cloud), set it to **DNS only**.

## The VPS panel says "enable panel access"
Press **Enable panel access**. If you changed the root password, type the current one once. We do not store it.

## The VPS panel says the server identity changed
We stopped on purpose: the server's SSH identity is different from the first time we connected. If you reinstalled the server this is expected; contact **Support** and we will confirm and reset it. If you did not reinstall it, tell us right away.

## "Backup space is full"
Delete old backups or turn down how many to keep, or ask **Support** for more space.

## I cannot restore a backup
The backup must be finished and saved to our storage, and the database must be running. Only one restore per database is allowed every ten minutes.
