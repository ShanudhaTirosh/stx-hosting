---
title: Delivering a VPS
summary: The provisioning queue, panel access, reminders and the 72 hour promise.
order: 3
---
## Queue

Paid VPS orders appear in **Provisioning Queue** with how long they have waited and whether they are overdue. An order for several servers needs one delivery per server; the order is `provisioning` until the last one is delivered, then `completed`.

## Delivering

Buy or prepare the server, then in the queue enter its IP, SSH port, root password and operating system. The customer is emailed and the details appear in their panel. The root password is stored encrypted and shown to them only after they confirm their password.

## Panel access

A minute after delivery the platform logs in with the delivered password and installs its own key, so stats, files and the terminal work even after the customer changes the password. This needs SSH reachable from the panel host and `root` key login allowed. The server's identity is pinned the first time. After a legitimate reinstall run `php artisan vps:reset-host-key <service-uuid>`.

## Reminders

An hourly job emails staff, and posts to the **Delivery reminder** Discord channel, once at 24, 48 and 72 hours after payment approval. Past 72 hours the order is marked overdue in the queue.

## Customer controls

Customers can restart, see usage, browse files and open a terminal. There is no power off.
