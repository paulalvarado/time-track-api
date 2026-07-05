import { pgTable, text, json, timestamp } from "drizzle-orm/pg-core";
import { Migration } from "../src/lib/migration.js";

export const catalogs = pgTable("catalog", {
  id: text("id").primaryKey(),
  name: text("name").notNull(),
  odooConfigId: text("odooConfigId").notNull(),
  lastSyncAt: timestamp("lastSyncAt"),
});

export const catalogItems = pgTable("catalogitem", {
  id: text("id").primaryKey(),
  catalogId: text("catalogId").notNull(),
  key: text("key").notNull(),
  value: text("value").notNull(),
  extra: json("extra"),
});

export default class CreateCatalogsTable extends Migration {
  override async run() {
    await this.createTable(catalogs);
    await this.execute(
      `CREATE UNIQUE INDEX IF NOT EXISTS "catalog_name_odooConfigId_key" ON "catalog" ("name", "odooConfigId")`,
    );
    await this.createTable(catalogItems);
    await this.execute(
      `CREATE UNIQUE INDEX IF NOT EXISTS "catalogitem_catalogId_key_key" ON "catalogitem" ("catalogId", "key")`,
    );
    await this.execute(
      `ALTER TABLE "catalogitem" ADD CONSTRAINT "catalogitem_catalogId_fkey" FOREIGN KEY ("catalogId") REFERENCES "catalog"("id") ON DELETE CASCADE`,
    );
  }

  override async down() {
    await this.dropTable("CatalogItem");
    await this.dropTable("Catalog");
  }
}
