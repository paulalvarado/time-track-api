import { pool } from "../src/lib/database.js";

const column = "geminiApiKey";

try {
  await pool.query(
    `ALTER TABLE "OdooConfig" ADD COLUMN IF NOT EXISTS "${column}" TEXT`,
  );
  console.log(`Column "${column}" added successfully.`);
} catch (err) {
  console.error("Error:", err);
} finally {
  await pool.end();
}
