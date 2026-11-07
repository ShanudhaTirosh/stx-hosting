---
title: SSH and SFTP
summary: Connect with your own tools, and what to keep in place for the panel.
order: 7
---
## SSH

From your computer:

```
ssh root@YOUR_SERVER_IP -p YOUR_SSH_PORT
```

The port is on your server's page (usually 22). Add your own key with `ssh-copy-id -p PORT root@YOUR_SERVER_IP`, then you can turn off password login if you like. Keep the `stx-panel` key line in `/root/.ssh/authorized_keys`.

## SFTP

Use any SFTP client, such as FileZilla or WinSCP: protocol **SFTP**, host your server's IP, your SSH port, user `root`, and your password or key. Plain FTP is not offered. For small files you can also use the **Files** section of the VPS panel.

## Browser terminal

The VPS panel has a terminal that opens a shell on your server without installing anything. It signs in with the panel key, not your password.
