import { Migration } from "../src/lib/migration.js";

export default class AddGeminiApiKey extends Migration {
  override async run() {
    await this.execute(
      `DO $$ BEGIN
        ALTER TABLE "OdooConfig" ADD COLUMN "geminiApiKey" TEXT;
      EXCEPTION WHEN duplicate_column THEN NULL;
      END $$;`,
    );
  }

  override async down() {
    await this.execute(
      `DO $$ BEGIN
        ALTER TABLE "OdooConfig" DROP COLUMN "geminiApiKey";
      EXCEPTION WHEN undefined_column THEN NULL;
      END $$;`,
    );
  }
}
