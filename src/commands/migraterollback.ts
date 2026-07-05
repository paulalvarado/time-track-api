import { migrateDown } from "../lib/migrate.js";
import { green, yellow, red, cyan } from "kolorist";

async function main() {
  const isDown = process.argv.includes("--down");

  if (isDown) {
    console.log(cyan("\n⬇️  Rolling back the last migration...\n"));
  } else {
    console.log(cyan("\n⬇️  Rolling back the last batch...\n"));
  }

  try {
    const rolled = await migrateDown();

    if (rolled.length === 0) {
      console.log(yellow("📭 No migrations to rollback."));
      return;
    }

    console.log(green(`\n✅ ${rolled.length} migration(s) rolled back.`));
  } catch (err) {
    console.error(red("❌ Rollback failed:"), err);
    process.exit(1);
  }
}

main();
