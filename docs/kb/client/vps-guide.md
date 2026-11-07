---
title: Your VPS
summary: Delivery time, login details, restart, usage, files and the terminal.
order: 6
---
## Delivery

A VPS is set up by hand after your payment is approved, usually within **24 to 72 hours**. We email you when it is ready. If it takes longer, tell us through **Support**.

## Login details

Open **My Services**, then your server. The IP address, user (`root`) and SSH port are shown. To reveal the root password you confirm your account password first. Change the password after your first login.

## The VPS panel

Open **Terminal, files and usage** on your server. The first time, press **Enable panel access**. This installs a key so the panel can reach your server. After that you can change your root password freely.

- **Restart:** type RESTART to confirm. The server reboots. There is no power-off button; contact us if you need it stopped.
- **Usage:** CPU, memory, disk and traffic, updated every minute, with the traffic used this month against your plan.
- **Files:** browse, download, upload and delete files over SFTP.
- **Terminal:** a shell in your browser.

## Things to know

- You can only use servers we provide.
- Do not delete the line ending in `stx-panel` from `/root/.ssh/authorized_keys`, and keep SSH reachable and root login by key allowed. Without them the panel cannot reach your server.
- If we ever tell you the server's identity changed, that is a safety stop; see *Troubleshooting*.
