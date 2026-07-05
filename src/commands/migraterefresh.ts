import { migrateRefresh } from "../lib/migrate.js";
import { cyan } from "kolorist";

async function main() {
  console.log(cyan("\n🔄 Refreshing all migrations...\n"));

  try {
    await migrateRefresh();
    console.log(cyan("\n✅ Refresh complete."));
  } catch (err) {
    console.error("❌ Refresh failed:", err);
    process.exit(1);
  }
}

main();
