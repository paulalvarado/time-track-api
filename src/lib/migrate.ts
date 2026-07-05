import { readdirSync } from "fs";
import { join, dirname } from "path";
import { fileURLToPath } from "url";
import { pool, db } from "./Database.js";
import type { Migration } from "./migration.js";
import type { PgDatabase } from "drizzle-orm/pg-core";

const __dirname = dirname(fileURLToPath(import.meta.url));
const MIGRATIONS_DIR = join(__dirname, "../../migrations");

interface MigrationRecord {
  id: number;
  migration_name: string;
  batch: number;
  executed_at: string;
  duration_ms: number;
}

async function ensureMigrationsTable(): Promise<void> {
  await pool.query(`
    CREATE TABLE IF NOT EXISTS "_migrations" (
      "id" SERIAL NOT NULL PRIMARY KEY,
      "migration_name" TEXT NOT NULL UNIQUE,
      "batch" INTEGER NOT NULL,
      "executed_at" TIMESTAMP NOT NULL DEFAULT NOW(),
      "duration_ms" INTEGER NOT NULL DEFAULT 0
    )
  `);
}

async function getExecutedMigrations(): Promise<MigrationRecord[]> {
  const result = await pool.query("SELECT * FROM \"_migrations\" ORDER BY migration_name ASC");
  return result.rows;
}

async function getLastBatch(): Promise<number> {
  const result = await pool.query("SELECT COALESCE(MAX(batch), 0) AS batch FROM \"_migrations\"");
  return result.rows[0]?.batch ?? 0;
}

export async function getStatus() {
  await ensureMigrationsTable();
  const executed = await getExecutedMigrations();
  const executedNames = new Set(executed.map((m) => m.migration_name));

  let files: string[];
  try {
    files = readdirSync(MIGRATIONS_DIR)
      .filter((f) => f.endsWith(".ts"))
      .sort();
  } catch {
    files = [];
  }

  return files.map((file) => {
    const record = executed.find((m) => m.migration_name === file);
    return {
      name: file,
      batch: record?.batch ?? null,
      executed: !!record,
      duration_ms: record?.duration_ms ?? null,
    };
  });
}

export async function migrateUp(): Promise<string[]> {
  await ensureMigrationsTable();
  const executed = await getExecutedMigrations();
  const executedNames = new Set(executed.map((m) => m.migration_name));

  let files: string[];
  try {
    files = readdirSync(MIGRATIONS_DIR)
      .filter((f) => f.endsWith(".ts"))
      .sort();
  } catch {
    console.log("📂 No migrations directory found.");
    return [];
  }

  const pending = files.filter((f) => !executedNames.has(f));
  if (pending.length === 0) {
    console.log("✅ All migrations have been run.");
    return [];
  }

  const batch = (await getLastBatch()) + 1;
  const ran: string[] = [];

  for (const file of pending) {
    const start = Date.now();
    try {
      const module = await import(join(MIGRATIONS_DIR, file));
      const MigClass = module.default as new (db: PgDatabase<any>) => Migration;
      const instance = new MigClass(db);
      await instance.run();
      const duration = Date.now() - start;

      await pool.query(
        "INSERT INTO \"_migrations\" (migration_name, batch, duration_ms) VALUES ($1, $2, $3)",
        [file, batch, duration]
      );
      ran.push(file);
      console.log(`✅ ${file} — ${duration}ms`);
    } catch (err) {
      console.error(`❌ Error running ${file}:`, err);
      throw err;
    }
  }

  return ran;
}

export async function migrateDown(): Promise<string[]> {
  await ensureMigrationsTable();
  const lastBatch = await getLastBatch();
  if (lastBatch === 0) {
    console.log("📭 No migrations to rollback.");
    return [];
  }

  const result = await pool.query(
    "SELECT * FROM \"_migrations\" WHERE batch = $1 ORDER BY migration_name DESC",
    [lastBatch]
  );
  const toRollback: MigrationRecord[] = result.rows;

  const rolled: string[] = [];

  for (const record of toRollback) {
    try {
      const module = await import(join(MIGRATIONS_DIR, record.migration_name));
      const MigClass = module.default as new (db: PgDatabase<any>) => Migration;
      const instance = new MigClass(db);
      await instance.down();

      await pool.query("DELETE FROM \"_migrations\" WHERE migration_name = $1", [record.migration_name]);
      rolled.push(record.migration_name);
      console.log(`⬇️  ${record.migration_name} rolled back`);
    } catch (err) {
      console.error(`❌ Error rolling back ${record.migration_name}:`, err);
      throw err;
    }
  }

  return rolled;
}

export async function migrateRefresh(): Promise<void> {
  console.log("🔄 Rolling back all migrations...");
  while (true) {
    const rolled = await migrateDown();
    if (rolled.length === 0) break;
  }
  console.log("🚀 Running all migrations...");
  await migrateUp();
}
