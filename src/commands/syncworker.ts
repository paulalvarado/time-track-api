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

    // Sincronizar todas las cuentas en paralelo
    const results = await Promise.allSettled(
      configs.map(async (config: any) => {
        if (config.syncState?.syncing) {
          console.log(`[SyncWorker] Skipping ${config.username} — already syncing`);
          return;
        }

        console.log(`[SyncWorker] Syncing ${config.username} (${config.dbName})...`);
        await SyncService.syncAll(config.id);
        console.log(`[SyncWorker] ✅ Synced ${config.username} successfully`);
      })
    );

    // Reportar errores
    for (let i = 0; i < results.length; i++) {
      const r = results[i];
      if (r.status === "rejected") {
        console.error(`[SyncWorker] ❌ Error syncing ${configs[i].username}: ${r.reason?.message || r.reason}`);
      }
    }

    console.log(`[SyncWorker] Sync complete at ${new Date().toISOString()}`);
  } catch (err: any) {
    console.error(`[SyncWorker] Fatal error: ${err.message}`);
  }

  await prisma.$disconnect();
}

main().catch((err) => {
  console.error("[SyncWorker] Failed to start:", err);
  process.exit(1);
});
