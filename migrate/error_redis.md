Procesando disparadores para libc-bin (2.41-12+deb13u3) ...
Scanning processes...                                                           
Scanning candidates...                                                          
Scanning linux images...                                                        

Running kernel seems to be up-to-date.

Restarting services...
Service restarts being deferred:
 systemctl restart NetworkManager.service
 systemctl restart lightdm.service

No containers need to be restarted.

User sessions running outdated binaries:
 consultor @ user service: xdg-desktop-portal.service[3393]

No VM guests are running outdated hypervisor (qemu) binaries on this host.
Synchronizing state of redis-server.service with SysV service script with /usr/lib/systemd/systemd-sysv-install.
Executing: /usr/lib/systemd/systemd-sysv-install enable redis-server
Job for redis-server.service failed because the control process exited with error code.
See "systemctl status redis-server.service" and "journalctl -xeu redis-server.service" for details.
