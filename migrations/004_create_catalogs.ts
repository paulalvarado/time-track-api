import { pgTable, text, json, timestamp } from "drizzle-orm/pg-core";
import { Migration } from "../src/lib/migration.js";

export const catalogs = pgTable("Catalog", {
  id: text("id").primaryKey(),
  name: text("name").notNull(),
  odooConfigId: text("odooConfigId").notNull(),
  lastSyncAt: timestamp("lastSyncAt"),
});

export const catalogItems = pgTable("CatalogItem", {
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
      `CREATE UNIQUE INDEX IF NOT EXISTS "Catalog_name_odooConfigId_key" ON "Catalog" ("name", "odooConfigId")`,
    );
    await this.createTable(catalogItems);
    await this.execute(
      `CREATE UNIQUE INDEX IF NOT EXISTS "CatalogItem_catalogId_key_key" ON "CatalogItem" ("catalogId", "key")`,
    );
    await this.execute(
      `ALTER TABLE "CatalogItem" ADD CONSTRAINT "CatalogItem_catalogId_fkey" FOREIGN KEY ("catalogId") REFERENCES "Catalog"("id") ON DELETE CASCADE`,
    );
  }

  override async down() {
    await this.dropTable("CatalogItem");
    await this.dropTable("Catalog");
  }
}
