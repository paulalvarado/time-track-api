import { pgTable, text, timestamp } from "drizzle-orm/pg-core";
import { Migration } from "../src/lib/migration.js";

export const users = pgTable("User", {
  id: text("id").primaryKey(),
  email: text("email").notNull().unique(),
  name: text("name").notNull(),
  password: text("password").notNull(),
  createdAt: timestamp("createdAt").notNull().defaultNow(),
  updatedAt: timestamp("updatedAt").notNull(),
});

export default class CreateUsersTable extends Migration {
  override async run() {
    await this.createTable(users);
  }

  override async down() {
    await this.dropTable("User");
  }
}
