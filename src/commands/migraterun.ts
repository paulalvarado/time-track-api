import { migrateUp } from "../lib/migrate.js";
import { green, yellow, cyan } from "kolorist";

async function main() {
  console.log(cyan("\n🚀 Running pending migrations...\n"));

  try {
    const ran = await migrateUp();

    if (ran.length === 0) {
      console.log(yellow("📭 No pending migrations."));
      return;
    }

    console.log(green(`\n✅ ${ran.length} migration(s) applied successfully.`));
  } catch (err) {
    console.error("❌ Migration failed:", err);
    process.exit(1);
  }
}

main();
