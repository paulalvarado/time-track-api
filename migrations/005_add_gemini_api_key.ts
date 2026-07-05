import { Migration } from "../src/lib/migration.js";

export default class AddGeminiApiKey extends Migration {
  override async run() {
    await this.execute(
      `ALTER TABLE "OdooConfig" ADD COLUMN "geminiApiKey" TEXT;`,
    );
  }

  override async down() {
    await this.execute(
      `ALTER TABLE "OdooConfig" DROP COLUMN "geminiApiKey";`,
    );
  }
}
