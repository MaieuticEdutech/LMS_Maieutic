# Hosting decision — Hostinger vs Railway (PD-10)

> Resolves **PD-10** (planning.md §16.3) for **Phase 16 — Deployment & Environments**.
> Prices checked **2026-08-28**; INR figures assume **₹85 / USD** unless the vendor bills in INR.
> Vendor prices change — re-check on the day of purchase.

---

## 1. What the app needs from a host (non-negotiable)

Derived from `architecture.md` §22–23, `phases.md` Phase 16, `config/lms.php`, `composer.json`.

| Need | Detail | Why |
|---|---|---|
| **PHP 8.5** + extensions | `pdo_pgsql pgsql mbstring fileinfo openssl curl zip gd intl exif sodium`, OPcache | `composer.json` requires `php ^8.5`; dompdf needs `gd`; certificates need `intl` |
| **PostgreSQL 17** | JSONB, partial unique indexes, CHECK constraints | Schema depends on PG-only features — no MySQL, no SQLite |
| **Redis** | cache · session · queue | A-11, NFR-SCAL-01 — app holds no local state |
| **Always-on queue workers** | 4 queues: `critical, mail, default, low`, under a process supervisor | Emails, webhooks, grading run async (A-10) |
| **Scheduler** | `schedule:run` every minute | `orders:reconcile`, `attempts:expire`, `backup:run`, … (architecture §14) |
| **Public HTTPS endpoint** | `POST /webhooks/razorpay`, HSTS | Rule 1: enrollment only from a signature-verified webhook |
| **S3-compatible object storage** | Private `content` bucket (videos ≤ 2 GB, PDFs, PPTX) + public bucket (thumbnails) | `LMS_CONTENT_DISK=s3_content`; `league/flysystem-aws-s3-v3` already installed |
| **Large uploads** | Up to 2 GB per video (`upload_max_filesize=2048M`) | `config/lms.php` `media.max_bytes.video` |
| **Nightly DB backup + rehearsed restore** | PITR where available | AC-38 |
| **Transactional email** with SPF/DKIM/DMARC | Activation emails are the onboarding critical path | A-09, PD-07 |
| **Staging** mirroring production | Test-mode Razorpay keys | Phase 16 |
| **India-first audience** | Students in India, INR pricing | A-08 |

---

## 2. The short answer

| | **Hostinger VPS (KVM 2, Mumbai)** | **Railway (Pro, Singapore)** |
|---|---|---|
| **Fits every requirement above?** | ✅ Yes — but *we* install and maintain everything | ✅ Yes — platform-managed, one Dockerfile |
| **Monthly cost (prod only)** | **≈ ₹950 – 2,600** | **≈ ₹4,500 – 7,000** |
| **Monthly cost (prod + staging)** | ≈ ₹950 – 3,100 (staging on the same VPS or a KVM 1) | ≈ ₹6,500 – 10,000 (second environment bills separately) |
| **What Vasu sir gets from us** | **A static IPv4 address** → he adds an **A record** | **A CNAME target + TXT record** → no IP exists |
| **Data centre** | **Mumbai** (two DCs) | **Singapore** (nearest; no India region) |
| **Ops burden** | High — Linux admin, patching, backups, firewall are ours | Low — platform patches, builds, restarts, backs up |
| **Billing** | INR, GST invoice, Indian cards/UPI | USD, international card + forex markup |

**Recommendation: Hostinger VPS KVM 2 in Mumbai + Cloudflare R2 for media**, *provided* the dev team (us) owns the server. It is 3–4× cheaper, bills in INR, keeps student data in India, and produces the static IP the IT hand-off process expects.

**Choose Railway instead** if nobody on the team will commit to owning a Linux server. The ₹3,000–5,000/month premium buys away OS patching, supervisor/cron setup, zero-downtime deploys and DB backups — but the IT process changes from "here is an IP" to "please add a CNAME", and student data sits in Singapore.

**Not viable:** Hostinger *shared / Cloud / Business* hosting. Hostinger's own support page confirms **PostgreSQL is not available on Web or Cloud plans — VPS only**. Do not buy a shared plan for this app.

---

## 3. Absolute comparison table

Legend: 🟢 fits · 🟡 works with effort/caveat · 🔴 gap

| Dimension | Hostinger VPS (KVM 2) | Railway (Pro) | What it means for us |
|---|---|---|---|
| **Model** | IaaS — a root-access Ubuntu VM | PaaS — Docker services from a Git repo | VPS = full control, full responsibility |
| **PHP 8.5** | 🟢 Install any version (`ondrej/php` PPA or Docker) | 🟡 Ship our own **Dockerfile** (don't rely on the auto-builder for 8.5 + extensions) | Either way we write a Dockerfile or a provisioning script |
| **PostgreSQL 17** | 🟡 Self-installed on the same VM; we tune, upgrade, back up | 🟢 Postgres service from template (PG 17 image), billed as usage | Railway removes the biggest ops task |
| **Redis** | 🟡 Self-installed | 🟢 Redis service from template | — |
| **Queue workers** | 🟢 Supervisor / systemd, any number of processes | 🟢 Separate "worker" service running `queue:work` (same repo, different start command) | Railway bills each service's RAM/CPU |
| **Scheduler** | 🟢 System cron → `schedule:run` | 🟡 Third service running `schedule:work` (or Railway cron-triggered service) | Every service is a bill line on Railway |
| **Public HTTPS + webhook** | 🟢 Nginx + Let's Encrypt (certbot auto-renews) | 🟢 Automatic TLS on the custom domain | Both need DNS live before TLS issues |
| **Static inbound IP** | 🟢 **Dedicated IPv4 included** | 🔴 None — custom domains are **CNAME only** | Drives the IT hand-off (§5) |
| **Region / latency to India** | 🟢 Mumbai | 🟡 Singapore (~50–80 ms) | Fine for page loads; video comes from the bucket either way |
| **Data residency** | 🟢 India | 🟡 Singapore — check company/DPDP policy for student PII | Ask Vasu sir |
| **2 GB video uploads** | 🟢 `client_max_body_size` + PHP limits are ours to set | 🟡 Proxy limits/timeouts on long uploads — plan **direct-to-bucket pre-signed uploads** | Do direct-to-bucket on both; it's better anyway |
| **Object storage** | 🔴 No managed object storage product (RustFS template = self-hosted) → external bucket | 🔴 Volumes are block storage (5 GB Hobby / 50 GB Pro default) → external bucket | **Both need R2/B2/S3 — see §4** |
| **Deployment** | 🟡 We script it: GitHub Actions → SSH → `down → pull → composer → migrate → cache → queue:restart → up` (Deployer/Envoy) | 🟢 Push to `main` → build → deploy, health-checked, rollback in UI | Railway matches architecture §23 out of the box |
| **Zero-downtime deploy** | 🟡 Releases + symlink (Deployer) | 🟢 Built in (not for services with volumes) | — |
| **DB backups** | 🟡 Weekly VPS snapshot included; **nightly `pg_dump` → bucket is ours to build**; PITR = WAL archiving, DIY | 🟡 Scheduled volume backups (daily/weekly/monthly); no PITR | AC-38 restore rehearsal is on us either way |
| **Scaling** | 🟡 Vertical resize (reboot); horizontal = manual | 🟢 Vertical automatic (pay for use); replicas for stateless services | KVM 2 comfortably serves a few hundred concurrent students |
| **OS patching / security** | 🔴 Ours: unattended-upgrades, ufw, fail2ban, SSH hardening | 🟢 Platform | Phase 14 hardening scope grows on VPS |
| **DDoS / firewall** | 🟢 Wanguard DDoS filtering + panel firewall | 🟢 Platform edge | — |
| **Logs / metrics** | 🟡 Laravel logs + `journalctl`; ship to Sentry/Papertrail ourselves | 🟢 Built-in log viewer + metrics per service | Sentry (PD-08) in both cases |
| **Staging** | 🟢 Second vhost + second DB on the same VPS (₹0) or a KVM 1 | 🟡 Second Railway environment — bills like a second prod | — |
| **Team access** | SSH keys per developer; one root | Workspace members (seats on Pro) | — |
| **Support** | 24/7 live chat | Community + Pro support; SLA only on Enterprise | — |
| **Uptime commitment** | 99.9% marketing guarantee | No SLA below Enterprise | Neither is contractual for us |
| **Billing** | 🟢 INR, GST invoice, UPI/cards | 🟡 USD, international card, forex ~3–4 % | INR billing is simpler for us |
| **Lock-in** | None (plain Linux) | Low (Dockerfile is portable) | — |
| **Skills required** | Linux sysadmin (Nginx, PHP-FPM, PG, Redis, Supervisor, certbot) | Docker basics | **The deciding factor** |
| **Vendor price stability** | 🟡 Promo requires 2-yr upfront; **renewal ≈ 1.5–2×** | 🟢 Flat rates, pay-as-you-go | Budget for the renewal price, not the promo |

---

## 4. Costs

### 4.1 Hostinger VPS plans (KVM, Ubuntu, Mumbai)

| Plan | vCPU | RAM | NVMe | Bandwidth | Global promo* | Global renewal | **India promo*** | **India renewal** |
|---|---|---|---|---|---|---|---|---|
| KVM 1 | 1 | 4 GB | 50 GB | 4 TB | $6.49 | $11.99 | ≈ ₹549 | ≈ ₹899 |
| **KVM 2** ← recommended | 2 | 8 GB | 100 GB | 8 TB | $8.99 | $14.99 | **₹779** | **₹1,199** |
| KVM 4 | 4 | 16 GB | 200 GB | 16 TB | $12.99 | $28.99 | ₹1,099 | ₹2,399 |
| KVM 8 | 8 | 32 GB | 400 GB | 32 TB | $25.99 | $49.99 | — | — |

\* Promo = 24-month term paid **upfront** (KVM 2 ≈ ₹18,700 for two years). Included on all plans: dedicated IPv4, weekly backups + manual snapshots, firewall, DDoS protection, root access, 1 Gbps. Third-party price trackers show slightly different promo figures depending on the sale running that week — confirm on hostinger.in at purchase.

**Why KVM 2:** 8 GB RAM covers Nginx + PHP-FPM + PostgreSQL + Redis + 3–4 queue workers with headroom; 100 GB disk is for OS, app and DB only (media lives in the bucket). KVM 1's 4 GB is tight once Postgres and workers are on the same box.

### 4.2 Railway (Pro, Singapore)

| Item | Rate |
|---|---|
| Pro plan | **$20 / month**, includes $20 of usage |
| Hobby plan | $5 / month, includes $5 of usage — meant for individuals; 5 GB volume cap; not for a company production system |
| vCPU | $20 per vCPU-month (billed per minute of actual use) |
| RAM | $10 per GB-month |
| Volume (Postgres/Redis data) | $0.15 per GB-month |
| Egress | $0.05 per GB |

Estimated steady-state usage for this app (5 services):

| Service | vCPU avg | RAM avg | Volume | ≈ $/mo |
|---|---|---|---|---|
| web (Nginx + PHP-FPM) | 0.5 | 1.0 GB | — | 20 |
| worker (`queue:work`) | 0.2 | 0.5 GB | — | 9 |
| scheduler (`schedule:work`) | 0.1 | 0.3 GB | — | 5 |
| postgres | 0.2 | 0.5 GB | 5 GB | 10 |
| redis | 0.1 | 0.25 GB | — | 4.5 |
| egress (~50 GB; video is served by the bucket, not Railway) | | | | 2.5 |
| **Usage total** | | | | **≈ $51** |
| **Bill** (usage exceeds the $20 credit, so bill ≈ usage) | | | | **≈ $51 ≈ ₹4,300** |

Realistic band **$35–70 / month** for production; a staging environment adds ≈ $25–35. Idle RAM is billed, so the floor is not zero.

### 4.3 Cloud object storage (needed on **both** platforms)

| Provider | Storage / GB-mo | Free tier | Egress | Requests | Notes |
|---|---|---|---|---|---|
| **Cloudflare R2** ← recommended | **$0.015** | 10 GB | **$0 — always** | Class A $4.50/M, Class B $0.36/M | S3-compatible; zero egress makes video streaming cost predictable |
| Backblaze B2 | $0.00695 ($6.95/TB) | 10 GB | Free up to 3× stored/month, then $0.01/GB; free via Cloudflare CDN | Class A/B/C free; Class D $0.004/10k | Cheapest storage; S3-compatible |
| Wasabi | $0.00699 ($6.99/TB) | — | "Free" but monthly egress must not exceed stored volume | Free | **1 TB minimum billed** ($6.99 floor), 90-day retention minimum |
| AWS S3 Standard (Mumbai) | ≈ $0.025 | 5 GB (12 mo) | ≈ $0.109/GB after 100 GB free | $0.005/1k PUT | Egress makes video **10–20× more expensive** than R2 |
| Hostinger | — | — | — | — | **No managed object storage product.** RustFS is a one-click template you run on your own VPS disk — not a bucket |

Monthly cost by scenario (students stream each video ~3× its size per month):

| Scenario | R2 | B2 | Wasabi | S3 Mumbai |
|---|---|---|---|---|
| 100 GB stored · 300 GB served | **≈ $1.4 (₹120)** | ≈ $0.7 | $6.99 (minimum) | ≈ $24 |
| 500 GB stored · 1.5 TB served | **≈ $7.4 (₹630)** | ≈ $3.5 | $6.99 | ≈ $165 |
| 1 TB stored · 3 TB served | **≈ $15 (₹1,275)** | ≈ $7 | $6.99 — but egress > stored breaks fair-use | ≈ $340 |

Pick **R2**: no egress surprise as video views grow, works with the existing `s3_content` disk by setting `AWS_ENDPOINT` + `AWS_USE_PATH_STYLE_ENDPOINT`, and pre-signed URLs (architecture §11) work unchanged. B2 is the fallback if storage volume dwarfs views.

### 4.4 Everything else (same on both platforms)

| Item | Choice | Cost |
|---|---|---|
| Transactional email (PD-07) | **Brevo** free tier (300/day) to start; **Amazon SES** ($0.10 / 1,000) at scale; Postmark ($15/mo) if deliverability is worth paying for | ₹0 – 1,300 |
| Error tracking (PD-08) | **Sentry Developer** (free, 5k errors/mo, 1 user); Team $26/mo if the whole team needs seats | ₹0 – 2,200 |
| TLS certificate | Let's Encrypt (VPS) / automatic (Railway) | ₹0 |
| Domain + subdomain | Company domain, managed by IT | ₹0 to us |
| Uptime monitoring | UptimeRobot / Better Stack free tier hitting `/up` | ₹0 |
| Offsite DB backups | Nightly `pg_dump` to an R2 bucket, 30-day retention (< 5 GB) | < ₹10 |

### 4.5 Total monthly picture

| | Hostinger KVM 2 + R2 | Railway Pro + R2 |
|---|---|---|
| Compute (year 1 promo / renewal) | ₹779 / ₹1,199 | ≈ ₹4,300 |
| Media storage (100 GB → 1 TB) | ₹120 – 1,275 | ₹120 – 1,275 |
| Email + Sentry (free tiers → paid) | ₹0 – 3,500 | ₹0 – 3,500 |
| **Production total** | **≈ ₹950 – 2,600** (₹1,300 – 6,000 at renewal + paid tiers) | **≈ ₹4,500 – 7,000** (₹5,500 – 9,000 with paid tiers) |
| + Staging | +₹0 (same VPS) or +₹549 (KVM 1) | +₹2,100 – 3,000 |
| **Upfront** | ₹18,700 (24 months of KVM 2) | ₹0 |

---

## 5. Dependency list — what has to happen, in what order, and who owns it

Only two parties are involved: **the dev team (us)** and **Vasu sir (IT)**, who holds all domain and networking rights — DNS, subdomains, records, firewall, inventory. Everything that is not networking is ours.

The hand-off with Vasu sir is a chain: nothing after a DNS step can start until his record is live. Order matters.

| # | Step | Owner | Needs | Produces | Typical wait |
|---|---|---|---|---|---|
| 0 | **Decide platform (PD-10)** and agree the names: production `lms.<company-domain>`, staging `staging-lms.<company-domain>`, media CDN `media.<company-domain>` *(optional)* | Dev team (us) | This document | Decision recorded in `planning.md` §16.4 | — |
| 1 | **Create the vendor account** under a company email with 2FA; assign a billing owner | Dev team (us) — or Vasu sir if IT holds vendor accounts | Decision #0, company card/UPI | Hostinger or Railway account | Same day |
| 2 | **Provision** — buy KVM 2 (Mumbai, Ubuntu 24.04) *or* create the Railway project (Singapore) with web/worker/scheduler/postgres/redis services | **Dev team (us)** | #1 | Hostinger: **static IPv4**. Railway: **CNAME target `xxxx.up.railway.app` + TXT verification record** | Minutes |
| 3 | **DNS records — hand-off to Vasu sir** (see §6 for the exact request) | **Vasu sir** | #2 | `lms.<domain>` and `staging-lms.<domain>` resolving | Minutes – 24 h propagation |
| 4 | **TLS** — certbot on the VPS / automatic on Railway. Only works once #3 resolves and ports 80/443 are reachable | Dev team (us) / platform | #3 | `https://lms.<domain>/up` → 200 | Minutes after DNS |
| 5 | **Object storage** — R2 account (Cloudflare, company email), two buckets (`lms-content` private, `lms-public`), an API token scoped to those buckets | Dev team (us) | #1-style account | `AWS_*` env values for `s3_content` | Same day. *If a `media.<domain>` CDN hostname is wanted → another CNAME via Vasu sir* |
| 6 | **Email provider (PD-07)** — sign up (Brevo/SES), add sending domain | Dev team (us) | Company email | **SPF, DKIM, DMARC (+ return-path) records** to hand to Vasu sir | Same day |
| 7 | **Email DNS — second hand-off to Vasu sir** | **Vasu sir** | #6 | Domain verified at the provider; activation emails deliverable | Up to 72 h for DKIM verification |
| 8 | **Razorpay live credentials (PD-09)** — live key/secret from the business account; register webhook `https://lms.<domain>/webhooks/razorpay`; copy the webhook secret into env only | Dev team (us), with the Razorpay account holder | #4 | `RAZORPAY_KEY_ID/KEY_SECRET/WEBHOOK_SECRET` in production env | Depends on Razorpay KYC status |
| 9 | **Error tracking (PD-08)** — Sentry project, DSN in env | Dev team (us) | — | `SENTRY_LARAVEL_DSN` | Minutes |
| 10 | **Deploy pipeline** — GitHub Actions deploy job (SSH + Deployer on VPS / auto-deploy on Railway); secrets in GitHub | Dev team (us) | #4 | Push-to-deploy | 1–2 days |
| 11 | **Backups + restore rehearsal (AC-38)** — nightly `pg_dump` → R2, restore into staging, timed | Dev team (us) | #5, #10 | Documented restore procedure | 1 day |
| 12 | **Asset registration with IT** — server IP/hostname, owner, environment, cost centre, vendor account, renewal date entered in IT inventory ("tags") | **Vasu sir** | #2, #3 | Inventory entry; renewal reminder | — |
| 13 | **Go-live checklist** — `APP_DEBUG=false`, HSTS, caches warmed, workers running, cron running, webhook test event, test purchase end-to-end on staging with test keys, then production | Dev team (us) + Vasu sir | #1–12 | Phase 16 Definition of Done | — |

**Critical path:** 0 → 1 → 2 → **3 (Vasu sir)** → 4 → 10 → 13, with **7 (Vasu sir)** in parallel from step 6. Two IT hand-offs, both DNS; do them as one request if possible.

---

## 6. The request to Vasu sir (copy-paste template)

Fill in once step 2 is done. Send both blocks together to avoid a second round-trip.

### 6.1 Web records

**If Hostinger (recommended):**

| Type | Name | Value | TTL |
|---|---|---|---|
| A | `lms` | `<static IPv4 from Hostinger>` | 300 |
| A | `staging-lms` | `<same IPv4>` | 300 |
| CAA *(optional, if IT restricts issuers)* | `lms` | `0 issue "letsencrypt.org"` | 3600 |

**If Railway:**

| Type | Name | Value | TTL |
|---|---|---|---|
| CNAME | `lms` | `<xxxx>.up.railway.app` | 300 |
| TXT | `_railway-verify.lms` *(exact name given by Railway)* | `<verification token>` | 300 |
| CNAME | `staging-lms` | `<yyyy>.up.railway.app` | 300 |
| TXT | *(as above, for staging)* | | |

*Railway note: if the domain is proxied through Cloudflare, set the record to DNS-only (grey cloud) until the certificate has issued, or use Cloudflare "Full (strict)" mode.*

### 6.2 Email records (values come from the email provider after step 6)

| Type | Name | Value |
|---|---|---|
| TXT | `@` (or the sending subdomain) | `v=spf1 include:<provider> ~all` — **merge** into the existing SPF record if one exists; a domain may only have one |
| CNAME / TXT | `<selector>._domainkey` | DKIM key (SES issues 3 CNAMEs; Brevo 1 TXT) |
| TXT | `_dmarc` | `v=DMARC1; p=quarantine; rua=mailto:dmarc@<domain>` |
| CNAME *(SES only)* | `bounce` / custom MAIL FROM | provider-supplied |

### 6.3 Questions for Vasu sir (answer before step 2)

1. Which domain do we use, and where is its DNS hosted (registrar / Cloudflare / other)? Can he add CNAMEs there, or only A records?
2. Does IT require a **static IP** for firewall/allow-listing or asset inventory? *(If yes → Hostinger; Railway cannot provide one.)*
3. Any policy on **data residency** for student personal data (Mumbai vs Singapore)?
4. Does IT issue its own TLS certificates (wildcard), or may we use Let's Encrypt / platform-issued certs? Any CAA policy?
5. Who holds vendor accounts and billing — IT (Vasu sir) or the dev team? Who receives renewal reminders?
6. Is the company already sending mail from this domain (existing SPF/DKIM)? We must **merge**, not replace.
7. Does IT run uptime/security monitoring we should register with?
8. What should the inventory/tag entry contain (owner, environment, cost centre, expiry)?

---

## 7. Decision record (for planning.md §16.4 once agreed)

| Field | Value |
|---|---|
| Decision | PD-10: **Hostinger VPS KVM 2 (Mumbai) + Cloudflare R2**, staging on the same VPS |
| Rationale | 3–4× cheaper than Railway at renewal, INR/GST billing, India data residency, static IPv4 matches the IT DNS process; all Phase 16 requirements met with standard Laravel VPS tooling |
| Consequence | The dev team owns server provisioning, hardening, backups and the deploy script; Phase 14 scope includes OS hardening. Budget the **renewal** price (₹1,199/mo) not the promo |
| Fallback | Railway Pro (Singapore) if server ownership cannot be staffed — switch to CNAME-based hand-off |

---

## Sources (checked 2026-08-28)

- Hostinger VPS plans & pricing — https://www.hostinger.com/vps-hosting
- Hostinger: PostgreSQL is VPS-only — https://www.hostinger.com/support/1583659-is-postgresql-supported-at-hostinger/
- Hostinger India VPS pricing (₹) — https://smarthostfinder.com/hostinger-vps-pricing/ , https://hostadvice.com/hosting-company/hostinger-reviews/vps-pricing/
- Hostinger Mumbai data centres — https://hostadvice.com/hosting-company/hostinger-reviews/hostinger-india-hosting-review/
- Railway plans & usage rates — https://docs.railway.com/pricing/plans
- Railway regions — https://docs.railway.com/reference/regions
- Railway volumes — https://docs.railway.com/reference/volumes
- Railway custom domains (CNAME + TXT) — https://docs.railway.com/guides/public-networking
- Railway Laravel multi-service pattern — https://docs.railway.com/guides/laravel
- Cloudflare R2 pricing — https://developers.cloudflare.com/r2/pricing/
- Backblaze B2 pricing — https://www.backblaze.com/cloud-storage/pricing
- Object storage comparison (Wasabi, S3) — https://sliplane.io/blog/5-cheap-object-storage-providers , https://leanopstech.com/blog/cloudflare-r2-pricing-2026/
- AWS S3 Mumbai — https://www.itforsme.in/pricing/aws-s3-india
- Email providers — https://www.buildmvpfast.com/api-costs/email
- Sentry pricing — https://costbench.com/software/developer-tools/sentry/
