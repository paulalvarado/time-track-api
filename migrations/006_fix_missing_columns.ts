import { Migration } from "../src/lib/migration.js";

/**
 * Agrega columnas faltantes que Prisma necesita pero las migraciones
 * originales de Drizzle no incluyeron.
 *
 * - User: faltaba password
 * - Project: faltaban odooUserId, color
 */
export default class FixMissingColumns extends Migration {
  override async run() {
    await this.execute(`DO $$ BEGIN
      ALTER TABLE "User" ADD COLUMN "password" TEXT NOT NULL DEFAULT '';
    EXCEPTION WHEN duplicate_column THEN NULL;
    END $$;`);

    await this.execute(`DO $$ BEGIN
      ALTER TABLE "Project" ADD COLUMN "odooUserId" INTEGER;
    EXCEPTION WHEN duplicate_column THEN NULL;
    END $$;`);

    await this.execute(`DO $$ BEGIN
      ALTER TABLE "Project" ADD COLUMN "color" INTEGER;
    EXCEPTION WHEN duplicate_column THEN NULL;
    END $$;`);
  }

  override async down() {
    await this.execute(`DO $$ BEGIN
      ALTER TABLE "User" DROP COLUMN "password";
    EXCEPTION WHEN undefined_column THEN NULL;
    END $$;`);

    await this.execute(`DO $$ BEGIN
      ALTER TABLE "Project" DROP COLUMN "odooUserId";
    EXCEPTION WHEN undefined_column THEN NULL;
    END $$;`);

    await this.execute(`DO $$ BEGIN
      ALTER TABLE "Project" DROP COLUMN "color";
    EXCEPTION WHEN undefined_column THEN NULL;
    END $$;`);
  }
}
