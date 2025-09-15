# ShopCommerce Product Sync Plugin — Requirements and Project Context (Concise)

Last updated: 2025-09-14 21:01 (local time)

## 1) Purpose and Scope
- Sync selected products from the ShopCommerce (MPS) wholesaler API into a WooCommerce store.
- Only a subset of brands and categories is required (not full catalog).
- Run via WordPress cron (wp-cron) with work chunking to avoid timeouts and heavy loads.

## 2) Current State (v1.1.0)
- Token retrieval implemented against base URL: https://shopcommerce.mps.com.co:7965/
- Catalog fetch implemented against api/Webapi/VerCatalogo with brand/category filtering attempt.
- Selective sync configured using provider’s MarcaHomologada and top-level category codes.
- Workload split: one brand per cron run; optional batching inside a run.
- Admin debug page: Tools > ShopCommerce Product Sync Debug with buttons to run next job and reset queue.
- Product upsert into WooCommerce is a placeholder (to be implemented next).

Files of interest:
- index.php — plugin bootstrap, admin page, activation/deactivation hooks.
- includes/product-sync.php — cron job, token fetch, job queue, filtering, logging.

## 3) Functional Requirements
1. Selective Brand/Category Sync
   - Brands and categories are restricted per client request:
     - HP corporativo: PCs, portátiles, workstations, servidores (volumen/valor), accesorios, monitores e impresión.
     - Dell corporativo: PCs, portátiles, workstations, monitores, servidores y accesorios.
     - Lenovo corporativo: PCs, portátiles, workstations, monitores, servidores y accesorios.
     - Apple: accesorios y portátiles.
     - Asus: portátiles corporativo.
     - JBL: todas las categorías.
     - Bose: todas las categorías.
     - Epson: todas las categorías.
   - Mapping uses MarcaHomologada to group provider brand variants.
   - Categories use the wholesaler’s top-level codes; if a brand has no specified categories, import all for that brand.

2. Scheduling and Chunking
   - A circular job queue stored in WP options processes one brand per cron invocation.
   - Default schedule: hourly (adjustable); testing schedule available: every_minute.
   - Within each run, further batch processing may be applied (default example batch: 100 items).

3. API Integration
   - Token endpoint: {base}/Token — grant_type=password with username/password.
   - Catalog endpoint: {base}/api/Webapi/VerCatalogo — attempt to send filters in the request body:
     - MarcaHomologada: string (brand)
     - Categorias: array<int> (top-level category codes) [if applicable]
   - If server ignores filters, fallback client-side filtering by MarcaHomologada and CodigoCategoria.

4. WooCommerce Product Handling (Next Step)
   - For each synced item: create or update a product (SKU as key if available), set price/stock, images, and taxonomy (brand/category).
   - Maintain idempotency and minimal diffs (update only changed fields).
   - Manage product visibility based on stock/availability rules.

5. Admin and Observability
   - Admin page to manually trigger the next job and reset the queue.
   - Logging to PHP error log with [ShopCommerce Sync] prefix: token, fetch, filter summaries, SKUs processed.

## 4) Non-Functional Requirements
- Performance: keep each cron run short to fit typical wp-cron limits; avoid full-catalog pulls in one run.
- Resilience: handle network/API failures gracefully; safe retries on next cron cycle.
- Security: never expose credentials publicly; consider moving credentials to WP options or env and masking logs.
- Compatibility: standard WordPress/WooCommerce; no hard dependencies beyond WooCommerce for final product upsert.
- Maintainability: configuration centralized in shopcommerce_sync_jobs_config().

## 5) Configuration (brands and categories)
- Defined in includes/product-sync.php (shopcommerce_sync_jobs_config):
  - Category reference codes from provider:
    - 1 Accesorios Y Perifericos
    - 7 Computadores (PCs, portátiles, workstations)
    - 12 Impresión
    - 14 Video (e.g., Monitores)
    - 18 Servidores Y Almacenamiento
  - Brand mapping via MarcaHomologada:
    - HP INC, DELL, LENOVO: [7, 18, 1, 14, 12]
    - APPLE: [1, 7]
    - ASUS: [7]
    - BOSE, EPSON, JBL: all categories

## 6) Known Gaps and Assumptions
- Exact API filter parameter names/content-type may need adjustment per provider documentation (JSON vs form).
- Subcategory mappings are not yet implemented; only top-level categories used.
- Product upsert to WooCommerce is pending implementation.
- Some provider brand aliases may need normalization to expected MarcaHomologada values.

## 7) How to Test
- Activate plugin; ensure wp-cron is running.
- Use Tools > ShopCommerce Product Sync Debug:
  - Run Next Job Now: triggers immediate processing for the next brand.
  - Reset Jobs: resets job pointer/config in options.
- Check wp-content/debug.log for lines starting with [ShopCommerce Sync].

## 8) Next Steps
- Implement WooCommerce product create/update pipeline with mapping from provider fields.
- Confirm API request format (possibly JSON) and adjust headers/body accordingly.
- Add subcategory and attribute mapping as needed.
- Add configuration UI (optional) for brands/categories and schedule.
