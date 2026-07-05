/**
 * Catalog Sync Worker — Single-run sync of Odoo catalogs (priority, users, etc.).
 * Designed to be called from crontab/scheduler una vez al día.
 *
 * Usage: tsx src/commands/synccatalogs.ts
 *
 * Sincroniza todos los catálogos definidos para todas las cuentas configuradas.
 */
import { prisma } from "../lib/prisma.js";
import { CatalogSyncService } from "../services/catalog-sync.js";

async function main() {
  console.log(`[CatalogSyncWorker] Starting catalog sync at ${new Date().toISOString()}`);

  try {
    const configs = await prisma.odooConfig.findMany();

    if (configs.length === 0) {
      console.log("[CatalogSyncWorker] No Odoo configurations found. Nothing to sync.");
      await prisma.$disconnect();
      return;
    }

    for (const config of configs) {
      console.log(`[CatalogSyncWorker] Syncing catalogs for ${config.username} (${config.dbName})...`);
      try {
        await CatalogSyncService.syncCatalogs(config.id);
        console.log(`[CatalogSyncWorker] ✅ Catalogs synced for ${config.username}`);
      } catch (err: any) {
        console.error(`[CatalogSyncWorker] ❌ Error syncing catalogs for ${config.username}: ${err.message}`);
      }
    }

    console.log(`[CatalogSyncWorker] Catalog sync complete at ${new Date().toISOString()}`);
  } catch (err: any) {
    console.error(`[CatalogSyncWorker] Fatal error: ${err.message}`);
  }

  await prisma.$disconnect();
}

main().catch((err) => {
  console.error("[CatalogSyncWorker] Failed to start:", err);
  process.exit(1);
});
