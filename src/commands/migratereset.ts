/**
 * Reset de migraciones — Limpia la tabla _migrations y ejecuta todas
 * las migraciones desde cero.
 *
 * Útil cuando las migraciones se marcaron como ejecutadas pero las tablas
 * no se crearon (ej: por falta de await en this.db.execute()).
 *
 * Uso: npx tsx src/commands/migratereset.ts
 */
import { pool } from "../lib/Database.js";
import { migrateUp } from "../lib/migrate.js";
import { yellow, green, cyan, red } from "kolorist";

async function main() {
  console.log(cyan("\n🔄 Resetando migraciones...\n"));

  // 1. Verificar conexión
  try {
    await pool.query("SELECT 1");
  } catch {
    console.error(red("❌ No se pudo conectar a la base de datos."));
    process.exit(1);
  }

  // 2. Vaciar la tabla de control de migraciones
  await pool.query('TRUNCATE "_migrations"');
  console.log(green("✅ Registros de migraciones eliminados."));

  // 3. Ejecutar todas las migraciones
  console.log(cyan("\n🚀 Ejecutando migraciones...\n"));
  const ran = await migrateUp();

  if (ran.length === 0) {
    console.log(yellow("📭 No se ejecutó ninguna migración."));
  } else {
    console.log(green(`\n✅ ${ran.length} migración(es) ejecutada(s) correctamente.`));
  }

  await pool.end();
  process.exit(0);
}

main().catch((err) => {
  console.error(red("❌ Error:"), err);
  process.exit(1);
});
