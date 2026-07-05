import { Migration } from "../src/lib/migration.js";

export default class AddGeminiApiKey extends Migration {
  override async run() {
    await this.execute(
      `ALTER TABLE "odooconfig" ADD COLUMN "geminiApiKey" TEXT;`,
    );
  }

  override async down() {
    await this.execute(
      `ALTER TABLE "odooconfig" DROP COLUMN "geminiApiKey";`,
    );
  }
}
