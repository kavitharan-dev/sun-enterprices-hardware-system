# Oracle Cloud Always Free — VPS for SUN ENTERPRICES

Use this when the live app must stay online while the developer PC is off.
After the VM exists, continue with [DEPLOYMENT.md](DEPLOYMENT.md).

**Target shape (Always Free):** `VM.Standard.A1.Flex` — **2 OCPU / 12 GB RAM** (one Ubuntu server).  
Sign up: https://www.oracle.com/cloud/free/

---

## 1. Create the free account

1. Open https://www.oracle.com/cloud/free/ → **Start for free**
2. Use a real email, phone, and card (Oracle verifies identity; Always Free resources stay $0 if you stay within limits)
3. Pick a **home region** and do not change it later (capacity is tied to it)
4. Wait until the tenancy is active and you can open the OCI Console

If signup is rejected, retry with accurate address/phone, or try again later. This is common.

---

## 2. Create the free Ubuntu VM

1. Console → **Compute** → **Instances** → **Create instance**
2. Name: `sunenterprise`
3. **Image:** Canonical Ubuntu **22.04** or **24.04** (ARM / aarch64 — Always Free eligible)
4. **Shape:** Change shape → **Ampere** → `VM.Standard.A1.Flex`
   - OCPUs: **2**
   - Memory: **12 GB**
   - Confirm it shows **Always Free-eligible**
5. **Networking:** public subnet, assign a public IPv4
6. **SSH keys:** download / save the **private** key (`.key`). You cannot recover it later
7. Create

### If you see “Out of capacity”

- Try another **Availability Domain** (AD-1 / AD-2 / AD-3)
- Retry at off-peak hours (early morning Asia/Colombo)
- Keep limits at **2 OCPU / 12 GB** (do not ask for more on a free account)
- Optional: upgrade account to Pay As You Go with a **$0 budget alert** — Always Free can still stay free, capacity is often easier

---

## 3. Open firewall ports (required)

Oracle blocks 80/443 until you open them.

1. Instance → subnet → **Security List** (or Network Security Group)
2. **Ingress** rules:

| Source | Protocol | Port | Why |
|--------|----------|------|-----|
| 0.0.0.0/0 | TCP | 22 | SSH |
| 0.0.0.0/0 | TCP | 80 | HTTP / Certbot |
| 0.0.0.0/0 | TCP | 443 | HTTPS |

3. On the VM after login, also allow UFW (see DEPLOYMENT.md §10):

```bash
sudo ufw allow OpenSSH
sudo ufw allow 'Nginx Full'
sudo ufw enable
```

---

## 4. SSH from your Windows PC

Save the private key (example: `C:\Users\kavit\.ssh\oracle-sunenterprise.key`).

PowerShell:

```powershell
ssh -i C:\Users\kavit\.ssh\oracle-sunenterprise.key ubuntu@YOUR_PUBLIC_IP
```

First login: say `yes` to the host fingerprint.  
If user is not `ubuntu`, check the instance “Connect” panel (sometimes `opc` on Oracle Linux — prefer Ubuntu image).

---

## 5. Point the domain at the VPS

At your domain registrar for `sunenterprise.lk`:

| Type | Name | Value |
|------|------|--------|
| A | `@` | `YOUR_PUBLIC_IP` |
| A | `www` | `YOUR_PUBLIC_IP` |

Wait until `ping sunenterprise.lk` shows that IP, then continue SSL in DEPLOYMENT.md.

---

## 6. Install the app (same as normal VPS)

On the Ubuntu VM, follow [DEPLOYMENT.md](DEPLOYMENT.md) from **§1 Server packages** onward:

1. Install Nginx, PHP 8.2, MySQL, Node 20  
2. Create MySQL database  
3. Clone repo → `composer` → `npm run build`  
4. Production `.env` with **MySQL**  
5. Migrate + **four** seeders only  
6. Change all passwords  
7. Certbot HTTPS  
8. Queue worker + cron + off-site backup  

Your **developer PC can stay off**. Staff use `https://sunenterprise.lk` only.

---

## 7. Oracle-specific checklist

- [ ] Always Free shape (2 OCPU / 12 GB), not a paid shape by mistake
- [ ] Public IP noted; SSH key backed up
- [ ] Ports 22, 80, 443 open in OCI Security List
- [ ] Domain A records → public IP
- [ ] DEPLOYMENT.md completed
- [ ] FIRST_DELIVERY.md soft checks done
- [ ] Budget alert set to $1 (optional Pay As You Go) so you get emailed if anything bills

---

## 8. Keep it free

- Stay on Always Free shapes and storage limits  
- Do not create extra paid block volumes unnecessarily  
- Set a **budget alert** in Billing if the account is Pay As You Go  
- Backups: use app hourly backup + off-site disk (Backblaze B2), not only the Oracle boot volume  

---

## Troubleshooting

| Problem | Fix |
|---------|-----|
| Out of capacity | Retry AD / time; patience; or PAYG for capacity |
| SSH timeout | Security List port 22; use correct key and user |
| Site not loading | Ports 80/443; Nginx running; DNS to public IP |
| SSL fails | DNS must already point to this IP |
| Slow / OOM | Confirm 12 GB ARM VM; restart MySQL/PHP-FPM |

When the VM is reachable over SSH, you are done with Oracle setup — finish with [DEPLOYMENT.md](DEPLOYMENT.md) and [FIRST_DELIVERY.md](FIRST_DELIVERY.md).
