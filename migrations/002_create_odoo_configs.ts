import { pgTable, text } from "drizzle-orm/pg-core";
import { Migration } from "../src/lib/migration.js";
import { users } from "./001_create_users.js";

export const odooConfigs = pgTable("odooconfig", {
  id: text("id").primaryKey(),
  userId: text("userId").notNull().unique().references(() => users.id),
  url: text("url").notNull(),
  dbName: text("dbName").notNull(),
  username: text("username").notNull(),
  apiKey: text("apiKey").notNull(),
});

export default class CreateOdooConfigsTable extends Migration {
  override async run() {
    await this.createTable(odooConfigs);
  }

  override async down() {
    await this.dropTable("odooconfig");
  }
}
