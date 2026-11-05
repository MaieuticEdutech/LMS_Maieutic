# Hosting decision — AWS vs Azure (follow-up to PD-10)

> Prices checked **2026-10-08**; INR figures assume **₹85 / USD** (same convention as
> `HOSTING-COMPARISON.md`). Vendor prices change — re-check on the day of purchase.
>
> Context: PD-10 (see `HOSTING-COMPARISON.md`) picked **Hostinger VPS KVM 2 + Cloudflare R2**,
> already live in production. A move to a major cloud provider is now under discussion
> (compliance-driven, per the team). This compares **AWS vs Azure specifically** for the agreed
> **lift-and-shift to a single VM** approach (replicate the current Coolify setup — app + Postgres
> + Redis + a queue worker — rather than re-architecting onto managed services yet).

---

## 1. The short answer

| | **AWS** | **Azure** |
|---|---|---|
| **Equivalent VM** (2 vCPU / 8 GB, matching the current Hostinger KVM 2) | `t3.large`, `ap-south-1` (Mumbai) | `Standard_D2s_v5`, Central India |
| **Compute** | **$65.41/mo** (₹5,560) | $73.73/mo (₹6,267) |
| **100 GB SSD storage** | **$9.12/mo** (gp3) | $31.00/mo (Premium SSD) |
| **Total (compute + storage)** | **≈ $74.53/mo ≈ ₹6,335/mo** | ≈ $104.73/mo ≈ ₹8,902/mo |
| **Egress (after 100 GB free)** | ≈ ₹8–9/GB | $0.12/GB (≈ ₹10/GB) — slightly worse |
| **India region options** | Mumbai only (`ap-south-1`) | Central India, South India, West India — more choice |
| **Static IP for the IT DNS hand-off** | Elastic IP — free while attached to a running instance | Static Public IP — small extra monthly charge |

**Recommendation if cost is a factor at all: AWS.** It's ~30% cheaper than Azure for the identical
spec, and the gap is almost entirely Azure's storage pricing (Premium SSD is 3.4× AWS's gp3 for
the same 100 GB — ask about Standard SSD instead if Azure is chosen, it closes most of that gap).

**The number that matters most for the meeting:** both options are **roughly 3–9× more expensive
than what's being paid on Hostinger right now** (₹950–2,600/mo today vs ₹6,300–8,900/mo on either
cloud, for the same VM). If this move is purely a compliance requirement, that's an accepted cost
of doing business — but the team should hear the actual number, not just pick a logo.

---

## 2. What drives the price gap, and what doesn't

- **Compute is close** (AWS $65 vs Azure $74/mo) — not the deciding factor either way.
- **Storage is the biggest swing**: AWS gp3 SSD is a flat $0.0912/GB-mo; Azure's comparable
  Premium SSD is $0.31/GB-mo. Azure does have a cheaper Standard SSD tier, worth asking about if
  100 GB of OS+app+DB storage doesn't need Premium-tier IOPS (media itself stays on R2 either way,
  so this disk is only ever OS + app + Postgres — unlikely to need Premium performance).
- **Data residency**: both support keeping data in India. AWS only has one India region
  (Mumbai); Azure has three (Central, South, West India), which gives more flexibility if the
  compliance requirement is specific about which city/region.
- **Compliance certifications**: both AWS and Azure carry the standard broad set (ISO 27001,
  SOC 2, PCI DSS, etc.) at the platform level. **Neither of these numbers answers the actual
  compliance question** — what specific framework or certification is being required is still
  unconfirmed (flagged in the earlier migration-prep notes too). Get that answered before this
  becomes a purely cost-driven pick.

---

## 3. What this doesn't cover yet

This is a cost/spec comparison only, sized for the meeting — it is **not** the full dependency/
hand-off breakdown `HOSTING-COMPARISON.md` has for Hostinger-vs-Railway (DNS steps, email DNS,
Razorpay webhook re-registration, IT asset registration, etc.). If AWS or Azure is chosen, that
full runbook should get the same treatment before migration starts — most of it already exists in
draft form from the earlier AWS migration-prep checklist and just needs the Azure-equivalent steps
added if Azure wins instead.

---

## Sources (checked 2026-10-08)

- [AWS EC2 t3.large pricing, ap-south-1](https://compute.doit.com/spot/ap-south-1/t3.large)
- [Azure Standard_D2s_v5 pricing, Central India](https://costcalc.cloudoptimo.com/azure-pricing-calculator/vm/Standard-D2s-v5)
- [AWS EBS gp3 pricing guide](https://cloudchipr.com/blog/aws-ebs-pricing)
- [Azure Premium SSD (LRS) pricing, Central India](https://pcr.cloud-mercato.com/providers/azure/volumes/None:Premium_LRS)
- [Azure egress/bandwidth pricing by zone](https://egresscost.com/azure)
- [AWS India pricing guide (data transfer)](https://precisiontech.in/cloud/amazon-aws-cloud/aws-pricing/aws-pricing-in-gurugram/)
