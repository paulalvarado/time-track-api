import { Pool } from "pg";
import { drizzle } from "drizzle-orm/node-postgres";
import { databaseConfig } from "../config/database.js";

const pool = new Pool({ connectionString: databaseConfig.url });
export const db = drizzle(pool);
export { pool };
