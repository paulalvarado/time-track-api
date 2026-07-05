import { Migration } from "../src/lib/migration.js";

/**
 * Renombra las tablas de PascalCase a lowercase para que coincidan
 * con lo que espera Prisma (PostgreSQL pliega identificadores sin
 * comillas a minúsculas).
 *
 * Las tablas fueron creadas por migraciones previas como "User",
 * "OdooConfig", etc. pero Prisma las busca como "user", "odooconfig", etc.
 */
export default class FixTableNames extends Migration {
  override async run() {
    // Solo renombrar si la tabla en PascalCase existe
    const tables = [
      { from: "User", to: "user" },
      { from: "OdooConfig", to: "odooconfig" },
      { from: "Project", to: "project" },
      { from: "Catalog", to: "catalog" },
      { from: "CatalogItem", to: "catalogitem" },
    ];

    for (const { from, to } of tables) {
      await this.execute(
        `ALTER TABLE IF EXISTS "${from}" RENAME TO "${to}";`,
      );
    }
  }

  override async down() {
    const tables = [
      { from: "user", to: "User" },
      { from: "odooconfig", to: "OdooConfig" },
      { from: "project", to: "Project" },
      { from: "catalog", to: "Catalog" },
      { from: "catalogitem", to: "CatalogItem" },
    ];

    for (const { from, to } of tables) {
      await this.execute(
        `ALTER TABLE IF EXISTS "${from}" RENAME TO "${to}";`,
      );
    }
  }
}
