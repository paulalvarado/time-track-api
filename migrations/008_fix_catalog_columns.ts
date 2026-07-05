import { Migration } from "../src/lib/migration.js";

/**
 * Agrega columnas faltantes en Catalog y CatalogItem.
 * Prisma espera createdAt pero la migración original no lo incluyó.
 */
export default class FixCatalogColumns extends Migration {
  override async run() {
    await this.execute(`DO $$ BEGIN
      ALTER TABLE "Catalog" ADD COLUMN "createdAt" TIMESTAMP NOT NULL DEFAULT NOW();
    EXCEPTION WHEN duplicate_column THEN NULL;
    END $$;`);

    await this.execute(`DO $$ BEGIN
      ALTER TABLE "CatalogItem" ADD COLUMN "createdAt" TIMESTAMP NOT NULL DEFAULT NOW();
    EXCEPTION WHEN duplicate_column THEN NULL;
    END $$;`);
  }

  override async down() {
    await this.execute(`DO $$ BEGIN
      ALTER TABLE "Catalog" DROP COLUMN "createdAt";
    EXCEPTION WHEN undefined_column THEN NULL;
    END $$;`);

    await this.execute(`DO $$ BEGIN
      ALTER TABLE "CatalogItem" DROP COLUMN "createdAt";
    EXCEPTION WHEN undefined_column THEN NULL;
    END $$;`);
  }
}
