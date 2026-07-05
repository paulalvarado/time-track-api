/**
 * Sync Worker — Single-run Odoo sync for all configured accounts.
 * Designed to be called from crontab or similar scheduler.
 *
 * Usage: tsx src/commands/syncworker.ts
 *
 * It will sync all OdooConfig entries once and exit.
 */
import { prisma } from "../lib/prisma.js";
import { SyncService } from "../services/sync.js";

async function main() {
  console.log(`[SyncWorker] Starting Odoo sync at ${new Date().toISOString()}`);

  try {
    const configs = await prisma.odooConfig.findMany({
      include: { syncState: true },
    });

    if (configs.length === 0) {
      console.log("[SyncWorker] No Odoo configurations found. Nothing to sync.");
      await prisma.$disconnect();
      return;
    }

    for (const config of configs) {
      if (config.syncState?.syncing) {
        console.log(`[SyncWorker] Skipping ${config.username} — already syncing`);
        continue;
      }

      console.log(`[SyncWorker] Syncing ${config.username} (${config.dbName})...`);
      try {
        await SyncService.syncAll(config.id);
        console.log(`[SyncWorker] ✅ Synced ${config.username} successfully`);
      } catch (err: any) {
        console.error(`[SyncWorker] ❌ Error syncing ${config.username}: ${err.message}`);
      }
    }

    console.log(`[SyncWorker] Sync complete at ${new Date().toISOString()}`);
  } catch (err: any) {
    console.error(`[SyncWorker] Fatal error: ${err.message}`);
  }

  await prisma.$disconnect();
}

main();

main().catch((err) => {
  console.error("[SyncWorker] Failed to start:", err);
  process.exit(1);
});
