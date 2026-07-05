import { getStatus } from "../lib/migrate.js";
import { printTable } from "console-table-printer";
import { green, red, yellow, cyan } from "kolorist";

async function main() {
  console.log(cyan("\n📋 Migration Status\n"));

  try {
    const list = await getStatus();

    if (list.length === 0) {
      console.log(yellow("📂 No migration files found."));
      return;
    }

    const rows = list.map((m) => ({
      Migración: m.name,
      Lote: m.batch ?? "—",
      Estado: m.executed ? green("✅ Up") : red("❌ Down"),
      Tiempo: m.duration_ms ? `${m.duration_ms}ms` : "—",
    }));

    printTable(rows);
  } catch (err) {
    console.error("❌ Error:", err);
    process.exit(1);
  }
}

main();
