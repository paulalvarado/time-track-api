import { Migration } from "../src/lib/migration.js";

/**
 * Crea las tablas de sincronización que Prisma necesita pero nunca
 * tuvieron una migración de Drizzle (SyncState, SyncProject, SyncStage,
 * SyncProjectStage, SyncTask, SyncTimesheet).
 */
export default class CreateSyncTables extends Migration {
  override async run() {
    // SyncState
    await this.execute(`CREATE TABLE IF NOT EXISTS "syncstate" (
      "id" TEXT NOT NULL PRIMARY KEY,
      "odooConfigId" TEXT NOT NULL UNIQUE,
      "lastSyncAt" TIMESTAMP,
      "syncing" BOOLEAN NOT NULL DEFAULT false,
      "error" TEXT,
      "odooUid" INTEGER,
      "createdAt" TIMESTAMP NOT NULL DEFAULT NOW(),
      "updatedAt" TIMESTAMP NOT NULL DEFAULT NOW(),
      FOREIGN KEY ("odooConfigId") REFERENCES "OdooConfig"("id")
    );`);

    // SyncProject
    await this.execute(`CREATE TABLE IF NOT EXISTS "syncproject" (
      "id" TEXT NOT NULL PRIMARY KEY,
      "odooId" INTEGER NOT NULL,
      "name" TEXT NOT NULL,
      "color" INTEGER,
      "odooUserId" INTEGER,
      "odooConfigId" TEXT NOT NULL,
      "createdAt" TIMESTAMP NOT NULL DEFAULT NOW(),
      "updatedAt" TIMESTAMP NOT NULL DEFAULT NOW(),
      FOREIGN KEY ("odooConfigId") REFERENCES "OdooConfig"("id"),
      UNIQUE ("odooId", "odooConfigId")
    );`);

    // SyncStage
    await this.execute(`CREATE TABLE IF NOT EXISTS "syncstage" (
      "id" TEXT NOT NULL PRIMARY KEY,
      "odooId" INTEGER NOT NULL,
      "name" TEXT NOT NULL,
      "sequence" INTEGER NOT NULL,
      "odooConfigId" TEXT NOT NULL,
      "createdAt" TIMESTAMP NOT NULL DEFAULT NOW(),
      "updatedAt" TIMESTAMP NOT NULL DEFAULT NOW(),
      FOREIGN KEY ("odooConfigId") REFERENCES "OdooConfig"("id"),
      UNIQUE ("odooId", "odooConfigId")
    );`);

    // SyncProjectStage
    await this.execute(`CREATE TABLE IF NOT EXISTS "syncprojectstage" (
      "id" TEXT NOT NULL PRIMARY KEY,
      "stageOdooId" INTEGER NOT NULL,
      "projectOdooId" INTEGER NOT NULL,
      "odooConfigId" TEXT NOT NULL,
      "createdAt" TIMESTAMP NOT NULL DEFAULT NOW(),
      FOREIGN KEY ("odooConfigId") REFERENCES "OdooConfig"("id"),
      FOREIGN KEY ("stageOdooId", "odooConfigId") REFERENCES "syncstage"("odooId", "odooConfigId"),
      UNIQUE ("stageOdooId", "projectOdooId", "odooConfigId")
    );`);
    await this.execute(`CREATE INDEX IF NOT EXISTS "syncprojectstage_project_idx" ON "syncprojectstage" ("projectOdooId", "odooConfigId");`);

    // SyncTask
    await this.execute(`CREATE TABLE IF NOT EXISTS "synctask" (
      "id" TEXT NOT NULL PRIMARY KEY,
      "odooId" INTEGER NOT NULL,
      "name" TEXT NOT NULL,
      "description" TEXT,
      "stageOdooId" INTEGER,
      "assigneeIds" JSONB,
      "priority" TEXT,
      "deadline" TEXT,
      "color" INTEGER,
      "projectOdooId" INTEGER NOT NULL,
      "odooConfigId" TEXT NOT NULL,
      "createdAt" TIMESTAMP NOT NULL DEFAULT NOW(),
      "updatedAt" TIMESTAMP NOT NULL DEFAULT NOW(),
      FOREIGN KEY ("odooConfigId") REFERENCES "OdooConfig"("id"),
      FOREIGN KEY ("stageOdooId", "odooConfigId") REFERENCES "syncstage"("odooId", "odooConfigId"),
      UNIQUE ("odooId", "odooConfigId")
    );`);
    await this.execute(`CREATE INDEX IF NOT EXISTS "synctask_project_idx" ON "synctask" ("projectOdooId", "odooConfigId");`);

    // SyncTimesheet
    await this.execute(`CREATE TABLE IF NOT EXISTS "synctimesheet" (
      "id" TEXT NOT NULL PRIMARY KEY,
      "odooId" INTEGER NOT NULL,
      "name" TEXT,
      "unitAmount" DOUBLE PRECISION NOT NULL,
      "date" TEXT,
      "userOdooId" INTEGER,
      "taskOdooId" INTEGER NOT NULL,
      "odooConfigId" TEXT NOT NULL,
      "createdAt" TIMESTAMP NOT NULL DEFAULT NOW(),
      "updatedAt" TIMESTAMP NOT NULL DEFAULT NOW(),
      FOREIGN KEY ("odooConfigId") REFERENCES "OdooConfig"("id"),
      FOREIGN KEY ("taskOdooId", "odooConfigId") REFERENCES "synctask"("odooId", "odooConfigId"),
      UNIQUE ("odooId", "odooConfigId")
    );`);
    await this.execute(`CREATE INDEX IF NOT EXISTS "synctimesheet_task_idx" ON "synctimesheet" ("taskOdooId", "odooConfigId");`);
  }

  override async down() {
    await this.execute(`DROP TABLE IF EXISTS "synctimesheet"`);
    await this.execute(`DROP TABLE IF EXISTS "synctask"`);
    await this.execute(`DROP TABLE IF EXISTS "syncprojectstage"`);
    await this.execute(`DROP TABLE IF EXISTS "syncstage"`);
    await this.execute(`DROP TABLE IF EXISTS "syncproject"`);
    await this.execute(`DROP TABLE IF EXISTS "syncstate"`);
  }
}
