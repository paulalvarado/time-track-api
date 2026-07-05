import { pgTable, text, integer, timestamp } from "drizzle-orm/pg-core";
import { Migration } from "../src/lib/migration.js";
import { users } from "./001_create_users.js";

export const projects = pgTable("project", {
  id: text("id").primaryKey(),
  odooId: integer("odooId").notNull(),
  name: text("name").notNull(),
  userId: text("userId").notNull().references(() => users.id),
  createdAt: timestamp("createdAt").notNull().defaultNow(),
});

export default class CreateProjectsTable extends Migration {
  override async run() {
    await this.createTable(projects);
  }

  override async down() {
    await this.dropTable("project");
  }
}
